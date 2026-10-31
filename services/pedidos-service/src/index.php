<?php
// ==============================================================================
// pedidos-service - Carrito, Gestión de Pedidos y Ciclo de Compra
// Controles de Seguridad: OWASP A01 (Broken Access Control), STRIDE Tampering (Precios del Servidor)
// ==============================================================================

require_once __DIR__ . '/../../common/Database.php';
require_once __DIR__ . '/../../common/JWT.php';
require_once __DIR__ . '/../../common/Security.php';
require_once __DIR__ . '/../../common/AuditClient.php';
require_once __DIR__ . '/../../common/UnitConverter.php';
require_once __DIR__ . '/../../common/InventoryManager.php';

Security::applySecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api/pedidos#', '', $path);
if ($path === '' || $path === false) $path = '/';

try {
    $db = Database::getMasterConnection($_ENV['DB_PEDIDOS_NAME'] ?? 'pedidos_db');

    // Healthcheck
    if ($method === 'GET' && ($path === '/health' || $path === '/healthcheck')) {
        Security::jsonResponse(['status' => 'UP', 'service' => 'pedidos-service', 'timestamp' => date('c')]);
    }

    // CREAR PEDIDO (POST /pedidos)
    if ($method === 'POST' && ($path === '' || $path === '/')) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user) {
            Security::jsonResponse(['success' => false, 'error' => 'Debe iniciar sesión para realizar un pedido'], 401);
        }

        $input = Security::getJsonInput();
        $items = $input['items'] ?? [];
        $direccion = Security::sanitizeString($input['direccion'] ?? '');
        $telefono = Security::sanitizeString($input['telefono'] ?? '');
        $notas = Security::sanitizeString($input['notas'] ?? '');

        $tipoEntrega = in_array($input['tipo_entrega'] ?? '', ['delivery', 'pickup'], true) ? $input['tipo_entrega'] : 'delivery';
        $sucursal = Security::sanitizeString($input['sucursal'] ?? 'Sucursal Zona 10 - Terraza Gourmet');
        $propina = max(0.00, Security::sanitizeFloat($input['propina'] ?? 0.00));

        if (empty($items) || !is_array($items)) {
            Security::jsonResponse(['success' => false, 'error' => 'El carrito no contiene platillos'], 400);
        }
        if ($tipoEntrega === 'delivery' && (empty($direccion) || empty($telefono))) {
            Security::jsonResponse(['success' => false, 'error' => 'Dirección de entrega y teléfono de contacto requeridos para delivery'], 400);
        }
        if ($tipoEntrega === 'pickup') {
            if (empty($telefono)) {
                Security::jsonResponse(['success' => false, 'error' => 'Teléfono de contacto requerido para pickup'], 400);
            }
            if (empty($direccion)) {
                $direccion = "Recojo en {$sucursal}";
            }
        }

        // Obtener o crear ID de cliente en pedidos_db
        $cStmt = $db->prepare("SELECT id FROM clientes WHERE usuario_id = :uid LIMIT 1");
        $cStmt->execute([':uid' => $user['id']]);
        $cliente = $cStmt->fetch();
        if (!$cliente) {
            $insC = $db->prepare("INSERT INTO clientes (usuario_id, nombre, email, telefono, direccion_defecto) VALUES (:uid, :nom, :em, :tel, :dir)");
            $insC->execute([
                ':uid' => $user['id'],
                ':nom' => $user['nombre'],
                ':em' => $user['email'],
                ':tel' => $telefono,
                ':dir' => $direccion,
            ]);
            $clienteId = (int)$db->lastInsertId();
        } else {
            $clienteId = (int)$cliente['id'];
        }

        // CONTRALORÍA DE PRECIOS E INVENTARIO EN SERVIDOR (STRIDE Tampering & BOM Gastronómico):
        // NUNCA confiar en precios ni stock enviados por el cliente. Consultar siempre catalogo_db directamente.
        $catDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');

        // VALIDACIÓN PREVIA DE MATERIAS PRIMAS / RECETAS / STOCK PARA TODA LA ORDEN
        $valInv = InventoryManager::validateOrderInventory($catDb, $items);
        if (!$valInv['valid']) {
            AuditClient::log(
                'VENTA_BLOQUEADA_STOCK_INSUFICIENTE',
                'pedidos-service',
                (int)$user['id'],
                $user['email'],
                $user['rol'] ?? 'cliente',
                null,
                null,
                null,
                ['error' => $valInv['error'], 'insumo' => $valInv['insumo_insuficiente'] ?? null],
                'FALLO'
            );
            Security::jsonResponse([
                'success' => false,
                'error' => $valInv['error'],
                'insumo_insuficiente' => $valInv['insumo_insuficiente'] ?? null
            ], 400);
        }

        $subtotal = 0.00;
        $itemsProcesados = [];

        foreach ($items as $item) {
            $prodId = (int)($item['id'] ?? $item['id_producto'] ?? 0);
            $cantidad = max(1, (int)($item['cantidad'] ?? 1));
            $itemNota = Security::sanitizeString($item['nota'] ?? $item['notas'] ?? '');

            $prodStmt = $catDb->prepare("SELECT id, nombre, sku, precio, stock, activo FROM productos WHERE id = :id LIMIT 1");
            $prodStmt->execute([':id' => $prodId]);
            $producto = $prodStmt->fetch();

            if (!$producto || (int)$producto['activo'] !== 1) {
                Security::jsonResponse(['success' => false, 'error' => "El platillo ID {$prodId} no está disponible"], 400);
            }

            $precioUnitario = (float)$producto['precio'];
            $lineSubtotal = round($precioUnitario * $cantidad, 2);
            $subtotal += $lineSubtotal;

            $itemsProcesados[] = [
                'id_producto'     => $prodId,
                'sku'             => $producto['sku'],
                'nombre_producto' => $producto['nombre'],
                'precio_unitario' => $precioUnitario,
                'cantidad'        => $cantidad,
                'subtotal'        => $lineSubtotal,
                'stock_actual'    => (int)$producto['stock'],
                'notas'           => $itemNota
            ];
        }

        $impuesto = round($subtotal * 0.12, 2); // 12% IVA
        // REGLA DE DELIVERY / PICKUP: Pickup siempre es $0.00. Delivery es gratis arriba de $25.00, sino $2.50.
        $envio = ($tipoEntrega === 'pickup') ? 0.00 : (($subtotal >= 25.00) ? 0.00 : 2.50);
        $total = round($subtotal + $impuesto + $envio + $propina, 2);

        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $numeroPedido = 'ORD-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        // Transacción para insertar pedido, detalles y descontar insumos del inventario
        $db->beginTransaction();
        try {
            $pedStmt = $db->prepare("
                INSERT INTO pedidos (uuid, numero_pedido, id_cliente, tipo_entrega, sucursal, subtotal, impuesto, envio, propina, total, estado, direccion_envio, telefono_contacto, notas, fecha)
                VALUES (:uuid, :num, :cid, :tipo, :suc, :sub, :imp, :env, :prop, :tot, 'recibido', :dir, :tel, :not, NOW())
            ");
            $pedStmt->execute([
                ':uuid' => $uuid,
                ':num'  => $numeroPedido,
                ':cid'  => $clienteId,
                ':tipo' => $tipoEntrega,
                ':suc'  => $sucursal,
                ':sub'  => $subtotal,
                ':imp'  => $impuesto,
                ':env'  => $envio,
                ':prop' => $propina,
                ':tot'  => $total,
                ':dir'  => $direccion,
                ':tel'  => $telefono,
                ':not'  => $notas,
            ]);
            $pedidoId = (int)$db->lastInsertId();

            $detStmt = $db->prepare("
                INSERT INTO detalle_pedidos (id_pedido, id_producto, sku, nombre_producto, precio_unitario, cantidad, subtotal, notas)
                VALUES (:pid, :prid, :sku, :nom, :pre, :can, :sub, :not)
            ");
            foreach ($itemsProcesados as $it) {
                $detStmt->execute([
                    ':pid'  => $pedidoId,
                    ':prid' => $it['id_producto'],
                    ':sku'  => $it['sku'],
                    ':nom'  => $it['nombre_producto'],
                    ':pre'  => $it['precio_unitario'],
                    ':can'  => $it['cantidad'],
                    ':sub'  => $it['subtotal'],
                    ':not'  => $it['notas'] ?: null,
                ]);
            }

            // DESCUENTO ATÓMICO DE MATERIAS PRIMAS (g, lb, ml, l, unid) CON HISTORIAL KARDEX
            $deduction = InventoryManager::deductOrderInventory(
                $catDb,
                $items,
                $numeroPedido,
                $pedidoId,
                (int)$user['id'],
                $user['email']
            );

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // REGISTRAR EVENTO AUDITORÍA: PEDIDO_CREADO
        AuditClient::log(
            'PEDIDO_CREADO',
            'pedidos-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'cliente',
            $pedidoId,
            null,
            null,
            ['numero_pedido' => $numeroPedido, 'total' => $total, 'items_count' => count($itemsProcesados)],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => 'Pedido de comida registrado exitosamente en cocina',
            'pedido' => [
                'id'            => $pedidoId,
                'uuid'          => $uuid,
                'numero_pedido' => $numeroPedido,
                'tipo_entrega'  => $tipoEntrega,
                'sucursal'      => $sucursal,
                'subtotal'      => $subtotal,
                'impuesto'      => $impuesto,
                'envio'         => $envio,
                'propina'       => $propina,
                'total'         => $total,
                'estado'        => 'recibido',
                'items'         => $itemsProcesados
            ]
        ], 201);
    }

    // LISTAR PEDIDOS (OWASP A01: Broken Access Control)
    if ($method === 'GET' && ($path === '' || $path === '/')) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 401);
        }

        if (in_array($user['rol'] ?? '', ['admin', 'cocina', 'auditor', 'operador'])) {
            // Personal del restaurante (Admin, Cocina KDS, Auditor) ve todos los pedidos
            $stmt = $db->query("
                SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email
                FROM pedidos p
                JOIN clientes c ON p.id_cliente = c.id
                ORDER BY p.id DESC
            ");
            $pedidos = $stmt->fetchAll();
        } else {
            // Cliente normal: VERIFICAR id_cliente del JWT == id_cliente del pedido en CADA consulta
            $cStmt = $db->prepare("SELECT id FROM clientes WHERE usuario_id = :uid LIMIT 1");
            $cStmt->execute([':uid' => $user['id']]);
            $cliente = $cStmt->fetch();

            if (!$cliente) {
                Security::jsonResponse(['success' => true, 'data' => []]);
            }

            $stmt = $db->prepare("
                SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email
                FROM pedidos p
                JOIN clientes c ON p.id_cliente = c.id
                WHERE p.id_cliente = :cid
                ORDER BY p.id DESC
            ");
            $stmt->execute([':cid' => $cliente['id']]);
            $pedidos = $stmt->fetchAll();
        }

        // Obtener detalles para cada pedido
        foreach ($pedidos as &$p) {
            $det = $db->prepare("SELECT * FROM detalle_pedidos WHERE id_pedido = :pid");
            $det->execute([':pid' => $p['id']]);
            $p['items'] = $det->fetchAll();
        }

        Security::jsonResponse(['success' => true, 'data' => $pedidos]);
    }

    // OBTENER PEDIDO INDIVIDUAL POR ID (GET /pedidos/:id)
    if ($method === 'GET' && preg_match('#^/([0-9]+)$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 401);
        }

        $pedidoId = (int)$matches[1];
        $stmt = $db->prepare("
            SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email
            FROM pedidos p
            JOIN clientes c ON p.id_cliente = c.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $pedidoId]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            Security::jsonResponse(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        if (!in_array($user['rol'] ?? '', ['admin', 'cocina', 'auditor', 'operador'])) {
            $cStmt = $db->prepare("SELECT id FROM clientes WHERE usuario_id = :uid LIMIT 1");
            $cStmt->execute([':uid' => $user['id']]);
            $cliente = $cStmt->fetch();
            if (!$cliente || (int)$cliente['id'] !== (int)$pedido['id_cliente']) {
                Security::jsonResponse(['success' => false, 'error' => 'Acceso denegado a este pedido'], 403);
            }
        }

        $det = $db->prepare("SELECT * FROM detalle_pedidos WHERE id_pedido = :pid");
        $det->execute([':pid' => $pedido['id']]);
        $pedido['items'] = $det->fetchAll();

        Security::jsonResponse(['success' => true, 'data' => $pedido]);
    }

    // CAMBIAR ESTADO DE PEDIDO (Admin / Cocina KDS - Requisito Audit PEDIDO_ESTADO_CAMBIADO)
    if ($method === 'PUT' && preg_match('#^/([0-9]+)/estado$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina', 'operador'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores y personal de cocina'], 403);
        }

        $pedidoId = (int)$matches[1];
        $input = Security::getJsonInput();
        $nuevoEstado = Security::sanitizeString($input['estado'] ?? '');
        $estadosPermitidos = ['recibido', 'pendiente', 'pagado', 'en_preparacion', 'listo', 'en_camino', 'entregado', 'cancelado'];

        if (!in_array($nuevoEstado, $estadosPermitidos, true)) {
            Security::jsonResponse(['success' => false, 'error' => 'Estado no válido'], 400);
        }

        $checkStmt = $db->prepare("SELECT id, numero_pedido, estado FROM pedidos WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $pedidoId]);
        $ped = $checkStmt->fetch();

        if (!$ped) {
            Security::jsonResponse(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $estadoAnterior = $ped['estado'];
        $upStmt = $db->prepare("UPDATE pedidos SET estado = :est WHERE id = :id");
        $upStmt->execute([':est' => $nuevoEstado, ':id' => $pedidoId]);

        // REINFORZAMIENTO DE INVENTARIO: Si se cancela la orden, reintegrar las materias primas al inventario
        if ($nuevoEstado === 'cancelado' && $estadoAnterior !== 'cancelado') {
            try {
                $catDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
                InventoryManager::revertOrderInventory(
                    $catDb,
                    $pedidoId,
                    $ped['numero_pedido'],
                    (int)$user['id'],
                    $user['email'],
                    'Pedido cancelado desde monitor de cocina / administración'
                );
            } catch (Throwable $e) {
                error_log("[REVERT INVENTORY EXCEPTION] " . $e->getMessage());
            }
        }

        // REGISTRAR EVENTO AUDITORÍA: PEDIDO_ESTADO_CAMBIADO
        AuditClient::log(
            'PEDIDO_ESTADO_CAMBIADO',
            'pedidos-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'operador',
            $pedidoId,
            null,
            ['estado' => $estadoAnterior],
            ['estado' => $nuevoEstado, 'cambiado_por' => $user['email']],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => 'Estado de pedido actualizado',
            'pedido_id' => $pedidoId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $nuevoEstado
        ]);
    }

    // EXPORTAR CLIENTES / PEDIDOS (Admin / Auditor - Requisito Audit EXPORTACION_CLIENTES)
    if ($method === 'GET' && $path === '/exportar') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'auditor'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores y auditores'], 403);
        }

        $formato = Security::sanitizeString($_GET['formato'] ?? 'json');

        $stmt = $db->query("
            SELECT p.id, p.numero_pedido, c.nombre AS cliente, c.email, p.total, p.estado, p.fecha
            FROM pedidos p
            JOIN clientes c ON p.id_cliente = c.id
            ORDER BY p.id DESC
        ");
        $exportData = $stmt->fetchAll();

        // AUDITORÍA: EXPORTACION_CLIENTES
        AuditClient::log(
            'EXPORTACION_CLIENTES',
            'pedidos-service',
            (int)$user['id'],
            $user['email'],
            'admin',
            null,
            null,
            null,
            ['formato' => $formato, 'total_registros' => count($exportData)],
            'EXITO'
        );

        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="reporte_pedidos_' . date('Ymd_His') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'No. Pedido', 'Cliente', 'Email', 'Total (USD)', 'Estado', 'Fecha']);
            foreach ($exportData as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit();
        }

        Security::jsonResponse(['success' => true, 'total' => count($exportData), 'data' => $exportData]);
    }

    Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

} catch (Throwable $e) {
    error_log("[PEDIDOS SERVICE EXCEPTION] " . $e->getMessage());
    Security::jsonResponse(['error' => 'Error interno en pedidos-service'], 500);
}

<?php
// ==============================================================================
// catalogo-service - Catálogo Gastronómico, Materia Prima, Recetas (BOM) y Caching
// Master-Slave (Lecturas en Slave, Escrituras en Master) + Redis Cache
// Controles de Seguridad: OWASP A01, STRIDE Tampering (Precio e inventario en servidor)
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
$path = preg_replace('#^/api/catalogo#', '', $path);
if ($path === '' || $path === false) $path = '/';

// Conexión Redis para Cache
function getRedis(): ?Redis {
    static $r = null;
    if ($r !== null) return $r;
    if (!class_exists('Redis')) return null;
    try {
        $redis = new Redis();
        if (@$redis->connect($_ENV['REDIS_HOST'] ?? 'redis', (int)($_ENV['REDIS_PORT'] ?? 6379), 1.0)) {
            if (!empty($_ENV['REDIS_PASSWORD'])) $redis->auth($_ENV['REDIS_PASSWORD']);
            $r = $redis;
            return $r;
        }
    } catch (Throwable $e) {}
    return null;
}

function enriquecerMateriaPrima(array $mp): array {
    $f = InventoryManager::evaluarFrescura($mp);
    $mp['es_perecedero'] = $f['es_perecedero'] ? 1 : 0;
    $mp['estado_frescura'] = $f['estado_frescura'];
    $mp['dias_para_vencer'] = $f['dias_para_vencer'];
    $mp['fecha_vencimiento'] = $f['fecha_vencimiento'];
    $mp['stock_utilizable'] = $f['stock_utilizable'];
    $mp['accion_recomendada'] = $f['accion'];
    return $mp;
}

function fechaVencimientoValida($valor): ?string {
    $fecha = is_string($valor) ? substr(trim($valor), 0, 10) : '';
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : null;
}

function invalidateCatalogueCache(): void {
    $r = getRedis();
    if ($r) {
        $keys = $r->keys('cat:*');
        if (!empty($keys)) {
            $r->del($keys);
        }
    }
}

try {
    // Healthcheck
    if ($path === '/health' || $path === '/') {
        Security::jsonResponse(['status' => 'UP', 'service' => 'catalogo-service', 'timestamp' => date('c')]);
    }

    // LISTAR CATEGORÍAS (Lectura desde Slave)
    if ($method === 'GET' && $path === '/categorias') {
        $redis = getRedis();
        $cacheKey = 'cat:categorias_list';
        if ($redis && ($cached = $redis->get($cacheKey))) {
            Security::jsonResponse(['success' => true, 'from_cache' => true, 'data' => json_decode($cached, true)]);
        }

        try {
            $slaveDb = Database::getSlaveConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $slaveDb->query("SELECT id, nombre, slug, descripcion, icono FROM categorias ORDER BY id ASC");
            $data = $stmt->fetchAll();
        } catch (Throwable $e) {
            $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $masterDb->query("SELECT id, nombre, slug, descripcion, icono FROM categorias ORDER BY id ASC");
            $data = $stmt->fetchAll();
        }

        if ($redis && !empty($data)) {
            $redis->setex($cacheKey, 600, json_encode($data));
        }

        Security::jsonResponse(['success' => true, 'from_cache' => false, 'data' => $data]);
    }

    // LISTAR PRODUCTOS CON MEDICIÓN DE STOCK DINÁMICO Y RECETAS (Lectura desde Slave + Redis Cache)
    if ($method === 'GET' && $path === '/productos') {
        $categoriaId = isset($_GET['categoria_id']) ? Security::sanitizeInt($_GET['categoria_id']) : null;
        $destacado = isset($_GET['destacado']) ? Security::sanitizeInt($_GET['destacado']) : null;
        $search = isset($_GET['q']) ? Security::sanitizeString($_GET['q']) : null;

        $cacheKey = "cat:prod_c{$categoriaId}_d{$destacado}_s" . md5((string)$search);
        $redis = getRedis();
        if ($redis && empty($search) && ($cached = $redis->get($cacheKey))) {
            Security::jsonResponse(['success' => true, 'from_cache' => true, 'data' => json_decode($cached, true)]);
        }

        // Consulta SQL con Prepared Statements (OWASP A03)
        $query = "
            SELECT p.id, p.id_categoria, c.nombre AS categoria_nombre, p.nombre, p.sku,
                   p.descripcion, p.especificaciones, p.precio, p.stock, p.imagen_url,
                   p.destacado, p.activo, p.updated_at
            FROM productos p
            JOIN categorias c ON p.id_categoria = c.id
            WHERE p.activo = 1
        ";
        $params = [];

        if ($categoriaId) {
            $query .= " AND p.id_categoria = :categoria_id";
            $params[':categoria_id'] = $categoriaId;
        }
        if ($destacado !== null) {
            $query .= " AND p.destacado = :destacado";
            $params[':destacado'] = $destacado;
        }
        if ($search) {
            $query .= " AND (p.nombre LIKE :search OR p.descripcion LIKE :search OR p.sku LIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        $query .= " ORDER BY p.destacado DESC, p.id ASC";

        try {
            $db = Database::getSlaveConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $productos = $stmt->fetchAll();
        } catch (Throwable $e) {
            // Fallback a Master
            $db = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $productos = $stmt->fetchAll();
        }

        // Enriquecer cada platillo con el cálculo de disponibilidad de materia prima (BOM)
        foreach ($productos as &$prod) {
            if (!empty($prod['especificaciones']) && is_string($prod['especificaciones'])) {
                $prod['especificaciones'] = json_decode($prod['especificaciones'], true);
            }

            // Calcular porciones disponibles en tiempo real según inventario de insumos
            $avail = InventoryManager::calculateProductAvailability($db, (int)$prod['id']);
            $prod['stock'] = $avail['available_portions'];
            $prod['porciones_disponibles'] = $avail['available_portions'];
            $prod['en_stock'] = $avail['is_available'];
            $prod['tiene_receta'] = $avail['has_recipe'];
            $prod['costo_produccion'] = $avail['costo_produccion'] ?? 0.00;
            $prod['insumos_faltantes'] = array_column($avail['bottlenecks'] ?? [], 'nombre');
        }

        if ($redis && empty($search) && !empty($productos)) {
            $redis->setex($cacheKey, 180, json_encode($productos));
        }

        Security::jsonResponse(['success' => true, 'from_cache' => false, 'data' => $productos]);
    }

    // OBTENER PRODUCTO POR ID CON DETALLE DE RECETA COMPLETA
    if ($method === 'GET' && preg_match('#^/productos/([0-9]+)$#', $path, $matches)) {
        $id = (int)$matches[1];
        try {
            $db = Database::getSlaveConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $db->prepare("
                SELECT p.*, c.nombre AS categoria_nombre
                FROM productos p
                JOIN categorias c ON p.id_categoria = c.id
                WHERE p.id = :id AND p.activo = 1
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);
            $producto = $stmt->fetch();
        } catch (Throwable $e) {
            $db = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $db->prepare("SELECT p.*, c.nombre AS categoria_nombre FROM productos p JOIN categorias c ON p.id_categoria = c.id WHERE p.id = :id AND p.activo = 1 LIMIT 1");
            $stmt->execute([':id' => $id]);
            $producto = $stmt->fetch();
        }

        if (!$producto) {
            Security::jsonResponse(['success' => false, 'error' => 'Producto no encontrado'], 404);
        }

        if (!empty($producto['especificaciones']) && is_string($producto['especificaciones'])) {
            $producto['especificaciones'] = json_decode($producto['especificaciones'], true);
        }

        // Obtener análisis de receta, costo de producción y porciones preparables
        $avail = InventoryManager::calculateProductAvailability($db, $id);
        $producto['stock'] = $avail['available_portions'];
        $producto['porciones_disponibles'] = $avail['available_portions'];
        $producto['en_stock'] = $avail['is_available'];
        $producto['tiene_receta'] = $avail['has_recipe'];
        $producto['costo_produccion'] = $avail['costo_produccion'] ?? 0.00;
        $producto['receta'] = $avail['ingredients'];
        $producto['insumos_faltantes'] = $avail['bottlenecks'];

        Security::jsonResponse(['success' => true, 'data' => $producto]);
    }

    // MODIFICAR PRECIO (Admin Only - Requisito Audit PRECIO_MODIFICADO)
    if ($method === 'PUT' && preg_match('#^/productos/([0-9]+)/precio$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || ($user['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $id = (int)$matches[1];
        $input = Security::getJsonInput();
        $nuevoPrecio = Security::sanitizeFloat($input['precio'] ?? 0);
        $motivo = Security::sanitizeString($input['motivo'] ?? 'Ajuste administrativo');

        if ($nuevoPrecio <= 0) {
            Security::jsonResponse(['success' => false, 'error' => 'El precio debe ser mayor a 0'], 400);
        }

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $checkStmt = $masterDb->prepare("SELECT id, nombre, sku, precio FROM productos WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $id]);
        $prodAnterior = $checkStmt->fetch();

        if (!$prodAnterior) {
            Security::jsonResponse(['success' => false, 'error' => 'Producto no encontrado'], 404);
        }

        $precioAnterior = (float)$prodAnterior['precio'];

        // Actualizar en Master
        $upStmt = $masterDb->prepare("UPDATE productos SET precio = :precio, updated_by = :uid WHERE id = :id");
        $upStmt->execute([':precio' => $nuevoPrecio, ':uid' => $user['id'], ':id' => $id]);

        // Invalidar cache Redis
        invalidateCatalogueCache();

        // REGISTRAR EVENTO AUDITORÍA: PRECIO_MODIFICADO
        AuditClient::log(
            'PRECIO_MODIFICADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'] ?? 'admin@bistrogourmet.com',
            'admin',
            null,
            null,
            ['producto_id' => $id, 'sku' => $prodAnterior['sku'], 'precio' => $precioAnterior],
            ['producto_id' => $id, 'sku' => $prodAnterior['sku'], 'precio' => $nuevoPrecio, 'motivo' => $motivo],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => 'Precio actualizado correctamente y registrado en auditoría',
            'producto_id' => $id,
            'precio_anterior' => $precioAnterior,
            'precio_nuevo' => $nuevoPrecio
        ]);
    }

    // MODIFICAR STOCK DIRECTO (Admin Only o pedidos-service interno)
    if ($method === 'PUT' && preg_match('#^/productos/([0-9]+)/stock$#', $path, $matches)) {
        $id = (int)$matches[1];
        $input = Security::getJsonInput();
        $nuevoStock = Security::sanitizeInt($input['stock'] ?? 0);

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $checkStmt = $masterDb->prepare("SELECT id, nombre, sku, stock FROM productos WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $id]);
        $prod = $checkStmt->fetch();

        if (!$prod) {
            Security::jsonResponse(['success' => false, 'error' => 'Producto no encontrado'], 404);
        }

        $stockAnterior = (int)$prod['stock'];
        $upStmt = $masterDb->prepare("UPDATE productos SET stock = :stock WHERE id = :id");
        $upStmt->execute([':stock' => $nuevoStock, ':id' => $id]);

        invalidateCatalogueCache();

        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if ($user) {
            AuditClient::log(
                'INVENTARIO_ACTUALIZADO',
                'catalogo-service',
                (int)$user['id'],
                $user['email'],
                $user['rol'] ?? 'admin',
                null,
                null,
                ['producto_id' => $id, 'sku' => $prod['sku'], 'stock_anterior' => $stockAnterior],
                ['producto_id' => $id, 'sku' => $prod['sku'], 'stock_nuevo' => $nuevoStock],
                'EXITO'
            );
        }

        if ($nuevoStock <= 0) {
            AuditClient::log(
                'PRODUCTO_AGOTADO',
                'catalogo-service',
                null,
                'sistema@bistrogourmet.internal',
                'servicio',
                null,
                null,
                ['producto_id' => $id, 'sku' => $prod['sku'], 'stock_previo' => $stockAnterior],
                ['producto_id' => $id, 'sku' => $prod['sku'], 'stock_actual' => $nuevoStock],
                'EXITO'
            );
        }

        Security::jsonResponse([
            'success' => true,
            'producto_id' => $id,
            'stock_anterior' => $stockAnterior,
            'stock_actual' => $nuevoStock
        ]);
    }

    // CREAR PRODUCTO (Admin Only)
    if ($method === 'POST' && $path === '/productos') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || ($user['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $input = Security::getJsonInput();
        $nombre    = Security::sanitizeString($input['nombre'] ?? '');
        $sku       = Security::sanitizeString($input['sku'] ?? '');
        $precio    = Security::sanitizeFloat($input['precio'] ?? 0);
        $stock     = Security::sanitizeInt($input['stock'] ?? 0);
        $catId     = Security::sanitizeInt($input['categoria_id'] ?? 0);
        $desc      = Security::sanitizeString($input['descripcion'] ?? '');
        $imgUrl    = filter_var($input['imagen_url'] ?? '', FILTER_SANITIZE_URL);
        $destacado = (int)!empty($input['destacado']);
        $specs     = $input['especificaciones'] ?? null;
        $receta    = $input['receta'] ?? null;

        if (empty($nombre) || empty($sku) || $precio <= 0 || $catId <= 0) {
            Security::jsonResponse(['success' => false, 'error' => 'Nombre, SKU, precio y categoría son obligatorios'], 400);
        }

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');

        // Verificar SKU único
        $skuCheck = $masterDb->prepare("SELECT id FROM productos WHERE sku = :sku LIMIT 1");
        $skuCheck->execute([':sku' => $sku]);
        if ($skuCheck->fetch()) {
            Security::jsonResponse(['success' => false, 'error' => "El SKU '$sku' ya existe en el catálogo"], 409);
        }

        $insertStmt = $masterDb->prepare("
            INSERT INTO productos (id_categoria, nombre, sku, descripcion, especificaciones, precio, stock, imagen_url, destacado, activo, created_at)
            VALUES (:cat, :nom, :sku, :desc, :specs, :precio, :stock, :img, :dest, 1, NOW())
        ");
        $insertStmt->execute([
            ':cat'   => $catId,
            ':nom'   => $nombre,
            ':sku'   => $sku,
            ':desc'  => $desc,
            ':specs' => $specs ? json_encode($specs) : null,
            ':precio'=> $precio,
            ':stock' => $stock,
            ':img'   => $imgUrl,
            ':dest'  => $destacado,
        ]);
        $newId = (int)$masterDb->lastInsertId();

        // Si se enviaron ingredientes para la receta (Ficha técnica BOM)
        $porcionesCalculadas = $stock;
        if (!empty($receta) && is_array($receta)) {
            $recStmt = $masterDb->prepare("
                INSERT INTO recetas (id_producto, id_materia_prima, cantidad_necesaria, unidad_medida, es_critico, notas, created_at)
                VALUES (:pid, :mpid, :cant, :um, :crit, :not, NOW())
            ");
            foreach ($receta as $ing) {
                $mpId = (int)($ing['id_materia_prima'] ?? 0);
                $cant = (float)($ing['cantidad_necesaria'] ?? 0);
                $um   = Security::sanitizeString($ing['unidad_medida'] ?? 'g');
                $crit = isset($ing['es_critico']) ? (int)!empty($ing['es_critico']) : 1;
                $not  = Security::sanitizeString($ing['notas'] ?? null);

                if ($mpId > 0 && $cant > 0) {
                    $recStmt->execute([
                        ':pid'  => $newId,
                        ':mpid' => $mpId,
                        ':cant' => $cant,
                        ':um'   => $um,
                        ':crit' => $crit,
                        ':not'  => $not
                    ]);
                }
            }
            // Sincronizar porciones calculadas en tiempo real según inventario de insumos
            $porcionesCalculadas = InventoryManager::syncProductStock($masterDb, $newId);
        }

        invalidateCatalogueCache();

        AuditClient::log(
            'PRODUCTO_CREADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'] ?? 'admin',
            'admin',
            null, null, null,
            [
                'producto_id'         => $newId,
                'nombre'              => $nombre,
                'sku'                 => $sku,
                'precio'              => $precio,
                'tiene_receta'        => !empty($receta),
                'porciones_iniciales' => $porcionesCalculadas
            ],
            'EXITO'
        );

        Security::jsonResponse([
            'success'            => true,
            'message'            => 'Producto creado exitosamente' . (!empty($receta) ? ' con su receta BOM configurada' : ''),
            'producto_id'        => $newId,
            'porciones_iniciales'=> $porcionesCalculadas
        ], 201);
    }

    // ACTUALIZAR PRODUCTO COMPLETO (Admin Only)
    if ($method === 'PUT' && preg_match('#^/productos/([0-9]+)$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || ($user['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $id    = (int)$matches[1];
        $input = Security::getJsonInput();

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $checkStmt = $masterDb->prepare("SELECT * FROM productos WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $id]);
        $prodAnterior = $checkStmt->fetch();
        if (!$prodAnterior) {
            Security::jsonResponse(['success' => false, 'error' => 'Producto no encontrado'], 404);
        }

        $nombre    = Security::sanitizeString($input['nombre'] ?? $prodAnterior['nombre']);
        $precio    = Security::sanitizeFloat($input['precio'] ?? $prodAnterior['precio']);
        $stock     = Security::sanitizeInt($input['stock'] ?? $prodAnterior['stock']);
        $catId     = Security::sanitizeInt($input['categoria_id'] ?? $prodAnterior['id_categoria']);
        $desc      = Security::sanitizeString($input['descripcion'] ?? $prodAnterior['descripcion']);
        $imgUrl    = filter_var($input['imagen_url'] ?? $prodAnterior['imagen_url'], FILTER_SANITIZE_URL);
        $destacado = isset($input['destacado']) ? (int)$input['destacado'] : (int)$prodAnterior['destacado'];

        $upStmt = $masterDb->prepare("
            UPDATE productos
            SET id_categoria = :cat, nombre = :nom, descripcion = :desc,
                precio = :precio, stock = :stock, imagen_url = :img,
                destacado = :dest, updated_at = NOW()
            WHERE id = :id
        ");
        $upStmt->execute([
            ':cat'  => $catId, ':nom'  => $nombre, ':desc' => $desc,
            ':precio'=> $precio, ':stock'=> $stock, ':img'  => $imgUrl,
            ':dest' => $destacado, ':id' => $id
        ]);

        invalidateCatalogueCache();

        AuditClient::log(
            'PRODUCTO_MODIFICADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'] ?? 'admin',
            'admin',
            null, null,
            ['nombre' => $prodAnterior['nombre'], 'precio' => $prodAnterior['precio']],
            ['nombre' => $nombre, 'precio' => $precio, 'stock' => $stock],
            'EXITO'
        );

        Security::jsonResponse(['success' => true, 'message' => 'Producto actualizado correctamente']);
    }

    // ELIMINAR PRODUCTO - Soft Delete (Admin Only)
    if ($method === 'DELETE' && preg_match('#^/productos/([0-9]+)$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || ($user['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $id = (int)$matches[1];
        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $checkStmt = $masterDb->prepare("SELECT id, nombre, sku FROM productos WHERE id = :id AND activo = 1 LIMIT 1");
        $checkStmt->execute([':id' => $id]);
        $prod = $checkStmt->fetch();
        if (!$prod) {
            Security::jsonResponse(['success' => false, 'error' => 'Producto no encontrado o ya eliminado'], 404);
        }

        // Soft delete (mantiene integridad de pedidos históricos)
        $delStmt = $masterDb->prepare("UPDATE productos SET activo = 0, updated_at = NOW() WHERE id = :id");
        $delStmt->execute([':id' => $id]);

        invalidateCatalogueCache();

        AuditClient::log(
            'PRODUCTO_ELIMINADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'] ?? 'admin',
            'admin',
            null, null,
            ['producto_id' => $id, 'sku' => $prod['sku'], 'nombre' => $prod['nombre']],
            null,
            'EXITO'
        );

        Security::jsonResponse(['success' => true, 'message' => 'Producto eliminado del catálogo activo']);
    }

    // =========================================================================
    // 6. GESTIÓN DE MATERIAS PRIMAS / INVENTARIO BASE (g, kg, lb, oz, ml, l, unid)
    // =========================================================================

    // LISTAR MATERIAS PRIMAS (Lectura desde Slave o Master)
    if ($method === 'GET' && $path === '/materias-primas') {
        $categoria = isset($_GET['categoria']) ? Security::sanitizeString($_GET['categoria']) : null;
        $bajoStock = isset($_GET['bajo_stock']) ? (int)$_GET['bajo_stock'] : null;
        $search = isset($_GET['q']) ? Security::sanitizeString($_GET['q']) : null;

        $columnasMp = "id, codigo, nombre, categoria, unidad_medida, stock_actual, stock_minimo, costo_unitario, activo, updated_at";
        $columnasFrescura = ", es_perecedero, fecha_vencimiento, dias_alerta";
        $sqlBase = "
            FROM materias_primas
            WHERE activo = 1
        ";
        $sql = "SELECT {$columnasMp}{$columnasFrescura} {$sqlBase}";
        $params = [];

        if ($categoria) {
            $sql .= " AND categoria = :cat";
            $params[':cat'] = $categoria;
        }
        if ($bajoStock) {
            $sql .= " AND stock_actual <= stock_minimo";
        }
        if ($search) {
            $sql .= " AND (nombre LIKE :search_nombre OR codigo LIKE :search_codigo OR categoria LIKE :search_categoria)";
            $params[':search_nombre'] = "%{$search}%";
            $params[':search_codigo'] = "%{$search}%";
            $params[':search_categoria'] = "%{$search}%";
        }
        $sql .= " ORDER BY (stock_actual <= stock_minimo) DESC, categoria ASC, nombre ASC";

        $sufijo = substr($sql, strlen("SELECT {$columnasMp}{$columnasFrescura} {$sqlBase}"));
        $sqlSinFrescura = "SELECT {$columnasMp} {$sqlBase}" . $sufijo;

        $cargarMaterias = function (PDO $db, bool $permitirSinFrescura) use ($sql, $sqlSinFrescura, $params) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                return $stmt->fetchAll();
            } catch (Throwable $e) {
                if (!$permitirSinFrescura) {
                    throw $e;
                }
                $stmt = $db->prepare($sqlSinFrescura);
                $stmt->execute($params);
                return $stmt->fetchAll();
            }
        };

        try {
            $db = Database::getSlaveConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $mps = $cargarMaterias($db, false);
        } catch (Throwable $e) {
            $db = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $mps = $cargarMaterias($db, true);
        }

        $resumenFrescura = ['vencidos' => 0, 'por_vencer' => 0, 'vigentes' => 0, 'sin_fecha' => 0];
        foreach ($mps as &$mp) {
            $mp = enriquecerMateriaPrima($mp);
            $stockVal = (float)$mp['stock_actual'];
            $minVal = (float)$mp['stock_minimo'];
            $mp['stock_formateado'] = UnitConverter::format($stockVal, $mp['unidad_medida']);
            $mp['minimo_formateado'] = UnitConverter::format($minVal, $mp['unidad_medida']);
            $mp['alerta_bajo_stock'] = ($stockVal <= $minVal);
            $mp['agotado'] = ($stockVal <= 0.0001);
            $mp['valor_inventario'] = round($stockVal * (float)$mp['costo_unitario'], 2);
            $clave = [
                'vencido' => 'vencidos',
                'por_vencer' => 'por_vencer',
                'vigente' => 'vigentes',
                'sin_fecha' => 'sin_fecha',
            ][$mp['estado_frescura']] ?? null;
            if ($clave) {
                $resumenFrescura[$clave]++;
            }
        }
        unset($mp);

        $filtroFrescura = isset($_GET['frescura']) ? Security::sanitizeString($_GET['frescura']) : null;
        if ($filtroFrescura === 'atencion') {
            $mps = array_values(array_filter($mps, function ($m) {
                return in_array($m['estado_frescura'], ['vencido', 'por_vencer', 'sin_fecha'], true);
            }));
        }

        Security::jsonResponse([
            'success' => true,
            'total' => count($mps),
            'resumen_frescura' => $resumenFrescura,
            'data' => $mps
        ]);
    }

    // OBTENER MATERIA PRIMA POR ID CON PLATILLOS QUE LA UTILIZAN
    if ($method === 'GET' && preg_match('#^/materias-primas/([0-9]+)$#', $path, $matches)) {
        $id = (int)$matches[1];
        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');

        $stmt = $masterDb->prepare("SELECT * FROM materias_primas WHERE id = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $mp = $stmt->fetch();

        if (!$mp) {
            Security::jsonResponse(['success' => false, 'error' => 'Materia prima no encontrada'], 404);
        }

        // Platillos que usan esta materia prima
        $platStmt = $masterDb->prepare("
            SELECT p.id, p.nombre, p.sku, p.precio, p.stock, r.cantidad_necesaria, r.unidad_medida AS receta_unidad, r.es_critico
            FROM recetas r
            JOIN productos p ON r.id_producto = p.id
            WHERE r.id_materia_prima = :mp_id AND p.activo = 1
        ");
        $platStmt->execute([':mp_id' => $id]);
        $mp['platillos_receta'] = $platStmt->fetchAll();
        $mp = enriquecerMateriaPrima($mp);
        $mp['stock_formateado'] = UnitConverter::format((float)$mp['stock_actual'], $mp['unidad_medida']);

        Security::jsonResponse(['success' => true, 'data' => $mp]);
    }

    // CREAR MATERIA PRIMA (Admin Only)
    if ($method === 'POST' && $path === '/materias-primas') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores y cocina'], 403);
        }

        $input = Security::getJsonInput();
        $codigo        = strtoupper(trim(Security::sanitizeString($input['codigo'] ?? '')));
        $nombre        = Security::sanitizeString($input['nombre'] ?? '');
        $categoria     = Security::sanitizeString($input['categoria'] ?? 'Insumos Generales');
        $unidadMedida  = UnitConverter::normalize(Security::sanitizeString($input['unidad_medida'] ?? 'g'));
        $stockActual   = max(0.000, Security::sanitizeFloat($input['stock_actual'] ?? 0));
        $stockMinimo   = max(0.000, Security::sanitizeFloat($input['stock_minimo'] ?? 5));
        $costoUnitario = max(0.00, Security::sanitizeFloat($input['costo_unitario'] ?? 0));
        $fechaVenc     = fechaVencimientoValida($input['fecha_vencimiento'] ?? null);
        $esPerecedero  = !empty($input['es_perecedero']) || $fechaVenc !== null ? 1 : 0;

        if (empty($codigo) || empty($nombre) || empty($unidadMedida)) {
            Security::jsonResponse(['success' => false, 'error' => 'Código, nombre y unidad de medida son obligatorios'], 400);
        }

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');

        // Validar código único
        $chk = $masterDb->prepare("SELECT id FROM materias_primas WHERE codigo = :cod LIMIT 1");
        $chk->execute([':cod' => $codigo]);
        if ($chk->fetch()) {
            Security::jsonResponse(['success' => false, 'error' => "El código de insumo '{$codigo}' ya existe"], 409);
        }

        try {
            $ins = $masterDb->prepare("
                INSERT INTO materias_primas (codigo, nombre, categoria, unidad_medida, stock_actual, stock_minimo, costo_unitario, es_perecedero, fecha_vencimiento, dias_alerta, activo, created_at)
                VALUES (:cod, :nom, :cat, :um, :stock, :min, :cost, :perec, :vence, 3, 1, NOW())
            ");
            $ins->execute([
                ':cod'   => $codigo,
                ':nom'   => $nombre,
                ':cat'   => $categoria,
                ':um'    => $unidadMedida,
                ':stock' => $stockActual,
                ':min'   => $stockMinimo,
                ':cost'  => $costoUnitario,
                ':perec' => $esPerecedero,
                ':vence' => $fechaVenc
            ]);
        } catch (Throwable $e) {
            $ins = $masterDb->prepare("
                INSERT INTO materias_primas (codigo, nombre, categoria, unidad_medida, stock_actual, stock_minimo, costo_unitario, activo, created_at)
                VALUES (:cod, :nom, :cat, :um, :stock, :min, :cost, 1, NOW())
            ");
            $ins->execute([
                ':cod'   => $codigo,
                ':nom'   => $nombre,
                ':cat'   => $categoria,
                ':um'    => $unidadMedida,
                ':stock' => $stockActual,
                ':min'   => $stockMinimo,
                ':cost'  => $costoUnitario
            ]);
        }
        $newId = (int)$masterDb->lastInsertId();

        // Registro de movimiento inicial si vino con stock > 0
        if ($stockActual > 0) {
            $insMov = $masterDb->prepare("
                INSERT INTO movimientos_inventario 
                (id_materia_prima, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, unidad_medida, referencia_tipo, referencia_id, motivo, usuario_id, usuario_email, created_at)
                VALUES (:mp_id, 'entrada_compra', :cant, 0, :nuevo, :um, 'compra', 'INICIAL', 'Inventario inicial de apertura de insumo', :uid, :uemail, NOW())
            ");
            $insMov->execute([
                ':mp_id'  => $newId,
                ':cant'   => $stockActual,
                ':nuevo'  => $stockActual,
                ':um'     => $unidadMedida,
                ':uid'    => $user['id'],
                ':uemail' => $user['email']
            ]);
        }

        invalidateCatalogueCache();

        AuditClient::log(
            'INVENTARIO_ACTUALIZADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'admin',
            null, null, null,
            ['materia_prima_id' => $newId, 'codigo' => $codigo, 'nombre' => $nombre, 'stock' => $stockActual, 'unidad' => $unidadMedida],
            'EXITO'
        );

        Security::jsonResponse([
            'success'          => true,
            'message'          => 'Materia prima registrada exitosamente',
            'materia_prima_id' => $newId
        ], 201);
    }

    // ACTUALIZAR DATOS DE MATERIA PRIMA (Admin / Cocina)
    if ($method === 'PUT' && preg_match('#^/materias-primas/([0-9]+)$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $id = (int)$matches[1];
        $input = Security::getJsonInput();

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $stmt = $masterDb->prepare("SELECT * FROM materias_primas WHERE id = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $anterior = $stmt->fetch();

        if (!$anterior) {
            Security::jsonResponse(['success' => false, 'error' => 'Materia prima no encontrada'], 404);
        }

        $nombre        = Security::sanitizeString($input['nombre'] ?? $anterior['nombre']);
        $categoria     = Security::sanitizeString($input['categoria'] ?? $anterior['categoria']);
        $unidadMedida  = UnitConverter::normalize(Security::sanitizeString($input['unidad_medida'] ?? $anterior['unidad_medida']));
        $stockMinimo   = max(0.000, Security::sanitizeFloat($input['stock_minimo'] ?? $anterior['stock_minimo']));
        $costoUnitario = max(0.00, Security::sanitizeFloat($input['costo_unitario'] ?? $anterior['costo_unitario']));
        $fechaVenc     = array_key_exists('fecha_vencimiento', $input)
            ? fechaVencimientoValida($input['fecha_vencimiento'])
            : ($anterior['fecha_vencimiento'] ?? null);
        $esPerecedero  = array_key_exists('es_perecedero', $input)
            ? (!empty($input['es_perecedero']) || $fechaVenc !== null ? 1 : 0)
            : (int)($anterior['es_perecedero'] ?? ($fechaVenc !== null ? 1 : 0));

        try {
            $up = $masterDb->prepare("
                UPDATE materias_primas 
                SET nombre = :nom, categoria = :cat, unidad_medida = :um, stock_minimo = :min, costo_unitario = :cost,
                    es_perecedero = :perec, fecha_vencimiento = :vence, updated_at = NOW()
                WHERE id = :id
            ");
            $up->execute([
                ':nom'   => $nombre,
                ':cat'   => $categoria,
                ':um'    => $unidadMedida,
                ':min'   => $stockMinimo,
                ':cost'  => $costoUnitario,
                ':perec' => $esPerecedero,
                ':vence' => $fechaVenc,
                ':id'    => $id
            ]);
        } catch (Throwable $e) {
            $up = $masterDb->prepare("
                UPDATE materias_primas 
                SET nombre = :nom, categoria = :cat, unidad_medida = :um, stock_minimo = :min, costo_unitario = :cost, updated_at = NOW()
                WHERE id = :id
            ");
            $up->execute([
                ':nom'  => $nombre,
                ':cat'  => $categoria,
                ':um'   => $unidadMedida,
                ':min'  => $stockMinimo,
                ':cost' => $costoUnitario,
                ':id'   => $id
            ]);
        }

        invalidateCatalogueCache();

        Security::jsonResponse(['success' => true, 'message' => 'Materia prima actualizada correctamente']);
    }

    // BAJA SANITARIA DE UN INSUMO VENCIDO (Admin / Cocina)
    if ($method === 'POST' && preg_match('#^/materias-primas/([0-9]+)/baja-vencido$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina'], true)) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores y cocina'], 403);
        }

        $id = (int)$matches[1];
        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $stmt = $masterDb->prepare("SELECT * FROM materias_primas WHERE id = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $mp = $stmt->fetch();
        if (!$mp) {
            Security::jsonResponse(['success' => false, 'error' => 'Materia prima no encontrada'], 404);
        }

        $frescura = InventoryManager::evaluarFrescura($mp);
        if ($frescura['estado_frescura'] !== 'vencido') {
            Security::jsonResponse([
                'success' => false,
                'error' => 'Solo se da de baja un insumo ya vencido. Si está por vencer, la cocina debe usarlo primero.'
            ], 400);
        }

        $stockAnterior = (float)$mp['stock_actual'];
        if ($stockAnterior <= 0.0001) {
            Security::jsonResponse(['success' => false, 'error' => 'Este insumo vencido ya no tiene stock en bodega'], 400);
        }

        $motivo = 'Baja sanitaria por vencimiento (' . $frescura['fecha_vencimiento'] . '). No apto para consumo.';
        $masterDb->beginTransaction();
        try {
            $upStmt = $masterDb->prepare("UPDATE materias_primas SET stock_actual = 0, updated_at = NOW() WHERE id = :id");
            $upStmt->execute([':id' => $id]);

            $insMov = $masterDb->prepare("
                INSERT INTO movimientos_inventario
                (id_materia_prima, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, unidad_medida, referencia_tipo, referencia_id, motivo, usuario_id, usuario_email, created_at)
                VALUES (:mp_id, 'merma_ajuste', :cant, :ant, 0, :um, 'baja_vencimiento', :ref_id, :motivo, :uid, :uemail, NOW())
            ");
            $insMov->execute([
                ':mp_id'  => $id,
                ':cant'   => -$stockAnterior,
                ':ant'    => $stockAnterior,
                ':um'     => $mp['unidad_medida'],
                ':ref_id' => 'VENC-' . date('Ymd-His'),
                ':motivo' => $motivo,
                ':uid'    => $user['id'],
                ':uemail' => $user['email']
            ]);

            $platStmt = $masterDb->prepare("SELECT DISTINCT id_producto FROM recetas WHERE id_materia_prima = :mp_id");
            $platStmt->execute([':mp_id' => $id]);
            $afectados = $platStmt->fetchAll();
            foreach ($afectados as $af) {
                InventoryManager::syncProductStock($masterDb, (int)$af['id_producto']);
            }
            $masterDb->commit();
        } catch (Throwable $e) {
            $masterDb->rollBack();
            throw $e;
        }

        invalidateCatalogueCache();
        AuditClient::log(
            'INVENTARIO_ACTUALIZADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'cocina',
            null, null,
            ['materia_prima_id' => $id, 'nombre' => $mp['nombre'], 'stock_anterior' => $stockAnterior, 'fecha_vencimiento' => $frescura['fecha_vencimiento']],
            ['materia_prima_id' => $id, 'stock_nuevo' => 0, 'accion' => 'baja_vencido', 'motivo' => $motivo],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => "Baja registrada. {$mp['nombre']} salió de bodega como merma y ya no se usa en cocina.",
            'materia_prima_id' => $id,
            'stock_dado_de_baja' => $stockAnterior,
            'unidad_medida' => $mp['unidad_medida']
        ]);
    }

    // REGISTRAR MOVIMIENTO DE ENTRADA, MERMA O AJUSTE EN KARDEX (Admin / Cocina)
    if ($method === 'POST' && preg_match('#^/materias-primas/([0-9]+)/movimiento$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina', 'operador'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a personal de cocina y administración'], 403);
        }

        $id = (int)$matches[1];
        $input = Security::getJsonInput();

        $tipoMovimiento = Security::sanitizeString($input['tipo_movimiento'] ?? 'entrada_compra');
        $cantidadIngresada = abs(Security::sanitizeFloat($input['cantidad'] ?? 0));
        $motivo = Security::sanitizeString($input['motivo'] ?? 'Ajuste operativo de inventario');
        $referenciaTipo = Security::sanitizeString($input['referencia_tipo'] ?? 'ajuste');
        $referenciaId = Security::sanitizeString($input['referencia_id'] ?? ('AJUSTE-' . date('Ymd-His')));

        if (!in_array($tipoMovimiento, ['entrada_compra', 'merma_ajuste', 'ajuste_manual'], true)) {
            Security::jsonResponse(['success' => false, 'error' => 'Tipo de movimiento no válido'], 400);
        }
        if ($cantidadIngresada <= 0) {
            Security::jsonResponse(['success' => false, 'error' => 'La cantidad debe ser mayor a 0'], 400);
        }

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $stmt = $masterDb->prepare("SELECT * FROM materias_primas WHERE id = :id AND activo = 1 LIMIT 1");
        $stmt->execute([':id' => $id]);
        $mp = $stmt->fetch();

        if (!$mp) {
            Security::jsonResponse(['success' => false, 'error' => 'Materia prima no encontrada'], 404);
        }

        $stockAnterior = (float)$mp['stock_actual'];
        $deltaCantidad = 0.0;
        $stockNuevo = $stockAnterior;

        if ($tipoMovimiento === 'entrada_compra') {
            $deltaCantidad = $cantidadIngresada;
            $stockNuevo = round($stockAnterior + $cantidadIngresada, 3);
        } elseif ($tipoMovimiento === 'merma_ajuste') {
            $deltaCantidad = -$cantidadIngresada;
            $stockNuevo = max(0.000, round($stockAnterior - $cantidadIngresada, 3));
        } elseif ($tipoMovimiento === 'ajuste_manual') {
            // Si la acción especifica 'fijar_stock_exacto' o similar
            $modo = $input['modo'] ?? 'delta';
            if ($modo === 'fijar_exacto') {
                $stockNuevo = $cantidadIngresada;
                $deltaCantidad = round($stockNuevo - $stockAnterior, 3);
            } else {
                $direccion = ($input['signo'] ?? '+') === '-' ? -1 : 1;
                $deltaCantidad = $direccion * $cantidadIngresada;
                $stockNuevo = max(0.000, round($stockAnterior + $deltaCantidad, 3));
            }
        }

        $masterDb->beginTransaction();
        try {
            // Actualizar stock de la materia prima
            $upStmt = $masterDb->prepare("UPDATE materias_primas SET stock_actual = :st, updated_at = NOW() WHERE id = :id");
            $upStmt->execute([':st' => $stockNuevo, ':id' => $id]);

            if ($tipoMovimiento === 'entrada_compra') {
                $nuevaFecha = fechaVencimientoValida($input['fecha_vencimiento'] ?? null);
                if ($nuevaFecha !== null) {
                    $fechaFinal = $nuevaFecha;
                    $fechaActual = $mp['fecha_vencimiento'] ?? null;
                    if ($stockAnterior > 0 && !empty($fechaActual) && substr((string)$fechaActual, 0, 10) < $nuevaFecha) {
                        $fechaFinal = substr((string)$fechaActual, 0, 10);
                    }
                    try {
                        $fecStmt = $masterDb->prepare("UPDATE materias_primas SET es_perecedero = 1, fecha_vencimiento = :f WHERE id = :id");
                        $fecStmt->execute([':f' => $fechaFinal, ':id' => $id]);
                    } catch (Throwable $e) {
                        // La columna aún no existe en instalaciones sin migrar.
                    }
                }
            }

            // Registrar movimiento en el kardex
            $insMov = $masterDb->prepare("
                INSERT INTO movimientos_inventario 
                (id_materia_prima, tipo_movimiento, cantidad, stock_anterior, stock_nuevo, unidad_medida, referencia_tipo, referencia_id, motivo, usuario_id, usuario_email, created_at)
                VALUES (:mp_id, :tipo, :cant, :ant, :nuevo, :um, :ref_tipo, :ref_id, :motivo, :uid, :uemail, NOW())
            ");
            $insMov->execute([
                ':mp_id'    => $id,
                ':tipo'     => $tipoMovimiento,
                ':cant'     => $deltaCantidad,
                ':ant'      => $stockAnterior,
                ':nuevo'    => $stockNuevo,
                ':um'       => $mp['unidad_medida'],
                ':ref_tipo' => $referenciaTipo,
                ':ref_id'   => $referenciaId,
                ':motivo'   => $motivo,
                ':uid'      => $user['id'],
                ':uemail'   => $user['email']
            ]);

            // Recalcular porciones y sincronizar stock de todos los platillos vinculados a este insumo
            $platStmt = $masterDb->prepare("SELECT DISTINCT id_producto FROM recetas WHERE id_materia_prima = :mp_id");
            $platStmt->execute([':mp_id' => $id]);
            $afectados = $platStmt->fetchAll();

            foreach ($afectados as $af) {
                InventoryManager::syncProductStock($masterDb, (int)$af['id_producto']);
            }

            $masterDb->commit();
        } catch (Throwable $e) {
            $masterDb->rollBack();
            throw $e;
        }

        invalidateCatalogueCache();

        // Registrar en Auditoría
        AuditClient::log(
            'INVENTARIO_ACTUALIZADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'operador',
            null, null,
            ['materia_prima_id' => $id, 'nombre' => $mp['nombre'], 'stock_anterior' => $stockAnterior, 'unidad' => $mp['unidad_medida']],
            ['materia_prima_id' => $id, 'nombre' => $mp['nombre'], 'stock_nuevo' => $stockNuevo, 'tipo' => $tipoMovimiento, 'motivo' => $motivo],
            'EXITO'
        );

        Security::jsonResponse([
            'success'            => true,
            'message'            => "Movimiento registrado correctamente en Kardex. Stock actualizado: " . UnitConverter::format($stockNuevo, $mp['unidad_medida']),
            'materia_prima_id'   => $id,
            'stock_anterior'     => $stockAnterior,
            'stock_nuevo'        => $stockNuevo,
            'unidad_medida'      => $mp['unidad_medida'],
            'platillos_recalculados' => count($afectados)
        ]);
    }

    // KARDEX DE UNA MATERIA PRIMA (GET /materias-primas/:id/kardex)
    if ($method === 'GET' && preg_match('#^/materias-primas/([0-9]+)/kardex$#', $path, $matches)) {
        $id = (int)$matches[1];
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));

        $db = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $stmt = $db->prepare("
            SELECT m.*, mp.nombre AS materia_prima_nombre, mp.codigo AS materia_prima_codigo
            FROM movimientos_inventario m
            JOIN materias_primas mp ON m.id_materia_prima = mp.id
            WHERE m.id_materia_prima = :id
            ORDER BY m.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $kardex = $stmt->fetchAll();

        Security::jsonResponse(['success' => true, 'total' => count($kardex), 'data' => $kardex]);
    }

    // KARDEX GLOBAL DE TODOS LOS MOVIMIENTOS (GET /movimientos-inventario)
    if ($method === 'GET' && $path === '/movimientos-inventario') {
        $limit = min(500, max(1, (int)($_GET['limit'] ?? 100)));
        $tipo = isset($_GET['tipo']) ? Security::sanitizeString($_GET['tipo']) : null;

        $db = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $sql = "
            SELECT m.*, mp.nombre AS materia_prima_nombre, mp.codigo AS materia_prima_codigo, mp.categoria AS materia_prima_categoria
            FROM movimientos_inventario m
            JOIN materias_primas mp ON m.id_materia_prima = mp.id
        ";
        $params = [];

        if ($tipo) {
            $sql .= " WHERE m.tipo_movimiento = :tipo";
            $params[':tipo'] = $tipo;
        }
        $sql .= " ORDER BY m.id DESC LIMIT " . (int)$limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $movs = $stmt->fetchAll();

        Security::jsonResponse(['success' => true, 'total' => count($movs), 'data' => $movs]);
    }

    // =========================================================================
    // 7. ESCANDALLO DE RECETAS (BOM) POR PLATILLO
    // =========================================================================

    // CONSULTAR RECETA DE UN PLATILLO (GET /productos/:id/receta)
    if ($method === 'GET' && preg_match('#^/productos/([0-9]+)/receta$#', $path, $matches)) {
        $prodId = (int)$matches[1];
        $db = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');

        $avail = InventoryManager::calculateProductAvailability($db, $prodId);
        if (!$avail['exists']) {
            Security::jsonResponse(['success' => false, 'error' => 'Platillo no encontrado'], 404);
        }

        Security::jsonResponse(['success' => true, 'data' => $avail]);
    }

    // AGREGAR / EDITAR INGREDIENTE EN LA RECETA DE UN PLATILLO (Admin / Cocina)
    if ($method === 'POST' && preg_match('#^/productos/([0-9]+)/receta$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administración y cocina'], 403);
        }

        $prodId = (int)$matches[1];
        $input = Security::getJsonInput();

        $mpId         = (int)($input['id_materia_prima'] ?? 0);
        $cantidadNec  = abs(Security::sanitizeFloat($input['cantidad_necesaria'] ?? 0));
        $unidadMedida = UnitConverter::normalize(Security::sanitizeString($input['unidad_medida'] ?? 'g'));
        $esCritico    = isset($input['es_critico']) ? (int)$input['es_critico'] : 1;
        $notas        = Security::sanitizeString($input['notas'] ?? '');

        if ($mpId <= 0 || $cantidadNec <= 0 || empty($unidadMedida)) {
            Security::jsonResponse(['success' => false, 'error' => 'ID de materia prima, cantidad y unidad son obligatorios'], 400);
        }

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');

        // Verificar que producto y materia prima existan
        $pChk = $masterDb->prepare("SELECT id, nombre FROM productos WHERE id = :id LIMIT 1");
        $pChk->execute([':id' => $prodId]);
        $prod = $pChk->fetch();
        if (!$prod) {
            Security::jsonResponse(['success' => false, 'error' => 'Platillo no encontrado'], 404);
        }

        $mChk = $masterDb->prepare("SELECT id, nombre, unidad_medida FROM materias_primas WHERE id = :id LIMIT 1");
        $mChk->execute([':id' => $mpId]);
        $mp = $mChk->fetch();
        if (!$mp) {
            Security::jsonResponse(['success' => false, 'error' => 'Materia prima no encontrada'], 404);
        }

        // Upsert en tabla recetas
        $upsertStmt = $masterDb->prepare("
            INSERT INTO recetas (id_producto, id_materia_prima, cantidad_necesaria, unidad_medida, es_critico, notas, created_at)
            VALUES (:pid, :mpid, :cant, :um, :crit, :not, NOW())
            ON DUPLICATE KEY UPDATE 
                cantidad_necesaria = VALUES(cantidad_necesaria),
                unidad_medida = VALUES(unidad_medida),
                es_critico = VALUES(es_critico),
                notas = VALUES(notas)
        ");
        $upsertStmt->execute([
            ':pid'  => $prodId,
            ':mpid' => $mpId,
            ':cant' => $cantidadNec,
            ':um'   => $unidadMedida,
            ':crit' => $esCritico,
            ':not'  => $notas
        ]);

        // Sincronizar porciones calculadas del platillo
        $nuevasPorciones = InventoryManager::syncProductStock($masterDb, $prodId);
        invalidateCatalogueCache();

        AuditClient::log(
            'RECETA_ACTUALIZADA',
            'catalogo-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'cocina',
            null, null, null,
            [
                'producto' => $prod['nombre'],
                'ingrediente' => $mp['nombre'],
                'cantidad' => UnitConverter::format($cantidadNec, $unidadMedida),
                'porciones_resultantes' => $nuevasPorciones
            ],
            'EXITO'
        );

        Security::jsonResponse([
            'success'          => true,
            'message'          => "Ingrediente '{$mp['nombre']}' asignado a '{$prod['nombre']}'. Porciones disponibles ahora: {$nuevasPorciones}",
            'porciones_actuales' => $nuevasPorciones
        ]);
    }

    // ELIMINAR INGREDIENTE DE LA RECETA DE UN PLATILLO (Admin / Cocina)
    if ($method === 'DELETE' && preg_match('#^/productos/([0-9]+)/receta/([0-9]+)$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administración y cocina'], 403);
        }

        $prodId = (int)$matches[1];
        $mpId = (int)$matches[2];

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $del = $masterDb->prepare("DELETE FROM recetas WHERE id_producto = :pid AND id_materia_prima = :mpid");
        $del->execute([':pid' => $prodId, ':mpid' => $mpId]);

        $nuevasPorciones = InventoryManager::syncProductStock($masterDb, $prodId);
        invalidateCatalogueCache();

        Security::jsonResponse([
            'success' => true,
            'message' => 'Ingrediente eliminado de la receta',
            'porciones_actuales' => $nuevasPorciones
        ]);
    }

    // SINCRONIZAR STOCK DE TODOS LOS PLATILLOS (Admin / Cocina)
    if ($method === 'POST' && $path === '/recetas/sincronizar') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'cocina'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido'], 403);
        }

        $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
        $synced = InventoryManager::syncAllProductsStock($masterDb);
        invalidateCatalogueCache();

        Security::jsonResponse([
            'success' => true,
            'message' => 'Stock culinario sincronizado para todos los platillos',
            'platillos_sincronizados' => count($synced)
        ]);
    }

    Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

} catch (Throwable $e) {
    error_log("[CATALOGO SERVICE EXCEPTION] " . $e->getMessage());
    Security::jsonResponse(['error' => 'Error interno en catalogo-service: ' . $e->getMessage()], 500);
}

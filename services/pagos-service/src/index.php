<?php
// ==============================================================================
// pagos-service - Pasarela de Pagos Stripe / PayPal (PCI-DSS & OWASP A08)
// CUMPLIMIENTO ESTRICTO PCI-DSS:
// NUNCA almacenar card_number, cvv ni fecha de expiración en ninguna tabla.
// Solo se almacena el payment_intent_id / transaction_id de Stripe.
// ==============================================================================

require_once __DIR__ . '/../../common/Database.php';
require_once __DIR__ . '/../../common/JWT.php';
require_once __DIR__ . '/../../common/Security.php';
require_once __DIR__ . '/../../common/AuditClient.php';

Security::applySecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api/pagos#', '', $path);
if ($path === '' || $path === false) $path = '/';

try {
    $db = Database::getMasterConnection($_ENV['DB_PAGOS_NAME'] ?? 'pagos_db');

    // Healthcheck
    if ($method === 'GET' && ($path === '/health' || $path === '/healthcheck')) {
        Security::jsonResponse(['status' => 'UP', 'service' => 'pagos-service', 'timestamp' => date('c')]);
    }

    // AUDITORÍA REAL DE ESQUEMA PCI-DSS (GET /pci-check)
    if ($method === 'GET' && $path === '/pci-check') {
        $colsStmt = $db->query("DESCRIBE pagos");
        $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

        $columnNames = array_column($cols, 'Field');
        $prohibited = ['card_number', 'pan', 'cvv', 'cvc', 'exp_date', 'expiration_date', 'numero_tarjeta', 'tarjeta'];
        $foundProhibited = array_intersect($prohibited, array_map('strtolower', $columnNames));

        $sampleStmt = $db->query("SELECT id, id_pedido, monto, moneda, metodo, payment_intent_id, stripe_status, fecha_pago FROM pagos ORDER BY id DESC LIMIT 5");
        $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);

        Security::jsonResponse([
            'success' => true,
            'pci_compliant' => empty($foundProhibited),
            'columns_present' => $columnNames,
            'prohibited_found' => array_values($foundProhibited),
            'sample_records' => $samples,
            'timestamp' => date('c')
        ]);
    }

    // LISTAR PAGOS (GET / o GET /list - Admin Only)
    if ($method === 'GET' && ($path === '/' || $path === '/list')) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'auditor'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores y auditores'], 403);
        }

        $stmt = $db->query("
            SELECT id, id_pedido, monto, moneda, metodo, payment_intent_id, stripe_status, cliente_email, fecha_pago
            FROM pagos
            ORDER BY fecha_pago DESC
            LIMIT 100
        ");
        $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Security::jsonResponse([
            'success' => true,
            'total' => count($pagos),
            'data' => $pagos
        ]);
    }

    // INICIAR PAGO (POST /iniciar)
    if ($method === 'POST' && $path === '/iniciar') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 401);
        }

        $input = Security::getJsonInput();
        $pedidoId = (int)($input['pedido_id'] ?? 0);

        if ($pedidoId <= 0) {
            Security::jsonResponse(['success' => false, 'error' => 'ID de pedido requerido'], 400);
        }

        // Consultar pedido en pedidos_db
        $pedDb = Database::getMasterConnection($_ENV['DB_PEDIDOS_NAME'] ?? 'pedidos_db');
        $pedStmt = $pedDb->prepare("
            SELECT p.*, c.usuario_id, c.email
            FROM pedidos p
            JOIN clientes c ON p.id_cliente = c.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $pedStmt->execute([':id' => $pedidoId]);
        $pedido = $pedStmt->fetch();

        if (!$pedido) {
            Security::jsonResponse(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        // Control de acceso OWASP A01: el cliente solo puede pagar sus propios pedidos
        if (($user['rol'] ?? '') !== 'admin' && (int)$pedido['usuario_id'] !== (int)$user['id']) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso denegado a este pedido'], 403);
        }

        if ($pedido['estado'] === 'pagado') {
            Security::jsonResponse(['success' => false, 'error' => 'Este pedido ya fue pagado previamente'], 400);
        }

        // Generar identificador de intención de pago seguro (Stripe format)
        // En producción se comunica con Stripe API usando STRIPE_SECRET_KEY
        $paymentIntentId = 'pi_3Nz' . bin2hex(random_bytes(10));
        $clientSecret = $paymentIntentId . '_secret_' . bin2hex(random_bytes(8));

        // REGISTRAR EVENTO AUDITORÍA: PAGO_INICIADO
        AuditClient::log(
            'PAGO_INICIADO',
            'pagos-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'cliente',
            $pedidoId,
            null,
            null,
            ['monto' => (float)$pedido['total'], 'moneda' => 'USD', 'payment_intent_id' => $paymentIntentId],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => 'Intención de pago generada exitosamente',
            'payment_intent_id' => $paymentIntentId,
            'client_secret'     => $clientSecret,
            'monto'             => (float)$pedido['total'],
            'moneda'            => 'USD',
            'stripe_public_key' => $_ENV['STRIPE_PUBLIC_KEY'] ?? 'pk_test_demo'
        ]);
    }

    // CONFIRMAR PAGO (POST /confirmar)
    if ($method === 'POST' && $path === '/confirmar') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 401);
        }

        $input = Security::getJsonInput();
        $pedidoId = (int)($input['pedido_id'] ?? 0);
        $paymentIntentId = Security::sanitizeString($input['payment_intent_id'] ?? '');
        $simularFallo = !empty($input['simular_fallo']); // Para pruebas de auditoría de pagos fallidos

        if ($pedidoId <= 0 || empty($paymentIntentId)) {
            Security::jsonResponse(['success' => false, 'error' => 'Parámetros incompletos'], 400);
        }

        $pedDb = Database::getMasterConnection($_ENV['DB_PEDIDOS_NAME'] ?? 'pedidos_db');
        $pedStmt = $pedDb->prepare("
            SELECT p.*, c.usuario_id, c.email
            FROM pedidos p
            JOIN clientes c ON p.id_cliente = c.id
            WHERE p.id = :id
            LIMIT 1
        ");
        $pedStmt->execute([':id' => $pedidoId]);
        $pedido = $pedStmt->fetch();

        if (!$pedido) {
            Security::jsonResponse(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        // Simulación o detección de fallo de pago (Fondo insuficiente, etc.)
        if ($simularFallo) {
            AuditClient::log(
                'PAGO_FALLIDO',
                'pagos-service',
                (int)$user['id'],
                $user['email'],
                $user['rol'] ?? 'cliente',
                $pedidoId,
                null,
                null,
                ['decline_code' => 'card_declined', 'motivo' => 'Fondos insuficientes o tarjeta rechazada'],
                'FALLO'
            );
            Security::jsonResponse(['success' => false, 'error' => 'El pago fue rechazado por el banco emisor'], 402);
        }

        // GUARDAR REGISTRO DE PAGO CUMPLIENDO PCI-DSS
        // OJO: ÚNICAMENTE se guarda payment_intent_id. NUNCA datos de tarjeta.
        $monto = (float)$pedido['total'];
        $insPago = $db->prepare("
            INSERT INTO pagos (id_pedido, monto, moneda, metodo, payment_intent_id, stripe_status, cliente_email, metadata_segura, fecha_pago)
            VALUES (:pid, :monto, 'USD', 'stripe', :intent, 'succeeded', :email, :meta, NOW())
        ");
        $metadataSegura = json_encode([
            'processor' => 'Stripe Payments',
            'verified_pci_dss' => true,
            'card_stored' => false
        ]);
        $insPago->execute([
            ':pid'   => $pedidoId,
            ':monto' => $monto,
            ':intent'=> $paymentIntentId,
            ':email' => $pedido['email'],
            ':meta'  => $metadataSegura
        ]);
        $pagoId = (int)$db->lastInsertId();

        // Actualizar estado del pedido a 'pagado'
        $upPed = $pedDb->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id");
        $upPed->execute([':id' => $pedidoId]);

        // REGISTRAR EVENTO AUDITORÍA: PAGO_CONFIRMADO
        AuditClient::log(
            'PAGO_CONFIRMADO',
            'pagos-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'] ?? 'cliente',
            $pedidoId,
            $pagoId,
            ['estado_anterior' => $pedido['estado']],
            ['payment_intent_id' => $paymentIntentId, 'monto' => $monto, 'status' => 'succeeded'],
            'EXITO'
        );

        // Notificar por correo (Disparo asíncrono a notificaciones-service)
        try {
            $notifPayload = json_encode([
                'tipo' => 'confirmacion_pedido',
                'email' => $pedido['email'],
                'nombre' => $user['nombre'],
                'numero_pedido' => $pedido['numero_pedido'],
                'total' => $monto
            ]);
            $ch = curl_init('http://notificaciones-service:8085/enviar');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $notifPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) {}

        Security::jsonResponse([
            'success' => true,
            'message' => 'Pago procesado exitosamente conforme a PCI-DSS',
            'pago_id' => $pagoId,
            'payment_intent_id' => $paymentIntentId,
            'monto' => $monto,
            'estado_pedido' => 'pagado'
        ]);
    }

    // WEBHOOK STRIPE CON VERIFICACIÓN DE FIRMA (OWASP A08: Software and Data Integrity)
    if ($method === 'POST' && $path === '/webhook') {
        $rawPayload = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? 'whsec_technova_dummy_signature_token_test_2026';

        // Validar cabecera de firma
        $isValidSignature = false;
        if (!empty($sigHeader)) {
            // En sandbox Stripe: parsear t=timestamp,v1=signature
            parse_str(str_replace(',', '&', $sigHeader), $sigParts);
            $timestamp = $sigParts['t'] ?? '';
            $signature = $sigParts['v1'] ?? '';

            if ($timestamp && $signature) {
                $signedPayload = "{$timestamp}.{$rawPayload}";
                $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
                if (hash_equals($expectedSignature, $signature)) {
                    $isValidSignature = true;
                }
            }
        }

        // Si la firma no coincide: ALERTA DE SEGURIDAD / FRAUDE (A08)
        if (!$isValidSignature) {
            AuditClient::log(
                'WEBHOOK_STRIPE_RECIBIDO',
                'pagos-service',
                null,
                'unverified_webhook@stripe.com',
                'anonimo',
                null,
                null,
                null,
                ['firma_recibida' => substr($sigHeader, 0, 30) . '...', 'motivo' => 'Firma Stripe-Signature no válida'],
                'FRAUDE'
            );
            Security::jsonResponse(['success' => false, 'error' => 'Firma de webhook inválida. Acceso rechazado.'], 400);
        }

        // Firma válida: Registrar evento y procesar
        $eventData = json_decode($rawPayload, true) ?: [];
        $eventType = $eventData['type'] ?? 'payment_intent.succeeded';

        AuditClient::log(
            'WEBHOOK_STRIPE_RECIBIDO',
            'pagos-service',
            null,
            'api_webhook@stripe.com',
            'servicio',
            null,
            null,
            null,
            ['event' => $eventType, 'signature_verified' => true],
            'EXITO'
        );

        Security::jsonResponse(['success' => true, 'received' => true]);
    }

    Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

} catch (Throwable $e) {
    error_log("[PAGOS SERVICE EXCEPTION] " . $e->getMessage());
    Security::jsonResponse(['error' => 'Error interno en pagos-service'], 500);
}

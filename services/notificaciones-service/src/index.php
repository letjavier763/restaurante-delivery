<?php
// ==============================================================================
// notificaciones-service - Envío Transaccional de Emails (EmailJS)
// ==============================================================================

require_once __DIR__ . '/../../common/Security.php';

Security::applySecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api/notificaciones#', '', $path);
if ($path === '' || $path === false) $path = '/';

if ($path === '/health' || $path === '/') {
    Security::jsonResponse(['status' => 'UP', 'service' => 'notificaciones-service', 'timestamp' => date('c')]);
}

if ($method === 'POST' && $path === '/enviar') {
    $input = Security::getJsonInput();
    $tipo = Security::sanitizeString($input['tipo'] ?? 'general');
    $email = Security::sanitizeEmail($input['email'] ?? '');
    $nombre = Security::sanitizeString($input['nombre'] ?? 'Cliente');
    $numeroPedido = Security::sanitizeString($input['numero_pedido'] ?? '');
    $total = Security::sanitizeFloat($input['total'] ?? 0);

    if (empty($email)) {
        Security::jsonResponse(['success' => false, 'error' => 'Email requerido'], 400);
    }

    // Integración EmailJS API (si están configuradas las credenciales)
    $serviceId = $_ENV['EMAILJS_SERVICE_ID'] ?? '';
    $templateId = $_ENV['EMAILJS_TEMPLATE_ID'] ?? '';
    $publicKey = $_ENV['EMAILJS_PUBLIC_KEY'] ?? '';

    $emailSent = true;
    $logMsg = "Correo transaccional simulado exitosamente a {$email} (Pedido: {$numeroPedido}, Total: \${$total})";

    if (!empty($serviceId) && !empty($templateId) && !empty($publicKey) && $publicKey !== 'user_technova_dummy_key_2026') {
        $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
        $payload = json_encode([
            'service_id' => $serviceId,
            'template_id' => $templateId,
            'user_id' => $publicKey,
            'template_params' => [
                'to_email' => $email,
                'to_name' => $nombre,
                'order_number' => $numeroPedido,
                'total_amount' => $total,
                'company_name' => $_ENV['APP_NAME'] ?? 'Bistró Gourmet'
            ]
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $emailSent = ($httpCode >= 200 && $httpCode < 300);
    }

    error_log("[NOTIFICACIONES] {$logMsg}");

    Security::jsonResponse([
        'success' => $emailSent,
        'message' => $logMsg,
        'destinatario' => $email,
        'tipo' => $tipo
    ]);
}

Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

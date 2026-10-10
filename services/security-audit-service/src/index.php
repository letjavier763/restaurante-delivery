<?php
// ==============================================================================
// security-audit-service - Microservicio de Auditoría y Trazabilidad Forense
// Requisitos de Seguridad:
// 1. APPEND-ONLY: Estrictamente PROHIBIDO endpoints PUT, PATCH o DELETE.
// 2. Control de Acceso: Visualización exclusiva para rol 'admin'.
// 3. 14 Eventos del ciclo de compra con trazabilidad inmutable.
// ==============================================================================

require_once __DIR__ . '/../../common/Database.php';
require_once __DIR__ . '/../../common/JWT.php';
require_once __DIR__ . '/../../common/Security.php';

Security::applySecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api/audit#', '', $path);
if ($path === '' || $path === false) $path = '/';

// Rechazo inmediato de métodos no permitidos (Garantía de Append-Only)
if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
    Security::jsonResponse([
        'error' => 'Operación no permitida. La base de datos de auditoría es estrictamente inmutable (APPEND-ONLY).'
    ], 405);
}

try {
    $db = Database::getAuditConnection();

    // Healthcheck
    if ($path === '/health' || $path === '/') {
        Security::jsonResponse(['status' => 'UP', 'service' => 'security-audit-service', 'timestamp' => date('c')]);
    }

    // REGISTRAR EVENTO (POST /events) - Ingesta de Logs
    if ($method === 'POST' && ($path === '/events' || $path === '')) {
        $input = Security::getJsonInput();
        $accion = Security::sanitizeString($input['accion'] ?? '');
        $servicio = Security::sanitizeString($input['servicio'] ?? 'desconocido');

        if (empty($accion)) {
            Security::jsonResponse(['success' => false, 'error' => 'Código de acción requerido'], 400);
        }

        $timestamp = date('Y-m-d H:i:s') . '.' . sprintf('%03d', (int)(microtime(true) * 1000) % 1000);
        $usuarioId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : null;
        $usuarioEmail = !empty($input['usuario_email']) ? Security::sanitizeEmail($input['usuario_email']) : null;
        $rol = in_array($input['rol'] ?? '', ['admin', 'cliente', 'anonimo', 'servicio', 'cocina', 'auditor', 'operador'], true) ? $input['rol'] : 'anonimo';
        $pedidoId = isset($input['pedido_id']) ? (int)$input['pedido_id'] : null;
        $pagoId = isset($input['pago_id']) ? (int)$input['pago_id'] : null;
        $datoAnterior = isset($input['dato_anterior']) ? json_encode($input['dato_anterior'], JSON_UNESCAPED_UNICODE) : null;
        $datoNuevo = isset($input['dato_nuevo']) ? json_encode($input['dato_nuevo'], JSON_UNESCAPED_UNICODE) : null;
        $ipOrigen = Security::sanitizeString($input['ip_origen'] ?? Security::getClientIP());
        $userAgent = Security::sanitizeString($input['user_agent'] ?? Security::getUserAgent());
        $resultado = in_array($input['resultado'] ?? '', ['EXITO', 'FALLO', 'FRAUDE'], true) ? $input['resultado'] : 'EXITO';

        $stmt = $db->prepare("
            INSERT INTO security_audit_logs
            (`timestamp`, `usuario_id`, `usuario_email`, `rol`, `accion`, `servicio`, `pedido_id`, `pago_id`, `dato_anterior`, `dato_nuevo`, `ip_origen`, `user_agent`, `resultado`)
            VALUES
            (:ts, :uid, :em, :rol, :acc, :srv, :ped, :pag, :ant, :nue, :ip, :ua, :res)
        ");
        $stmt->execute([
            ':ts'  => $timestamp,
            ':uid' => $usuarioId,
            ':em'  => $usuarioEmail,
            ':rol' => $rol,
            ':acc' => $accion,
            ':srv' => $servicio,
            ':ped' => $pedidoId,
            ':pag' => $pagoId,
            ':ant' => $datoAnterior,
            ':nue' => $datoNuevo,
            ':ip'  => $ipOrigen,
            ':ua'  => $userAgent,
            ':res' => $resultado,
        ]);

        Security::jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()], 201);
    }

    // CONSULTAR LOGS DE AUDITORÍA (GET /logs) - Admin Only (SEC-2)
    if ($method === 'GET' && ($path === '/logs' || $path === '')) {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'auditor'])) {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso denegado. Solo administradores y auditores pueden auditar el sistema.'], 403);
        }

        $accion = isset($_GET['accion']) ? Security::sanitizeString($_GET['accion']) : null;
        $resultado = isset($_GET['resultado']) ? Security::sanitizeString($_GET['resultado']) : null;
        $servicio = isset($_GET['servicio']) ? Security::sanitizeString($_GET['servicio']) : null;
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));

        $query = "SELECT * FROM security_audit_logs WHERE 1=1";
        $countQuery = "SELECT COUNT(*) as total FROM security_audit_logs WHERE 1=1";
        $params = [];

        if (!empty($accion)) {
            $query .= " AND accion = :accion";
            $countQuery .= " AND accion = :accion";
            $params[':accion'] = $accion;
        }
        if (!empty($resultado)) {
            $query .= " AND resultado = :resultado";
            $countQuery .= " AND resultado = :resultado";
            $params[':resultado'] = $resultado;
        }
        if (!empty($servicio)) {
            $query .= " AND servicio = :servicio";
            $countQuery .= " AND servicio = :servicio";
            $params[':servicio'] = $servicio;
        }

        // Conteo total
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['total'];

        // Consulta ordenada cronológicamente (más recientes primero)
        $query .= " ORDER BY timestamp DESC, id DESC LIMIT {$limit} OFFSET {$offset}";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        // Deserializar JSON si existe
        foreach ($logs as &$log) {
            if ($log['dato_anterior']) $log['dato_anterior'] = json_decode($log['dato_anterior'], true);
            if ($log['dato_nuevo']) $log['dato_nuevo'] = json_decode($log['dato_nuevo'], true);
        }

        Security::jsonResponse([
            'success' => true,
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'data'    => $logs
        ]);
    }

    // MÉTRICAS DE SEGURIDAD (GET /metrics) - Para Dashboard Admin Chart.js
    if ($method === 'GET' && $path === '/metrics') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'auditor'])) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 403);
        }

        // Totales por resultado
        $stmtRes = $db->query("SELECT resultado, COUNT(*) as count FROM security_audit_logs GROUP BY resultado");
        $resultados = $stmtRes->fetchAll();

        // Top eventos
        $stmtEventos = $db->query("SELECT accion, COUNT(*) as count FROM security_audit_logs GROUP BY accion ORDER BY count DESC LIMIT 10");
        $eventos = $stmtEventos->fetchAll();

        // Eventos por servicio
        $stmtSrv = $db->query("SELECT servicio, COUNT(*) as count FROM security_audit_logs GROUP BY servicio");
        $servicios = $stmtSrv->fetchAll();

        // Eventos recientes de fraude o fallo
        $stmtAlertas = $db->query("SELECT * FROM security_audit_logs WHERE resultado IN ('FALLO', 'FRAUDE') ORDER BY timestamp DESC LIMIT 5");
        $alertas = $stmtAlertas->fetchAll();

        $stmtTotal = $db->query("SELECT COUNT(*) FROM security_audit_logs");
        $total = (int)$stmtTotal->fetchColumn();

        Security::jsonResponse([
            'success' => true,
            'total'   => $total,
            'metrics' => [
                'por_resultado'     => $resultados,
                'top_eventos'       => $eventos,
                'por_servicio'      => $servicios,
                'alertas_recientes' => $alertas
            ]
        ]);
    }

    // REPORTE RESUMIDO (GET /report) - Para Entregable SEC-2
    if ($method === 'GET' && $path === '/report') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'auditor'])) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 403);
        }

        $stmtTotal = $db->query("SELECT COUNT(*) as total FROM security_audit_logs");
        $total = (int)$stmtTotal->fetch()['total'];

        $stmtResumen = $db->query("
            SELECT resultado, COUNT(*) as count,
                   ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM security_audit_logs), 2) as porcentaje
            FROM security_audit_logs GROUP BY resultado
        ");
        $resumen = $stmtResumen->fetchAll();

        $stmtUltimo = $db->query("SELECT timestamp FROM security_audit_logs ORDER BY timestamp DESC LIMIT 1");
        $ultimoEvento = $stmtUltimo->fetchColumn();

        $stmtPrimero = $db->query("SELECT timestamp FROM security_audit_logs ORDER BY timestamp ASC LIMIT 1");
        $primerEvento = $stmtPrimero->fetchColumn();

        Security::jsonResponse([
            'success' => true,
            'report'  => [
                'total_eventos'   => $total,
                'primer_evento'   => $primerEvento,
                'ultimo_evento'   => $ultimoEvento,
                'por_resultado'   => $resumen,
                'generado_en'     => date('c'),
                'cumplimiento'    => 'PCI-DSS v4.0 / OWASP Top 10 / STRIDE'
            ]
        ]);
    }

    // EXPORTAR LOGS (GET /export) - CSV o JSON — EVENTO: EXPORTACION_CLIENTES
    if ($method === 'GET' && $path === '/export') {
        $token = JWT::getBearerToken();
        // Permitir token por query param también para descargas directas
        if (!$token) {
            $token = Security::sanitizeString($_GET['token'] ?? '');
        }
        $user = $token ? JWT::decode($token) : null;
        if (!$user || !in_array($user['rol'] ?? '', ['admin', 'auditor'])) {
            Security::jsonResponse(['success' => false, 'error' => 'No autorizado'], 403);
        }

        $formato = strtolower(Security::sanitizeString($_GET['formato'] ?? 'json'));
        $limit = min(1000, max(1, (int)($_GET['limit'] ?? 500)));

        $stmt = $db->prepare("SELECT * FROM security_audit_logs ORDER BY timestamp DESC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        foreach ($logs as &$log) {
            if ($log['dato_anterior']) $log['dato_anterior'] = json_decode($log['dato_anterior'], true);
            if ($log['dato_nuevo']) $log['dato_nuevo'] = json_decode($log['dato_nuevo'], true);
        }

        // Registrar exportación en auditoría (self-log)
        $exportStmt = $db->prepare("
            INSERT INTO security_audit_logs (timestamp, usuario_id, usuario_email, rol, accion, servicio, dato_nuevo, ip_origen, resultado)
            VALUES (NOW(), :uid, :em, 'admin', 'EXPORTACION_AUDITORIA', 'security-audit-service', :datos, :ip, 'EXITO')
        ");
        $exportStmt->execute([
            ':uid'   => $user['id'] ?? null,
            ':em'    => $user['email'] ?? 'admin',
            ':datos' => json_encode(['formato' => $formato, 'registros' => count($logs)]),
            ':ip'    => Security::getClientIP()
        ]);

        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="technova_audit_log_' . date('Y-m-d') . '.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'timestamp', 'accion', 'servicio', 'usuario_email', 'rol', 'resultado', 'ip_origen']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log['id'], $log['timestamp'], $log['accion'], $log['servicio'],
                    $log['usuario_email'] ?? 'Anónimo', $log['rol'], $log['resultado'], $log['ip_origen']
                ]);
            }
            fclose($out);
            exit;
        }

        // JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="technova_audit_log_' . date('Y-m-d') . '.json"');
        echo json_encode([
            'exportado_en' => date('c'),
            'total'        => count($logs),
            'data'         => $logs
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }



    Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

} catch (Throwable $e) {
    error_log("[SECURITY AUDIT EXCEPTION] " . $e->getMessage());
    Security::jsonResponse(['error' => 'Error interno en security-audit-service'], 500);
}


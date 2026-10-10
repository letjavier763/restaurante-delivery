<?php
// ==============================================================================
// auth-service - Microservicio de Autenticación, JWT y Control de Acceso
// OWASP A01 (Broken Access Control), A02 (Cryptographic), A07 (Auth Failures)
// ==============================================================================

require_once __DIR__ . '/../../common/Database.php';
require_once __DIR__ . '/../../common/JWT.php';
require_once __DIR__ . '/../../common/Security.php';
require_once __DIR__ . '/../../common/AuditClient.php';

Security::applySecurityHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api/auth#', '', $path);
if ($path === '' || $path === false) $path = '/';

try {
    $db = Database::getMasterConnection($_ENV['DB_AUTH_NAME'] ?? 'auth_db');

    // Healthcheck
    if ($path === '/health' || $path === '/') {
        Security::jsonResponse(['status' => 'UP', 'service' => 'auth-service', 'timestamp' => date('c')]);
    }

    // REGISTRO DE CLIENTE
    if ($method === 'POST' && $path === '/register') {
        $input = Security::getJsonInput();
        $nombre = Security::sanitizeString($input['nombre'] ?? '');
        $email = Security::sanitizeEmail($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($nombre) || empty($email) || strlen($password) < 8) {
            Security::jsonResponse([
                'success' => false,
                'error' => 'Datos inválidos. La contraseña debe tener al menos 8 caracteres.'
            ], 400);
        }

        // Verificar si ya existe
        $checkStmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetch()) {
            Security::jsonResponse([
                'success' => false,
                'error' => 'El correo electrónico ya se encuentra registrado.'
            ], 409);
        }

        // Hasheo con bcrypt cost 12 (OWASP A02)
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $insertStmt = $db->prepare("
            INSERT INTO usuarios (nombre, email, password_hash, rol, activo)
            VALUES (:nombre, :email, :password_hash, 'cliente', 1)
        ");
        $insertStmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);
        $newUserId = (int)$db->lastInsertId();

        // Crear perfil en pedidos_db
        try {
            $pedidosDb = Database::getMasterConnection($_ENV['DB_PEDIDOS_NAME'] ?? 'pedidos_db');
            $pStmt = $pedidosDb->prepare("
                INSERT INTO clientes (usuario_id, nombre, email, telefono, direccion_defecto)
                VALUES (:uid, :nom, :em, :tel, :dir)
            ");
            $pStmt->execute([
                ':uid' => $newUserId,
                ':nom' => $nombre,
                ':em' => $email,
                ':tel' => Security::sanitizeString($input['telefono'] ?? ''),
                ':dir' => Security::sanitizeString($input['direccion'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log("[PERFIL CLIENTE ERROR] " . $e->getMessage());
        }

        // Log de Auditoría (REGISTRO_CLIENTE)
        AuditClient::log(
            'REGISTRO_CLIENTE',
            'auth-service',
            $newUserId,
            $email,
            'cliente',
            null,
            null,
            null,
            ['nombre' => $nombre, 'email' => $email],
            'EXITO'
        );

        // Generar JWT
        $token = JWT::encode([
            'id' => $newUserId,
            'nombre' => $nombre,
            'email' => $email,
            'rol' => 'cliente'
        ]);

        Security::jsonResponse([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'token' => $token,
            'user' => ['id' => $newUserId, 'nombre' => $nombre, 'email' => $email, 'rol' => 'cliente']
        ], 201);
    }

    // LOGIN
    if ($method === 'POST' && $path === '/login') {
        $input = Security::getJsonInput();
        $email = Security::sanitizeEmail($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $clientIp = Security::getClientIP();

        if (empty($email) || empty($password)) {
            Security::jsonResponse(['success' => false, 'error' => 'Email y contraseña requeridos'], 400);
        }

        // Rate limiting: 5 intentos fallidos -> bloqueo por 15 min (900 seg)
        $rateLimitKey = "login_attempts_{$email}_{$clientIp}";
        if (!Security::checkRateLimit($rateLimitKey, 5, 900)) {
            AuditClient::log('LOGIN_CLIENTE_FALLIDO', 'auth-service', null, $email, 'anonimo', null, null, null, ['bloqueo' => true, 'ip' => $clientIp], 'FRAUDE');
            Security::jsonResponse([
                'success' => false,
                'error' => 'Demasiados intentos fallidos. Su cuenta o IP ha sido bloqueada temporalmente por 15 minutos.'
            ], 429);
        }

        $stmt = $db->prepare("SELECT id, nombre, email, password_hash, rol, activo, bloqueado_hasta FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            AuditClient::log(
                'LOGIN_CLIENTE_FALLIDO',
                'auth-service',
                $user ? (int)$user['id'] : null,
                $email,
                'anonimo',
                null,
                null,
                null,
                ['motivo' => 'Credenciales inválidas'],
                'FALLO'
            );
            Security::jsonResponse(['success' => false, 'error' => 'Credenciales inválidas'], 401);
        }

        if ((int)$user['activo'] !== 1) {
            Security::jsonResponse(['success' => false, 'error' => 'Cuenta deshabilitada. Contacte soporte.'], 403);
        }

        // Login exitoso: Resetear rate limit
        Security::resetRateLimit($rateLimitKey);

        // Actualizar último acceso
        $upStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW(), intentos_fallidos = 0 WHERE id = :id");
        $upStmt->execute([':id' => $user['id']]);

        // Registrar Evento en Auditoría
        $evento = ($user['rol'] === 'admin') ? 'ACCESO_ADMIN_PANEL' : 'LOGIN_CLIENTE_EXITOSO';
        AuditClient::log(
            $evento,
            'auth-service',
            (int)$user['id'],
            $user['email'],
            $user['rol'],
            null,
            null,
            null,
            ['rol' => $user['rol'], 'metodo' => 'password'],
            'EXITO'
        );

        // Generar JWT
        $token = JWT::encode([
            'id' => (int)$user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
            'rol' => $user['rol']
        ]);

        Security::jsonResponse([
            'success' => true,
            'message' => 'Autenticación exitosa',
            'token' => $token,
            'user' => [
                'id' => (int)$user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ]
        ]);
    }

    // LOGOUT (TOKEN REVOCADO)
    if ($method === 'POST' && $path === '/logout') {
        $token = JWT::getBearerToken();
        if ($token) {
            $payload = JWT::decode($token);
            if ($payload && isset($payload['jti'])) {
                JWT::revoke($payload['jti'], (int)($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600));

                AuditClient::log(
                    'TOKEN_REVOCADO',
                    'auth-service',
                    $payload['id'] ?? null,
                    $payload['email'] ?? null,
                    $payload['rol'] ?? 'cliente',
                    null,
                    null,
                    null,
                    ['jti' => $payload['jti']],
                    'EXITO'
                );
            }
        }
        Security::jsonResponse(['success' => true, 'message' => 'Sesión cerrada exitosamente']);
    }

    // VERIFICAR TOKEN (Zero Trust)
    if ($method === 'GET' && $path === '/verify') {
        $token = JWT::getBearerToken();
        if (!$token) {
            Security::jsonResponse(['success' => false, 'error' => 'Token no provisto'], 401);
        }

        $payload = JWT::decode($token);
        if (!$payload) {
            Security::jsonResponse(['success' => false, 'error' => 'Token inválido o expirado'], 401);
        }

        Security::jsonResponse(['success' => true, 'user' => $payload]);
    }

    // LISTADO DE USUARIOS (Solo Admin - SEC GESTIÓN)
    if ($method === 'GET' && $path === '/users') {
        $token = JWT::getBearerToken();
        $user = $token ? JWT::decode($token) : null;
        if (!$user || ($user['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $stmt = $db->query("
            SELECT id, nombre, email, rol, activo, intentos_fallidos,
                   DATE_FORMAT(ultimo_acceso, '%Y-%m-%d %H:%i') AS ultimo_acceso,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') AS created_at
            FROM usuarios
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll();

        Security::jsonResponse([
            'success' => true,
            'total'   => count($users),
            'data'    => $users
        ]);
    }

    // EXPORTAR USUARIOS / CLIENTES (Admin Only)
    if ($method === 'GET' && $path === '/users/export') {
        $token = JWT::getBearerToken();
        $adminUser = $token ? JWT::decode($token) : null;
        if (!$adminUser || ($adminUser['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $formato = Security::sanitizeString($_GET['formato'] ?? 'json');
        $stmt = $db->query("SELECT id, nombre, email, rol, activo, ultimo_acceso, created_at FROM usuarios ORDER BY id ASC");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        AuditClient::log(
            'EXPORTACION_CLIENTES',
            'auth-service',
            (int)$adminUser['id'],
            $adminUser['email'],
            'admin',
            null, null, null,
            ['formato' => $formato, 'total_usuarios' => count($users)],
            'EXITO'
        );

        if ($formato === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="reporte_usuarios_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nombre', 'Email', 'Rol', 'Activo', 'Último Acceso', 'Fecha Creación']);
            foreach ($users as $u) {
                fputcsv($out, $u);
            }
            fclose($out);
            exit();
        }

        Security::jsonResponse(['success' => true, 'total' => count($users), 'data' => $users]);
    }

    // CAMBIAR ESTADO DE USUARIO (Activar/Desactivar - Solo Admin)
    if ($method === 'PUT' && preg_match('#^/users/([0-9]+)/status$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $adminUser = $token ? JWT::decode($token) : null;
        if (!$adminUser || ($adminUser['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $targetId = (int)$matches[1];
        $input  = Security::getJsonInput();
        $activo = (int)!empty($input['activo']);

        // No desactivarse a uno mismo
        if ($targetId === (int)$adminUser['id']) {
            Security::jsonResponse(['success' => false, 'error' => 'No puedes desactivar tu propia cuenta de administrador'], 400);
        }

        $checkStmt = $db->prepare("SELECT id, nombre, email, activo FROM usuarios WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $targetId]);
        $targetUser = $checkStmt->fetch();
        if (!$targetUser) {
            Security::jsonResponse(['success' => false, 'error' => 'Usuario no encontrado'], 404);
        }

        $upStmt = $db->prepare("UPDATE usuarios SET activo = :activo WHERE id = :id");
        $upStmt->execute([':activo' => $activo, ':id' => $targetId]);

        AuditClient::log(
            $activo ? 'USUARIO_ACTIVADO' : 'USUARIO_DESACTIVADO',
            'auth-service',
            (int)$adminUser['id'],
            $adminUser['email'],
            'admin',
            null, null,
            ['usuario_id' => $targetId, 'email' => $targetUser['email'], 'activo_anterior' => $targetUser['activo']],
            ['usuario_id' => $targetId, 'email' => $targetUser['email'], 'activo_nuevo' => $activo],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => "Cuenta del usuario '{$targetUser['nombre']}' " . ($activo ? 'activada' : 'desactivada') . ' exitosamente'
        ]);
    }

    // CAMBIAR ROL DE USUARIO (Solo Admin)
    if ($method === 'PUT' && preg_match('#^/users/([0-9]+)/rol$#', $path, $matches)) {
        $token = JWT::getBearerToken();
        $adminUser = $token ? JWT::decode($token) : null;
        if (!$adminUser || ($adminUser['rol'] ?? '') !== 'admin') {
            Security::jsonResponse(['success' => false, 'error' => 'Acceso restringido a administradores'], 403);
        }

        $targetId = (int)$matches[1];
        $input    = Security::getJsonInput();
        $nuevoRol = Security::sanitizeString($input['rol'] ?? '');

        $rolesPermitidos = ['admin', 'cocina', 'cliente', 'auditor', 'operador'];
        if (!in_array($nuevoRol, $rolesPermitidos)) {
            Security::jsonResponse(['success' => false, 'error' => "Rol inválido. Permitidos: " . implode(', ', $rolesPermitidos)], 400);
        }

        $checkStmt = $db->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $targetId]);
        $targetUser = $checkStmt->fetch();
        if (!$targetUser) {
            Security::jsonResponse(['success' => false, 'error' => 'Usuario no encontrado'], 404);
        }

        $upStmt = $db->prepare("UPDATE usuarios SET rol = :rol WHERE id = :id");
        $upStmt->execute([':rol' => $nuevoRol, ':id' => $targetId]);

        AuditClient::log(
            'ROL_USUARIO_CAMBIADO',
            'auth-service',
            (int)$adminUser['id'],
            $adminUser['email'],
            'admin',
            null, null,
            ['usuario_id' => $targetId, 'email' => $targetUser['email'], 'rol_anterior' => $targetUser['rol']],
            ['usuario_id' => $targetId, 'email' => $targetUser['email'], 'rol_nuevo' => $nuevoRol],
            'EXITO'
        );

        Security::jsonResponse([
            'success' => true,
            'message' => "Rol de '{$targetUser['nombre']}' cambiado a '{$nuevoRol}' exitosamente"
        ]);
    }

    Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

} catch (Throwable $e) {
    error_log("[AUTH SERVICE EXCEPTION] " . $e->getMessage());
    Security::jsonResponse(['error' => 'Error interno en auth-service'], 500);
}

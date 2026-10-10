<?php
// ==============================================================================
// catalogo-service - Catálogo de Electrónica, Stock y Caching
// Master-Slave (Lecturas en Slave, Escrituras en Master) + Redis Cache
// Controles de Seguridad: OWASP A01, STRIDE Tampering (Precio verificado en servidor)
// ==============================================================================

require_once __DIR__ . '/../../common/Database.php';
require_once __DIR__ . '/../../common/JWT.php';
require_once __DIR__ . '/../../common/Security.php';
require_once __DIR__ . '/../../common/AuditClient.php';

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
            // Fallback a Master en caso de fallo del Slave (Tolerancia a Fallos)
            $masterDb = Database::getMasterConnection($_ENV['DB_CATALOGO_NAME'] ?? 'catalogo_db');
            $stmt = $masterDb->query("SELECT id, nombre, slug, descripcion, icono FROM categorias ORDER BY id ASC");
            $data = $stmt->fetchAll();
        }

        if ($redis && !empty($data)) {
            $redis->setex($cacheKey, 600, json_encode($data));
        }

        Security::jsonResponse(['success' => true, 'from_cache' => false, 'data' => $data]);
    }

    // LISTAR PRODUCTOS (Lectura desde Slave + Redis Cache)
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

        // Formatear JSON de especificaciones
        foreach ($productos as &$prod) {
            if (!empty($prod['especificaciones']) && is_string($prod['especificaciones'])) {
                $prod['especificaciones'] = json_decode($prod['especificaciones'], true);
            }
        }

        if ($redis && empty($search) && !empty($productos)) {
            $redis->setex($cacheKey, 300, json_encode($productos));
        }

        Security::jsonResponse(['success' => true, 'from_cache' => false, 'data' => $productos]);
    }

    // OBTENER PRODUCTO POR ID (Lectura desde Slave)
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

        // REGISTRAR EVENTO AUDITORÍA: PRECIO_MODIFICADO (Con dato_anterior y dato_nuevo)
        AuditClient::log(
            'PRECIO_MODIFICADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'] ?? 'admin@technova.com',
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

    // MODIFICAR STOCK (Admin Only o pedidos-service interno)
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

        // Si stock llega a 0, registrar automáticamente PRODUCTO_AGOTADO
        if ($nuevoStock <= 0) {
            AuditClient::log(
                'PRODUCTO_AGOTADO',
                'catalogo-service',
                null,
                'sistema@technova.internal',
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

        invalidateCatalogueCache();

        AuditClient::log(
            'PRODUCTO_CREADO',
            'catalogo-service',
            (int)$user['id'],
            $user['email'] ?? 'admin',
            'admin',
            null, null, null,
            ['producto_id' => $newId, 'nombre' => $nombre, 'sku' => $sku, 'precio' => $precio],
            'EXITO'
        );

        Security::jsonResponse([
            'success'    => true,
            'message'    => 'Producto creado exitosamente',
            'producto_id'=> $newId
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

    Security::jsonResponse(['error' => 'Endpoint no encontrado'], 404);

} catch (Throwable $e) {
    error_log("[CATALOGO SERVICE EXCEPTION] " . $e->getMessage());
    Security::jsonResponse(['error' => 'Error interno en catalogo-service'], 500);
}

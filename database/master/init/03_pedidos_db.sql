-- ==============================================================================
-- 03_pedidos_db.sql - Creación de base de datos para pedidos-service
-- Control de acceso OWASP A01 (Broken Access Control)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `pedidos_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pedidos_db`;

CREATE TABLE IF NOT EXISTS `clientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL UNIQUE,
    `nombre` VARCHAR(120) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `telefono` VARCHAR(30) NULL,
    `direccion_defecto` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_usuario` (`usuario_id`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `direcciones_entrega` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_cliente` INT NOT NULL,
    `direccion` VARCHAR(255) NOT NULL,
    `ciudad` VARCHAR(100) NOT NULL,
    `departamento` VARCHAR(100) NOT NULL,
    `codigo_postal` VARCHAR(20) NULL,
    `referencia` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dir_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pedidos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `uuid` CHAR(36) NOT NULL UNIQUE,
    `numero_pedido` VARCHAR(30) NOT NULL UNIQUE,
    `id_cliente` INT NOT NULL,
    `tipo_entrega` ENUM('delivery', 'pickup') NOT NULL DEFAULT 'delivery',
    `sucursal` VARCHAR(120) NULL DEFAULT 'Sucursal Principal - Zona 10',
    `subtotal` DECIMAL(10, 2) NOT NULL,
    `impuesto` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `envio` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `propina` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `total` DECIMAL(10, 2) NOT NULL,
    `estado` ENUM('recibido', 'pendiente', 'pagado', 'en_preparacion', 'listo', 'en_camino', 'entregado', 'cancelado') NOT NULL DEFAULT 'recibido',
    `direccion_envio` TEXT NOT NULL,
    `telefono_contacto` VARCHAR(30) NOT NULL,
    `notas` TEXT NULL,
    `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cliente` (`id_cliente`),
    INDEX `idx_estado` (`estado`),
    INDEX `idx_tipo_entrega` (`tipo_entrega`),
    INDEX `idx_uuid` (`uuid`),
    CONSTRAINT `fk_ped_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detalle_pedidos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_pedido` INT NOT NULL,
    `id_producto` INT NOT NULL,
    `sku` VARCHAR(50) NOT NULL,
    `nombre_producto` VARCHAR(150) NOT NULL,
    `precio_unitario` DECIMAL(10, 2) NOT NULL,
    `cantidad` INT NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    `notas` VARCHAR(255) NULL,
    INDEX `idx_pedido` (`id_pedido`),
    CONSTRAINT `fk_det_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

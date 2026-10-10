-- ==============================================================================
-- 02_catalogo_db.sql - Creación de base de datos para catalogo-service
-- Bistró Gourmet - Restaurante & Comida en Línea (Delivery / Pickup)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `catalogo_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `catalogo_db`;

CREATE TABLE IF NOT EXISTS `categorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `icono` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `productos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_categoria` INT NOT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `descripcion` TEXT NULL,
    `especificaciones` JSON NULL,
    `precio` DECIMAL(10, 2) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `imagen_url` VARCHAR(255) NOT NULL,
    `destacado` TINYINT(1) NOT NULL DEFAULT 0,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_categoria` (`id_categoria`),
    INDEX `idx_activo` (`activo`),
    INDEX `idx_destacado` (`destacado`),
    CONSTRAINT `fk_prod_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- 01_auth_db.sql - Creación de base de datos y tabla para auth-service
-- OWASP A01 (Broken Access Control), A07 (Auth Failures), A02 (Cryptographic)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `auth_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `auth_db`;

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol` ENUM('admin', 'cocina', 'cliente', 'auditor', 'operador') NOT NULL DEFAULT 'cliente',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `intentos_fallidos` INT NOT NULL DEFAULT 0,
    `bloqueado_hasta` DATETIME NULL,
    `ultimo_acceso` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

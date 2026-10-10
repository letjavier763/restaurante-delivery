-- ==============================================================================
-- 04_pagos_db.sql - Creación de base de datos para pagos-service
-- Cumplimiento estricto PCI-DSS (Sección 7 del documento):
-- PROHIBIDO: card_number, cvv, expiration_date.
-- PERMITIDO ÚNICAMENTE: payment_intent_id, referencia, estado, monto, moneda.
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `pagos_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pagos_db`;

CREATE TABLE IF NOT EXISTS `pagos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_pedido` INT NOT NULL UNIQUE,
    `monto` DECIMAL(10, 2) NOT NULL,
    `moneda` VARCHAR(5) NOT NULL DEFAULT 'USD',
    `metodo` ENUM('stripe', 'paypal', 'transferencia') NOT NULL DEFAULT 'stripe',
    `payment_intent_id` VARCHAR(150) NOT NULL UNIQUE,
    `stripe_status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `cliente_email` VARCHAR(120) NOT NULL,
    `metadata_segura` JSON NULL,
    `fecha_pago` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_pedido` (`id_pedido`),
    INDEX `idx_intent` (`payment_intent_id`),
    INDEX `idx_status` (`stripe_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

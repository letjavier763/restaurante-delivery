-- ==============================================================================
-- 01_audit_db.sql - Microservicio Dedicado security-audit-service
-- Estructura de auditoría inmutable (APPEND-ONLY) para ciclo de compra
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `audit_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `audit_db`;

CREATE TABLE IF NOT EXISTS `security_audit_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `timestamp` DATETIME(3) NOT NULL,
    `usuario_id` INT NULL,
    `usuario_email` VARCHAR(120) NULL,
    `rol` ENUM('admin', 'cliente', 'anonimo', 'servicio', 'cocina', 'auditor', 'operador') NOT NULL DEFAULT 'anonimo',
    `accion` VARCHAR(80) NOT NULL,
    `servicio` VARCHAR(50) NOT NULL,
    `pedido_id` INT NULL,
    `pago_id` INT NULL,
    `dato_anterior` JSON NULL,
    `dato_nuevo` JSON NULL,
    `ip_origen` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `resultado` ENUM('EXITO', 'FALLO', 'FRAUDE') NOT NULL DEFAULT 'EXITO',
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_accion` (`accion`),
    INDEX `idx_usuario` (`usuario_id`),
    INDEX `idx_servicio` (`servicio`),
    INDEX `idx_resultado` (`resultado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enforzamiento de Principio de Inmutabilidad y Mínimo Privilegio (ISO 27001 A.8.15 & PCI-DSS)
-- Usuario de aplicación de auditoría: SOLO INSERT y SELECT (APPEND-ONLY)
CREATE USER IF NOT EXISTS 'audit_service_user'@'%' IDENTIFIED BY 'AuditServicePass_2026!';
GRANT INSERT, SELECT ON `audit_db`.`security_audit_logs` TO 'audit_service_user'@'%';
REVOKE UPDATE, DELETE, DROP, ALTER ON `audit_db`.`security_audit_logs` FROM 'audit_service_user'@'%';

FLUSH PRIVILEGES;

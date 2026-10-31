-- ==============================================================================
-- 02_catalogo_db.sql - Creación de base de datos para catalogo-service
-- Bistró Gourmet - Restaurante & Comida en Línea (Delivery / Pickup)
-- Inventario Culinario, Materia Prima, Escandallo de Recetas (BOM) y Kardex
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

-- -----------------------------------------------------------------------------
-- 3. MATERIAS PRIMAS E INSUMOS GASTRONÓMICOS (Inventario base con unidades de medida)
-- Medición en unidades del mundo culinario: g, kg, lb, oz, ml, l, unid
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `materias_primas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(150) NOT NULL,
    `categoria` VARCHAR(80) NOT NULL DEFAULT 'Insumos Generales',
    `unidad_medida` VARCHAR(20) NOT NULL, -- 'g', 'kg', 'lb', 'oz', 'ml', 'l', 'unid'
    `stock_actual` DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    `stock_minimo` DECIMAL(12, 3) NOT NULL DEFAULT 5.000,
    `costo_unitario` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mp_activo` (`activo`),
    INDEX `idx_mp_categoria` (`categoria`),
    INDEX `idx_mp_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. ESCANDALLO DE RECETAS / BILL OF MATERIALS (BOM)
-- Relación de ingredientes y cantidades requeridas por cada comida/platillo
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recetas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_producto` INT NOT NULL,
    `id_materia_prima` INT NOT NULL,
    `cantidad_necesaria` DECIMAL(12, 3) NOT NULL,
    `unidad_medida` VARCHAR(20) NOT NULL, -- Unidad de la receta (ej. g, lb, ml, unid)
    `es_critico` TINYINT(1) NOT NULL DEFAULT 1,
    `notas` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_producto_materia` (`id_producto`, `id_materia_prima`),
    INDEX `idx_rec_producto` (`id_producto`),
    INDEX `idx_rec_mp` (`id_materia_prima`),
    CONSTRAINT `fk_rec_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_materia_prima` FOREIGN KEY (`id_materia_prima`) REFERENCES `materias_primas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. KARDEX Y MOVIMIENTOS HISTÓRICOS DE INVENTARIO
-- Registro auditable de entradas por compras, salidas por órdenes y mermas
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_materia_prima` INT NOT NULL,
    `tipo_movimiento` ENUM('entrada_compra', 'consumo_orden', 'merma_ajuste', 'ajuste_manual', 'devolucion_orden') NOT NULL,
    `cantidad` DECIMAL(12, 3) NOT NULL,
    `stock_anterior` DECIMAL(12, 3) NOT NULL,
    `stock_nuevo` DECIMAL(12, 3) NOT NULL,
    `unidad_medida` VARCHAR(20) NOT NULL,
    `referencia_tipo` VARCHAR(50) NULL, -- 'pedido', 'compra', 'ajuste'
    `referencia_id` VARCHAR(50) NULL,
    `motivo` VARCHAR(255) NULL,
    `usuario_id` INT NULL,
    `usuario_email` VARCHAR(120) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mov_mp` (`id_materia_prima`),
    INDEX `idx_mov_tipo` (`tipo_movimiento`),
    INDEX `idx_mov_fecha` (`created_at`),
    CONSTRAINT `fk_mov_materia_prima` FOREIGN KEY (`id_materia_prima`) REFERENCES `materias_primas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

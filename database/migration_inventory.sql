-- ==============================================================================
-- migration_inventory.sql - Migración Incremental de Inventario de Materias Primas,
-- Escandallo de Recetas (BOM) y Kardex para Bistró Gourmet
-- Ejecutar en MariaDB Master:
-- docker exec -i technova-mariadb-master mariadb -u root -pAppMasterPass_2026! < database/migration_inventory.sql
-- ==============================================================================

USE `catalogo_db`;

-- 1. TABLA: MATERIAS PRIMAS
CREATE TABLE IF NOT EXISTS `materias_primas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE,
    `nombre` VARCHAR(150) NOT NULL,
    `categoria` VARCHAR(80) NOT NULL DEFAULT 'Insumos Generales',
    `unidad_medida` VARCHAR(20) NOT NULL, -- 'g', 'kg', 'lb', 'oz', 'ml', 'l', 'unid'
    `stock_actual` DECIMAL(12, 3) NOT NULL DEFAULT 0.000,
    `stock_minimo` DECIMAL(12, 3) NOT NULL DEFAULT 5.000,
    `costo_unitario` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `es_perecedero` TINYINT(1) NOT NULL DEFAULT 0,
    `fecha_vencimiento` DATE NULL,
    `dias_alerta` INT NOT NULL DEFAULT 3,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_mp_activo` (`activo`),
    INDEX `idx_mp_categoria` (`categoria`),
    INDEX `idx_mp_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA: RECETAS / BILL OF MATERIALS (BOM)
CREATE TABLE IF NOT EXISTS `recetas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_producto` INT NOT NULL,
    `id_materia_prima` INT NOT NULL,
    `cantidad_necesaria` DECIMAL(12, 3) NOT NULL,
    `unidad_medida` VARCHAR(20) NOT NULL,
    `es_critico` TINYINT(1) NOT NULL DEFAULT 1,
    `notas` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_producto_materia` (`id_producto`, `id_materia_prima`),
    INDEX `idx_rec_producto` (`id_producto`),
    INDEX `idx_rec_mp` (`id_materia_prima`),
    CONSTRAINT `fk_rec_producto` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rec_materia_prima` FOREIGN KEY (`id_materia_prima`) REFERENCES `materias_primas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABLA: MOVIMIENTOS HISTÓRICOS / KARDEX
CREATE TABLE IF NOT EXISTS `movimientos_inventario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `id_materia_prima` INT NOT NULL,
    `tipo_movimiento` ENUM('entrada_compra', 'consumo_orden', 'merma_ajuste', 'ajuste_manual', 'devolucion_orden') NOT NULL,
    `cantidad` DECIMAL(12, 3) NOT NULL,
    `stock_anterior` DECIMAL(12, 3) NOT NULL,
    `stock_nuevo` DECIMAL(12, 3) NOT NULL,
    `unidad_medida` VARCHAR(20) NOT NULL,
    `referencia_tipo` VARCHAR(50) NULL,
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

-- 4. POBLADO DE MATERIAS PRIMAS (g, kg, lb, oz, ml, l, unid)
INSERT INTO `materias_primas` (`id`, `codigo`, `nombre`, `categoria`, `unidad_medida`, `stock_actual`, `stock_minimo`, `costo_unitario`, `activo`) VALUES
(1, 'MP-CARNE-ANGUS-BIFE', 'Corte Angus Certificado para Bife (Pieza)', 'Carnes & Proteínas', 'kg', 18.500, 5.000, 22.00, 1),
(2, 'MP-CARNE-ANGUS-MOLIDA', 'Carne de Res Angus Molida Premium (80/20)', 'Carnes & Proteínas', 'g', 15000.000, 2000.000, 0.015, 1),
(3, 'MP-LOMO-ANGUS', 'Lomo Angus de Exportación Sellado', 'Carnes & Proteínas', 'g', 6000.000, 1500.000, 0.025, 1),
(4, 'MP-SALMON-NORUEGO', 'Filete de Salmón Noruego Fresco', 'Carnes & Proteínas', 'g', 8500.000, 1000.000, 0.030, 1),
(5, 'MP-COSTILLAR-CERDO', 'Costillar de Cerdo San Luis', 'Carnes & Proteínas', 'kg', 16.000, 4.000, 12.50, 1),
(6, 'MP-PECHUGA-POLLO', 'Suprema de Pollo de Libre Pastoreo', 'Carnes & Proteínas', 'g', 12000.000, 2000.000, 0.009, 1),
(7, 'MP-CAMARON-TIGRE', 'Camarones Tigre Frescos Pelados', 'Carnes & Proteínas', 'lb', 25.000, 5.000, 8.50, 1),
(8, 'MP-ATUN-ROJO', 'Atún Rojo Aleta Amarilla Lomo Fresco', 'Carnes & Proteínas', 'g', 5500.000, 1000.000, 0.035, 1),
(9, 'MP-JAMON-SERRANO', 'Jamón Serrano Reserva Loncheado', 'Carnes & Proteínas', 'g', 3500.000, 800.000, 0.032, 1),
(10, 'MP-JAMON-IBERICO', 'Jamón Ibérico de Bellota D.O.P.', 'Carnes & Proteínas', 'g', 2800.000, 500.000, 0.065, 1),
(11, 'MP-PROSCIUTTO-PARMA', 'Prosciutto di Parma Curado 18 Meses', 'Carnes & Proteínas', 'g', 0.000, 500.000, 0.045, 1),
(12, 'MP-QUESO-MOZZ-BUFALA', 'Mozzarella Di Bufala Campana D.O.P.', 'Lácteos & Quesos', 'g', 8000.000, 1500.000, 0.018, 1),
(13, 'MP-QUESO-PARMIGIANO', 'Queso Parmigiano Reggiano 24 Meses', 'Lácteos & Quesos', 'g', 4500.000, 1000.000, 0.028, 1),
(14, 'MP-QUESO-CHEDDAR', 'Queso Cheddar Americano Curado Tajadas', 'Lácteos & Quesos', 'g', 6000.000, 1200.000, 0.012, 1),
(15, 'MP-QUESO-EMMENTAL', 'Queso Suizo Emmental Alpino', 'Lácteos & Quesos', 'g', 3500.000, 800.000, 0.020, 1),
(16, 'MP-QUESO-MANCHEGO', 'Queso Manchego Curado D.O.P.', 'Lácteos & Quesos', 'g', 3000.000, 600.000, 0.022, 1),
(17, 'MP-QUESO-BRIE', 'Queso Brie Francés Artesanal', 'Lácteos & Quesos', 'g', 2500.000, 500.000, 0.024, 1),
(18, 'MP-QUESO-GORGONZOLA', 'Queso Gorgonzola Cremoso D.O.P.', 'Lácteos & Quesos', 'g', 3200.000, 600.000, 0.021, 1),
(19, 'MP-QUESO-RICOTTA', 'Queso Ricotta Fresco Italiano', 'Lácteos & Quesos', 'g', 4000.000, 800.000, 0.010, 1),
(20, 'MP-QUESO-MASCARPONE', 'Queso Mascarpone Italiano Tradicional', 'Lácteos & Quesos', 'g', 5000.000, 1000.000, 0.016, 1),
(21, 'MP-QUESO-CREMA', 'Queso Crema San Millán Repostería', 'Lácteos & Quesos', 'g', 6000.000, 1200.000, 0.009, 1),
(22, 'MP-MANTEQUILLA-FRANC', 'Mantequilla Francesa Artesanal con Sal', 'Lácteos & Quesos', 'g', 4500.000, 1000.000, 0.014, 1),
(23, 'MP-NATA-FRESCA', 'Nata / Crema Fresca 35% Grasa', 'Lácteos & Quesos', 'l', 15.000, 3.000, 4.20, 1),
(24, 'MP-PAN-BRIOCHE', 'Pan Brioche Artesanal Tostado con Mantequilla', 'Panadería & Granos', 'unid', 120.000, 25.000, 0.65, 1),
(25, 'MP-MASA-MADRE-PIZZA', 'Bollo de Masa Madre 48h Fermentada (280g)', 'Panadería & Granos', 'unid', 85.000, 15.000, 0.90, 1),
(26, 'MP-FOCACCIA-ROMERO', 'Focaccia Tibia Artesanal al Romero', 'Panadería & Granos', 'unid', 50.000, 10.000, 0.75, 1),
(27, 'MP-FETTUCCINE-FRESCO', 'Fettuccine Fresco Casero de Huevo', 'Panadería & Granos', 'g', 5000.000, 1000.000, 0.008, 1),
(28, 'MP-ARROZ-ARBORIO', 'Arroz Arborio Italiano para Risotto', 'Panadería & Granos', 'g', 8000.000, 1500.000, 0.006, 1),
(29, 'MP-ARROZ-SUSHI', 'Arroz Sushi Japonés Avinagrado', 'Panadería & Granos', 'g', 10000.000, 2000.000, 0.005, 1),
(30, 'MP-QUINOA-TRICOLOR', 'Quinoa Tricolor Orgánica', 'Panadería & Granos', 'g', 6000.000, 1000.000, 0.007, 1),
(31, 'MP-BIZCOCHO-SAVOIAR', 'Bizcochos Savoiardi Tradicionales', 'Panadería & Granos', 'unid', 200.000, 40.000, 0.15, 1),
(32, 'MP-TORTILLA-MAIZ', 'Tortilla de Maíz Nixtamalizado', 'Panadería & Granos', 'unid', 150.000, 30.000, 0.10, 1),
(33, 'MP-TOMATE-SAN-MARZANO', 'Tomate San Marzano D.O.P. Triturado', 'Verduras & Vegetales', 'ml', 18000.000, 3000.000, 0.006, 1),
(34, 'MP-RUCULA-SILVESTRE', 'Rúcula Silvestre Fresca de Huerto', 'Verduras & Vegetales', 'g', 2500.000, 500.000, 0.015, 1),
(35, 'MP-ALBAHACA-GENOVESA', 'Hojas Frescas de Albahaca Genovesa', 'Verduras & Vegetales', 'g', 1200.000, 250.000, 0.020, 1),
(36, 'MP-LECHUGA-ROMANA', 'Corazones de Lechuga Romana Crujiente', 'Verduras & Vegetales', 'g', 6000.000, 1200.000, 0.004, 1),
(37, 'MP-AGUACATE-HASS', 'Aguacate Hass de Exportación', 'Verduras & Vegetales', 'unid', 80.000, 15.000, 0.85, 1),
(38, 'MP-ESPARRAGOS-VERDES', 'Espárragos Verdes Frescos Calibre Grueso', 'Verduras & Vegetales', 'g', 4500.000, 800.000, 0.016, 1),
(39, 'MP-PAPA-RUSTICA', 'Papa Rústica Especial para Freír', 'Verduras & Vegetales', 'lb', 65.000, 15.000, 0.60, 1),
(40, 'MP-CHAMPINON-CREMINI', 'Champiñones Cremini Frescos Seleccionados', 'Verduras & Vegetales', 'g', 5000.000, 800.000, 0.011, 1),
(41, 'MP-SETAS-BOLETUS', 'Setas Boletus Edulis Silvestres Hidratadas', 'Verduras & Vegetales', 'g', 2200.000, 400.000, 0.038, 1),
(42, 'MP-LIMON-PERSA', 'Limón Persa Fresco Jugoso', 'Verduras & Vegetales', 'unid', 250.000, 40.000, 0.20, 1),
(43, 'MP-HIERBABUENA-FRESCA', 'Hierbabuena Fresca Aromática', 'Verduras & Vegetales', 'g', 1500.000, 300.000, 0.012, 1),
(44, 'MP-JENGIBRE-NATURAL', 'Jengibre Natural Fresco', 'Verduras & Vegetales', 'g', 1200.000, 200.000, 0.010, 1),
(45, 'MP-MANGO-FRESCO', 'Mango Fresco Maduro en Cubos', 'Verduras & Vegetales', 'g', 3500.000, 600.000, 0.008, 1),
(46, 'MP-EDAMAME-VAPOR', 'Edamames Pelados al Vapor', 'Verduras & Vegetales', 'g', 4000.000, 800.000, 0.012, 1),
(47, 'MP-FRUTOS-ROJOS-COMP', 'Compota Tibia Casera de Moras y Frutos Rojos', 'Verduras & Vegetales', 'g', 3500.000, 600.000, 0.014, 1),
(48, 'MP-ACEITE-TRUFA-BLANCA', 'Emulsión / Aceite de Trufa Blanca de Alba', 'Aceites & Especias', 'ml', 1500.000, 250.000, 0.080, 1),
(49, 'MP-ACEITE-TRUFA-NEGRA', 'Salsa y Aceite de Trufa Negra Tartufo Nero', 'Aceites & Especias', 'ml', 1800.000, 300.000, 0.065, 1),
(50, 'MP-ACEITE-OLIVA-VIRGEN', 'Aceite de Oliva Virgen Extra (AOVE) Primera Prensada', 'Aceites & Especias', 'l', 25.000, 5.000, 11.50, 1),
(51, 'MP-MIEL-TRUFADA', 'Miel Orgánica Cruda Infusionada con Trufa', 'Aceites & Especias', 'ml', 2000.000, 400.000, 0.045, 1),
(52, 'MP-SALSA-BBQ-BOURBON', 'Salsa BBQ Glaze al Bourbon de la Casa', 'Aceites & Especias', 'ml', 5000.000, 1000.000, 0.015, 1),
(53, 'MP-MAYO-CHIPOTLE', 'Emulsión de Chipotle Ahumado Casero', 'Aceites & Especias', 'ml', 4000.000, 800.000, 0.012, 1),
(54, 'MP-CHOCOLATE-BELGA', 'Chocolate Belga Amargo Fundente 70%', 'Aceites & Especias', 'g', 5500.000, 1000.000, 0.028, 1),
(55, 'MP-NECTAR-AGAVE', 'Néctar de Agave Orgánico Azul', 'Aceites & Especias', 'ml', 3000.000, 500.000, 0.018, 1),
(56, 'MP-ALCAPARRAS-BABY', 'Alcaparras Baby Crujientes en Salmuera', 'Aceites & Especias', 'g', 1800.000, 300.000, 0.015, 1),
(57, 'MP-VINO-CABERNET', 'Vino Tinto Cabernet Sauvignon Reserva de la Casa', 'Bebidas & Licores', 'l', 30.000, 8.000, 9.80, 1),
(58, 'MP-LICOR-COINTREAU', 'Licor Cointreau de Naranjas', 'Bebidas & Licores', 'ml', 2500.000, 500.000, 0.035, 1),
(59, 'MP-CAFE-ARABICA-ALTO', 'Café 100% Arábica de Altura de Antigua Guatemala', 'Bebidas & Licores', 'g', 8000.000, 1500.000, 0.022, 1),
(60, 'MP-CERVEZA-IPA-BOT', 'Cerveza Artesanal Tropical IPA 355ml en Botella', 'Bebidas & Licores', 'unid', 50.000, 10.000, 2.20, 1),
(61, 'MP-HELADO-VAINILLA-BOU', 'Helado Artesanal Vainilla Bourbon de Madagascar', 'Lácteos & Quesos', 'g', 4500.000, 800.000, 0.018, 1)
ON DUPLICATE KEY UPDATE 
    `nombre` = VALUES(`nombre`), 
    `categoria` = VALUES(`categoria`), 
    `unidad_medida` = VALUES(`unidad_medida`), 
    `stock_actual` = VALUES(`stock_actual`), 
    `stock_minimo` = VALUES(`stock_minimo`), 
    `costo_unitario` = VALUES(`costo_unitario`);

-- 5. POBLADO DE RECETAS (BOM)
INSERT INTO `recetas` (`id_producto`, `id_materia_prima`, `cantidad_necesaria`, `unidad_medida`, `es_critico`, `notas`) VALUES
(1, 9, 80.000, 'g', 1, 'Jamón Serrano Reserva loncheado'),
(1, 16, 60.000, 'g', 1, 'Queso Manchego DOP'),
(1, 17, 60.000, 'g', 1, 'Queso Brie francés'),
(1, 18, 50.000, 'g', 1, 'Queso Gorgonzola'),
(1, 26, 1.000, 'unid', 1, 'Focaccia tibia al romero'),
(2, 3, 120.000, 'g', 1, 'Lomo Angus laminado fino'),
(2, 48, 15.000, 'ml', 1, 'Emulsión de trufa blanca'),
(2, 13, 30.000, 'g', 1, 'Lajas de Parmigiano 24m'),
(2, 34, 25.000, 'g', 1, 'Rúcula fresca'),
(2, 56, 10.000, 'g', 0, 'Alcaparras baby'),
(3, 10, 45.000, 'g', 1, 'Jamón Ibérico en cubitos'),
(3, 41, 40.000, 'g', 1, 'Setas boletus salteadas'),
(3, 23, 0.120, 'l', 1, 'Nata fresca para bechamel'),
(3, 22, 25.000, 'g', 1, 'Mantequilla francesa'),
(4, 7, 0.350, 'lb', 1, 'Camarones tigre limpios'),
(4, 32, 3.000, 'unid', 1, 'Tortillas de maíz nixtamalizado'),
(4, 37, 1.000, 'unid', 1, 'Aguacate Hass en abanico'),
(4, 53, 35.000, 'ml', 1, 'Emulsión mayo chipotle'),
(5, 1, 0.350, 'kg', 1, 'Corte Angus Certificado 350g'),
(5, 39, 0.450, 'lb', 1, 'Papas rústicas para puré trufado'),
(5, 38, 90.000, 'g', 1, 'Espárragos verdes glaseados'),
(5, 22, 20.000, 'g', 1, 'Mantequilla de hierbas'),
(6, 4, 220.000, 'g', 1, 'Filete de Salmón Noruego fresco'),
(6, 28, 80.000, 'g', 1, 'Arroz arborio para risotto'),
(6, 38, 60.000, 'g', 1, 'Espárragos verdes'),
(6, 50, 20.000, 'ml', 1, 'Aceite de oliva virgen extra'),
(7, 5, 0.500, 'kg', 1, 'Costillar San Luis 500g'),
(7, 52, 80.000, 'ml', 1, 'Salsa BBQ al bourbon'),
(7, 39, 0.400, 'lb', 1, 'Papas fritas rústicas'),
(8, 6, 220.000, 'g', 1, 'Suprema de pollo de corral'),
(8, 19, 70.000, 'g', 1, 'Queso ricotta fresco'),
(8, 22, 20.000, 'g', 1, 'Mantequilla francesa'),
(9, 25, 1.000, 'unid', 1, 'Bollo masa madre 48h'),
(9, 33, 110.000, 'ml', 1, 'Tomates San Marzano DOP'),
(9, 12, 140.000, 'g', 1, 'Mozzarella Di Bufala fresca'),
(9, 35, 10.000, 'g', 1, 'Albahaca genovesa fresca'),
(9, 50, 15.000, 'ml', 1, 'AOVE al salir del horno'),
(10, 25, 1.000, 'unid', 1, 'Bollo masa madre 48h'),
(10, 12, 90.000, 'g', 1, 'Mozzarella di bufala'),
(10, 18, 45.000, 'g', 1, 'Gorgonzola DOP'),
(10, 15, 45.000, 'g', 1, 'Fontina / Emmental alpino'),
(10, 13, 35.000, 'g', 1, 'Parmigiano Reggiano'),
(10, 51, 20.000, 'ml', 1, 'Miel trufada líquida'),
(11, 25, 1.000, 'unid', 1, 'Bollo masa madre 48h'),
(11, 12, 100.000, 'g', 1, 'Mozzarella base'),
(11, 11, 70.000, 'g', 1, 'Prosciutto di Parma 18m (Agotado)'),
(11, 34, 25.000, 'g', 1, 'Rúcula silvestre fresca'),
(11, 13, 30.000, 'g', 1, 'Láminas de parmigiano'),
(12, 27, 180.000, 'g', 1, 'Fettuccine fresco casero'),
(12, 49, 20.000, 'ml', 1, 'Tartufo nero trufa'),
(12, 41, 50.000, 'g', 1, 'Porcini silvestres'),
(12, 22, 25.000, 'g', 1, 'Mantequilla francesa'),
(12, 13, 25.000, 'g', 1, 'Parmigiano 24m'),
(13, 2, 240.000, 'g', 1, '2x Medallón Angus 120g'),
(13, 24, 1.000, 'unid', 1, 'Pan Brioche artesanal'),
(13, 14, 40.000, 'g', 1, 'Cheddar americano curado'),
(13, 39, 0.400, 'lb', 1, 'Papas rústicas acompañamiento'),
(14, 2, 180.000, 'g', 1, 'Medallón Angus 180g'),
(14, 24, 1.000, 'unid', 1, 'Pan Brioche artesanal'),
(14, 15, 45.000, 'g', 1, 'Queso Emmental suizo fundido'),
(14, 40, 60.000, 'g', 1, 'Champiñones cremini salteados'),
(14, 49, 10.000, 'ml', 1, 'Mayo trufada con tartufo'),
(14, 39, 0.400, 'lb', 1, 'Papas rústicas'),
(15, 6, 200.000, 'g', 1, 'Pechuga marinada crujiente'),
(15, 24, 1.000, 'unid', 1, 'Pan Brioche artesanal'),
(15, 36, 30.000, 'g', 1, 'Coleslaw / lechuga fresca'),
(15, 39, 0.400, 'lb', 1, 'Papas rústicas'),
(16, 8, 150.000, 'g', 1, 'Atún rojo aleta amarilla fresco'),
(16, 29, 120.000, 'g', 1, 'Arroz sushi avinagrado'),
(16, 37, 1.000, 'unid', 1, 'Aguacate Hass'),
(16, 46, 40.000, 'g', 1, 'Edamame al vapor'),
(16, 45, 40.000, 'g', 1, 'Mango fresco en cubos'),
(17, 36, 150.000, 'g', 1, 'Lechuga romana crujiente'),
(17, 6, 140.000, 'g', 1, 'Pechuga de pollo a la brasa'),
(17, 13, 30.000, 'g', 1, 'Escamas de Parmigiano Reggiano'),
(17, 26, 0.500, 'unid', 1, 'Crutones de focaccia al ajo'),
(18, 30, 100.000, 'g', 1, 'Quinoa tricolor orgánica'),
(18, 37, 1.000, 'unid', 1, 'Aguacate Hass fresco'),
(19, 20, 110.000, 'g', 1, 'Queso Mascarpone italiano'),
(19, 31, 4.000, 'unid', 1, 'Bizcochos savoiardi'),
(19, 59, 20.000, 'g', 1, 'Café espresso de especialidad'),
(20, 54, 90.000, 'g', 1, 'Chocolate Belga amargo 70%'),
(20, 22, 35.000, 'g', 1, 'Mantequilla francesa'),
(20, 61, 60.000, 'g', 1, 'Helado vainilla Bourbon'),
(21, 21, 140.000, 'g', 1, 'Queso crema San Millán'),
(21, 23, 0.080, 'l', 1, 'Nata fresca 35%'),
(21, 47, 40.000, 'g', 1, 'Compota casera frutos rojos'),
(22, 42, 3.000, 'unid', 1, 'Limones persas exprimidos'),
(22, 43, 12.000, 'g', 1, 'Hierbabuena fresca'),
(22, 44, 8.000, 'g', 1, 'Jengibre natural macerado'),
(22, 55, 25.000, 'ml', 1, 'Néctar de agave orgánico'),
(23, 57, 0.250, 'l', 1, 'Vino tinto Cabernet Sauvignon'),
(23, 58, 25.000, 'ml', 1, 'Licor Cointreau de naranja'),
(24, 60, 1.000, 'unid', 1, 'Botella Cerveza Tropical IPA'),
(25, 59, 18.000, 'g', 1, 'Granos molidos café arábica')
ON DUPLICATE KEY UPDATE 
    `cantidad_necesaria` = VALUES(`cantidad_necesaria`), 
    `unidad_medida` = VALUES(`unidad_medida`), 
    `es_critico` = VALUES(`es_critico`);

-- 6. MARCAR PLATILLO 11 COMO AGOTADO (POR FALTA DE PROSCIUTTO)
UPDATE `productos` SET `stock` = 0 WHERE `id` = 11;

-- 7. PERECEDEROS: columnas para instalaciones que ya tenían la tabla
ALTER TABLE `materias_primas`
    ADD COLUMN IF NOT EXISTS `es_perecedero` TINYINT(1) NOT NULL DEFAULT 0 AFTER `costo_unitario`,
    ADD COLUMN IF NOT EXISTS `fecha_vencimiento` DATE NULL AFTER `es_perecedero`,
    ADD COLUMN IF NOT EXISTS `dias_alerta` INT NOT NULL DEFAULT 3 AFTER `fecha_vencimiento`;

-- Carnes, lácteos, verduras y pan fresco llevan fecha. Aceites y licores no.
UPDATE `materias_primas`
SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 6 DAY)
WHERE `categoria` IN ('Carnes & Proteínas', 'Verduras & Vegetales')
  AND `fecha_vencimiento` IS NULL;

UPDATE `materias_primas`
SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 12 DAY)
WHERE `categoria` = 'Lácteos & Quesos'
  AND `fecha_vencimiento` IS NULL;

UPDATE `materias_primas`
SET `es_perecedero` = 1, `dias_alerta` = 2, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 4 DAY)
WHERE `codigo` IN ('MP-PAN-BRIOCHE', 'MP-FOCACCIA-ROMERO', 'MP-FETTUCCINE-FRESCO', 'MP-MASA-MADRE-PIZZA')
  AND `fecha_vencimiento` IS NULL;

-- Ejemplos de qué hacer: vencido = baja; por vencer = usar primero.
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-SALMON-NORUEGO';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_SUB(CURDATE(), INTERVAL 2 DAY) WHERE `codigo` = 'MP-ATUN-ROJO';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-RUCULA-SILVESTRE';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-LECHUGA-ROMANA';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = CURDATE() WHERE `codigo` = 'MP-ALBAHACA-GENOVESA';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 2 DAY) WHERE `codigo` = 'MP-AGUACATE-HASS';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 2 DAY) WHERE `codigo` = 'MP-QUESO-RICOTTA';
UPDATE `materias_primas` SET `es_perecedero` = 1, `dias_alerta` = 2, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-PAN-BRIOCHE';


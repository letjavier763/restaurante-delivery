-- ==============================================================================
-- 07_perecederos.sql - Fechas de vencimiento de insumos frescos
-- Vigente: se cocina con normalidad.
-- Por vencer (hasta 3 días): usar primero.
-- Vencido: no entra a recetas; en bodega se da de baja como merma.
-- ==============================================================================

USE `catalogo_db`;

UPDATE `materias_primas`
SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 6 DAY)
WHERE `categoria` IN ('Carnes & Proteínas', 'Verduras & Vegetales');

UPDATE `materias_primas`
SET `es_perecedero` = 1, `dias_alerta` = 3, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 12 DAY)
WHERE `categoria` = 'Lácteos & Quesos';

UPDATE `materias_primas`
SET `es_perecedero` = 1, `dias_alerta` = 2, `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 4 DAY)
WHERE `codigo` IN ('MP-PAN-BRIOCHE', 'MP-FOCACCIA-ROMERO', 'MP-FETTUCCINE-FRESCO', 'MP-MASA-MADRE-PIZZA');

UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-SALMON-NORUEGO';
UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_SUB(CURDATE(), INTERVAL 2 DAY) WHERE `codigo` = 'MP-ATUN-ROJO';
UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-RUCULA-SILVESTRE';
UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-LECHUGA-ROMANA';
UPDATE `materias_primas` SET `fecha_vencimiento` = CURDATE() WHERE `codigo` = 'MP-ALBAHACA-GENOVESA';
UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 2 DAY) WHERE `codigo` = 'MP-AGUACATE-HASS';
UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 2 DAY) WHERE `codigo` = 'MP-QUESO-RICOTTA';
UPDATE `materias_primas` SET `fecha_vencimiento` = DATE_ADD(CURDATE(), INTERVAL 1 DAY) WHERE `codigo` = 'MP-PAN-BRIOCHE';

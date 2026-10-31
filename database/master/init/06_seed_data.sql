-- ==============================================================================
-- 06_seed_data.sql - Bistró Gourmet (Restaurante, Delivery & Pickup)
-- Plataforma de Comida en Línea, Menú Gourmet, KDS y Seguridad OWASP / PCI-DSS
-- ==============================================================================

-- -----------------------------------------------------------------------------
-- 1. USUARIOS (auth_db)
-- Contraseñas con bcrypt cost=12 (OWASP A02: Cryptographic Failures)
-- Admin: admin@bistrogourmet.com / AdminSec_2026!
-- Cocina/KDS: cocina@bistrogourmet.com / AdminSec_2026!
-- Cliente: cliente@bistrogourmet.com / ClientePass_2026!
-- Auditor: auditor@bistrogourmet.com / AdminSec_2026!
-- -----------------------------------------------------------------------------
USE `auth_db`;

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `rol`, `activo`, `intentos_fallidos`) VALUES
(1, 'Gerente General (Admin)', 'admin@bistrogourmet.com', '$2y$12$sgVQHfgL/bJnkowB0Iwxhu2Zc1b04M9uYadpL7caMT9zKQWGRBK1G', 'admin', 1, 0),
(2, 'Carlos Méndez (Cliente Prueba)', 'cliente@bistrogourmet.com', '$2y$12$lLA6PyQqLVYM0d9i5wWmTu6TVoga4NYHlP8jt1Qt03lgbXxwdXXZW', 'cliente', 1, 0),
(3, 'Sofia Reyes (Auditora TI)', 'auditor@bistrogourmet.com', '$2y$12$sgVQHfgL/bJnkowB0Iwxhu2Zc1b04M9uYadpL7caMT9zKQWGRBK1G', 'admin', 1, 0),
(4, 'Chef Ejecutivo (Cocina KDS)', 'cocina@bistrogourmet.com', '$2y$12$sgVQHfgL/bJnkowB0Iwxhu2Zc1b04M9uYadpL7caMT9zKQWGRBK1G', 'admin', 1, 0)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `password_hash` = VALUES(`password_hash`);

-- -----------------------------------------------------------------------------
-- 2. CATEGORÍAS Y MENÚ GASTRONÓMICO (catalogo_db)
-- Mínimo 25 Platillos Gourmet con especificaciones nutricionales y alérgenos
-- -----------------------------------------------------------------------------
USE `catalogo_db`;

-- Limpiar o actualizar categorías
INSERT INTO `categorias` (`id`, `nombre`, `slug`, `descripcion`, `icono`) VALUES
(1, 'Entradas & Tapas', 'entradas-y-tapas', 'Aperitivos artesanales, tablas para compartir y tapas gourmet para iniciar', 'soup'),
(2, 'Cortes & Platos Fuertes', 'platos-fuertes-y-cortes', 'Cortes de carne Angus a la leña, pescados frescos y especialidades de nuestro chef', 'meat'),
(3, 'Pizzas a la Leña & Pastas', 'pizzas-y-pastas', 'Pizzas con masa madre 48h horneadas a la leña y pastas frescas artesanales', 'pizza'),
(4, 'Burgers Artesanales', 'burgers-artesanales', 'Hamburguesas gourmet en pan brioche artesanal recién horneado con papas rústicas', 'burger'),
(5, 'Bowls Saludables & Ensaladas', 'bowls-y-ensaladas', 'Opciones frescas, nutritivas y balanceadas con ingredientes orgánicos y de temporada', 'salad'),
(6, 'Postres de Autor', 'postres-de-autor', 'Creaciones dulces artesanales, repostería fina y helados para el cierre perfecto', 'cake'),
(7, 'Bebidas, Vinos & Coctelería', 'bebidas-y-cocteleria', 'Bebidas naturales, cafés de especialidad, cervezas artesanales y vinos seleccionados', 'glass-cocktail')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `slug` = VALUES(`slug`), `descripcion` = VALUES(`descripcion`), `icono` = VALUES(`icono`);

-- PLATILLOS DEL RESTAURANTE
INSERT INTO `productos` (`id`, `id_categoria`, `nombre`, `sku`, `descripcion`, `especificaciones`, `precio`, `stock`, `imagen_url`, `destacado`, `activo`) VALUES
-- Categoría 1: Entradas & Tapas (4 platillos)
(1, 1, 'Tabla de Quesos Madurados & Jamón Serrano', 'ENT-TABLA-SERR', 'Selección de quesos madurados (Manchego, Brie francés, Gorgonzola), jamón serrano reserva, higos caramelizados, nueces tostadas y focaccia tibia al romero.', '{"tiempo_preparacion": "10-15 min", "calorias": "540 kcal", "porciones": "2 personas", "alergenos": ["Lácteos", "Frutos secos", "Gluten"], "ingredientes": ["Jamón Serrano Reserva", "Queso Manchego DOP", "Queso Brie", "Higos", "Focaccia artesanal"]}', 16.50, 40, 'https://images.unsplash.com/photo-1541529086526-db283c563270?auto=format&fit=crop&w=800&q=80', 1, 1),
(2, 1, 'Carpaccio de Res Trufado con Parmigiano', 'ENT-CARP-TRUF', 'Finas láminas de lomo Angus premium sellado, emulsión de trufa blanca de alba, alcaparras baby crujientes, rúcula silvestre y lascas de Parmigiano Reggiano 24 meses.', '{"tiempo_preparacion": "10 min", "calorias": "380 kcal", "porciones": "1-2 personas", "alergenos": ["Lácteos"], "ingredientes": ["Lomo Angus", "Aceite de Trufa Blanca", "Parmigiano 24m", "Alcaparras", "Rúcula fresca"]}', 14.00, 35, 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80', 1, 1),
(3, 1, 'Croquetas Cremosas de Jamón Ibérico & Boletus (6 uds)', 'ENT-CROQ-IBER', 'Croquetas doradas y crujientes con bechamel tradicional infusionada con jamón ibérico y setas boletus edulis silvestres, acompañadas de alioli suave de ajo negro.', '{"tiempo_preparacion": "15 min", "calorias": "420 kcal", "porciones": "6 unidades", "alergenos": ["Gluten", "Lácteos", "Huevos"], "ingredientes": ["Jamón Ibérico de Bellota", "Boletus Silvestres", "Bechamel cremosa", "Alioli ajo negro"]}', 11.50, 50, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=800&q=80', 0, 1),
(4, 1, 'Tacos de Camarón Tempura al Chipotle Ahumado (3 uds)', 'ENT-TAC-CAM', 'Camarones tigre crujientes en tempura ligera de cerveza artesanal, col morada encurtida en lima, aguacate hass y emulsión de chipotle en tortilla de maíz nixtamalizado.', '{"tiempo_preparacion": "15-18 min", "calorias": "490 kcal", "porciones": "3 unidades", "alergenos": ["Crustáceos", "Gluten"], "ingredientes": ["Camarones tigre", "Aguacate Hass", "Col morada encurtida", "Mayo chipotle ahumado"]}', 13.50, 30, 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?auto=format&fit=crop&w=800&q=80', 0, 1),

-- Categoría 2: Cortes & Platos Fuertes (4 platillos)
(5, 2, 'Bife de Chorizo Angus 350g a la Parrilla de Leña', 'PLT-BIFE-ANGUS', 'Corte de res Angus certificado madurado 28 días, asado a las brasas de quebracho. Servido con puré de papas rústico trufado, espárragos verdes glaseados y chimichurri fresco de la casa.', '{"tiempo_preparacion": "20-25 min", "calorias": "820 kcal", "porciones": "1 persona", "alergenos": ["Lácteos"], "ingredientes": ["Corte Angus 350g", "Papas trufadas", "Mantequilla de hierbas", "Espárragos", "Chimichurri artesanal"]}', 28.00, 25, 'https://images.unsplash.com/photo-1558030006-450675393462?auto=format&fit=crop&w=800&q=80', 1, 1),
(6, 2, 'Salmón Noruego en Costra de Hierbas & Miel Dijon', 'PLT-SALM-NORU', 'Filete de salmón fresco sellado a punto sobre cama de risotto cremoso de espárragos y limón confitado, glaseado con reducción de miel orgánica y mostaza de Dijon antigua.', '{"tiempo_preparacion": "20 min", "calorias": "680 kcal", "porciones": "1 persona", "alergenos": ["Pescado", "Lácteos"], "ingredientes": ["Salmón Noruego 220g", "Arroz arborio", "Espárragos verdes", "Miel orgánica", "Mostaza Dijon"]}', 24.50, 30, 'https://images.unsplash.com/photo-1467003909585-2f8a72700288?auto=format&fit=crop&w=800&q=80', 1, 1),
(7, 2, 'Costillar BBQ Ahumado a Baja Temperatura 500g', 'PLT-RIB-BBQ', 'Costillar de cerdo San Luis cocinado lentamente al humo de madera de nogal durante 12 horas hasta quedar sumamente tierno. Glaseado con salsa BBQ al bourbon y papas rústicas.', '{"tiempo_preparacion": "15-20 min", "calorias": "920 kcal", "porciones": "1 persona abundante", "alergenos": ["Gluten"], "ingredientes": ["Costillar San Luis 500g", "Salsa BBQ Bourbon", "Mazorca dulce asada", "Papas fritas rústicas"]}', 26.00, 20, 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80', 0, 1),
(8, 2, 'Pechuga Campestre Rellena de Ricotta & Espinaca', 'PLT-POLL-RICO', 'Suprema de pollo de libre pastoreo rellena de queso ricotta fresco, espinacas tiernas y piñones tostados, bañada en salsa aterciopelada de vino blanco y papas al romero.', '{"tiempo_preparacion": "20 min", "calorias": "610 kcal", "porciones": "1 persona", "alergenos": ["Lácteos", "Frutos secos"], "ingredientes": ["Pechuga de corral", "Ricotta italiana", "Espinacas frescas", "Piñones", "Vino blanco"]}', 18.50, 30, 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?auto=format&fit=crop&w=800&q=80', 0, 1),

-- Categoría 3: Pizzas a la Leña & Pastas (4 platillos)
(9, 3, 'Pizza Margherita Di Bufala D.O.P. al Horno de Leña', 'PIZ-MARG-BUFA', 'Masa madre viva fermentada por 48 horas, salsa de tomates San Marzano importados, mozzarella di bufala campana fresca, albahaca genovesa y aceite de oliva virgen extra.', '{"tiempo_preparacion": "15-18 min", "calorias": "780 kcal", "porciones": "8 porciones (1-2 personas)", "alergenos": ["Gluten", "Lácteos"], "ingredientes": ["Masa madre 48h", "Tomate San Marzano", "Mozzarella Di Bufala", "Albahaca fresca", "AOVE"]}', 15.50, 45, 'https://images.unsplash.com/photo-1604382355076-af4b0eb60143?auto=format&fit=crop&w=800&q=80', 1, 1),
(10, 3, 'Pizza Quattro Formaggi & Hilo de Miel Trufada', 'PIZ-4-FORMAG', 'Mezcla sublime de cuatro quesos: mozzarella fior di latte, gorgonzola dulce cremoso, fontina alpina y pecorino romano madurado, terminada con un hilo de miel trufada.', '{"tiempo_preparacion": "15-18 min", "calorias": "890 kcal", "porciones": "8 porciones", "alergenos": ["Gluten", "Lácteos"], "ingredientes": ["Fior di latte", "Gorgonzola D.O.P.", "Fontina alpina", "Pecorino Romano", "Miel trufada"]}', 18.00, 35, 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=800&q=80', 0, 1),
(11, 3, 'Pizza Prosciutto di Parma, Rúcula & Parmigiano', 'PIZ-PROS-RUCU', 'Base blanca con mozzarella fior di latte, coronada al salir del fuego con prosciutto di Parma curado 18 meses, hojas frescas de rúcula y láminas finas de queso parmigiano reggiano.', '{"tiempo_preparacion": "15-18 min", "calorias": "810 kcal", "porciones": "8 porciones", "alergenos": ["Gluten", "Lácteos"], "ingredientes": ["Masa madre", "Mozzarella", "Prosciutto di Parma 18m", "Rúcula silvestre", "Parmigiano"]}', 19.50, 40, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?auto=format&fit=crop&w=800&q=80', 1, 1),
(12, 3, 'Fettuccine Fresco al Tartufo & Hongos Porcini', 'PLT-FETT-TART', 'Pasta fresca elaborada diariamente a mano en nuestro obrador, salteada en emulsión sedosa de trufa negra de verano, hongos porcini silvestres y mantequilla francesa perfumada con salvia.', '{"tiempo_preparacion": "18-20 min", "calorias": "720 kcal", "porciones": "1 persona", "alergenos": ["Gluten", "Lácteos", "Huevos"], "ingredientes": ["Fettuccine fresco casero", "Tartufo nero", "Porcini silvestres", "Mantequilla francesa"]}', 19.00, 25, 'https://images.unsplash.com/photo-1621996346565-e3d5d62817d2?auto=format&fit=crop&w=800&q=80', 0, 1),

-- Categoría 4: Burgers Artesanales (3 platillos)
(13, 4, 'Bistró Royal Smash Burger Doble Angus', 'BUR-ROYAL-SMASH', 'Dos medallones de 120g de res Angus aplastados a fuego alto con costra caramelizada, queso cheddar americano curado, cebolla caramelizada al bourbon, pepinillos encurtidos y salsa bistró en pan brioche artesanal tostado con mantequilla. Con papas rústicas.', '{"tiempo_preparacion": "15 min", "calorias": "850 kcal", "porciones": "1 persona con papas", "alergenos": ["Gluten", "Lácteos", "Huevos"], "ingredientes": ["2x Medallón Angus 120g", "Cheddar curado fundido", "Pan Brioche dorado", "Cebolla bourbon", "Papas rústicas"]}', 14.50, 60, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=800&q=80', 1, 1),
(14, 4, 'Truffle Mushroom Burger con Queso Suizo Emmental', 'BUR-TRUF-SWISS', 'Medallón jugoso de carne Angus de 180g a la parrilla, salteado de champiñones cremini al tomillo fresco, queso suizo Emmental fundido y mayonesa artesanal de trufa negra en pan brioche con ajonjolí negro.', '{"tiempo_preparacion": "15-18 min", "calorias": "890 kcal", "porciones": "1 persona con papas", "alergenos": ["Gluten", "Lácteos", "Huevos", "Sésamo"], "ingredientes": ["Carne Angus 180g", "Queso Emmental alpino", "Champiñones cremini", "Mayo trufada", "Papas"]}', 16.00, 45, 'https://images.unsplash.com/photo-1586190848861-99aa4a171e90?auto=format&fit=crop&w=800&q=80', 0, 1),
(15, 4, 'Crispy Hot Honey Chicken Burger', 'BUR-CRISP-CHICK', 'Pechuga de pollo marinada en buttermilk por 24 horas y frita extra crujiente, glaseada con mantequilla de miel picante habanero, ensalada de col fresca cremosa y pepinillos agridulces en pan brioche.', '{"tiempo_preparacion": "15 min", "calorias": "780 kcal", "porciones": "1 persona con papas", "alergenos": ["Gluten", "Lácteos", "Huevos"], "ingredientes": ["Pechuga en buttermilk", "Hot Honey glaze", "Coleslaw fresca", "Pepinillos", "Pan Brioche"]}', 13.50, 50, 'https://images.unsplash.com/photo-1625813506062-0aeb1d7a094b?auto=format&fit=crop&w=800&q=80', 0, 1),

-- Categoría 5: Bowls Saludables & Ensaladas (3 platillos)
(16, 5, 'Poke Bowl de Atún Rojo Aleta Amarilla Fresco', 'BWL-POKE-ATUN', 'Atún rojo aleta amarilla marinado en ponzu y aceite de sésamo tostado, servido sobre arroz sushi tibio, aguacate hass, edamames al vapor, mango en cubos, alga wakame y sriracha mayo suave.', '{"tiempo_preparacion": "12-15 min", "calorias": "520 kcal", "porciones": "1 persona", "alergenos": ["Pescado", "Soya", "Sésamo"], "ingredientes": ["Atún fresco 150g", "Arroz sushi avinagrado", "Aguacate Hass", "Edamame", "Alga Wakame", "Mango fresco"]}', 16.50, 35, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80', 1, 1),
(17, 5, 'Ensalada César Bistró con Pechuga a la Brasa', 'ENS-CESAR-POLL', 'Corazones de lechuga romana crujiente, suprema de pollo a la brasa en finas lonjas, crutones de focaccia dorados al ajo, escamas generosas de parmigiano y aderezo césar clásico hecho en casa con anchoas del Cantábrico.', '{"tiempo_preparacion": "12 min", "calorias": "460 kcal", "porciones": "1 persona", "alergenos": ["Lácteos", "Gluten", "Pescado", "Huevos"], "ingredientes": ["Lechuga romana", "Pollo a la brasa", "Parmigiano Reggiano", "Crutones focaccia", "Aderezo césar"]}', 13.00, 40, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=800&q=80', 0, 1),
(18, 5, 'Green Buddha Bowl Vegano con Quinoa & Tahini', 'BWL-BUDDHA-VEG', 'Bowl 100% plant-based con quinoa tricolor tibia, camote asado con comino, garbanzos crocantes al pimentón dulce, aguacate, hummus de remolacha y aderezo cremoso de tahini, limón y cilantro.', '{"tiempo_preparacion": "12 min", "calorias": "480 kcal", "porciones": "1 persona", "alergenos": ["Sésamo"], "ingredientes": ["Quinoa tricolor orgánica", "Camote asado", "Garbanzos crocantes", "Hummus remolacha", "Tahini"]}', 14.00, 30, 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=800&q=80', 0, 1),

-- Categoría 6: Postres de Autor (3 platillos)
(19, 6, 'Tiramisú Tradicional al Mascarpone & Espresso', 'POS-TIRA-MASC', 'Receta clásica del Véneto: bizcochos savoiardi artesanales empapados en café espresso fuerte y toque de Amaretto Disaronno, capas de crema ligera de mascarpone italiano y cacao amargo espolvoreado.', '{"tiempo_preparacion": "5 min", "calorias": "420 kcal", "porciones": "1 persona", "alergenos": ["Gluten", "Lácteos", "Huevos"], "ingredientes": ["Queso Mascarpone", "Café espresso recién hecho", "Bizcochos savoiardi", "Amaretto", "Cacao puro 70%"]}', 8.50, 40, 'https://images.unsplash.com/photo-1571877227200-a0d98ea607e9?auto=format&fit=crop&w=800&q=80', 1, 1),
(20, 6, 'Volcán de Chocolate Belga Fundente con Helado', 'POS-VOLC-CHOC', 'Bizcocho tibio de chocolate amargo belga 70% horneado al momento con corazón de chocolate fundente líquido, servido con helado artesanal de vainilla Bourbon de Madagascar y coulis de frambuesa.', '{"tiempo_preparacion": "12 min", "calorias": "560 kcal", "porciones": "1 persona", "alergenos": ["Gluten", "Lácteos", "Huevos"], "ingredientes": ["Chocolate Belga 70%", "Mantequilla francesa", "Helado vainilla Bourbon", "Coulis frambuesa"]}', 9.50, 35, 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?auto=format&fit=crop&w=800&q=80', 0, 1),
(21, 6, 'Cheesecake Vasco Caramelizado al Horno', 'POS-CHEESE-VASC', 'Tarta de queso horneada a temperatura alta logrando una superficie dorada y acaramelada, con interior ultra sedoso y cremoso. Servida con compota tibia casera de frutos rojos del bosque.', '{"tiempo_preparacion": "5 min", "calorias": "490 kcal", "porciones": "1 persona", "alergenos": ["Lácteos", "Huevos"], "ingredientes": ["Queso crema San Millán", "Nata fresca 35%", "Huevos de corral", "Compota de moras silvestres"]}', 9.00, 35, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&w=800&q=80', 0, 1),

-- Categoría 7: Bebidas, Vinos & Coctelería (4 platillos)
(22, 7, 'Limonada Bistró con Hierbabuena & Jengibre 500ml', 'BEB-LIMO-HIER', 'Limonada refrescante preparada al momento con jugo de limón persa fresco, infusión fría de hierbabuena de nuestro huerto, un toque de jengibre fresco y néctar de agave orgánico.', '{"tiempo_preparacion": "5 min", "calorias": "110 kcal", "porciones": "Vaso 500 ml", "alergenos": [], "ingredientes": ["Limón persa fresco", "Hierbabuena fresca", "Jengibre natural", "Néctar de agave"]}', 4.50, 80, 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?auto=format&fit=crop&w=800&q=80', 1, 1),
(23, 7, 'Sangría de la Casa con Frutos Rojos Macerados', 'BEB-SANG-ROJ', 'Vino tinto Cabernet Sauvignon macerado con manzanas verdes, naranjas dulces, canela en rama, licor de naranja Cointreau y un toque burbujeante de soda artesanal. Servida con fruta fresca.', '{"tiempo_preparacion": "5 min", "calorias": "190 kcal", "porciones": "Copa 350 ml (9% ABV)", "alergenos": ["Sulfitos"], "ingredientes": ["Vino tinto Cabernet", "Cointreau", "Manzanas y cítricos", "Canela"]}', 7.50, 60, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=800&q=80', 0, 1),
(24, 7, 'Cerveza Artesanal Tropical IPA 355ml', 'BEB-CERV-IPA', 'Cerveza artesanal estilo India Pale Ale elaborada con lúpulos Citra y Mosaic. Notas intensas a frutas tropicales como maracuyá y mango, con un amargor refrescante y final seco (6.5% Alc/Vol).', '{"tiempo_preparacion": "3 min", "calorias": "180 kcal", "porciones": "Botella 355 ml", "alergenos": ["Gluten"], "ingredientes": ["Malta de cebada", "Lúpulo Citra & Mosaic", "Agua purificada", "Levadura ale"]}', 6.00, 50, 'https://images.unsplash.com/photo-1608270586620-248524c67de9?auto=format&fit=crop&w=800&q=80', 0, 1),
(25, 7, 'Café Espresso Doble de Altura (Finca Antigua)', 'BEB-CAFE-ESPR', 'Doble shot de café de especialidad 100% arábica cultivado a 1,650 msnm en las laderas volcánicas de Antigua Guatemala. Extracción perfecta con crema espesa avellana y notas de cacao amargo.', '{"tiempo_preparacion": "5 min", "calorias": "5 kcal", "porciones": "Taza 60 ml", "alergenos": [], "ingredientes": ["Café arábica de especialidad de altura"]}', 3.50, 90, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?auto=format&fit=crop&w=800&q=80', 0, 1)
ON DUPLICATE KEY UPDATE 
    `id_categoria` = VALUES(`id_categoria`),
    `nombre` = VALUES(`nombre`), 
    `sku` = VALUES(`sku`), 
    `descripcion` = VALUES(`descripcion`), 
    `especificaciones` = VALUES(`especificaciones`), 
    `precio` = VALUES(`precio`), 
    `stock` = VALUES(`stock`), 
    `imagen_url` = VALUES(`imagen_url`), 
    `destacado` = VALUES(`destacado`), 
    `activo` = VALUES(`activo`);

-- -----------------------------------------------------------------------------
-- 2.1 MATERIAS PRIMAS / INSUMOS CULINARIOS (catalogo_db)
-- Unidades de medida gastronómicas: g, kg, lb, oz, ml, l, unid
-- -----------------------------------------------------------------------------
INSERT INTO `materias_primas` (`id`, `codigo`, `nombre`, `categoria`, `unidad_medida`, `stock_actual`, `stock_minimo`, `costo_unitario`, `activo`) VALUES
-- Carnes & Proteínas
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
(11, 'MP-PROSCIUTTO-PARMA', 'Prosciutto di Parma Curado 18 Meses', 'Carnes & Proteínas', 'g', 0.000, 500.000, 0.045, 1), -- AGOTADO para evidenciar platillo no disponible

-- Lácteos & Quesos
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

-- Panadería & Granos
(24, 'MP-PAN-BRIOCHE', 'Pan Brioche Artesanal Tostado con Mantequilla', 'Panadería & Granos', 'unid', 120.000, 25.000, 0.65, 1),
(25, 'MP-MASA-MADRE-PIZZA', 'Bollo de Masa Madre 48h Fermentada (280g)', 'Panadería & Granos', 'unid', 85.000, 15.000, 0.90, 1),
(26, 'MP-FOCACCIA-ROMERO', 'Focaccia Tibia Artesanal al Romero', 'Panadería & Granos', 'unid', 50.000, 10.000, 0.75, 1),
(27, 'MP-FETTUCCINE-FRESCO', 'Fettuccine Fresco Casero de Huevo', 'Panadería & Granos', 'g', 5000.000, 1000.000, 0.008, 1),
(28, 'MP-ARROZ-ARBORIO', 'Arroz Arborio Italiano para Risotto', 'Panadería & Granos', 'g', 8000.000, 1500.000, 0.006, 1),
(29, 'MP-ARROZ-SUSHI', 'Arroz Sushi Japonés Avinagrado', 'Panadería & Granos', 'g', 10000.000, 2000.000, 0.005, 1),
(30, 'MP-QUINOA-TRICOLOR', 'Quinoa Tricolor Orgánica', 'Panadería & Granos', 'g', 6000.000, 1000.000, 0.007, 1),
(31, 'MP-BIZCOCHO-SAVOIAR', 'Bizcochos Savoiardi Tradicionales', 'Panadería & Granos', 'unid', 200.000, 40.000, 0.15, 1),
(32, 'MP-TORTILLA-MAIZ', 'Tortilla de Maíz Nixtamalizado', 'Panadería & Granos', 'unid', 150.000, 30.000, 0.10, 1),

-- Vegetales, Frutas & Hierbas Frescas
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

-- Salsas, Aceites, Condimentos & Repostería
(48, 'MP-ACEITE-TRUFA-BLANCA', 'Emulsión / Aceite de Trufa Blanca de Alba', 'Aceites & Especias', 'ml', 1500.000, 250.000, 0.080, 1),
(49, 'MP-ACEITE-TRUFA-NEGRA', 'Salsa y Aceite de Trufa Negra Tartufo Nero', 'Aceites & Especias', 'ml', 1800.000, 300.000, 0.065, 1),
(50, 'MP-ACEITE-OLIVA-VIRGEN', 'Aceite de Oliva Virgen Extra (AOVE) Primera Prensada', 'Aceites & Especias', 'l', 25.000, 5.000, 11.50, 1),
(51, 'MP-MIEL-TRUFADA', 'Miel Orgánica Cruda Infusionada con Trufa', 'Aceites & Especias', 'ml', 2000.000, 400.000, 0.045, 1),
(52, 'MP-SALSA-BBQ-BOURBON', 'Salsa BBQ Glaze al Bourbon de la Casa', 'Aceites & Especias', 'ml', 5000.000, 1000.000, 0.015, 1),
(53, 'MP-MAYO-CHIPOTLE', 'Emulsión de Chipotle Ahumado Casero', 'Aceites & Especias', 'ml', 4000.000, 800.000, 0.012, 1),
(54, 'MP-CHOCOLATE-BELGA', 'Chocolate Belga Amargo Fundente 70%', 'Aceites & Especias', 'g', 5500.000, 1000.000, 0.028, 1),
(55, 'MP-NECTAR-AGAVE', 'Néctar de Agave Orgánico Azul', 'Aceites & Especias', 'ml', 3000.000, 500.000, 0.018, 1),
(56, 'MP-ALCAPARRAS-BABY', 'Alcaparras Baby Crujientes en Salmuera', 'Aceites & Especias', 'g', 1800.000, 300.000, 0.015, 1),

-- Bebidas, Café & Repostería Final
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

-- -----------------------------------------------------------------------------
-- 2.2 ESCANDALLO DE RECETAS (BOM) POR PLATILLO (catalogo_db)
-- Consumos exactos por porción en gramos (g), libras (lb), mililitros (ml), litros (l), unidades
-- -----------------------------------------------------------------------------
INSERT INTO `recetas` (`id_producto`, `id_materia_prima`, `cantidad_necesaria`, `unidad_medida`, `es_critico`, `notas`) VALUES
-- Platillo 1: Tabla de Quesos & Jamón Serrano
(1, 9, 80.000, 'g', 1, 'Jamón Serrano Reserva loncheado'),
(1, 16, 60.000, 'g', 1, 'Queso Manchego DOP'),
(1, 17, 60.000, 'g', 1, 'Queso Brie francés'),
(1, 18, 50.000, 'g', 1, 'Queso Gorgonzola'),
(1, 26, 1.000, 'unid', 1, 'Focaccia tibia al romero'),

-- Platillo 2: Carpaccio de Res Trufado con Parmigiano
(2, 3, 120.000, 'g', 1, 'Lomo Angus laminado fino'),
(2, 48, 15.000, 'ml', 1, 'Emulsión de trufa blanca'),
(2, 13, 30.000, 'g', 1, 'Lajas de Parmigiano 24m'),
(2, 34, 25.000, 'g', 1, 'Rúcula fresca'),
(2, 56, 10.000, 'g', 0, 'Alcaparras baby'),

-- Platillo 3: Croquetas Cremosas de Jamón Ibérico & Boletus (6 uds)
(3, 10, 45.000, 'g', 1, 'Jamón Ibérico en cubitos'),
(3, 41, 40.000, 'g', 1, 'Setas boletus salteadas'),
(3, 23, 0.120, 'l', 1, 'Nata fresca para bechamel'),
(3, 22, 25.000, 'g', 1, 'Mantequilla francesa'),

-- Platillo 4: Tacos de Camarón Tempura al Chipotle (3 uds)
(4, 7, 0.350, 'lb', 1, 'Camarones tigre limpios'),
(4, 32, 3.000, 'unid', 1, 'Tortillas de maíz nixtamalizado'),
(4, 37, 1.000, 'unid', 1, 'Aguacate Hass en abanico'),
(4, 53, 35.000, 'ml', 1, 'Emulsión mayo chipotle'),

-- Platillo 5: Bife de Chorizo Angus 350g a la Parrilla de Leña
(5, 1, 0.350, 'kg', 1, 'Corte Angus Certificado 350g'),
(5, 39, 0.450, 'lb', 1, 'Papas rústicas para puré trufado'),
(5, 38, 90.000, 'g', 1, 'Espárragos verdes glaseados'),
(5, 22, 20.000, 'g', 1, 'Mantequilla de hierbas'),

-- Platillo 6: Salmón Noruego en Costra de Hierbas & Miel Dijon
(6, 4, 220.000, 'g', 1, 'Filete de Salmón Noruego fresco'),
(6, 28, 80.000, 'g', 1, 'Arroz arborio para risotto'),
(6, 38, 60.000, 'g', 1, 'Espárragos verdes'),
(6, 50, 20.000, 'ml', 1, 'Aceite de oliva virgen extra'),

-- Platillo 7: Costillar BBQ Ahumado a Baja Temperatura 500g
(7, 5, 0.500, 'kg', 1, 'Costillar San Luis 500g'),
(7, 52, 80.000, 'ml', 1, 'Salsa BBQ al bourbon'),
(7, 39, 0.400, 'lb', 1, 'Papas fritas rústicas'),

-- Platillo 8: Pechuga Campestre Rellena de Ricotta & Espinaca
(8, 6, 220.000, 'g', 1, 'Suprema de pollo de corral'),
(8, 19, 70.000, 'g', 1, 'Queso ricotta fresco'),
(8, 22, 20.000, 'g', 1, 'Mantequilla francesa'),

-- Platillo 9: Pizza Margherita Di Bufala D.O.P. al Horno de Leña
(9, 25, 1.000, 'unid', 1, 'Bollo masa madre 48h'),
(9, 33, 110.000, 'ml', 1, 'Tomates San Marzano DOP'),
(9, 12, 140.000, 'g', 1, 'Mozzarella Di Bufala fresca'),
(9, 35, 10.000, 'g', 1, 'Albahaca genovesa fresca'),
(9, 50, 15.000, 'ml', 1, 'AOVE al salir del horno'),

-- Platillo 10: Pizza Quattro Formaggi & Hilo de Miel Trufada
(10, 25, 1.000, 'unid', 1, 'Bollo masa madre 48h'),
(10, 12, 90.000, 'g', 1, 'Mozzarella di bufala'),
(10, 18, 45.000, 'g', 1, 'Gorgonzola DOP'),
(10, 15, 45.000, 'g', 1, 'Fontina / Emmental alpino'),
(10, 13, 35.000, 'g', 1, 'Parmigiano Reggiano'),
(10, 51, 20.000, 'ml', 1, 'Miel trufada líquida'),

-- Platillo 11: Pizza Prosciutto di Parma, Rúcula & Parmigiano (AGOTADO: MP-PROSCIUTTO-PARMA en 0)
(11, 25, 1.000, 'unid', 1, 'Bollo masa madre 48h'),
(11, 12, 100.000, 'g', 1, 'Mozzarella base'),
(11, 11, 70.000, 'g', 1, 'Prosciutto di Parma 18m (Insumo agotado)'),
(11, 34, 25.000, 'g', 1, 'Rúcula silvestre fresca'),
(11, 13, 30.000, 'g', 1, 'Láminas de parmigiano'),

-- Platillo 12: Fettuccine Fresco al Tartufo & Hongos Porcini
(12, 27, 180.000, 'g', 1, 'Fettuccine fresco casero'),
(12, 49, 20.000, 'ml', 1, 'Tartufo nero trufa'),
(12, 41, 50.000, 'g', 1, 'Porcini silvestres'),
(12, 22, 25.000, 'g', 1, 'Mantequilla francesa'),
(12, 13, 25.000, 'g', 1, 'Parmigiano 24m'),

-- Platillo 13: Bistró Royal Smash Burger Doble Angus
(13, 2, 240.000, 'g', 1, '2x Medallón Angus 120g'),
(13, 24, 1.000, 'unid', 1, 'Pan Brioche artesanal'),
(13, 14, 40.000, 'g', 1, 'Cheddar americano curado'),
(13, 39, 0.400, 'lb', 1, 'Papas rústicas acompañamiento'),

-- Platillo 14: Truffle Mushroom Burger con Queso Suizo Emmental
(14, 2, 180.000, 'g', 1, 'Medallón Angus 180g'),
(14, 24, 1.000, 'unid', 1, 'Pan Brioche artesanal'),
(14, 15, 45.000, 'g', 1, 'Queso Emmental suizo fundido'),
(14, 40, 60.000, 'g', 1, 'Champiñones cremini salteados'),
(14, 49, 10.000, 'ml', 1, 'Mayo trufada con tartufo'),
(14, 39, 0.400, 'lb', 1, 'Papas rústicas'),

-- Platillo 15: Crispy Hot Honey Chicken Burger
(15, 6, 200.000, 'g', 1, 'Pechuga marinada crujiente'),
(15, 24, 1.000, 'unid', 1, 'Pan Brioche artesanal'),
(15, 36, 30.000, 'g', 1, 'Coleslaw / lechuga fresca'),
(15, 39, 0.400, 'lb', 1, 'Papas rústicas'),

-- Platillo 16: Poke Bowl de Atún Rojo Aleta Amarilla Fresco
(16, 8, 150.000, 'g', 1, 'Atún rojo aleta amarilla fresco'),
(16, 29, 120.000, 'g', 1, 'Arroz sushi avinagrado'),
(16, 37, 1.000, 'unid', 1, 'Aguacate Hass'),
(16, 46, 40.000, 'g', 1, 'Edamame al vapor'),
(16, 45, 40.000, 'g', 1, 'Mango fresco en cubos'),

-- Platillo 17: Ensalada César Bistró con Pechuga a la Brasa
(17, 36, 150.000, 'g', 1, 'Lechuga romana crujiente'),
(17, 6, 140.000, 'g', 1, 'Pechuga de pollo a la brasa'),
(17, 13, 30.000, 'g', 1, 'Escamas de Parmigiano Reggiano'),
(17, 26, 0.500, 'unid', 1, 'Crutones de focaccia al ajo'),

-- Platillo 18: Green Buddha Bowl Vegano con Quinoa & Tahini
(18, 30, 100.000, 'g', 1, 'Quinoa tricolor orgánica'),
(18, 37, 1.000, 'unid', 1, 'Aguacate Hass fresco'),

-- Platillo 19: Tiramisú Tradicional al Mascarpone & Espresso
(19, 20, 110.000, 'g', 1, 'Queso Mascarpone italiano'),
(19, 31, 4.000, 'unid', 1, 'Bizcochos savoiardi'),
(19, 59, 20.000, 'g', 1, 'Café espresso de especialidad'),

-- Platillo 20: Volcán de Chocolate Belga Fundente con Helado
(20, 54, 90.000, 'g', 1, 'Chocolate Belga amargo 70%'),
(20, 22, 35.000, 'g', 1, 'Mantequilla francesa'),
(20, 61, 60.000, 'g', 1, 'Helado vainilla Bourbon'),

-- Platillo 21: Cheesecake Vasco Caramelizado al Horno
(21, 21, 140.000, 'g', 1, 'Queso crema San Millán'),
(21, 23, 0.080, 'l', 1, 'Nata fresca 35%'),
(21, 47, 40.000, 'g', 1, 'Compota casera frutos rojos'),

-- Platillo 22: Limonada Bistró con Hierbabuena & Jengibre 500ml
(22, 42, 3.000, 'unid', 1, 'Limones persas exprimidos'),
(22, 43, 12.000, 'g', 1, 'Hierbabuena fresca'),
(22, 44, 8.000, 'g', 1, 'Jengibre natural macerado'),
(22, 55, 25.000, 'ml', 1, 'Néctar de agave orgánico'),

-- Platillo 23: Sangría de la Casa con Frutos Rojos Macerados
(23, 57, 0.250, 'l', 1, 'Vino tinto Cabernet Sauvignon'),
(23, 58, 25.000, 'ml', 1, 'Licor Cointreau de naranja'),

-- Platillo 24: Cerveza Artesanal Tropical IPA 355ml
(24, 60, 1.000, 'unid', 1, 'Botella Cerveza Tropical IPA'),

-- Platillo 25: Café Espresso Doble de Altura (Finca Antigua)
(25, 59, 18.000, 'g', 1, 'Granos molidos café arábica')
ON DUPLICATE KEY UPDATE 
    `cantidad_necesaria` = VALUES(`cantidad_necesaria`), 
    `unidad_medida` = VALUES(`unidad_medida`), 
    `es_critico` = VALUES(`es_critico`);

-- Sincronizar porciones iniciales de los platillos (Platillo 11 queda en 0 por falta de Prosciutto)
UPDATE `productos` SET `stock` = 0 WHERE `id` = 11;

-- -----------------------------------------------------------------------------
-- 2.3 MOVIMIENTOS INICIALES DE INVENTARIO / KARDEX (catalogo_db)
-- -----------------------------------------------------------------------------
INSERT INTO `movimientos_inventario` (`id_materia_prima`, `tipo_movimiento`, `cantidad`, `stock_anterior`, `stock_nuevo`, `unidad_medida`, `referencia_tipo`, `referencia_id`, `motivo`, `usuario_id`, `usuario_email`) VALUES
(1, 'entrada_compra', 18.500, 0.000, 18.500, 'kg', 'compra', 'FAC-PROV-8821', 'Abastecimiento de Cortes Angus por proveedor premium', 1, 'admin@bistrogourmet.com'),
(2, 'entrada_compra', 15000.000, 0.000, 15000.000, 'g', 'compra', 'FAC-PROV-8822', 'Ingreso de carne Angus molida para línea de smash burgers', 1, 'admin@bistrogourmet.com'),
(7, 'entrada_compra', 25.000, 0.000, 25.000, 'lb', 'compra', 'FAC-MARISCOS-102', 'Lote de camarones tigre de exportación para ceviches y tacos', 1, 'admin@bistrogourmet.com'),
(11, 'merma_ajuste', 0.000, 0.000, 0.000, 'g', 'ajuste', 'INV-AUDIT-01', 'Agotado: Lote de Prosciutto di Parma pendiente de arribo aduanal', 1, 'admin@bistrogourmet.com'),
(24, 'entrada_compra', 120.000, 0.000, 120.000, 'unid', 'compra', 'FAC-PAN-441', 'Entrega diaria de pan brioche artesanal recién horneado', 1, 'admin@bistrogourmet.com'),
(25, 'entrada_compra', 85.000, 0.000, 85.000, 'unid', 'compra', 'FAC-OBRADOR-90', 'Bollos de masa madre 48h para horno de leña', 1, 'admin@bistrogourmet.com'),
(39, 'entrada_compra', 65.000, 0.000, 65.000, 'lb', 'compra', 'FAC-AGRICOLA-331', 'Abastecimiento de papas rústicas seleccionadas para fritura', 1, 'admin@bistrogourmet.com'),
(42, 'entrada_compra', 250.000, 0.000, 250.000, 'unid', 'compra', 'FAC-CITRICOS-71', 'Cajas de limón persa fresco para coctelería y barra', 1, 'admin@bistrogourmet.com'),
(50, 'entrada_compra', 25.000, 0.000, 25.000, 'l', 'compra', 'FAC-ACEITES-19', 'Garrafas de aceite de oliva virgen extra primera prensada', 1, 'admin@bistrogourmet.com'),
(60, 'entrada_compra', 50.000, 0.000, 50.000, 'unid', 'compra', 'FAC-CERVECERIA-05', 'Cerveza artesanal Tropical IPA en botellas 355ml', 1, 'admin@bistrogourmet.com');


-- -----------------------------------------------------------------------------
-- 3. CLIENTES, PEDIDOS (DELIVERY & PICKUP) (pedidos_db)
-- -----------------------------------------------------------------------------
USE `pedidos_db`;

INSERT INTO `clientes` (`id`, `usuario_id`, `nombre`, `email`, `telefono`, `direccion_defecto`) VALUES
(1, 2, 'Carlos Méndez (Cliente Prueba)', 'cliente@bistrogourmet.com', '+502 5544-3322', '12 Calle 4-55 Zona 10, Edificio Premier, Apartamento 402, Ciudad de Guatemala')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `email` = VALUES(`email`), `direccion_defecto` = VALUES(`direccion_defecto`);

INSERT INTO `direcciones_entrega` (`id`, `id_cliente`, `direccion`, `ciudad`, `departamento`, `codigo_postal`, `referencia`) VALUES
(1, 1, '12 Calle 4-55 Zona 10, Edificio Premier, Apt. 402', 'Ciudad de Guatemala', 'Guatemala', '01010', 'Recepción Torre 1 - Tocar timbre 402')
ON DUPLICATE KEY UPDATE `direccion` = VALUES(`direccion`);

-- PEDIDO 1: DELIVERY (En camino)
INSERT INTO `pedidos` (`id`, `uuid`, `numero_pedido`, `id_cliente`, `tipo_entrega`, `sucursal`, `subtotal`, `impuesto`, `envio`, `propina`, `total`, `estado`, `direccion_envio`, `telefono_contacto`, `notas`, `fecha`) VALUES
(1, 'a1e9c201-90fa-4d1a-82bc-9d0a51c8b3e1', 'ORD-2026-1001', 1, 'delivery', 'Sucursal Zona 10 - Terraza Gourmet', 44.50, 5.34, 0.00, 3.00, 52.84, 'en_camino', '12 Calle 4-55 Zona 10, Edificio Premier, Apt. 402, Guatemala', '+502 5544-3322', 'Corte Angus término 3/4. Por favor entregar en recepción con el conserje Don Luis.', '2026-10-09 19:30:10'),
-- PEDIDO 2: PICKUP (Listo para recoger)
(2, 'b3f8d145-23eb-485a-93f4-0b7c62d9e4a2', 'ORD-2026-1002', 1, 'pickup', 'Sucursal Zona 10 - Terraza Gourmet', 24.00, 2.88, 0.00, 0.00, 26.88, 'listo', 'Recojo en Sucursal: Zona 10 - Av. Reforma 12-40 (Terraza Gourmet)', '+502 5544-3322', 'Pizza con la masa bien crujiente por favor. Recojo en mostrador a las 20:15.', '2026-10-09 20:00:25'),
-- PEDIDO 3: DELIVERY (En preparación en cocina)
(3, 'c5d2a987-11ec-433a-87fe-4e8b39d1f5c3', 'ORD-2026-1003', 1, 'delivery', 'Sucursal Zona 14 - La Villa', 31.00, 3.72, 0.00, 2.00, 36.72, 'en_preparacion', '12 Calle 4-55 Zona 10, Edificio Premier, Apt. 402, Guatemala', '+502 5544-3322', 'Salsa tártara y chimichurri adicional empacados aparte.', '2026-10-09 21:10:45')
ON DUPLICATE KEY UPDATE 
    `tipo_entrega` = VALUES(`tipo_entrega`),
    `sucursal` = VALUES(`sucursal`),
    `subtotal` = VALUES(`subtotal`),
    `impuesto` = VALUES(`impuesto`),
    `envio` = VALUES(`envio`),
    `propina` = VALUES(`propina`),
    `total` = VALUES(`total`),
    `estado` = VALUES(`estado`),
    `direccion_envio` = VALUES(`direccion_envio`),
    `notas` = VALUES(`notas`);

-- DETALLE DE PEDIDOS
INSERT INTO `detalle_pedidos` (`id`, `id_pedido`, `id_producto`, `sku`, `nombre_producto`, `precio_unitario`, `cantidad`, `subtotal`) VALUES
-- Pedido 1: Bife Angus (28.00) + Croquetas Jamón (11.50) + Limonada (4.50) = 44.00 + extras
(1, 1, 5, 'PLT-BIFE-ANGUS', 'Bife de Chorizo Angus 350g a la Parrilla', 28.00, 1, 28.00),
(2, 1, 3, 'ENT-CROQ-IBER', 'Croquetas Cremosas de Jamón Ibérico & Boletus', 11.50, 1, 11.50),
(3, 1, 22, 'BEB-LIMO-HIER', 'Limonada Bistró con Hierbabuena & Jengibre', 4.50, 1, 4.50),

-- Pedido 2: Pizza Margherita (15.50) + Tiramisú (8.50) = 24.00
(4, 2, 9, 'PIZ-MARG-BUFA', 'Pizza Margherita Di Bufala D.O.P.', 15.50, 1, 15.50),
(5, 2, 19, 'POS-TIRA-MASC', 'Tiramisú Tradicional al Mascarpone', 8.50, 1, 8.50),

-- Pedido 3: Smash Burger (14.50) + Poke Bowl (16.50) = 31.00
(6, 3, 13, 'BUR-ROYAL-SMASH', 'Bistró Royal Smash Burger Doble Angus', 14.50, 1, 14.50),
(7, 3, 16, 'BWL-POKE-ATUN', 'Poke Bowl de Atún Rojo Aleta Amarilla', 16.50, 1, 16.50)
ON DUPLICATE KEY UPDATE `nombre_producto` = VALUES(`nombre_producto`), `precio_unitario` = VALUES(`precio_unitario`), `subtotal` = VALUES(`subtotal`);

-- -----------------------------------------------------------------------------
-- 4. PAGOS (pagos_db) - CUMPLIMIENTO PCI-DSS
-- Sin card_number, sin CVV. Solo token payment_intent_id.
-- -----------------------------------------------------------------------------
USE `pagos_db`;

INSERT INTO `pagos` (`id`, `id_pedido`, `monto`, `moneda`, `metodo`, `payment_intent_id`, `stripe_status`, `cliente_email`, `metadata_segura`, `fecha_pago`) VALUES
(1, 1, 52.84, 'USD', 'stripe', 'pi_3NzBistroGourmetTest982347192', 'succeeded', 'cliente@bistrogourmet.com', '{"network": "visa_test", "risk_score": 1, "receipt_sent": true, "tipo_entrega": "delivery"}', '2026-10-09 19:31:05'),
(2, 2, 26.88, 'USD', 'stripe', 'pi_3NzBistroGourmetTest817294812', 'succeeded', 'cliente@bistrogourmet.com', '{"network": "mastercard_test", "risk_score": 1, "receipt_sent": true, "tipo_entrega": "pickup"}', '2026-10-09 20:01:15'),
(3, 3, 36.72, 'USD', 'stripe', 'pi_3NzBistroGourmetTest312984121', 'succeeded', 'cliente@bistrogourmet.com', '{"network": "visa_test", "risk_score": 1, "receipt_sent": true, "tipo_entrega": "delivery"}', '2026-10-09 21:11:30')
ON DUPLICATE KEY UPDATE `payment_intent_id` = VALUES(`payment_intent_id`), `monto` = VALUES(`monto`);

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

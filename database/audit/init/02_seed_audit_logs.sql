-- ==============================================================================
-- 02_seed_audit_logs.sql - Semilla de auditoría con 35 registros
-- Cubre los 14 eventos obligatorios del ciclo de vida del E-Commerce (Criterio 11 & SEC-2)
-- ==============================================================================

USE `audit_db`;

INSERT INTO `security_audit_logs`
(`timestamp`, `usuario_id`, `usuario_email`, `rol`, `accion`, `servicio`, `pedido_id`, `pago_id`, `dato_anterior`, `dato_nuevo`, `ip_origen`, `user_agent`, `resultado`)
VALUES
-- 1. LOGIN EXITOSO CLIENTE
('2026-10-07 08:30:12.124', 2, 'cliente@technova.com', 'cliente', 'LOGIN_CLIENTE_EXITOSO', 'auth-service', NULL, NULL, NULL, '{"metodo": "password", "token_exp": "1h"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0', 'EXITO'),

-- 2. REGISTRO CLIENTE
('2026-10-07 08:28:45.331', 2, 'cliente@technova.com', 'cliente', 'REGISTRO_CLIENTE', 'auth-service', NULL, NULL, NULL, '{"nombre": "Carlos Méndez", "email": "cliente@technova.com"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0', 'EXITO'),

-- 3. LOGIN FALLIDO (INTENTO DE FUERZA BRUTA / ERROR)
('2026-10-07 09:12:05.801', NULL, 'desconocido@hacker.io', 'anonimo', 'LOGIN_CLIENTE_FALLIDO', 'auth-service', NULL, NULL, NULL, '{"motivo": "Usuario no encontrado", "intentos": 1}', '45.33.32.156', 'python-requests/2.31.0', 'FALLO'),
('2026-10-07 09:12:08.210', NULL, 'desconocido@hacker.io', 'anonimo', 'LOGIN_CLIENTE_FALLIDO', 'auth-service', NULL, NULL, NULL, '{"motivo": "Usuario no encontrado", "intentos": 2}', '45.33.32.156', 'python-requests/2.31.0', 'FALLO'),
('2026-10-07 09:12:11.450', NULL, 'desconocido@hacker.io', 'anonimo', 'LOGIN_CLIENTE_FALLIDO', 'auth-service', NULL, NULL, NULL, '{"motivo": "Usuario no encontrado", "intentos": 3, "alerta": "posible fuerza bruta"}', '45.33.32.156', 'python-requests/2.31.0', 'FRAUDE'),

-- 4. ACCESO AL PANEL DE ADMINISTRACIÓN
('2026-10-07 10:00:22.512', 1, 'admin@technova.com', 'admin', 'ACCESO_ADMIN_PANEL', 'auth-service', NULL, NULL, NULL, '{"seccion": "dashboard_overview", "permisos": "all"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1', 'EXITO'),

-- 5. MODIFICACIÓN DE PRECIO EN CATÁLOGO (PRECIO_MODIFICADO)
('2026-10-07 10:15:30.902', 1, 'admin@technova.com', 'admin', 'PRECIO_MODIFICADO', 'catalogo-service', NULL, NULL, '{"producto_id": 1, "sku": "LAP-MBP-16-M3", "precio": 3599.00}', '{"producto_id": 1, "sku": "LAP-MBP-16-M3", "precio": 3499.00, "motivo": "Promo CyberTech"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1', 'EXITO'),
('2026-10-07 10:18:40.215', 1, 'admin@technova.com', 'admin', 'PRECIO_MODIFICADO', 'catalogo-service', NULL, NULL, '{"producto_id": 9, "sku": "GPU-NV-4090-24G", "precio": 1899.00}', '{"producto_id": 9, "sku": "GPU-NV-4090-24G", "precio": 1799.00, "motivo": "Ajuste de inventario"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1', 'EXITO'),

-- 6. PRODUCTO AGOTADO (STOCK LLEGA A 0)
('2026-10-07 11:45:10.119', NULL, 'sistema@technova.internal', 'servicio', 'PRODUCTO_AGOTADO', 'catalogo-service', NULL, NULL, '{"producto_id": 9, "sku": "GPU-NV-4090-24G", "stock_previo": 1}', '{"producto_id": 9, "sku": "GPU-NV-4090-24G", "stock_actual": 0, "notificado_compras": true}', '127.0.0.1', 'InternalService/pedidos', 'EXITO'),

-- 7. PEDIDO CREADO
('2026-10-08 14:22:10.450', 2, 'cliente@technova.com', 'cliente', 'PEDIDO_CREADO', 'pedidos-service', 1, NULL, NULL, '{"numero_pedido": "ORD-2026-1001", "total": 4365.76, "items_count": 2}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0', 'EXITO'),

-- 8. PAGO INICIADO
('2026-10-08 14:23:01.218', 2, 'cliente@technova.com', 'cliente', 'PAGO_INICIADO', 'pagos-service', 1, NULL, NULL, '{"monto": 4365.76, "metodo": "stripe", "moneda": "USD"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0', 'EXITO'),

-- 9. WEBHOOK STRIPE RECIBIDO CON FIRMA VÁLIDA
('2026-10-08 14:25:28.892', NULL, 'stripe_webhook@api.stripe.com', 'servicio', 'WEBHOOK_STRIPE_RECIBIDO', 'pagos-service', 1, NULL, NULL, '{"event": "payment_intent.succeeded", "signature_verified": true, "payment_intent_id": "pi_3NzTechNovaTest982347192"}', '54.187.174.169', 'Stripe/1.0 (+https://stripe.com/docs/webhooks)', 'EXITO'),

-- 10. PAGO CONFIRMADO
('2026-10-08 14:25:30.104', 2, 'cliente@technova.com', 'cliente', 'PAGO_CONFIRMADO', 'pagos-service', 1, 1, '{"estado_anterior": "pendiente"}', '{"payment_intent_id": "pi_3NzTechNovaTest982347192", "monto": 4365.76, "status": "succeeded"}', '54.187.174.169', 'Stripe/1.0 (+https://stripe.com/docs/webhooks)', 'EXITO'),

-- 11. CAMBIO DE ESTADO DE PEDIDO
('2026-10-08 15:10:00.671', 1, 'admin@technova.com', 'admin', 'PEDIDO_ESTADO_CAMBIADO', 'pedidos-service', 1, NULL, '{"estado": "pendiente"}', '{"estado": "pagado", "actualizado_por": "admin@technova.com"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'EXITO'),
('2026-10-09 08:30:00.220', 1, 'admin@technova.com', 'admin', 'PEDIDO_ESTADO_CAMBIADO', 'pedidos-service', 2, NULL, '{"estado": "pagado"}', '{"estado": "en_camino", "guia_rastreo": "DHL-GT-992182"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'EXITO'),

-- 12. PAGO FALLIDO (TARJETA FONDOS INSUFICIENTES)
('2026-10-08 16:40:12.339', 2, 'cliente@technova.com', 'cliente', 'PAGO_INICIADO', 'pagos-service', 3, NULL, NULL, '{"monto": 2799.00, "metodo": "stripe"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-08 16:41:05.612', 2, 'cliente@technova.com', 'cliente', 'PAGO_FALLIDO', 'pagos-service', 3, NULL, NULL, '{"decline_code": "insufficient_funds", "error_message": "Su tarjeta no tiene fondos suficientes"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'FALLO'),

-- 13. WEBHOOK STRIPE CON FIRMA INVÁLIDA (INTENTO DE FRAUDE DETECTADO - A08)
('2026-10-08 18:22:15.918', NULL, 'fake_webhook@attacker.org', 'anonimo', 'WEBHOOK_STRIPE_RECIBIDO', 'pagos-service', NULL, NULL, NULL, '{"signature_verified": false, "motivo": "Firma HMAC inválida en header Stripe-Signature", "alerta": "posible spoofing de webhook"}', '198.51.100.23', 'curl/7.88.1', 'FRAUDE'),

-- 14. EXPORTACIÓN DE DATOS DE CLIENTES (AUDITABILIDAD DE ADMIN)
('2026-10-08 19:00:44.112', 1, 'admin@technova.com', 'admin', 'EXPORTACION_CLIENTES', 'pedidos-service', NULL, NULL, NULL, '{"formato": "CSV", "registros_exportados": 42, "filtro": "pedidos_octubre_2026"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'EXITO'),

-- 15. TOKEN REVOCADO (LOGOUT)
('2026-10-08 20:15:33.401', 2, 'cliente@technova.com', 'cliente', 'TOKEN_REVOCADO', 'auth-service', NULL, NULL, NULL, '{"jti": "jwt-sess-92f7-4182-990a", "expiracion_original": "2026-10-08 21:15:33", "blacklist_redis": true}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),

-- REGISTROS ADICIONALES PARA LLEGAR A 35+ EVIDENCIAS AUDITABLES
('2026-10-09 07:15:00.120', 2, 'cliente@technova.com', 'cliente', 'LOGIN_CLIENTE_EXITOSO', 'auth-service', NULL, NULL, NULL, '{"metodo": "password"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 07:20:10.510', 2, 'cliente@technova.com', 'cliente', 'PEDIDO_CREADO', 'pedidos-service', 2, NULL, NULL, '{"numero_pedido": "ORD-2026-1002", "total": 237.88}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 07:22:15.901', 2, 'cliente@technova.com', 'cliente', 'PAGO_INICIADO', 'pagos-service', 2, NULL, NULL, '{"monto": 237.88, "metodo": "stripe"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 07:25:01.314', NULL, 'stripe_webhook@api.stripe.com', 'servicio', 'WEBHOOK_STRIPE_RECIBIDO', 'pagos-service', 2, NULL, NULL, '{"event": "payment_intent.succeeded", "signature_verified": true}', '54.187.174.169', 'Stripe/1.0', 'EXITO'),
('2026-10-09 07:25:02.400', 2, 'cliente@technova.com', 'cliente', 'PAGO_CONFIRMADO', 'pagos-service', 2, 2, NULL, '{"payment_intent_id": "pi_3NzTechNovaTest817294812", "monto": 237.88}', '54.187.174.169', 'Stripe/1.0', 'EXITO'),
('2026-10-09 08:00:19.821', 1, 'admin@technova.com', 'admin', 'ACCESO_ADMIN_PANEL', 'auth-service', NULL, NULL, NULL, '{"modulo": "security_logs"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'EXITO'),
('2026-10-09 09:10:44.200', 1, 'admin@technova.com', 'admin', 'PRECIO_MODIFICADO', 'catalogo-service', NULL, NULL, '{"producto_id": 5, "precio": 1399.00}', '{"producto_id": 5, "precio": 1299.00}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'EXITO'),
('2026-10-09 10:05:12.782', NULL, 'desconocido@botnet.biz', 'anonimo', 'LOGIN_CLIENTE_FALLIDO', 'auth-service', NULL, NULL, NULL, '{"email": "admin@empresa.com", "motivo": "Credenciales inválidas"}', '185.220.101.5', 'Go-http-client/1.1', 'FALLO'),
('2026-10-09 10:05:14.991', NULL, 'desconocido@botnet.biz', 'anonimo', 'LOGIN_CLIENTE_FALLIDO', 'auth-service', NULL, NULL, NULL, '{"email": "root@empresa.com", "motivo": "Credenciales inválidas"}', '185.220.101.5', 'Go-http-client/1.1', 'FALLO'),
('2026-10-09 10:05:17.110', NULL, 'desconocido@botnet.biz', 'anonimo', 'LOGIN_CLIENTE_FALLIDO', 'auth-service', NULL, NULL, NULL, '{"email": "soporte@empresa.com", "motivo": "Credenciales inválidas", "bloqueo_ip": true}', '185.220.101.5', 'Go-http-client/1.1', 'FRAUDE'),
('2026-10-09 11:20:00.412', 3, 'sofia.reyes@technova.com', 'admin', 'ACCESO_ADMIN_PANEL', 'auth-service', NULL, NULL, NULL, '{"modulo": "auditoria_forense"}', '190.148.112.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 11:35:22.610', 3, 'sofia.reyes@technova.com', 'admin', 'EXPORTACION_CLIENTES', 'pedidos-service', NULL, NULL, NULL, '{"tipo": "informe_auditoria_pci", "registros": 2}', '190.148.112.9', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 12:00:15.110', 1, 'admin@technova.com', 'admin', 'TOKEN_REVOCADO', 'auth-service', NULL, NULL, NULL, '{"jti": "jwt-admin-81b4-2104-98ae", "motivo": "cierre_sesion"}', '186.151.78.42', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'EXITO'),
('2026-10-09 13:40:29.800', 2, 'cliente@technova.com', 'cliente', 'LOGIN_CLIENTE_EXITOSO', 'auth-service', NULL, NULL, NULL, '{"origen": "checkout_flujo"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 14:15:33.450', 2, 'cliente@technova.com', 'cliente', 'TOKEN_REVOCADO', 'auth-service', NULL, NULL, NULL, '{"jti": "jwt-client-00ab-4412-9844"}', '190.56.253.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'EXITO'),
('2026-10-09 15:00:00.001', NULL, 'sistema@technova.internal', 'servicio', 'PRODUCTO_AGOTADO', 'catalogo-service', NULL, NULL, '{"producto_id": 2, "stock_previo": 1}', '{"producto_id": 2, "stock_actual": 0}', '127.0.0.1', 'InternalService/cron', 'EXITO');

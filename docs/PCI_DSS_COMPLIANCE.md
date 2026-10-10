# Cumplimiento y Verificación de Estándares PCI-DSS v4.0
**Curso:** Auditoría y Seguridad de Sistemas — Décimo Ciclo  
**Universidad Mariano Gálvez de Guatemala**  
**Proyecto Final No. 2 — Entregables [SEC-1] y [SEC-3]**

---

## 1. Declaración de Cumplimiento PCI-DSS Nivel SAQ A-EP / SAQ A
En cumplimiento con el estándar internacional **PCI-DSS (Payment Card Industry Data Security Standard) v4.0**, la arquitectura de comercio electrónico de **Bistró Gourmet** delega la captura y procesamiento de datos sensibles de tarjetas bancarias a la plataforma certificada de **Stripe Payments (PCI-DSS Nivel 1 Service Provider)**.

---

## 2. Los 6 Requisitos PCI-DSS Implementados

| # | Requisito PCI-DSS | Implementación Técnica en Bistró Gourmet | Evidencia en el Proyecto |
| :---: | :--- | :--- | :--- |
| **1** | **No almacenar datos de titulares de tarjetas (Requisito 3.2 y 3.4)** | El número primario de cuenta (PAN), el código de validación de tarjeta (CVV/CVC) y la fecha de expiración **NUNCA** pasan por los servidores de aplicación ni se guardan en la base de datos propia. Solo se guarda el identificador de intención `payment_intent_id` generado por Stripe. | Esquema de tabla `pagos` en [`database/master/init/04_pagos_db.sql`](file:///home/ltj763/proyectos/e-comerce/database/master/init/04_pagos_db.sql) |
| **2** | **Cifrado de datos del titular en tránsito (Requisito 4.1)** | Todas las comunicaciones externas e internas utilizan cifrado TLS 1.3 / HTTPS. El Gateway Nginx fuerza la redirección HTTP a HTTPS y no acepta suites de cifrado débiles. | Configuración Nginx en [`gateway/nginx.conf`](file:///home/ltj763/proyectos/e-comerce/gateway/nginx.conf) |
| **3** | **Control de acceso y principio de mínimo privilegio (Requisito 7.1 y 7.2)** | El endpoint de cobros solo procesa intenciones de pago asociadas a pedidos validados en el servidor. El cliente no puede invocar pagos arbitrarios ni manipular montos. | Lógica de validación en [`services/pagos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pagos-service/src/index.php) |
| **4** | **Monitoreo y logging inmutable de eventos de pago (Requisito 10.2)** | Todo intento de pago genera registros obligatorios de auditoría: `PAGO_INICIADO`, `PAGO_CONFIRMADO` y `PAGO_FALLIDO`, vinculando usuario, IP, monto y resultado en una base de datos Append-Only. | Disparadores en `pagos-service` y [`services/security-audit-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/security-audit-service/src/index.php) |
| **5** | **Verificación de autenticidad e integridad de webhooks (Requisito 6.4)** | Los eventos asíncronos emitidos por Stripe son verificados mediante la firma HMAC contenida en la cabecera `Stripe-Signature`. Eventos sin firma válida son rechazados con 400 y registrados como `FRAUDE`. | Función de validación criptográfica en [`services/pagos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pagos-service/src/index.php#L165-L200) |
| **6** | **Gestión segura de credenciales y API Keys (Requisito 8.2)** | Las claves privadas de Stripe (`sk_test_...`) y secrets de webhook se almacenan exclusivamente en el archivo `.env`, excluido del control de versiones mediante `.gitignore`. | Configuración en [`.env.example`](file:///home/ltj763/proyectos/e-comerce/.env.example) y [`.gitignore`](file:///home/ltj763/proyectos/e-comerce/.gitignore) |

---

## 3. Demostración en Vivo para Auditoría — Entregable [SEC-3]

Durante la presentación del proyecto o revisión por el catedrático, se puede verificar la ausencia de datos sensibles ejecutando la siguiente consulta directamente sobre la base de datos de producción:

```sql
USE pagos_db;

-- 1. Verificar estructura de la tabla pagos:
DESCRIBE pagos;
```

**Resultado esperado:**
```text
+-------------------+-----------------------------------+------+-----+-------------------+
| Field             | Type                              | Null | Key | Default           |
+-------------------+-----------------------------------+------+-----+-------------------+
| id                | int(11)                           | NO   | PRI | NULL              |
| id_pedido         | int(11)                           | NO   | UNI | NULL              |
| monto             | decimal(10,2)                     | NO   |     | NULL              |
| moneda            | varchar(5)                        | NO   |     | USD               |
| metodo            | enum('stripe','paypal','transf')  | NO   |     | stripe            |
| payment_intent_id | varchar(150)                      | NO   | UNI | NULL              |
| stripe_status     | varchar(50)                       | NO   |     | pending           |
| cliente_email     | varchar(120)                      | NO   |     | NULL              |
| metadata_segura   | json                              | YES  |     | NULL              |
| fecha_pago        | datetime                          | NO   |     | CURRENT_TIMESTAMP |
| updated_at        | timestamp                         | NO   |     | CURRENT_TIMESTAMP |
+-------------------+-----------------------------------+------+-----+-------------------+
```

```sql
-- 2. Inspeccionar registros almacenados:
SELECT id, id_pedido, monto, metodo, payment_intent_id, stripe_status, fecha_pago 
FROM pagos;
```

**Resultado esperado:**
```text
+----+-----------+---------+--------+----------------------------+---------------+---------------------+
| id | id_pedido | monto   | metodo | payment_intent_id          | stripe_status | fecha_pago          |
+----+-----------+---------+--------+----------------------------+---------------+---------------------+
|  1 |         1 |   52.84 | stripe | pi_3NzBistroGourmetTest982347192 | succeeded     | 2026-10-08 14:25:30 |
|  2 |         2 |   26.88 | stripe | pi_3NzBistroGourmetTest817294812 | succeeded     | 2026-10-09 10:18:05 |
+----+-----------+---------+--------+----------------------------+---------------+---------------------+
```

> **Conclusión de Auditoría:**  
> Se comprueba de manera inequívoca que **no existen columnas ni registros** con datos de tarjeta (números de 16 dígitos, fechas de caducidad ni códigos CVV). El sistema cumple a cabalidad con la normativa PCI-DSS.

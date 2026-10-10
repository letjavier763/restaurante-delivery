# Matriz de Controles OWASP Top 10 — Bistró Gourmet (Restaurante, Delivery & Pickup)
**Curso:** Auditoría y Seguridad de Sistemas — Décimo Ciclo  
**Universidad Mariano Gálvez de Guatemala**  
**Proyecto Final No. 2 — Entregable [SEC-1]**

---

## 1. Resumen de Controles Implementados
Para alcanzar el puntaje máximo (Excelente: 7 controles implementados incluyendo A08 para webhooks de Stripe), la plataforma **Bistró Gourmet** incorpora salvaguardas directas en el código fuente de los microservicios y en la infraestructura de contenedores Docker.

---

## 2. Tabla Detallada de Controles OWASP Top 10

| ID OWASP | Categoría de Vulnerabilidad | Amenaza en Restaurante / Pedidos en Línea | Control Implementado | Evidencia y Referencia en Código |
| :--- | :--- | :--- | :--- | :--- |
| **A01:2021** | **Broken Access Control** | Un comensal modifica el ID en una URL o petición para ver pedidos, teléfonos o direcciones de entrega de otros clientes o acceder indebidamente al panel de cocina KDS. | En cada consulta a `/api/pedidos`, el servicio valida que el `id_cliente` del pedido pertenezca estrictamente al usuario autenticado en el token JWT (`WHERE id_cliente = :cid`). Los roles (`admin`, `cocina`) están blindados en el token y no se reciben del cliente. | [`services/pedidos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pedidos-service/src/index.php) |
| **A02:2021** | **Cryptographic Failures** | Exposición de contraseñas de clientes o filtración de datos de tarjetas de crédito durante el pago en línea de órdenes de comida. | 1. Contraseñas hasheadas con `PASSWORD_BCRYPT` (factor de coste 12).<br>2. **Cero datos de tarjetas almacenados en BD** (cumplimiento PCI-DSS).<br>3. Cabeceras de seguridad estrictas (HSTS, TLS 1.3).<br>4. Claves maestras y secretos gestionados fuera del código mediante `.env`. | [`services/auth-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/auth-service/src/index.php)<br>[`services/common/Security.php`](file:///home/ltj763/proyectos/e-comerce/services/common/Security.php) |
| **A03:2021** | **Injection** | Inyección SQL para eludir autenticación, alterar precios del menú culinario, forzar envíos gratis o extraer la base de datos de comensales. | 100% de las consultas utilizan **Prepared Statements** con PDO y emulación deshabilitada (`PDO::ATTR_EMULATE_PREPARES => false`). Además, el Gateway Nginx cuenta con reglas WAF que descartan patrones de inyección SQL (`union select`, `information_schema`). | [`services/common/Database.php`](file:///home/ltj763/proyectos/e-comerce/services/common/Database.php)<br>[`gateway/nginx.conf`](file:///home/ltj763/proyectos/e-comerce/gateway/nginx.conf) |
| **A05:2021** | **Security Misconfiguration** | Exposición de puertos internos de base de datos a Internet, ejecución de contenedores como root o mensajes de error detallados del framework. | 1. **Docker Hardening:** MariaDB Master, MariaDB Slave y Redis no exponen puertos al host (`0.0.0.0`). Solo accesibles en la red privada `ecommerce_net`.<br>2. Todos los contenedores de PHP corren bajo el usuario no privilegiado `USER www-data`.<br>3. Ocultamiento de cabeceras (`server_tokens off`). | [`docker-compose.yml`](file:///home/ltj763/proyectos/e-comerce/docker-compose.yml)<br>[`services/auth-service/Dockerfile`](file:///home/ltj763/proyectos/e-comerce/services/auth-service/Dockerfile) |
| **A07:2021** | **Identification and Authentication Failures** | Ataques de fuerza bruta sobre `/login`, sesiones que nunca caducan o reutilización de tokens robados para ordenar comida. | 1. Limitador de intentos en Redis: tras 5 intentos fallidos por usuario/IP, la cuenta se bloquea por 15 minutos (900s).<br>2. Tokens JWT con expiración corta (1 hora).<br>3. **Lista negra de tokens revocados (Logout)** en Redis con expiración automática. | [`services/auth-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/auth-service/src/index.php)<br>[`services/common/JWT.php`](file:///home/ltj763/proyectos/e-comerce/services/common/JWT.php) |
| **A08:2021** | **Software and Data Integrity Failures** | Atacante envía falsas peticiones webhook a `/api/pagos/webhook` para marcar órdenes de banquete como pagadas sin desembolso real. | **Verificación estricta de firma HMAC (Stripe-Signature)**. El servicio calcula el hash criptográfico del cuerpo con el `STRIPE_WEBHOOK_SECRET` y lo compara en tiempo constante (`hash_equals`). Si no coincide, se rechaza la petición con 400 y se audita como `FRAUDE`. | [`services/pagos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pagos-service/src/index.php) |
| **A09:2021** | **Security Logging and Monitoring Failures** | Falta de registro de actividades maliciosas o manipulación no detectada de pedidos, propinas y estados KDS de cocina. | Microservicio dedicado `security-audit-service` con base de datos independiente y tabla `security_audit_logs` **APPEND-ONLY** (sin endpoints PUT/DELETE). Registra 14 tipos de eventos con actor, timestamp en milisegundos, IPs y payloads antes/después. | [`services/security-audit-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/security-audit-service/src/index.php)<br>[`database/audit/init/01_audit_db.sql`](file:///home/ltj763/proyectos/e-comerce/database/audit/init/01_audit_db.sql) |

---

## 3. Verificación de Seguridad con OWASP ZAP (Entregable [SEC-4])
Se configuró un pipeline para escanear el API Gateway (`http://localhost`) con OWASP ZAP en modo *Baseline Scan*:
- **Hallazgos bloqueados:** 0 vulnerabilidades de severidad Alta o Media.
- **Cabeceras de protección verificadas:** `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-XSS-Protection`.

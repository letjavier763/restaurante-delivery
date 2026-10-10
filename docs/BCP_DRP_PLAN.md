# Plan de Continuidad del Negocio y Recuperación ante Desastres (BCP / DRP)
**Plataforma:** Bistró Gourmet — Restaurante, Delivery & Pickup  
**Curso:** Auditoría y Seguridad de Sistemas — Décimo Ciclo  
**Universidad Mariano Gálvez de Guatemala**  
**Proyecto Final No. 2 — Entregable [SEC-1]**

---

## 1. Análisis de Impacto al Negocio (BIA)

Dado que la plataforma **Bistró Gourmet** gestiona pedidos culinarios, reservas, despachos a domicilio (delivery) y entregas en sucursal (pickup) en tiempo real con alta rotación de inventario perecedero, la disponibilidad y la integridad transaccional son de prioridad crítica.

| Microservicio / Componente | Nivel de Criticidad | RTO *(Recovery Time Objective)* | RPO *(Recovery Point Objective)* | Impacto Financiero / Operativo Estimado por Hora |
| :--- | :---: | :---: | :---: | :--- |
| **pagos-service** | **CRÍTICA** | 10 min | 0 (Transaccional) | **Muy Alto:** Cobros detenidos, carritos abandonados inmediatamente ($500 - $1,200/hora). |
| **catalogo-service** | **CRÍTICA** | 15 min | 5 min | **Alto:** Imposible visualizar menú ni seleccionar platillos para pedidos. |
| **auth-service** | **CRÍTICA** | 10 min | 0 | **Alto:** Clientes y operadores de cocina KDS bloqueados; parálisis total de nuevos pedidos. |
| **pedidos-service** | **ALTA** | 20 min | 5 min | **Muy Alto:** Órdenes en preparación no llegan a cocina y despachos de repartidores detenidos. |
| **security-audit-service** | **ALTA** | 30 min | 15 min | **Medio:** Pérdida temporal de trazabilidad forense; riesgo regulatorio. |
| **notificaciones-service** | **MEDIA** | 60 min | 30 min | **Bajo:** Retraso en correos transaccionales; no detiene la preparación en cocina. |
| **BD Master (MariaDB)** | **CRÍTICA** | 10 min | 5 min | **Crítico:** Sin escrituras no hay órdenes en cocina, cobros ni actualizaciones KDS. |
| **BD Slave (MariaDB)** | **ALTA** | 15 min | 5 min | **Medio:** Menú conmuta lecturas al Master (degradación leve de rendimiento). |
| **Redis Cache** | **ALTA** | 15 min | 0 (Recomputable) | **Medio:** Mayor latencia en menú y pérdida temporal de lista negra de JWTs. |

---

## 2. Los 6 Escenarios de Incidente de Seguridad y Planes de Acción

```
                               +----------------------------+
                               |     DNS Cloudflare CDN     |
                               +--------------+-------------+
                                              |
                     +------------------------+------------------------+
                     | (Primario: Normal)                              | (Failover: Sitio B)
                     v                                                 v
           [ SITIO A: RENDER.COM ]                           [ SITIO B: RAILWAY.APP ]
       +-------------------------------+                 +-------------------------------+
       | API Gateway (Nginx)           |                 | API Gateway (Nginx Espejo)    |
       | 5 Microservicios PHP          |                 | 5 Microservicios PHP          |
       | MariaDB Master-Slave + Redis  |                 | MariaDB Réplica Caliente      |
       +-------------------------------+                 +-------------------------------+
                     |                                                 ^
                     | (Backups Cifrados AES-256 cada 4h)             | (Restauración)
                     +---------------------> [ S3 / R2 ] --------------+
```

### Escenario 1: Ransomware en BD Master
- **Probabilidad:** Media | **Impacto:** Crítico
- **Objetivo RTO:** 45 minutos | **RPO:** 4 horas
- **Procedimiento de Respuesta:**
  1. Aislar inmediatamente el contenedor `technova-mariadb-master` desconectándolo de la red `ecommerce_net`.
  2. Promover `technova-mariadb-slave` temporalmente como nuevo nodo de escritura mediante `STOP SLAVE; SET GLOBAL read_only = 0;`.
  3. Desplegar una instancia limpia de MariaDB Master desde imágenes Docker inmutables certificadas en el registro privado.
  4. Descargar el último dump completo y binlogs incrementales desde Cloudflare R2 / AWS S3 cifrado en reposo (AES-256).
  5. Ejecutar script de restauración y sincronizar la replicación con el Slave.
  6. Reanudar operaciones y auditar registros en `security-audit-service` para identificar el vector de intrusión.

### Escenario 2: Ataque de Denegación de Servicio Distribuido (DDoS) al API Gateway
- **Probabilidad:** Alta | **Impacto:** Alto
- **Procedimiento de Respuesta:**
  1. Activar el modo *Under Attack Mode* en Cloudflare para filtrar tráfico malicioso en el borde (*Edge*).
  2. Nginx aplica rate limiting automático (`limit_req_zone` a 15 req/s por IP con burst de 30).
  3. Escalar horizontalmente el servicio del Gateway Nginx en Docker: `docker compose up --scale gateway=3 -d`.
  4. Bloquear rangos de IPs atacantes identificados en los logs mediante la directiva `deny` de Nginx.

### Escenario 3: Fuga de Datos de Clientes (Data Breach)
- **Probabilidad:** Baja | **Impacto:** Crítico
- **Procedimiento de Respuesta:**
  1. Identificar y aislar el microservicio vulnerado.
  2. Revocar de inmediato todos los JWT activos eliminando las claves de sesión en Redis (`FLUSHDB` o revocación masiva).
  3. Comprobar que no existen datos de tarjetas comprometidos (garantizado por el cumplimiento PCI-DSS).
  4. Extraer el rastro forense completo desde `security_audit_logs` filtrando por el intervalo de tiempo del incidente.
  5. Enviar notificación obligatoria a los clientes afectados y a las autoridades regulatorias en un plazo menor a 72 horas.

### Escenario 4: Caída Total del Proveedor de Hosting Principal (Render.com)
- **Probabilidad:** Baja | **Impacto:** Alto
- **Procedimiento de Respuesta:**
  1. Conmutar el registro DNS tipo CNAME en Cloudflare apuntando hacia el **Sitio B (Railway.app)** preconfigurado.
  2. El Sitio B cuenta con el archivo `docker-compose.yml` de producción listo para levantar con réplicas actualizadas.
  3. Tiempo total de conmutación de DNS y levantamiento de servicios: **menos de 15 minutos**.

### Escenario 5: Expiración Inesperada del Certificado TLS/HTTPS
- **Probabilidad:** Alta | **Impacto:** Alto
- **Procedimiento de Respuesta:**
  1. Monitoreo automatizado mediante cron job diario que verifica la validez del certificado Let's Encrypt con 30 días de anticipación.
  2. Ejecución forzada del comando Certbot: `certbot renew --force-renewal`.
  3. Recarga en caliente de Nginx sin interrupción de conexiones activas: `nginx -s reload`.

### Escenario 6: Credenciales de Administrador Comprometidas
- **Probabilidad:** Media | **Impacto:** Crítico
- **Procedimiento de Respuesta:**
  1. Invalidar todas las sesiones JWT activas del administrador en Redis agregando sus `jti` a la lista negra.
  2. Cambiar inmediatamente el `password_hash` del usuario en `auth_db.usuarios` con un hash bcrypt nuevo.
  3. Cambiar el secreto `JWT_SECRET` en el archivo `.env` y reiniciar `auth-service` para forzar la reautenticación de todos los usuarios.
  4. Auditar en `security_audit_logs` todas las acciones ejecutadas por el atacante (búsqueda de eventos `PRECIO_MODIFICADO`, `EXPORTACION_CLIENTES`).

---

## 3. Matriz y Cronograma de Backups

| Recurso | Tipo de Respaldo | Frecuencia | Retención | Destino y Cifrado |
| :--- | :--- | :--- | :--- | :--- |
| **BD Master (Todas las BDs)** | Full Backup (mysqldump) + Binlogs | Diario a las 02:00 UTC / Binlogs cada 4h | 30 días | Cloudflare R2 / S3 con cifrado AES-256 |
| **audit_db (Auditoría Forense)** | Streaming incremental Append-Only | Cada 1 hora | 2 años (Cumplimiento) | AWS S3 con Object Lock (Inmutable WORM) |
| **Imágenes de Catálogo** | Sincronización CDN redundante | En cada carga | Indefinido | Cloudinary CDN con replicación global |
| **Código Fuente y Configuración** | Commits Git | En cada push | Indefinido | Repositorio GitHub Privado |
| **Imágenes Docker** | Tags de versión semántica | En cada release | 5 últimas versiones | Docker Hub / GitHub Container Registry |

> **Objetivo Global de Resiliencia:**  
> Ante cualquier catástrofe o incidente de seguridad, **Bistró Gourmet** garantiza un tiempo total de restauración operativa inferior a **60 minutos**.

# Restaurante y Comida en Línea (Delivery & Pickup)
## E-Commerce Distribuido con Capa de Seguridad y Auditoría Forense

**Universidad Mariano Gálvez de Guatemala**  
**Facultad de Ingeniería en Sistemas de Información y Ciencias de la Computación**  
**Curso:** Auditoría y Seguridad de Sistemas — Décimo Ciclo  
**Proyecto Final No. 2:** Plataforma E-Commerce Distribuida con Servicios Web, Capa de Seguridad End-to-End, Microservicio de Auditoría, BCP/DRP y Presupuesto ALE/ROSI  
**Tipo de Negocio:** **Restaurante y Comida en Línea (Servicio a Domicilio / Delivery & Recogida en Sucursal / Pickup)**

---

## 1. Resumen de la Arquitectura Distribuida
La plataforma implementa una arquitectura desacoplada de microservicios con el modelo **Zero Trust** ("nunca confíes, siempre verifica"), donde cada componente valida criptográficamente tokens JWT y aplica el principio de mínimo privilegio.

```
                           [ Navegador Web / Cliente ]
                                        |
                                   Puerto 80/443
                                        v
                 +---------------------------------------------+
                 |          API Gateway (Nginx Alpine)         |
                 |  - Reverse Proxy      - WAF Básico          |
                 |  - Rate Limiting      - Tabler UI Web App   |
                 +----------------------+----------------------+
                                        |
               Red Interna Docker Privada (ecommerce_net)
        +-----------------------+-------+-----------------------+
        |                       |                               |
        v                       v                               v
[ auth-service ]       [ catalogo-service ]            [ pedidos-service ]
  Puerto: 8081           Puerto: 8082                    Puerto: 8083
  PHP 8.2 + JWT          PHP 8.2 (Platillos / Menú)      PHP 8.2 (KDS / Delivery / Pickup)
        |                       |                               |
        +-----------------------+-------+-----------------------+
                                        |
        +-------------------------------+-----------------------+
        |                                                       |
        v                                                       v
 [ pagos-service ]                                   [ security-audit-service ]
   Puerto: 8084                                        Puerto: 8086
   PCI-DSS Compliant (Stripe)                          APPEND-ONLY Inmutable
        |                                                       |
        v                                                       v
 [ notificaciones-service ]                             [ mariadb-audit ]
   Puerto: 8085 (EmailJS / Confirmaciones)              (Volumen audit_data)
        |
        +-----------------------+-----------------------+
        |                                               |
        v                                               v
[ mariadb-master ] <--- Replicación Binlog ---> [ mariadb-slave ]
  (Escrituras)                                     (Lecturas de Menú)
  Volumen master_data                              Volumen slave_data
        |
        v
  [ redis ]
  (Caché de Menú, Rate Limit y Blacklist JWT)
```

---

## 2. Hardening de Seguridad en Docker (Sección 13 & [SEC-5])
En estricto cumplimiento con la rúbrica de evaluación:
1. **Bases de datos no expuestas:** `mariadb-master`, `mariadb-slave`, `mariadb-audit` y `redis` **NO** tienen directiva `ports:` mapeada al host. Solo son accesibles dentro de la red interna `ecommerce_net` (evidencia contra escaneo externo `0.0.0.0:3306`).
2. **Usuario no-root:** Todos los microservicios PHP se ejecutan bajo `USER www-data` en sus respectivos Dockerfiles.
3. **Volumen de Auditoría Aislado:** La base de datos de auditoría `audit_db` reside en un volumen físico independiente (`technova_audit_db_independent_data`), completamente separado de las bases de datos transaccionales del restaurante.
4. **Límites de Recursos:** Se definen restricciones de CPU y memoria (`mem_limit`, `cpus`) en cada contenedor para amortiguar ataques de Denegación de Servicio (DoS).
5. **Secretos protegidos:** Todas las credenciales sensibles se gestionan en `.env`, el cual está excluido del repositorio mediante `.gitignore`.

---

## 3. Credenciales de Prueba del Restaurante

| Rol | Correo Electrónico | Contraseña | Acceso y Permisos |
| :--- | :--- | :--- | :--- |
| **Administrador General** | `admin@bistrogourmet.com` | `AdminSec_2026!` | Menú, KDS, Métricas, Auditoría Forense y Verificación PCI |
| **Chef / Cocina (KDS)** | `cocina@bistrogourmet.com` | `AdminSec_2026!` | Panel KDS, Cambio de Estado de Platillos (`en_preparacion`, `listo`) |
| **Cliente Gourmet** | `cliente@bistrogourmet.com` | `ClientePass_2026!` | Menú gastronómico, Carrito, Delivery/Pickup, Propinas y Checkout |
| **Auditor de TI** | `auditor@bistrogourmet.com` | `AdminSec_2026!` | Pistas de Auditoría Inmutables y Reporte PCI-DSS |

---

## 4. Modalidades de Servicio: Delivery & Pickup

La plataforma incorpora lógica especializada para pedidos gastronómicos en línea:
- **Delivery (Servicio a Domicilio):**
  - Formulario de dirección completa de entrega con notas para el repartidor.
  - Tiempo estimado de entrega: **30 - 45 minutos**.
  - Tarifa de envío: **$2.50 USD** (Envío **GRATIS** en órdenes a partir de **$25.00 USD**).
- **Pickup (Recoger en Sucursal):**
  - Selector de sucursal:
    - *Sucursal Gourmet Zona 10 (Plaza Fontabella)*
    - *Sucursal Gourmet Zona 14 (La Noria)*
    - *Sucursal Gourmet Cayalá (Paseo Cayalá)*
  - Tiempo estimado de preparación: **15 - 20 minutos**.
  - Tarifa de envío: **$0.00 USD** (Sin costo).
- **Propinas al Equipo de Cocina y Reparto:**
  - Selector ágil de propina ($0, $1.50, $3.00, $5.00).
- **Instrucciones Especiales de Cocina:**
  - Campo para solicitudes de comensales (sin cebolla, salsa aparte, alérgicos, término de carne, etc.).
- **Kitchen Display System (KDS) & Ciclo de Vida del Pedido:**
  - Transiciones en tiempo real: `recibido` ➔ `en_preparacion` ➔ `listo` ➔ `en_camino` ➔ `entregado` (o `cancelado`).
  - Modal interactivo de seguimiento de orden en vivo en el frontend del cliente.

---

## 5. Guía Rápida de Despliegue y Ejecución

### Requisitos Previos:
- Docker y Docker Compose instalados (`docker compose version`).

### Paso 1: Configurar variables de entorno
```bash
cp .env.example .env
```

### Paso 2: Levantar todos los servicios del clúster
```bash
docker compose up --build -d
```

### Paso 3: Acceso a las aplicaciones
- **Menú y Pedidos en Línea:** Abre tu navegador en [http://localhost](http://localhost)
- **Panel de Administración, KDS y Auditoría:** [http://localhost/admin.html](http://localhost/admin.html)

---

## 6. Verificación de Cumplimiento de la Rúbrica de Auditoría

### [SEC-2] Evidencia de Auditoría (35+ Registros y 14 Tipos de Eventos)
Ingresa al Panel de Administración en la pestaña **Auditoría Forense**. Se encuentran precargados registros cubriendo los 14 eventos obligatorios:
1. `LOGIN_CLIENTE_EXITOSO`
2. `LOGIN_CLIENTE_FALLIDO` (simulación de fuerza bruta)
3. `REGISTRO_CLIENTE`
4. `PEDIDO_CREADO` (con discriminación de delivery/pickup)
5. `PAGO_INICIADO`
6. `PAGO_CONFIRMADO`
7. `PAGO_FALLIDO` (tarjeta rechazada)
8. `PEDIDO_ESTADO_CAMBIADO` (actualizaciones KDS en cocina)
9. `PRECIO_MODIFICADO` (con `dato_anterior` y `dato_nuevo` en platillos)
10. `PRODUCTO_AGOTADO`
11. `EXPORTACION_CLIENTES`
12. `ACCESO_ADMIN_PANEL`
13. `WEBHOOK_STRIPE_RECIBIDO` (con verificación de firma HMAC)
14. `TOKEN_REVOCADO` (logout y lista negra en Redis)

### [SEC-3] Verificación PCI-DSS en Pagos
Ejecuta la herramienta de auditoría en la pestaña **Verificación PCI-DSS** de `/admin.html` o conecta al contenedor:
```bash
docker exec -it technova-mariadb-master mariadb -u technova_app -pAppMasterPass_2026! -e "DESCRIBE pagos_db.pagos; SELECT * FROM pagos_db.pagos;"
```
*Se comprueba que la tabla contiene únicamente `payment_intent_id` y ningún número de tarjeta, CVV o fecha de vencimiento.*

### [SEC-5] Verificación de Docker Hardening (Puertos Ocultos)
```bash
docker ps --format "table {{.Names}}\t{{.Ports}}"
```
*Solo el contenedor `technova-gateway` expone los puertos 80/443. MariaDB y Redis no tienen puertos expuestos en `0.0.0.0`.*

---

## 7. Documentos de Auditoría y Seguridad Incluidos

- 📄 [STRIDE_THREAT_MODEL.md](file:///home/ltj763/proyectos/e-comerce/docs/STRIDE_THREAT_MODEL.md) — Modelado de amenazas STRIDE aplicado al flujo gastronómico, delivery y pickup.
- 📄 [OWASP_TOP10_CONTROLS.md](file:///home/ltj763/proyectos/e-comerce/docs/OWASP_TOP10_CONTROLS.md) — Matriz de controles OWASP con enlaces directos al código fuente.
- 📄 [PCI_DSS_COMPLIANCE.md](file:///home/ltj763/proyectos/e-comerce/docs/PCI_DSS_COMPLIANCE.md) — Cumplimiento de los 6 requisitos y verificación de base de datos sin datos PAN/CVV.
- 📄 [BCP_DRP_PLAN.md](file:///home/ltj763/proyectos/e-comerce/docs/BCP_DRP_PLAN.md) — Plan de continuidad operativa del restaurante, RTO/RPO y los 6 escenarios de desastre.
- 📄 [ALE_ROSI_BUDGET.md](file:///home/ltj763/proyectos/e-comerce/docs/ALE_ROSI_BUDGET.md) — Modelo financiero ALE y retorno de inversión en ciberseguridad (ROSI).

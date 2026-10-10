# Modelado de Amenazas STRIDE — Restaurante y Comida en Línea (Bistró Gourmet)
**Curso:** Auditoría y Seguridad de Sistemas — Décimo Ciclo  
**Universidad Mariano Gálvez de Guatemala**  
**Proyecto Final No. 2 — Entregable [SEC-1]**

---

## 1. Introducción y Alcance
El presente modelo de amenazas STRIDE analiza la superficie de ataque y los flujos de datos del ciclo gastronómico completo de la plataforma **Bistró Gourmet — Restaurante, Delivery & Pickup**:
`Menú Gastronómico → Carrito e Instrucciones Especiales → Selección Delivery / Pickup → Creación de Pedido → Procesamiento de Pago Stripe Tokenizado → KDS (Kitchen Display System) y Despacho`.

La plataforma maneja pedidos con preparación en tiempo real, cálculo dinámico de tarifas de envío, recolección en sucursales físicas y cobros tokenizados, por lo que las motivaciones de fraude económico, spoofing de identidad, alteración de costos de envío o suplantación en recogida son vectores prioritarios de análisis.

---

## 2. Diagrama de Flujo de Datos (DFD) y Límites de Confianza

```
                 [ Navegador Cliente / Comensal / Atacante ]
                                |
                         HTTPS (TLS 1.3)
                                |
                    [ LÍMITE DE CONFIANZA EXTERNO ]
                                v
                      [ Nginx API Gateway ]
               (WAF Básico + Rate Limiting Global)
                                |
                    [ LÍMITE DE CONFIANZA INTERNO ]
                                v
                Red Interna Docker (ecommerce_net)
         +----------------------+----------------------+
         |                      |                      |
 [ auth-service ]       [ catalogo-service ]   [ pedidos-service ]
    (JWT / Bcrypt)        (Slave Read / Menú)    (KDS / Precios / Envío)
         |                      |                      |
         +----------------------+----------------------+
                                |
                        [ pagos-service ]
                    (Stripe Sandbox Tokenizado)
                                |
                                +---> [ notificaciones-service ]
                                |
                  +-------------+-------------+
                  |                           |
         [ security-audit-service ]    [ Stripe API Externa ]
         (Append-Only Audit DB)        (Webhook con HMAC)
```

---

## 3. Matriz Completa de Amenazas STRIDE por Flujo Gastronómico

| Elemento STRIDE | Flujo Afectado | Escenario Concreto de Ataque | Impacto en el Negocio | Contramedida Implementada en Código | Ubicación en Código |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **S — Spoofing** *(Suplantación de Identidad)* | Autenticación y Checkout | Intento de *Credential Stuffing* o robo de sesión para ordenar banquetes costosos con cargo a otro cliente o retirar pedidos de pickup ajenos. | Pérdida de reputación, pedidos no pagados y retiro fraudulento en sucursal. | Rate limiting en Redis (bloqueo tras 5 intentos fallidos por 15 min), contraseñas con bcrypt (coste 12), JWT firmado con secreto HS256 de 256 bits y verificación de identidad antes de procesar el pedido. | [`services/auth-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/auth-service/src/index.php)<br>[`services/common/Security.php`](file:///home/ltj763/proyectos/e-comerce/services/common/Security.php) |
| **T — Tampering** *(Alteración de Datos)* | Carrito, Modalidad y Creación de Pedido | Modificación del precio unitario de un Tomahawk o Corte Prime ($38.50 a $1.00), o alteración del costo de delivery en el payload JSON mediante DevTools/Burp Suite. | Pérdida financiera directa, consumo de insumos culinarios de alto costo y desbalance contable. | **El precio SIEMPRE se consulta en el servidor (`catalogo_db`)**. El servicio recalcula subtotal, impuestos, valida tarifa de delivery ($2.50 o $0 si >= $25 o $0 si pickup) y descarta cualquier total proveniente del cliente. | [`services/pedidos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pedidos-service/src/index.php) |
| **R — Repudiation** *(Repudio)* | Confirmación de Pago y Despacho en Cocina | El comensal afirma que nunca ordenó el pedido de delivery o que el repartidor no entregó la orden en la dirección indicada. | Pérdidas económicas por reclamos falsos (*friendly fraud*) y contracargos bancarios. | Registro inmutable de cada transacción en `security-audit-service`, vinculando `usuario_id`, `ip_origen`, `user_agent`, `payment_intent_id` de Stripe, estado KDS y envío de recibo digital vía correo. | [`services/pagos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pagos-service/src/index.php)<br>[`services/security-audit-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/security-audit-service/src/index.php) |
| **I — Information Disclosure** *(Fuga de Información)* | Consulta de Pedidos, Direcciones y KDS | Atacante ejecuta `GET /api/pedidos` sin parámetros o manipula el parámetro ID para ver órdenes, teléfonos y direcciones de residencia de otros comensales (IDOR). | Exposición masiva de datos personales sensibles (direcciones particulares, horarios de entrega) violando leyes de privacidad. | Control de Acceso OWASP A01: El servicio valida que `id_cliente` extraído del token JWT validado coincida estrictamente con la orden consultada (excepto administradores/cocineros autorizados). | [`services/pedidos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pedidos-service/src/index.php)<br>[`services/common/JWT.php`](file:///home/ltj763/proyectos/e-comerce/services/common/JWT.php) |
| **D — Denial of Service** *(Denegación de Servicio)* | API Gateway y Menú Gastronómico | *Botnet* ejecutando peticiones masivas al endpoint del menú durante horas pico del almuerzo o cena para colapsar la base de datos y evitar que los comensales ordenen. | Parálisis operativa en horas de máxima facturación; comensales frustrados que eligen otros restaurantes. | Rate limiting en Nginx (`15r/s`), micro-caché en Redis con TTL de 300 segundos, lecturas de platillos delegadas a MariaDB Slave y límites de CPU/RAM en Docker (`mem_limit`, `cpus`). | [`gateway/nginx.conf`](file:///home/ltj763/proyectos/e-comerce/gateway/nginx.conf)<br>[`services/catalogo-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/catalogo-service/src/index.php)<br>[`docker-compose.yml`](file:///home/ltj763/proyectos/e-comerce/docker-compose.yml) |
| **E — Elevation of Privilege** *(Elevación de Privilegios)* | Panel KDS, Cocina y Administración | Un cliente manipula el claim `rol` de su token JWT para acceder a `/admin.html`, alterar el estado de su pedido a `entregado` o cambiar los precios del menú. | Fraude en entregas, colapso del flujo de cocina (KDS) y manipulación del catálogo gastronómico. | La firma criptográfica HMAC-SHA256 del JWT impide la modificación de claims. El backend valida el rol contra la clave secreta en `.env` y consulta la lista negra de Redis en cada petición protegida. | [`services/common/JWT.php`](file:///home/ltj763/proyectos/e-comerce/services/common/JWT.php)<br>[`services/pedidos-service/src/index.php`](file:///home/ltj763/proyectos/e-comerce/services/pedidos-service/src/index.php) |

---

## 4. Conclusiones del Modelado
La arquitectura de Bistró Gourmet mitiga de manera integral los vectores de amenaza STRIDE adaptados al sector gastronómico digital mediante defensa en profundidad:
1. **Perímetro:** Nginx WAF y protección anti-DDoS para resguardar la disponibilidad en horas pico de servicio de comida.
2. **Identidad:** Zero Trust con tokens efímeros y revocación instantánea en Redis.
3. **Lógica de Negocio:** Autoridad estricta del servidor sobre precios de platillos, tarifas de delivery ($2.50 vs gratis >= $25), pickup ($0) y propinas.
4. **Almacenamiento:** Separación física del microservicio de auditoría forense para mantener trazabilidad inmutable de órdenes y cambios de estado KDS en cocina.

# Presupuesto de Ciberseguridad y Análisis ALE / ROSI
**Plataforma:** Bistró Gourmet — Restaurante, Delivery & Pickup  
**Curso:** Auditoría y Seguridad de Sistemas — Décimo Ciclo  
**Universidad Mariano Gálvez de Guatemala**  
**Proyecto Final No. 2 — Entregable [SEC-1]**

---

## 1. Justificación y Metodología
En una plataforma gastronómica de pedidos en línea, delivery y pickup, el volumen de transacciones diarias es intensivo durante las horas de almuerzo y cena. Las amenazas cibernéticas no solo ocasionan pérdidas de disponibilidad en momentos pico de consumo, sino riesgo de fraude con tarjetas de crédito, alteración de precios de platillos/combos y manipulación de pedidos de entrega.

Se utiliza la metodología estándar:
- **SLE (Single Loss Expectancy):** Pérdida estimada por la ocurrencia de un único evento (en USD).
- **ARO (Annualized Rate of Occurrence):** Tasa anualizada estimada de ocurrencia del evento.
- **ALE (Annualized Loss Expectancy):** Pérdida esperada anual sin salvaguardas: $\text{ALE} = \text{SLE} \times \text{ARO}$.
- **ALE Post-Control:** Pérdida esperada remanente tras aplicar la salvaguarda de seguridad.
- **ROSI (Return on Security Investment):** Beneficio neto de la inversión:
  $$\text{ROSI} = (\text{ALE}_{\text{pre}} - \text{ALE}_{\text{post}}) - \text{Costo Anual del Control}$$

---

## 2. Matriz de Análisis de Riesgos ALE y ROSI (Ajustada al Modelo Gastronómico)

| Amenaza Específica en E-Commerce de Tecnología | SLE ($) | ARO | ALE Pre-Control ($) | Control de Seguridad Implementado | Costo Anual ($) | ALE Post-Control ($) | ROSI Anual ($) |
| :--- | :---: | :---: | :---: | :--- | :---: | :---: | :---: |
| **Fuga de datos de tarjetas y multas PCI-DSS** | $25,000 | 0.3 | $7,500 | **Arquitectura PCI-DSS:** No almacenar tarjetas; delegar en Stripe tokenizado. | $0 | $0 | **+$7,500** |
| **Secuestro de cuenta de cliente (Credential Stuffing)** | $600 | 12.0 | $7,200 | **Rate Limiting + Bcrypt (cost 12) + Revocación en Redis.** | $0 | $600 | **+$6,600** |
| **Ransomware / Secuestro de Base de Datos Master** | $22,000 | 0.4 | $8,800 | **Backups inmutables en Cloudflare R2/S3 (AES-256) + DRP <45 min.** | $300 | $800 | **+$7,700** |
| **DDoS saturando catálogo en temporada alta (Black Friday)** | $4,500 | 2.0 | $9,000 | **Cloudflare CDN + Nginx Rate Limiting (15 req/s) + Redis Cache.** | $0 | $900 | **+$8,100** |
| **Scraping masivo de precios por competidores** | $1,500 | 4.0 | $6,000 | **Caché en memoria Redis + WAF Nginx con bloqueo de User-Agents.** | $0 | $600 | **+$5,400** |
| **Inyección de Webhook falso (Pedidos marcados pagados sin pago)** | $5,000 | 1.0 | $5,000 | **Verificación estricta de firma criptográfica Stripe-Signature (HMAC).** | $0 | $0 | **+$5,000** |
| **TOTALES CONSOLIDADOS** | — | — | **$43,500** | — | **$300** | **$2,900** | **+$40,300** |

> **Interpretación Financiera:**  
> Con una inversión directa mínima de únicamente **$300 USD anuales**, la empresa evita pérdidas estimadas de **$40,600 USD**, lo que representa un retorno sobre la inversión en seguridad (**ROSI**) extraordinario de más del **1,300%**.

---

## 3. Presupuesto Anual de Herramientas y Servicios de Seguridad

| Categoría | Servicio / Herramienta | Finalidad Técnica | Costo Estimado Anual (USD) |
| :--- | :--- | :--- | :---: |
| **Gestión de Pagos Seguros** | Stripe Payments Sandbox | Procesamiento seguro tokenizado PCI-DSS Nivel 1. (En desarrollo/pruebas: $0). | $0.00 |
| **Mitigación DDoS y CDN** | Cloudflare Free Plan | Protección contra ataques de denegación de servicio, CDN y SSL. | $0.00 |
| **Certificados TLS** | Let's Encrypt | Cifrado en tránsito HTTPS automático cada 90 días. | $0.00 |
| **Almacenamiento de Respaldo** | Cloudflare R2 / AWS S3 | Backups cifrados de MariaDB Master y logs de auditoría (10 GB gratis). | $25.00 – $50.00 |
| **Optimización Multimedia** | Cloudinary CDN | Entrega de imágenes de platillos gourmet con compresión WebP. | $0.00 *(Plan Free 25GB)* |
| **Cache y Blacklist** | Redis 7 Alpine | Aislamiento en contenedor Docker local / nube. | $0.00 – $60.00 |
| **Alojamiento Cloud con HTTPS** | Render.com / Railway | Despliegue de microservicios con orquestación de contenedores. | $0.00 – $120.00 |
| **Escaneo de Vulnerabilidades** | OWASP ZAP (Open Source) | DAST continuo contra endpoints públicos del API Gateway. | $0.00 |
| **Análisis Estático de Código** | GitHub CodeQL (SAST) | Detección automatizada de inyecciones y fallos en repositorio. | $0.00 |
| **Fondo de Contingencia (10%)** | Imprevistos de Seguridad | Actualizaciones de dependencias, parches de emergencia. | $35.00 |
| **PRESUPUESTO TOTAL ANUAL** | — | **Operación segura y certificable de Bistró Gourmet** | **$60.00 – $265.00 USD / año** |

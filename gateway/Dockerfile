FROM nginx:alpine

# Copiar configuración con WAF y enrutamiento
COPY nginx.conf /etc/nginx/nginx.conf

# Copiar archivos frontend
COPY html/ /var/www/html/

# Permisos para el usuario nginx
RUN chown -R nginx:nginx /var/www/html && \
    chmod -R 755 /var/www/html

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]

FROM php:8.2-cli-alpine

# Instalar dependencias del sistema y extensiones PHP
RUN apk add --no-cache bash curl \
    && docker-php-ext-install pdo pdo_mysql bcmath \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar código fuente
COPY . /var/www/html

# Ajustar permisos para usuario no-root (Hardening Docker)
RUN chown -R www-data:www-data /var/www/html

# Cambiar a usuario no-root www-data (Requisito estricto sección 13)
USER www-data

EXPOSE 8081

CMD ["php", "-S", "0.0.0.0:8081", "-t", "/var/www/html/src"]

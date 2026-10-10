FROM php:8.2-cli-alpine

RUN apk add --no-cache bash curl \
    && docker-php-ext-install pdo pdo_mysql bcmath

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8086

CMD ["php", "-S", "0.0.0.0:8086", "-t", "/var/www/html/src"]

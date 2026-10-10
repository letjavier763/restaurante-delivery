FROM php:8.2-cli-alpine

RUN apk add --no-cache bash curl \
    && docker-php-ext-install pdo pdo_mysql bcmath \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8082

CMD ["php", "-S", "0.0.0.0:8082", "-t", "/var/www/html/src"]

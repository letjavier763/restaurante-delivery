FROM php:8.2-cli-alpine

RUN apk add --no-cache bash curl

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 8085

CMD ["php", "-S", "0.0.0.0:8085", "-t", "/var/www/html/src"]

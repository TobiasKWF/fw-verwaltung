FROM php:8.3-apache

RUN docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY src/ /var/www/src/

RUN mkdir -p /var/www/data \
    && chown -R www-data:www-data /var/www/data

EXPOSE 80

CMD ["apache2-foreground"]

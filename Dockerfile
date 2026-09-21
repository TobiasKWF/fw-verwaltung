FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev pkg-config \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride AuthConfig/' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY public/ /var/www/html/
COPY src/ /var/www/src/

RUN mkdir -p /var/www/data/uploads/incidents \
    && ln -s /var/www/data/uploads /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/data

EXPOSE 80

CMD ["sh","-c","chown -R www-data:www-data /var/www/data && exec apache2-foreground"]

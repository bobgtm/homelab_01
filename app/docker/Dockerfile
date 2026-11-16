FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
      libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install mysqli pdo pdo_mysql gd exif \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-enabled/servername.conf

RUN printf 'upload_max_filesize = 15M\npost_max_size = 20M\nmemory_limit = 256M\n' \
    > /usr/local/etc/php/conf.d/app.ini

COPY src/ /var/www/html/

EXPOSE 80

#Barrel Book images.
#   dev  : used by Compose; code is bind-mounted from ./src for live editing.
#   prod : self-contained image for Kubernetes; code baked in, state on a volume at /data.
# Build prod:  docker build -f docker/Dockerfile --target prod -t barrelbook:<version> .

FROM php:8.4-apache AS base
RUN apt-get update && apt-get install -y --no-install-recommends wget unzip libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install mysqli pdo pdo_mysql gd exif \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite \
 && printf 'ServerName localhost\n' > /etc/apache2/conf-enabled/servername.conf
# Photo uploads (admin): allow phone-camera sized images.
RUN printf 'upload_max_filesize = 15M\npost_max_size = 20M\nmemory_limit = 256M\n' > /usr/local/etc/php/conf.d/barrelbook.ini
EXPOSE 80

# ---------------------------------------------------------------- dev
FROM base AS dev
# Run Apache as your host user's UID/GID so ./userspice stays editable from the host.
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

# ---------------------------------------------------------------- prod
FROM base AS prod
ARG VERSION=dev
LABEL org.opencontainers.image.title="Barrel Book" org.opencontainers.image.version="${VERSION}"
COPY --chown=www-data:www-data src/ /var/www/html/
COPY db-init/bourbon.sql /docker/sql.sql
RUN rm -rf /var/www/html/bourbon/images/uploads && mkdir -p /data && chown www-data:www-data /data \
 && echo "${VERSION}" > /var/www/html/version.txt
# Production PHP: hide version and errors from visitors (errors still go to the container log).
RUN printf 'expose_php = Off\ndisplay_errors = Off\nlog_errors = On\nerror_log = /dev/stderr\n' > /usr/local/etc/php/conf.d/zz-production.ini
ENV BB_DATA_DIR=/data BASE_SCHEMA=/docker/sql.sql
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s CMD php -r 'exit(@file_get_contents("http://127.0.0.1/healthz.php") === "ok\n" ? 0 : 1);'

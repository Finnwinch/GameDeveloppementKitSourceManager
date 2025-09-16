FROM php:8.4-apache

ARG STEAM_UID=1000
ARG STEAM_GID=1000
RUN mkdir -p /var/www/sqlite && chown -R www-data:www-data /var/www/sqlite
RUN apt-get update && apt-get install -y \
    nano \
    docker.io \
    curl \
    libzip-dev \
    zip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

RUN groupmod -g 1001 docker && usermod -aG docker www-data

RUN groupmod -g ${STEAM_GID} www-data \
    && usermod -u ${STEAM_UID} -g ${STEAM_GID} www-data

RUN a2enmod rewrite

COPY web/. /var/www/html/

COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]

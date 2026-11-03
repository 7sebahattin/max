# syntax=docker/dockerfile:1
FROM php:8.3-apache-bookworm@sha256:96f820bf68a6e443521586cdd29b9efa1b990df6105b0f8c5524a49492752676
# Network downloads retain TLS checks and use the host's trusted CA bundle.
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update \
    && apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends \
       libcurl4-openssl-dev libfreetype6-dev libicu-dev libjpeg62-turbo-dev \
       libonig-dev libpng-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 mysqli mbstring gd curl calendar simplexml intl zip soap \
    && a2enmod proxy proxy_http \
    && rm -rf /var/lib/apt/lists/*
COPY deployment/local/apache.conf /etc/apache2/sites-available/000-default.conf
COPY deployment/local/php.ini /usr/local/etc/php/conf.d/max-local.ini
WORKDIR /opt/dolibarr/htdocs

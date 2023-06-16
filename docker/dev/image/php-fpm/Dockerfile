FROM php:8.2-fpm-alpine3.17

RUN apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} ${BUILD_DEPENDENSIES}
RUN apk add --no-cache curl git unzip imagemagick imagemagick-dev libzip-dev libpng-dev libxml2-dev icu-dev yaml-dev ${DEPENDENSIES}

RUN pecl install -f imagick yaml
RUN docker-php-ext-install -j$(nproc) zip gd intl soap ${EXTRA_EXTENSIONS}
RUN docker-php-ext-enable opcache.so imagick yaml
RUN docker-php-source delete

RUN rm -rf /var/cache/apk/*

WORKDIR /var/container

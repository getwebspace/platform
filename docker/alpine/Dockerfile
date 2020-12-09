FROM php:7.4-fpm-alpine
MAINTAINER Aleksey Ilyin <alksily@outlook.com>

# Set args
ARG BRANCH="master"
ARG COMMIT="latest"
ARG NO_DEV="--no-dev"

# Set env vars
ENV COMMIT_BRANCH=${BRANCH}
ENV COMMIT_SHA=${COMMIT}
ENV PLATFORM_HOME="/var/container"

# Install packages, install php modules
RUN set -x \
    && apk update \
    && apk upgrade \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && apk add --no-cache --virtual .build-deps libstdc++ make \
    && apk add --no-cache \
        git \
        libzip-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        zlib-dev \
        gettext-dev \
        libmcrypt-dev \
        oniguruma-dev \
        yaml-dev \
        imagemagick-dev \
        imagemagick \
    && pecl install imagick yaml \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j$(nproc) gettext zip gd pdo_mysql mbstring \
    && docker-php-ext-enable opcache.so imagick yaml \
    && docker-php-source delete \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer \
    && rm composer-setup.php

# Copy configs
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Set homedir as work
WORKDIR ${PLATFORM_HOME}

# Install PHP libs
COPY composer.json ${PLATFORM_HOME}/composer.json
COPY composer.lock ${PLATFORM_HOME}/composer.lock
RUN composer install ${NO_DEV} --no-suggest --no-progress

# Copy platform
ADD config ${PLATFORM_HOME}/config
ADD plugin ${PLATFORM_HOME}/plugin
ADD public ${PLATFORM_HOME}/public
ADD src ${PLATFORM_HOME}/src
ADD theme ${PLATFORM_HOME}/theme
ADD var ${PLATFORM_HOME}/var

# Final step
RUN set -x \
    && apk del --purge .build-deps \
    && rm -rf /var/cache/apk/* \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0777 ${PLATFORM_HOME}/public/resource \
    && chmod -R 0777 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0777 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var

EXPOSE 80/tcp
VOLUME ["${PLATFORM_HOME}/plugin", "${PLATFORM_HOME}/public/resource", "${PLATFORM_HOME}/theme", "${PLATFORM_HOME}/var", "${PLATFORM_HOME}/public/uploads"]

STOPSIGNAL SIGTERM

ENTRYPOINT ["php", "-S", "0.0.0.0:80", "-t", "public", "public/index.php"]

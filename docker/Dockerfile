FROM php:8.1-fpm-alpine3.17

# Lightweight container with Nginx & PHP-FPM based on Alpine Linux for WebSpace Engine
MAINTAINER Aleksey Ilyin <alksily@getwebspace.org>

# Set args
ARG VERSION="dev"
ARG COMMIT=""
ARG NO_DEV=""

# Set env vars
ENV COMMIT_BRANCH=${VERSION} \
    COMMIT_SHA=${COMMIT} \
    BUILD_DEPENDENSIES="g++ make autoconf" \
    DEPENDENSIES="" \
    EXTRA_EXTENSIONS="pdo_mysql" \
    PLATFORM_HOME="/var/container"

# Copy configs
COPY --chown=www-data docker/rootfs/ /

# Copy platform files
COPY --chown=www-data . ${PLATFORM_HOME}

# Set homedir as work
WORKDIR ${PLATFORM_HOME}

# Install packages
RUN set -x \
    && apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} ${BUILD_DEPENDENSIES} \
    && apk add --no-cache \
        runit \
        nginx \
        curl \
        git \
        unzip \
        imagemagick \
        imagemagick-dev \
        libzip-dev \
        libpng-dev \
        libxml2-dev \
        icu-dev \
        yaml-dev \
        ${DEPENDENSIES} \
    && nginx -V \
    && pecl install -f imagick yaml \
    && docker-php-ext-install -j$(nproc) zip gd intl soap ${EXTRA_EXTENSIONS} \
    && docker-php-ext-enable opcache.so imagick yaml \
    && docker-php-source delete \
    \
    # Remove alpine cache
    && rm -rf /var/cache/apk/* \
    \
    # Remove default server definition
    && rm /etc/nginx/http.d/default.conf \
    \
    # Make sure files/folders needed by the processes are accessable when they run under the www-data user
    && chown -R www-data:www-data /run \
    && chown -R www-data:www-data /var/lib/nginx \
    && chown -R www-data:www-data /var/log/nginx \
    && chmod -R 0755 /etc/service \
    && chmod 0755 /bin/docker-entrypoint.sh \
    \
    # Install composer
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer \
    && rm composer-setup.php \
    \
    # Install platform
    && composer install ${NO_DEV} --no-suggest --no-progress \
    && chown -R www-data:www-data ${PLATFORM_HOME} \
    && chmod -R 0755 ${PLATFORM_HOME} \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0776 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0776 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var

# Switch to use a non-root user from here on
USER www-data

# Set volumes
VOLUME ["${PLATFORM_HOME}/plugin", "${PLATFORM_HOME}/public/resource", "${PLATFORM_HOME}/theme", "${PLATFORM_HOME}/var", "${PLATFORM_HOME}/public/uploads"]

# Set ports
EXPOSE 80/tcp 443/tcp

CMD ["/bin/docker-entrypoint.sh"]

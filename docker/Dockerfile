# syntax=docker/dockerfile:1

# Composer binary comes straight from the official image - no curl bootstrap
FROM composer:2 AS composer

FROM php:8.5-fpm-alpine3.22

LABEL org.opencontainers.image.authors="Aleksey Ilyin <alksily@outlook.com>"
LABEL org.opencontainers.image.source="https://github.com/getwebspace/platform"
LABEL org.opencontainers.image.description="Optimised container with nginx & PHP-FPM based on Alpine Linux for WebSpace Engine"
LABEL org.opencontainers.image.licenses="MIT"

# Build-time metadata
ARG VERSION="dev"
ARG COMMIT=""
ARG NO_DEV=""

ENV COMMIT_BRANCH=${VERSION} \
    COMMIT_SHA=${COMMIT} \
    EXTRA_EXTENSIONS="pdo_mysql" \
    PLATFORM_HOME="/var/container"

WORKDIR ${PLATFORM_HOME}

# Service definitions (nginx, php-fpm, cron via runit) and entrypoint
COPY docker/rootfs/ /
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Runtime packages + PHP extensions.
# Build toolchain is installed as a virtual package and dropped afterwards,
# keeping only the shared libraries the compiled extensions actually link against.
RUN set -eux; \
    apk add --no-cache \
        dcron \
        runit \
        nginx; \
    apk add --no-cache --virtual .build-deps \
        ${PHPIZE_DEPS} \
        pax-utils \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libxpm-dev \
        libzip-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm; \
    docker-php-ext-install -j"$(nproc)" gd intl zip ${EXTRA_EXTENSIONS}; \
    php -m | grep -q 'Zend OPcache'; \
    docker-php-source delete; \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache ${runDeps}; \
    apk del --no-network .build-deps; \
    rm -f /etc/nginx/http.d/default.conf; \
    mkdir -p /etc/cron.d /docker-entrypoint-init.d; \
    chmod 0755 /bin/docker-entrypoint.sh; \
    chmod -R 0755 /etc/service; \
    echo "* * * * * /usr/local/bin/php ${PLATFORM_HOME}/bin/cron_worker.php >/dev/null 2>&1" > /etc/crontabs/root; \
    nginx -V; \
    php -v; \
    php -m

# Dependencies first so the vendor layer is reused when only source changes
COPY composer.json composer.lock ./
RUN composer install ${NO_DEV} --no-scripts --no-autoloader --no-interaction --no-progress

# Application source
COPY --chmod=755 . ${PLATFORM_HOME}

RUN set -eux; \
    composer dump-autoload ${NO_DEV} --optimize --no-interaction; \
    mkdir -p \
        var/cache var/log var/xml \
        public/resource public/uploads; \
    chmod -R 0777 \
        bin \
        plugin \
        public/resource \
        public/uploads \
        theme \
        var

EXPOSE 80

CMD ["/bin/docker-entrypoint.sh"]

FROM php:8.3-fpm-alpine3.20

MAINTAINER Aleksey Ilyin <alksily@getwebspace.org>

LABEL org.opencontainers.image.source="https://github.com/getwebspace/platform"
LABEL org.opencontainers.image.description="Optimised container with nginx & PHP-FPM based on Alpine Linux for WebSpace Engine"
LABEL org.opencontainers.image.licenses="MIT"

# Set args
ARG VERSION="dev"
ARG COMMIT=""
ARG NO_DEV=""

# Set env vars
ENV COMMIT_BRANCH=${VERSION} \
    COMMIT_SHA=${COMMIT} \
    BUILD_DEPENDENCIES="g++ make autoconf" \
    DEPENDENCIES="" \
    EXTRA_EXTENSIONS="pdo_mysql" \
    PLATFORM_HOME="/var/container"

# Copy configs
COPY docker/rootfs/ /

# Copy platform files
COPY --chmod=755 . ${PLATFORM_HOME}

# Set homedir as work
WORKDIR ${PLATFORM_HOME}

# Install packages and dependencies
RUN set -x \
    && apk add --update --no-cache \
        dcron \
        runit \
        nginx \
        libzip-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        libxpm-dev \
        freetype-dev \
        icu-dev \
        ${PHPIZE_DEPS} \
        ${BUILD_DEPENDENCIES} \
        ${DEPENDENCIES} \
    \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-xpm \
    && docker-php-ext-install -j$(nproc) zip gd intl ${EXTRA_EXTENSIONS} \
    && docker-php-ext-enable opcache.so \
    && docker-php-source delete \
    \
    # Remove alpine cache and unused dependencies
    && rm -rf /var/cache/apk/* \
    \
    # Remove default server definition
    && rm /etc/nginx/http.d/default.conf \
    \
    # Checks
    && nginx -V \
    && php -v \
    && php -m \
    \
    # Make sure exists files/folders and set correct permissions
    && mkdir -p /etc/cron.d \
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
    && chmod -R 0755 ${PLATFORM_HOME} \
    && chmod -R 0777 ${PLATFORM_HOME}/bin \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0776 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0777 ${PLATFORM_HOME}/public/resource \
    && chmod -R 0777 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var \
    \
    # Add cron task
    && echo "* * * * * /usr/local/bin/php ${PLATFORM_HOME}/bin/cron_worker.php >/dev/null 2>&1" > /etc/crontabs/root

# Set ports
EXPOSE 80

CMD ["/bin/docker-entrypoint.sh"]

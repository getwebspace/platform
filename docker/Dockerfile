FROM php:8.3-fpm-alpine3.20

MAINTAINER Aleksey Ilyin <alksily@getwebspace.org>

LABEL org.opencontainers.image.source="https://github.com/getwebspace/platform"
LABEL org.opencontainers.image.description="Lightweight container with Nginx & PHP-FPM based on Alpine Linux for WebSpace Engine"
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
COPY --chown=www-data docker/rootfs/ /

# Copy platform files
COPY --chown=www-data:www-data --chmod=755 . ${PLATFORM_HOME}

# Set homedir as work
WORKDIR ${PLATFORM_HOME}

# Install packages and dependencies
RUN set -x \
    && apk add --update --no-cache \
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
    # Make sure files/folders needed by the processes are accessible when they run under the www-data user
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
    && find ${PLATFORM_HOME} -type f -exec chmod 0644 {} + \
    && find ${PLATFORM_HOME} -type d -exec chmod 0755 {} + \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0776 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0777 ${PLATFORM_HOME}/public/resource \
    && chmod -R 0777 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var

# Switch to use a non-root user from here on
USER www-data

# Set ports
EXPOSE 80/tcp

CMD ["/bin/docker-entrypoint.sh"]

FROM php:8.1-fpm-alpine3.17
MAINTAINER Aleksey Ilyin <alksily@getwebspace.org>

# Lightweight container with Nginx & PHP-FPM based on Alpine Linux for WebSpace Engine

# Set env vars
ENV COMMIT_BRANCH="dev" \
    COMMIT_SHA="specific" \
    BUILD_DEPENDENSIES="g++ make autoconf" \
    DEPENDENSIES="imagemagick-dev libzip-dev libpng-dev libxml2-dev icu-dev yaml-dev" \
    EXTRA_EXTENSIONS="pdo_mysql" \
    PLATFORM_HOME="/var/container"

# Copy configs
COPY --chown=nobody . ${PLATFORM_HOME}
COPY --chown=nobody docker/rootfs/ /

# Set homedir as work
WORKDIR ${PLATFORM_HOME}

# Install packages
RUN set -x \
    && apk update \
    && apk upgrade \
    && apk add --no-cache --virtual .build-deps ${PHPIZE_DEPS} ${BUILD_DEPENDENSIES} \
    && apk add --no-cache \
        runit \
        nginx \
        curl \
        git \
        unzip \
        imagemagick \
        ${DEPENDENSIES} \
    && nginx -V \
    && pecl install -f imagick yaml \
    && docker-php-ext-install -j$(nproc) zip gd intl soap ${EXTRA_EXTENSIONS} \
    && docker-php-ext-enable opcache.so imagick yaml \
    && docker-php-source delete \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer \
    && rm composer-setup.php \
    # Bring in gettext so we can get `envsubst`, then throw
    # the rest away. To do this, we need to install `gettext`
    # then move `envsubst` out of the way so `gettext` can
    # be deleted completely, then move `envsubst` back.
    && apk add --no-cache --virtual .gettext gettext \
    && mv /usr/bin/envsubst /tmp/ \
    && runDeps="$( \
        scanelf --needed --nobanner /tmp/envsubst \
            | awk '{ gsub(/,/, "\nso:", $2); print "so:" $2 }' \
            | sort -u \
            | xargs -r apk info --installed \
            | sort -u \
    )" \
    && apk add --no-cache $runDeps \
    && apk del .gettext \
    && mv /tmp/envsubst /usr/local/bin/ \
    # Remove alpine cache
    && rm -rf /var/cache/apk/* \
    # Remove default server definition
    && rm /etc/nginx/http.d/default.conf \
    # Make sure files/folders needed by the processes are accessable when they run under the nobody user
    && chown -R nobody.nobody /run \
    && chown -R nobody.nobody /var/lib/nginx \
    && chown -R nobody.nobody /var/log/nginx \
    && chmod -R 0755 /etc/service \
    && chmod 0755 /bin/docker-entrypoint.sh \
    # Install platform
    && composer install --no-suggest --no-progress \
    && chown -R nobody.nobody ${PLATFORM_HOME} \
    && chmod -R 0755 ${PLATFORM_HOME} \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0776 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0776 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var

# Switch to use a non-root user from here on
USER nobody

# Set volumes
VOLUME ["${PLATFORM_HOME}/plugin", "${PLATFORM_HOME}/public/resource", "${PLATFORM_HOME}/theme", "${PLATFORM_HOME}/var", "${PLATFORM_HOME}/public/uploads"]

# Set ports
EXPOSE 80/tcp 443/tcp

CMD ["/bin/docker-entrypoint.sh"]

ENV short_open_tag=On \
    client_max_body_size=32M \
    clear_env=no \
    allow_url_fopen=On \
    allow_url_include=Off \
    display_errors=Off \
    file_uploads=On \
    max_execution_time=0 \
    max_input_time=-1 \
    max_input_vars=1000 \
    memory_limit=128M \
    post_max_size=10M \
    upload_max_filesize=2M \
    zlib.output_compression=On

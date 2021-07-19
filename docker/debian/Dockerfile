FROM php:7.4-fpm
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
    && apt-get update \
    && apt-get install --no-install-recommends -y \
        wget \
        gnupg2 \
    && echo "deb-src http://nginx.org/packages/debian buster nginx" | tee /etc/apt/sources.list.d/nginx.list \
    && wget http://nginx.org/keys/nginx_signing.key && apt-key add nginx_signing.key && rm nginx_signing.key \
    && apt-get update \
    && apt-get install --no-install-recommends -y \
        nginx \
        git \
        unzip \
        supervisor \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        libpng-dev \
        libzip-dev \
        libonig-dev \
        libmagickwand-dev \
        libyaml-dev \
        zlib1g-dev \
        jpegoptim \
        optipng \
        pngquant \
        gifsicle \
        imagemagick \
    && nginx -V \
    && pecl install imagick yaml \
    && docker-php-ext-configure gd \
    && docker-php-ext-install -j$(nproc) gettext zip gd pdo_mysql soap \
    && docker-php-ext-enable opcache.so imagick yaml \
    && docker-php-source delete \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer \
    && rm composer-setup.php

# Copy configs
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/debian/nginx.conf /etc/nginx/nginx.conf
COPY docker/debian/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
COPY docker/debian/entrypoint.sh /entrypoint.sh

# Set homedir as work
WORKDIR ${PLATFORM_HOME}

# Install PHP libs
COPY composer.json ${PLATFORM_HOME}/composer.json
COPY composer.lock ${PLATFORM_HOME}/composer.lock
RUN composer install ${NO_DEV} --no-suggest --no-progress

# Copy platform
ADD bin ${PLATFORM_HOME}/bin
ADD config ${PLATFORM_HOME}/config
ADD plugin ${PLATFORM_HOME}/plugin
ADD locale ${PLATFORM_HOME}/locale
ADD public ${PLATFORM_HOME}/public
ADD src ${PLATFORM_HOME}/src
ADD theme ${PLATFORM_HOME}/theme
ADD var ${PLATFORM_HOME}/var

# Final step
RUN set -x \
    && chmod 755 /entrypoint.sh \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm /var/log/lastlog /var/log/faillog \
    && chmod -R 0755 ${PLATFORM_HOME}/locale \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0777 ${PLATFORM_HOME}/public/resource \
    && chmod -R 0777 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0777 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var

EXPOSE 80/tcp 443/tcp
VOLUME ["${PLATFORM_HOME}/locale", "${PLATFORM_HOME}/plugin", "${PLATFORM_HOME}/public/resource", "${PLATFORM_HOME}/theme", "${PLATFORM_HOME}/var", "${PLATFORM_HOME}/public/uploads"]

STOPSIGNAL SIGTERM

CMD ["/entrypoint.sh"]

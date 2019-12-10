FROM php:7.3-fpm
MAINTAINER Aleksey Ilyin <alksily@outlook.com>

ENV PLATFORM_HOME="/var/container"

# Install common packages
RUN set -x \
    && apt-get update -y \
    && apt-get install --no-install-recommends -y \
        gnupg2 \
        wget \
        git \
        unzip \
        supervisor

# Build custom Nginx with module push stream
RUN set -x \
    && echo "deb-src http://nginx.org/packages/debian buster nginx" | tee /etc/apt/sources.list.d/nginx.list \
    && wget http://nginx.org/keys/nginx_signing.key && apt-key add nginx_signing.key && rm nginx_signing.key \
    && cd /tmp \
    && apt-get update -y \
    && apt-get source nginx \
    && apt-get build-dep nginx --no-install-recommends -y \
    && git clone https://github.com/wandenberg/nginx-push-stream-module.git nginx-push-stream-module \
    && cd nginx-1* \
    && sed -i "s@--with-stream_ssl_module@--with-stream_ssl_module --add-module=/tmp/nginx-push-stream-module @g" debian/rules \
    && dpkg-buildpackage -uc -us -b \
    && cd .. \
    && dpkg -i nginx_*~buster_amd64.deb \
    && nginx -V \
    && apt-mark hold nginx \
    && rm -rf /tmp/nginx* \
    && chmod -R 777 /var/log/nginx /var/cache/nginx/ /run \
    && chmod 644 /etc/nginx/* \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

# Install ImageMagic and other modules
RUN set -x \
    && apt-get update -y \
    && apt-get install --no-install-recommends -y \
        libzip-dev \
        zlib1g-dev \
        jpegoptim \
        optipng \
        pngquant \
        gifsicle \
        libmagickwand-dev \
        imagemagick \
    && pecl install imagick \
    && docker-php-ext-install mbstring \
    && docker-php-ext-install zip \
    && docker-php-ext-install gd \
    && docker-php-ext-enable opcache.so \
    && docker-php-ext-enable imagick \
    && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --quiet --install-dir=/usr/bin --filename=composer \
    && rm composer-setup.php

# Install Redis
#RUN apt-get update \
#    && apt-get install -y --no-install-recommends \
#        redis-server

# Copy configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisor.conf /etc/supervisor/conf.d/supervisor.conf

# Copy start script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod 755 /entrypoint.sh

# Prepare for platform
RUN mkdir ${PLATFORM_HOME}

# Copy platform
ADD app ${PLATFORM_HOME}/app
ADD config ${PLATFORM_HOME}/config
ADD public ${PLATFORM_HOME}/public
ADD src ${PLATFORM_HOME}/src
ADD theme ${PLATFORM_HOME}/theme
ADD var ${PLATFORM_HOME}/var
COPY composer.json ${PLATFORM_HOME}/composer.json
COPY composer.lock ${PLATFORM_HOME}/composer.lock

# Install dependency
RUN cd ${PLATFORM_HOME} \
    && chmod -R 0777 ${PLATFORM_HOME}/public/resource \
    && chmod -R 0777 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0777 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var \
    && composer install --no-dev

# Finally cleanup
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    rm /var/log/lastlog /var/log/faillog

# Expose web
EXPOSE 80/tcp

# Define data volumes
VOLUME ["${PLATFORM_HOME}/public/resource", "${PLATFORM_HOME}/theme", "${PLATFORM_HOME}/var", "${PLATFORM_HOME}/public/uploads"]

WORKDIR ${PLATFORM_HOME}
STOPSIGNAL SIGTERM

CMD ["/entrypoint.sh"]

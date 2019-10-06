FROM php:7.2-fpm

###########################################################################
# Zip
###########################################################################
RUN apt-get update -yqq && apt-get install -y zlib1g-dev && docker-php-ext-install zip

###########################################################################
# Image optimizers:
###########################################################################
RUN apt-get install -y --force-yes jpegoptim optipng pngquant gifsicle

###########################################################################
# ImageMagick:
###########################################################################
RUN apt-get install -y libmagickwand-dev imagemagick && \
    pecl install imagick && \
    docker-php-ext-enable imagick

COPY config/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN docker-php-ext-enable opcache.so

############################################################################
## xDebug:
############################################################################
ARG XDEBUG_INSTALL=false
RUN if [ ${XDEBUG_INSTALL} = true ]; then \
    # Install the xdebug extension
    pecl install xdebug && \
    docker-php-ext-enable xdebug \
;fi
# Copy xdebug configuration for remote debugging
COPY config/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql
RUN apt-get install sqlite sqlite3
RUN docker-php-ext-install pdo_sqlite && docker-php-ext-enable pdo_sqlite

RUN mkdir /var/www/var
RUN mkdir /var/www/var/cache      && chown www-data:www-data /var/www/var/cache
RUN mkdir /var/www/var/log        && chown www-data:www-data /var/www/var/log
RUN mkdir /var/www/var/upload        && chown www-data:www-data /var/www/var/upload

###########################################################################
# Clean up
###########################################################################
RUN apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* && \
    rm /var/log/lastlog /var/log/faillog

# Permission fix
RUN usermod -u 1000 www-data

USER www-data

WORKDIR /var/container

FROM 0x12f/service
MAINTAINER Aleksey Ilyin <alksily@outlook.com>

# Set args
ARG BRANCH="master"
ARG COMMIT="latest"

# Set env vars
ENV COMMIT_BRANCH=${BRANCH}
ENV COMMIT_SHA=${COMMIT}

# Install PHP libs
COPY composer.json ${PLATFORM_HOME}/composer.json
COPY composer.lock ${PLATFORM_HOME}/composer.lock
RUN composer install --no-dev --no-suggest --no-progress

# Copy platform
ADD config ${PLATFORM_HOME}/config
ADD public ${PLATFORM_HOME}/public
ADD src ${PLATFORM_HOME}/src
ADD theme ${PLATFORM_HOME}/theme
ADD var ${PLATFORM_HOME}/var

# Final step
RUN set -x \
    && chmod -R 0777 ${PLATFORM_HOME}/plugin \
    && chmod -R 0777 ${PLATFORM_HOME}/public/resource \
    && chmod -R 0777 ${PLATFORM_HOME}/public/uploads \
    && chmod -R 0777 ${PLATFORM_HOME}/theme \
    && chmod -R 0777 ${PLATFORM_HOME}/var

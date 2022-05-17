# @description php image base on the debian 9.x
#
#                       Some Information
# ------------------------------------------------------------------------------------
# @link https://hub.docker.com/_/debian/      alpine image
# @link https://hub.docker.com/_/php/         php image
# @link https://github.com/docker-library/php php dockerfiles
# @see https://github.com/docker-library/php/tree/master/7.2/stretch/cli/Dockerfile
# ------------------------------------------------------------------------------------
#
FROM php:7.2

LABEL maintainer="wayne<waynechen@hinabian.com>" version="2.0"

ARG timezone
ARG service_port
ARG service_port1
ARG service_port2


ENV PHPREDIS_VERSION=4.3.0 \
    MSGPACK_VERSION=2.0.3 \
    SWOOLE_VERSION=4.3.5 \
    SWOOLE_TRACHER_INI=/usr/local/etc/php/conf.d/swoole-tracker.ini

COPY docker-file /docker-file

# Libs -y --no-install-recommends
RUN mv /etc/apt/sources.list /etc/apt/sources.list.bak \
    && mv /docker-file/sources.list /etc/apt/ \
    && apt-get update && apt-get install -y \
#        curl wget git zip unzip \
        less vim openssl iproute2 iputils-ping net-tools procps \
        libz-dev libssl-dev libnghttp2-dev libpcre3-dev libjpeg-dev libpng-dev libfreetype6-dev \
#
# Install PHP extensions
    && docker-php-ext-install \
#    mbstring \
       bcmath gd pdo_mysql sockets zip sysvmsg sysvsem sysvshm \
#
# Install redis extension
#    && wget http://pecl.php.net/get/redis-${PHPREDIS_VERSION}.tgz -O /tmp/redis.tar.tgz \
#    && pecl install /tmp/redis.tar.tgz \
#    && rm -rf /tmp/redis.tar.tgz \
#
    && pecl install /docker-file/redis-${PHPREDIS_VERSION}.tgz \
#
    && docker-php-ext-enable redis \
#
# Install msgpack extension
#    && wget http://pecl.php.net/get/msgpack-${MSGPACK_VERSION}.tgz -O /tmp/msgpack.tar.tgz \
#    && pecl install /tmp/msgpack.tar.tgz \
#    && rm -rf /tmp/msgpack.tar.tgz \
#
    && pecl install /docker-file/msgpack-${MSGPACK_VERSION}.tgz \
#
    && docker-php-ext-enable msgpack \
#
# Install swoole extension
#    && wget https://github.com/swoole/swoole-src/archive/v${SWOOLE_VERSION}.tar.gz -O swoole.tar.gz \
#    && mkdir -p swoole \
#    && tar -xf swoole.tar.gz -C swoole --strip-components=1 \
#    && rm swoole.tar.gz \
#    && ( \
#        cd swoole \
#        && phpize \
#        && ./configure --enable-mysqlnd --enable-sockets --enable-openssl --enable-http2 \
#        && make -j$(nproc) \
#        && make install \
#    ) \
#    && rm -r swoole \
#
#    && pecl install /docker-file/swoole-${SWOOLE_VERSION}.tgz \
#
    && mkdir -p swoole \
    && tar -xf /docker-file/swoole-${SWOOLE_VERSION}.tgz -C swoole --strip-components=1 \
    && ( \
        cd swoole \
        && phpize \
        && ./configure --enable-mysqlnd --enable-sockets --enable-openssl --enable-http2 \
        && make -j$(nproc) \
        && make install \
    ) \
    && rm -r swoole \
#
    && docker-php-ext-enable swoole \
#
# Install swoole-tracker extension
#    && mkdir -p /usr/local/php/swoole-tracker \
#    && tar -xf /docker-file/swoole-tracker.tar.gz -C /usr/local/php/swoole-tracker --strip-components=1 \
#        && ( \
#            cd /usr/local/php/swoole-tracker \
#            && ./deploy_env.sh www.swoole-cloud.com \
#            && php_dir=$(php -r "echo @ini_get("extension_dir").PHP_EOL;") \
#            && cp ./swoole_tracker72.so $php_dir/swoole_tracker.so \
#            # Enable swoole_tracker
#            && echo "extension=swoole_tracker.so" > ${SWOOLE_TRACHER_INI} \
#            # Open the main switch
#            && echo "apm.enable=1" >> ${SWOOLE_TRACHER_INI} \
#            # Sampling Rate, eg: 10%
#            && echo "apm.sampling_rate=10" >> ${SWOOLE_TRACHER_INI} \
#            # Turn on memory leak detection Default 0 Off
#            && echo "apm.enable_memcheck=1" >> ${SWOOLE_TRACHER_INI} \
#        ) \
#
#entrypoint
    && cp /docker-file/entrypoint.sh /opt/entrypoint.sh \
    && chmod 777 /opt/entrypoint.sh \
#
    && rm -rf /docker-file \
#
# Clear dev deps
    && apt-get clean \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
#
# Timezone
    && cp /usr/share/zoneinfo/${timezone} /etc/localtime \
    && echo "${timezone}" > /etc/timezone \
    && echo "[Date]\ndate.timezone=${timezone}" > /usr/local/etc/php/conf.d/timezone.ini

#ENTRYPOINT [ "sh", "-x", "/opt/swoole/script/php/swoole_php /opt/swoole/node-agent/src/node.php &" ]

EXPOSE $service_port $service_port1 $service_port2

CMD ["sh", "/opt/entrypoint.sh"]
#CMD exec php /var/www/one-app/App/swoole.php >> /var/www/one-app/console.log
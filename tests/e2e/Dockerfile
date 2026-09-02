FROM composer:2 AS composer

FROM php:8.3-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client fping libfreetype6-dev libgmp-dev libicu-dev libjpeg-dev libldap2-dev libonig-dev libpng-dev libsnmp-dev libxml2-dev rrdtool snmp snmpd \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd gmp intl ldap mbstring mysqli pcntl pdo_mysql snmp sockets xml \
    && rm -rf /var/lib/apt/lists/*

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && { echo 'date.timezone = UTC'; echo 'memory_limit = 512M'; echo 'max_execution_time = 60'; } > /usr/local/etc/php/conf.d/mactrack-e2e.ini

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

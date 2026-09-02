FROM composer:2 AS composer

FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS git unzip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist

COPY . .

CMD ["sh", "-ec", "php vendor/bin/pest && php vendor/bin/pest --configuration=phpunit.runtime.xml --coverage --min=100 && composer validate --strict"]

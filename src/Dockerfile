FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    linux-headers \
    oniguruma-dev \
    libzip-dev \
    unzip \
    curl \
    autoconf \
    g++ \
    make \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]

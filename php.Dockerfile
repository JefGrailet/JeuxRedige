FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
 libfreetype6-dev \
 libjpeg62-turbo-dev \
 libpng-dev \
 libwebp-dev \
 msmtp

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN { \
    echo 'opcache.enable=0'; \
    echo 'opcache.enable_cli=0'; \
    } > /usr/local/etc/php/conf.d/zzz-opcache.ini

RUN a2enmod rewrite

RUN service apache2 restart

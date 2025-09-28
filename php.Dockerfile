FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN { \
    echo 'opcache.enable=0'; \
    echo 'opcache.enable_cli=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

RUN a2enmod rewrite

RUN service apache2 restart

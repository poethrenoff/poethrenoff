FROM php:7.2-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev

RUN docker-php-ext-install \
    gd \
    gettext \
    exif \
    intl \
    opcache \
    pdo_mysql

RUN echo "date.timezone = Europe/Moscow" >> /usr/local/etc/php/php.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 128M" >> /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize = 128M" >> /usr/local/etc/php/php.ini
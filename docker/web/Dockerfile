FROM php:8.1-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y \
    git \
    unzip \
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

RUN curl -sS https://getcomposer.org/installer | \
    php -- --install-dir=/usr/local/bin --filename=composer --version=2.6.6 \

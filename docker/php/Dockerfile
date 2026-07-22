FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql opcache intl zip gd

RUN echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini
RUN echo "memory_limit = 2G" >> /usr/local/etc/php/php.ini
RUN echo "post_max_size = 256M" >> /usr/local/etc/php/php.ini
RUN echo "upload_max_filesize = 256M" >> /usr/local/etc/php/php.ini
RUN echo "date.timezone = Europe/Moscow" >> /usr/local/etc/php/php.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

RUN chown -R www-data:www-data /var/www/symfony

EXPOSE 9000

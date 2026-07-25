FROM php:8.1-apache

RUN apt-get update && apt-get install -y default-mysql-client && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/uploads && chmod -R 775 /var/www/html/uploads

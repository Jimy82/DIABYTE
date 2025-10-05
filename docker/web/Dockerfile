FROM php:8.2-apache

# Extensiones necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Opcional: mod_rewrite si lo vas a usar
RUN a2enmod rewrite

# Por claridad (ya mapeas /var/www/html desde compose)
ENV APACHE_DOCUMENT_ROOT=/var/www/html

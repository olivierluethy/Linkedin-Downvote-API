# Dockerfile
FROM php:8.2-apache

# Installiere PDO MySQL
RUN docker-php-ext-install pdo_mysql

# Optional: mysqli (falls du mal brauchst)
# RUN docker-php-ext-install mysqli

# Apache: mod_rewrite aktivieren (falls du URLs umschreibst)
RUN a2enmod rewrite

# Kopiere php.ini (für Debug)
COPY php.ini /usr/local/etc/php/
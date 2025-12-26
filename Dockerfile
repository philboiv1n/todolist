# Dockerfile
FROM php:8.4-apache

WORKDIR /app

RUN docker-php-ext-enable opcache

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-todo-opcache.ini

COPY . .
RUN rm -rf /var/www/html && ln -s /app/public_html/todo /var/www/html

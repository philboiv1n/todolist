# Dockerfile
FROM php:8.4-apache
WORKDIR /app
COPY . .
RUN rm -rf /var/www/html && ln -s /app/public_html/todo /var/www/html

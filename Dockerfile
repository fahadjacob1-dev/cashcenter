FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite \
    && sed -i 's/Listen 80/Listen ${PORT:-80}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT:-80}>/' /etc/apache2/sites-enabled/000-default.conf
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
CMD ["/bin/bash", "-c", "sed -i \"s/Listen 80/Listen $PORT/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:$PORT>/\" /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"]

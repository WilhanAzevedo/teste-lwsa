FROM php:8.2.7-apache

# Instala dependências e Nginx
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libssl-dev \
    zip \
    unzip \
    git \
    supervisor \
    libpq-dev \
    && docker-php-ext-install zip pdo pdo_pgsql pcntl


RUN pecl install redis && docker-php-ext-enable redis


COPY . /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

RUN chmod -R 755 /var/www/html
RUN chmod -R 775 /var/www/html/storage 
RUN chown -R www-data:www-data /var/www/html/storage
RUN chmod -R 775 /var/www/html/bootstrap/cache

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN a2enmod rewrite

COPY infra/docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

# Copiando configs do apache
COPY infra/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY infra/supervisor/queue.conf /etc/supervisor/conf.d/queue.conf

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Comando para inicializar o servidor Apache
CMD ["apache2-foreground"]




FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev mariadb-client \
    && docker-php-ext-install pdo_mysql mbstring opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers expires

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
COPY . .
COPY docker/php.ini /usr/local/etc/php/conf.d/onesalez.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN mkdir -p logs && chown -R www-data:www-data logs

EXPOSE 80
CMD ["apache2-foreground"]

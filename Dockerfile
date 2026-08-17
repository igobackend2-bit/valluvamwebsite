FROM composer:2 AS composer_stage
WORKDIR /app
COPY composer.json ./
RUN composer install --ignore-platform-reqs --no-dev --no-interaction

FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /app
COPY --from=composer_stage /app/vendor /app/vendor

RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/app
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

WORKDIR /app
EXPOSE 80

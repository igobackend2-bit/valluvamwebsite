FROM composer:2 AS composer_stage
WORKDIR /app
COPY composer.json ./
RUN composer install --ignore-platform-reqs --no-dev --no-interaction

FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /app
COPY --from=composer_stage /app/vendor /app/vendor

RUN mkdir -p /app/assets/b2b_enquiries \
    && chown -R www-data:www-data /app \
    && chmod -R 775 /app/assets/b2b_enquiries

RUN a2enmod rewrite

# Without this, Apache silently ignores everything in .htaccess (the upload
# size limits at the top of the file, and the clean-URL rewrite rules) - this
# is the actual root cause of both the "Failed to upload image" errors and
# the URLs still showing .php.
RUN printf '<Directory /app>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/zzz-allow-override.conf \
    && a2enconf zzz-allow-override

ENV APACHE_DOCUMENT_ROOT=/app
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

WORKDIR /app
EXPOSE 80

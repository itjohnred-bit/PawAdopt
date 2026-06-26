FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
        libzip-dev \
        zip unzip git \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
RUN a2enmod rewrite headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./


RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader

COPY . .

RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html/public \
 && mkdir -p /var/www/html/uploads \
 && chown -R www-data:www-data /var/www/html/uploads

RUN ln -sfn /var/www/html/assets /var/www/html/public/assets \
 && ln -sfn /var/www/html/uploads /var/www/html/public/uploads

RUN if [ -d "/etc/secrets" ]; then \
        chown -R www-data:www-data /etc/secrets && chmod -R 644 /etc/secrets; \
    fi

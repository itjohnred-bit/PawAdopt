FROM php:8.2-apache

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libzip-dev unzip git \
 && docker-php-ext-install -j$(nproc) \
        pdo pdo_mysql zip bcmath gd intl opcache \
 && rm -rf /var/lib/apt/lists/*

COPY ./public/                   /var/www/html/
COPY ./render/000-default.conf   /etc/apache2/sites-available/000-default.conf
COPY ./render/php.ini            /usr/local/etc/php/conf.d/zz-pawadopt.ini

RUN a2enmod rewrite headers expires \
 && a2dissite 000-default \
 && a2ensite 000-default \
 && chown -R www-data:www-data /var/www/html

RUN mkdir -p /opt/render/project/storage/uploads/avatars \
             /opt/render/project/storage/uploads/pets \
             /opt/render/project/storage/uploads/certificates \
             /opt/render/project/storage/uploads/logos \
             /opt/render/project/storage/logs \
             /opt/render/project/storage/sessions \
 && chown -R www-data:www-data /opt/render/project/storage \
 && find /opt/render/project/storage -type d -exec chmod 0750 {} \; \
 && find /opt/render/project/storage -type f -exec chmod 0640 {} \;

RUN ln -sfn /opt/render/project/storage/uploads /var/www/html/uploads \
 && chown -h www-data:www-data /var/www/html/uploads

EXPOSE 80

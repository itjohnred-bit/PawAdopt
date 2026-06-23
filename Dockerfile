FROM php:8.2-apache

# Install necessary extensions
RUN docker-php-ext-install pdo pdo_mysql

# 1. Change the Apache DocumentRoot to the 'public' folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
# Allow .htaccess to work and override Apache settings
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
# 2. Copy your project files
COPY . /var/www/html/

# 3. Enable mod_rewrite for your .htaccess
RUN a2enmod rewrite

# 4. Set permissions
RUN chown -R www-data:www-data /var/www/html/public
COPY api /var/www/html/api
RUN ln -s /var/www/html/assets /var/www/html/public/assets
RUN ln -s /var/www/html/uploads /var/www/html/public/uploads
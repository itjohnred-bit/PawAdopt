FROM php:8.2-apache

# Install necessary extensions
RUN docker-php-ext-install pdo pdo_mysql

# 1. Change the Apache DocumentRoot to the 'public' folder
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 2. Copy your project files
COPY . /var/www/html/

# 3. Enable mod_rewrite for your .htaccess
RUN a2enmod rewrite

# 4. FIX: Grant web server permissions to secrets and public folder
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/public && \
    if [ -d "/etc/secrets" ]; then chown -R www-data:www-data /etc/secrets && chmod -R 644 /etc/secrets; fi

# 5. Fix symlinks (use -f to overwrite if they exist)
RUN ln -sf /var/www/html/assets /var/www/html/public/assets && \
    ln -sf /var/www/html/uploads /var/www/html/public/uploads
FROM php:8.2-apache

# 1. Install dependencies
RUN docker-php-ext-install pdo pdo_mysql

# 2. Configure Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 3. Enable rewrite module
RUN a2enmod rewrite

# 4. Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 5. Copy project files and install dependencies
WORKDIR /var/www/html
COPY composer.json ./
# Install dependencies BEFORE copying the rest of the code to cache layers
RUN composer install --no-dev --optimize-autoloader

COPY . .

# 6. Set Permissions
# Ensure www-data owns the entire directory including the new vendor folder
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/public

# 7. Symlinks
RUN ln -sf /var/www/html/assets /var/www/html/public/assets && \
    ln -sf /var/www/html/uploads /var/www/html/public/uploads

# 8. Handle Secrets
RUN if [ -d "/etc/secrets" ]; then \
    chown -R www-data:www-data /etc/secrets && \
    chmod -R 644 /etc/secrets; \
    fi
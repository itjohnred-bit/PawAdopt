# 1. Use an official PHP image with Apache
FROM php:8.2-apache

# 2. Install the MySQL driver
RUN docker-php-ext-install pdo pdo_mysql

# 2.5. Enable Apache mod_rewrite
RUN a2enmod rewrite

# 3. Copy your local files into the container's web directory
COPY . /var/www/html/

# 4. Create production upload directory and grant Apache write access
RUN mkdir -p /opt/render/project/uploads/pets \
    && chown -R www-data:www-data /var/www/html/ \
    && chown -R www-data:www-data /opt/render/project/uploads/ \
    && chmod -R 775 /opt/render/project/uploads/

# 5. Create a symbolic link so Apache can serve files from the external directory
RUN ln -s /opt/render/project/uploads /var/www/html/uploads \
    && chown -h www-data:www-data /var/www/html/uploads
# 1. Use an official PHP image with Apache
FROM php:8.2-apache

# 2. Install the MySQL driver (this fixes your 'could not find driver' error)
RUN docker-php-ext-install pdo pdo_mysql

# 3. Copy your local files into the container's web directory
COPY . /var/www/html/

# 4. (Optional) Set permissions if needed
RUN chown -R www-data:www-data /var/www/html/


# Use an official PHP image with Apache
FROM php:8.2-apache

# Install mysqli extension (required for most PHP database apps)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy your project files to the container's web directory
COPY . /var/www/html/

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html
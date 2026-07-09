# Use official PHP 8.2 image with Apache pre-installed
FROM php:8.2-apache

# Install build dependencies and PHP MongoDB extension
RUN apt-get update && apt-get install -y \
        libssl-dev \
        unzip \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# Enable Apache mod_rewrite (required for custom routing and clean URLs)
RUN a2enmod rewrite

# Copy all project files to the web server root directory
COPY . /var/www/html/

# Set correct ownership and permissions for web server access (needed for file uploads)
RUN chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/

# Expose default HTTP port
EXPOSE 80

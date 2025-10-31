# Use the official PHP image with Apache
FROM php:8.2-apache

# Enable mod_rewrite (needed by many PHP frameworks)
RUN a2enmod rewrite

# Copy all project files into the container
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port 80
EXPOSE 80

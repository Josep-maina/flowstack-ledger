# Use official PHP image with Apache
FROM php:8.2-apache

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"]

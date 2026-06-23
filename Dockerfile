FROM php:8.1-apache

# Enable Apache mod_rewrite (required by Slim)
RUN a2enmod rewrite

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better layer caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (no memory limit, no interaction)
RUN php -d memory_limit=-1 /usr/bin/composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts

# Copy the rest of the app
COPY . .

# Point Apache document root to public/
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Allow .htaccess overrides (needed for Slim routing)
RUN echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

EXPOSE 80

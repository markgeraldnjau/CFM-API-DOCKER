## Dockerfile for cfm_api
#
#FROM php:8.2-fpm
#
#RUN docker-php-ext-configure exif
#RUN docker-php-ext-install exif
#
#RUN apt-get update -y && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libzip-dev openssl zip unzip supervisor git -o Debug::pkgProblemResolver=yes
#
#RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
#    && docker-php-ext-install gd pdo pdo_mysql zip
#
#RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
#
#RUN apt-get update
#
#WORKDIR /var/www/html
#
#COPY --chown=www-data:www-data . .
#
#RUN chown -R www-data:www-data /var/www/html/storage/logs
#RUN chown -R www-data:www-data /var/www/html/storage/app
#
#ENV COMPOSER_ALLOW_SUPERUSER=1
#RUN composer update
#RUN composer install
#
#COPY package*.json ./
#
#RUN php artisan storage:link
#
#RUN if [ ! -f storage/oauth-private.key ]; then \
#        php artisan passport:keys; \
#    fi
#
#
#RUN chown -R www-data:www-data /var/www/html/storage/oauth-private.key
#RUN chown -R www-data:www-data /var/www/html/storage/oauth-public.key
#
#COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
#
#CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
#
## Command to run your app in development mode
## Install npm dependencies
## RUN npm install
## Run npm build to build your project (if needed)
##RUN npm run build
## --optimize-autoloader --no-dev
#RUN chown -R www-data:www-data /var/www/html/vendor/
#
#
#
#CMD  ["php-fpm", "-F"]



# Dockerfile for cfm_api

FROM php:8.2-fpm

# Install dependencies and required PHP extensions
RUN apt-get update -y && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libzip-dev \
    openssl \
    zip \
    unzip \
    supervisor \
    git \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    pkg-config \
    zlib1g-dev \
    redis \
    -o Debug::pkgProblemResolver=yes

# Configure and install PHP extensions
RUN docker-php-ext-configure exif
RUN docker-php-ext-install exif gd pdo pdo_mysql zip

# Install Redis PHP extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY --chown=www-data:www-data . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage/logs
RUN chown -R www-data:www-data /var/www/html/storage/app

# Install Composer dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer update
RUN composer install

# Copy package.json and run npm install if necessary
COPY package*.json ./

# Run Laravel-specific commands
RUN php artisan storage:link

# Generate OAuth keys if not present
RUN if [ ! -f storage/oauth-private.key ]; then \
        php artisan passport:keys; \
    fi

# Set permissions for OAuth keys
RUN chown -R www-data:www-data /var/www/html/storage/oauth-private.key
RUN chown -R www-data:www-data /var/www/html/storage/oauth-public.key

# Copy Supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose the Redis port (optional if you need to access Redis externally)
EXPOSE 6379

# Command to run Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Command to run your app in development mode
# Install npm dependencies if necessary
# RUN npm install

# Run npm build to build your project if necessary
# RUN npm run build --optimize-autoloader --no-dev

# Set ownership of the vendor directory
RUN chown -R www-data:www-data /var/www/html/vendor/

# Default command
CMD ["php-fpm", "-F"]


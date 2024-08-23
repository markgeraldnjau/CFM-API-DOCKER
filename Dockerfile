# Dockerfile for cfm_api

FROM php:8.2-fpm

RUN docker-php-ext-configure exif
RUN docker-php-ext-install exif

RUN apt-get update -y && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libzip-dev openssl zip unzip supervisor git -o Debug::pkgProblemResolver=yes

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN apt-get update

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .

RUN chown -R www-data:www-data /var/www/html/storage/logs
RUN chown -R www-data:www-data /var/www/html/storage/app

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer update
RUN composer install

COPY package*.json ./

RUN php artisan storage:link

RUN if [ ! -f storage/oauth-private.key ]; then \
        php artisan passport:keys; \
    fi

RUN chown -R www-data:www-data /var/www/html/storage/oauth-private.key
RUN chown -R www-data:www-data /var/www/html/storage/oauth-public.key

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Command to run your app in development mode
# Install npm dependencies
# RUN npm install
# Run npm build to build your project (if needed)
#RUN npm run build
# --optimize-autoloader --no-dev
RUN chown -R www-data:www-data /var/www/html/vendor/



CMD  ["php-fpm", "-F"]

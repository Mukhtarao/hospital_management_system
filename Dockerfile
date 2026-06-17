FROM php:8.2-cli

RUN apt-get update && apt-get install -y unzip git

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install mysqli pdo pdo_mysql

WORKDIR /app

COPY . /app

RUN composer install --no-dev --optimize-autoloader

CMD sh -c "php -S 0.0.0.0:${PORT:-8080} -t /app"
FROM php:8.2-cli

RUN apt-get update && apt-get install -y unzip libzip-dev && \
    docker-php-ext-install mysqli pdo pdo_mysql zip

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "/var/www/html", "index.php"]

FROM php:8.2-apache
RUN docker-php-ext-install mysqli
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip

# Copy application source
COPY . .
RUN php composer.phar install

WORKDIR /var/www/html
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public/"]
EXPOSE 8080

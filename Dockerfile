FROM php:8.2-apache

# Install common packages and extensions
RUN apt-get update && apt-get install -y \
    zlib1g-dev \
    libzip-dev \
    unzip \
    git \
    curl && rm -rf /var/lib/apt/lists/*

# Enable mysqli and PDO MySQL (if using MySQL/MariaDB)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application source and install PHP dependencies
WORKDIR /var/www/html
COPY . /var/www/html
RUN composer install --no-dev --prefer-dist --no-interaction || true

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public/"]
EXPOSE 8080

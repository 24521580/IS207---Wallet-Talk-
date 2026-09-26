FROM php:8.3-cli-alpine

# Install system dependencies, PHP extensions, and Node.js
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    linux-headers \
    mysql-client \
    curl-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath curl

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy application files
COPY . .

# Install PHP and Node dependencies, build Vite assets
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci || npm install \
    && npm run build \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 8080

CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --class=CategorySeeder --force && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]

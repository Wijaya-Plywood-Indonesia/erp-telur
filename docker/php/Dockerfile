FROM php:8.3-fpm

# Install dependencies sistem & ekstensi PHP yang dibutuhkan Laravel & Filament
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    gnupg

# Install Node.js (Versi LTS terbaru via NodeSource) & npm
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get install -y nodejs

# Clear cache apt untuk memperkecil ukuran image
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install ekstensi PHP (termasuk intl dan zip)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd intl zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# --- Layer caching npm ---
# Sama seperti composer, copy package.json & lock file dulu
COPY package.json package-lock.json ./
RUN npm ci

# Baru copy seluruh source code aplikasi
COPY . .

# Build asset frontend (Vite/Laravel Mix)
RUN npm run build

# Jalankan composer scripts (misalnya package:discover) setelah semua file ada
RUN composer dump-autoload --optimize

# [TAMBAHAN] Beri hak tulis ke www-data (user PHP-FPM) untuk log, cache, & session
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
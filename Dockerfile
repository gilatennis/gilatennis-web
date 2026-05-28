FROM dunglas/frankenphp:php8.4 AS base

RUN install-php-extensions \
    pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && apt-get update && apt-get install -y supervisor curl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# --- Build stage (PHP + Node so Wayfinder plugin can run) ---
FROM base AS build

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY package*.json ./
RUN npm ci

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && npm run build

# --- Production stage ---
FROM build AS production

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80 443

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

FROM php:8.3-fpm

COPY ./docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Install dependencies
RUN apt-get update && apt-get install -y \
    nano nginx git unzip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    libjpeg62-turbo-dev libfreetype6-dev libicu-dev supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd pcntl sockets intl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Runtime .env is injected by the host (e.g. Coolify). This copy is only for build-time artisan/vite.
# PHP 8.3 image (Reverb + Laravel 12). Lock file targets 8.2–8.3 (see composer.json platform).
# Production Reverb/CORS: .env.production.example. Broadcast loopback if needed:
# REVERB_BROADCAST_HOST=127.0.0.1 REVERB_BROADCAST_PORT=8089 REVERB_BROADCAST_SCHEME=http
RUN cp .env.example .env || touch .env \
    && mkdir -p storage/framework/{views,sessions,cache} storage/logs bootstrap/cache \
    && composer install --no-dev --optimize-autoloader \
    && php artisan package:discover --ansi \
    && npm ci --ignore-scripts \
    && npm run build \
    && php artisan storage:link --force --ansi \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# supervisorctl defaults to /etc/supervisord.conf; supervisord is started with -c conf.d/...
RUN ln -sf /etc/supervisor/conf.d/supervisord.conf /etc/supervisord.conf
COPY ./docker/ensure-passport.sh /usr/local/bin/ensure-passport.sh
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY ./docker/start-reverb.sh /usr/local/bin/start-reverb.sh
RUN chmod +x /usr/local/bin/ensure-passport.sh /usr/local/bin/entrypoint.sh /usr/local/bin/start-reverb.sh

# Public: 80 (nginx + WebSocket /app). Internal Reverb: 8089 (not published on Coolify).
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

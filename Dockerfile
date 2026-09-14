FROM php:8.3-fpm

# Coolify often injects NODE_ENV=production at build time; that would skip Vite
# (listed under package.json "devDependencies") and break `npm run build`.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    NODE_ENV=development

COPY ./docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Install dependencies
RUN apt-get update && apt-get install -y \
    nano nginx git unzip curl libpng-dev libonig-dev libxml2-dev libzip-dev \
    libjpeg62-turbo-dev libfreetype6-dev libicu-dev supervisor \
    jpegoptim optipng gifsicle webp \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd pcntl sockets intl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g svgo \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Runtime .env is injected by Coolify at boot (see docker/entrypoint.sh).
# PHP 8.3 image — API publisher only; WebSockets run in olabisiolai_websocket (ws.gidira.tech).
# Production env template: docs/env.coolify.example
# Split layers so Coolify logs show which step failed (composer vs npm vs link).
RUN cp .env.example .env || touch .env \
    && mkdir -p \
    storage/framework/views \
    storage/framework/sessions \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache \
    && composer install --no-dev --optimize-autoloader --no-interaction \
    && php artisan package:discover --ansi \
    && (php artisan key:generate --force --no-interaction || true)

# Vite lives in devDependencies — force include even if build env is production.
RUN npm ci --ignore-scripts --include=dev \
    && npm run build \
    && rm -rf node_modules

RUN php artisan storage:link --force --ansi \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

ENV NODE_ENV=production

COPY ./docker/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
# supervisorctl defaults to /etc/supervisord.conf; supervisord is started with -c conf.d/...
RUN ln -sf /etc/supervisor/conf.d/supervisord.conf /etc/supervisord.conf
COPY ./docker/ensure-passport.sh /usr/local/bin/ensure-passport.sh
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/ensure-passport.sh /usr/local/bin/entrypoint.sh

# Public: 80 (nginx + Laravel). WebSockets: separate olabisiolai_websocket service.
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

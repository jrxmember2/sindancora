FROM php:8.4-fpm-alpine AS base

# Extensões PHP necessárias
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    shadow \
    nginx \
    supervisor \
    nodejs \
    npm \
    && docker-php-ext-install \
        pdo_pgsql \
        mbstring \
        bcmath \
        zip \
        pcntl \
        posix \
        intl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---- Build stage: assets ----
FROM base AS builder

COPY package*.json ./
RUN npm ci --legacy-peer-deps

COPY . .
RUN npm run build

# ---- Production stage ----
FROM base AS production

WORKDIR /var/www/html

# Copiar arquivos da aplicação
COPY --from=builder /var/www/html /var/www/html

# Instalar dependências PHP (sem dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-ansi

# Permissões
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/sindancora.ini

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

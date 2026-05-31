FROM php:8.4-fpm-alpine AS base

# Extensões PHP e dependências do sistema
# $PHPIZE_DEPS é necessário para instalar extensões via PECL, como redis.
RUN apk add --no-cache \
    $PHPIZE_DEPS \
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
    && docker-php-ext-install gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear ~/.pearrc

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

# Diretórios usados em runtime por supervisor/nginx/php
RUN mkdir -p \
    /var/log/supervisor \
    /var/log/nginx \
    /run/nginx \
    /var/lib/nginx/tmp \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Copiar arquivos da aplicação
COPY --from=builder /var/www/html /var/www/html

# Instalar dependências PHP sem pacotes de desenvolvimento
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

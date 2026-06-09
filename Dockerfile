FROM php:8.4-fpm-alpine

# Dependências do sistema e extensões PHP
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
    bash \
    nginx \
    supervisor \
    wget \
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

# Diretórios de runtime (git não rastreia diretórios vazios)
RUN mkdir -p \
    /var/log/supervisor \
    /var/log/nginx \
    /var/log/php-fpm \
    /run/nginx \
    /var/lib/nginx/tmp \
    /var/www/html/storage/logs \
    /var/www/html/storage/app/private \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/testing \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache

# Copiar código da aplicação (inclui public/build/ já buildado)
COPY . .

# Instalar dependências PHP de produção
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-ansi

# Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data \
        /var/log/php-fpm \
        /var/log/supervisor \
        /var/log/nginx \
        /run/nginx \
        /var/lib/nginx

# Nginx rodando como www-data
RUN sed -i 's/user nginx;/user www-data;/g' /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/sindancora.ini

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Health check — aguarda 40s antes da primeira verificação
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD wget -qO- http://localhost/up || exit 1

EXPOSE 80

STOPSIGNAL SIGTERM

ENTRYPOINT ["/entrypoint.sh"]

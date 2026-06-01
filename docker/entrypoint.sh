#!/bin/sh
set -e

echo "==> SindÂncora — iniciando container..."

cd /var/www/html

# APP_KEY é obrigatória em produção
if [ -z "$APP_KEY" ]; then
    echo "ERRO: variável APP_KEY não definida."
    echo "Gere com: php artisan key:generate --show"
    echo "E configure nas variáveis de ambiente do EasyPanel."
    exit 1
fi

echo "==> Cacheando configurações..."
php artisan config:cache
php artisan route:cache
# view:cache não é necessário em apps Inertia (único template é app.blade.php)
mkdir -p resources/views
php artisan view:cache || true

echo "==> Rodando migrations..."
php artisan migrate --force

echo "==> Rodando seeds (idempotente — usa updateOrCreate)..."
php artisan db:seed --force

echo "==> Gerando documentação da API..."
php artisan l5-swagger:generate || true

echo "==> Iniciando serviços (nginx + php-fpm + queue + scheduler)..."
exec /usr/bin/supervisord -c /etc/supervisord.conf

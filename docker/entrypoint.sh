#!/bin/sh
set -e

# Configuração da Porta dinâmica (padrão Render ou 8000)
PORT="${PORT:-8000}"
sed -i "s/PORT_PLACEHOLDER/${PORT}/g" /etc/nginx/http.d/default.conf

# Criação e permissão do banco SQLite caso não exista
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi

# Ajuste de permissões para o www-data
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Criação do link simbólico de storage se necessário
php artisan storage:link --no-interaction || true

# Execução das migrations
echo "==> Executando migrations..."
php artisan migrate --force --no-interaction

# Otimizações de cache para produção (se APP_KEY existir)
if [ -n "$APP_KEY" ]; then
    echo "==> Otimizando caches do Laravel..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "==> Iniciando Nginx e PHP-FPM na porta ${PORT}..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

# ==============================================================================
# Multi-stage Build para Laravel + Vue (Inertia + Vite) no Render
# ==============================================================================

# --- Etapa 1: Frontend (Compilação dos Assets Vite) ---
FROM node:20-alpine AS frontend

WORKDIR /app

# Cache das dependências do Node
COPY package.json package-lock.json ./
RUN npm ci --no-audit --prefer-offline

# Cópia do código-fonte para compilação do bundle Vue/Tailwind
COPY . .
RUN npm run build

# --- Etapa 2: Backend & Produção (PHP 8.3 FPM + Nginx + Alpine) ---
FROM php:8.3-fpm-alpine AS app

# Instalação de utilitários do sistema, Nginx, PostgreSQL client e Supervisor
RUN apk update && apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    sqlite \
    sqlite-dev \
    postgresql-client \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev

# Instalação de extensões PHP essenciais (incluindo pdo_pgsql para PostgreSQL do Render)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    pdo_sqlite \
    sqlite3 \
    pdo_mysql \
    bcmath \
    mbstring \
    xml \
    zip \
    pcntl \
    intl \
    opcache \
    curl

# Cópia do binário do Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Configurações de Nginx, PHP e Supervisor
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/php.ini $PHP_INI_DIR/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Cache de dependências do Composer (instalação rápida sem dev)
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Cópia do código da aplicação (filtrado pelo .dockerignore)
COPY . /var/www/html

# Cópia dos assets compilados na Etapa 1
COPY --from=frontend /app/public/build /var/www/html/public/build

# Finalização do autoload do Composer otimizado para produção
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Criação de pastas necessárias e configuração de permissões
RUN mkdir -p \
    /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache \
    /var/www/html/database && \
    touch /var/www/html/database/database.sqlite && \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Image de production pour la boutique (Symfony 7.1, PHP 8.3 + Apache, base PostgreSQL).
# Utilisable sur Render, Koyeb, Fly.io, un VPS, etc.
FROM php:8.3-apache

# Extensions PHP nécessaires (intl, accès PostgreSQL) + modules Apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libpq-dev \
 && docker-php-ext-install intl opcache pdo_pgsql \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache sert le dossier public/ de Symfony
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

# 1) Dépendances (couche mise en cache tant que composer.json/lock ne changent pas)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist --no-interaction

# 2) Code de l'application
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
 && mkdir -p var/cache var/log \
 && php bin/console asset-map:compile --env=prod \
 && DATABASE_URL="postgresql://build:build@127.0.0.1:5432/build?serverVersion=16&charset=utf8" \
    php bin/console cache:warmup --env=prod \
 && chown -R www-data:www-data var

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Render fournit la variable PORT (10000 par défaut dans start.sh)
EXPOSE 10000
CMD ["start.sh"]

# Image de production pour la boutique (Symfony 7.4, PHP 8.3 + Apache, base PostgreSQL).
# Utilisable sur Render, Koyeb, Fly.io, un VPS, etc.
FROM php:8.3-apache

# Extensions PHP nécessaires (intl, accès PostgreSQL, gd + exif pour les photos) + modules Apache
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libpq-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install intl opcache pdo_pgsql gd exif \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Envoi de photos jusqu'à 8 Mo (réduites ensuite à 800 px) ; mémoire suffisante pour les traiter
RUN printf 'upload_max_filesize=8M\npost_max_size=10M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/boutique.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache sert le dossier public/ de Symfony
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Toutes les URL (/produits, etc.) sont envoyées au contrôleur frontal Symfony.
# Réglé ici, dans la configuration Apache, pour ne pas dépendre du fichier caché public/.htaccess.
RUN printf '<Directory /var/www/html/public>\n    DirectoryIndex index.php\n    FallbackResource /index.php\n</Directory>\n' > /etc/apache2/conf-available/symfony.conf \
 && a2enconf symfony

WORKDIR /var/www/html

# 1) Dépendances (couche mise en cache tant que composer.json/lock ne changent pas)
COPY composer.json composer.lock symfony.lock ./
# Réessaie jusqu'à 4 fois en cas de panne réseau passagère pendant le téléchargement
RUN for essai in 1 2 3 4; do \
      composer install --no-dev --no-scripts --no-progress --prefer-dist --no-interaction && exit 0; \
      echo "Échec du téléchargement (essai $essai), nouvel essai dans 10 s…" >&2; sleep 10; \
    done; exit 1

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

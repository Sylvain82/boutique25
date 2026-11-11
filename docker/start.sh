#!/bin/sh
# Démarre la boutique : migrations de base de données, produits de démonstration (optionnel), puis Apache.
set -e
PORT="${PORT:-10000}"
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p var/cache var/log
chown -R www-data:www-data var

if [ -z "${DATABASE_URL}" ]; then
  echo "ERREUR : la variable DATABASE_URL n'est pas définie (voir DEPLOIEMENT.md)." >&2
  exit 1
fi

# Crée / met à jour les tables (idempotent : ne refait pas ce qui est déjà appliqué)
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Produits de démonstration, seulement si SEED_DEMO=1 ET que la table est vide
if [ "${SEED_DEMO}" = "1" ]; then
  # Une erreur ici ne doit pas empêcher le site de démarrer
  php bin/console app:produits:seed --no-interaction || echo "Avertissement : produits de démonstration non ajoutés." >&2
fi

# Premier administrateur : créé s'il n'existe pas encore, avec ADMIN_EMAIL et ADMIN_PASSWORD.
# Un compte existant n'est jamais modifié. Pratique sur l'offre gratuite de Render (pas de terminal).
if [ -n "${ADMIN_EMAIL}" ] && [ -n "${ADMIN_PASSWORD}" ]; then
  php bin/console app:admin:creer "${ADMIN_EMAIL}" --si-absent --no-interaction || echo "Avertissement : administrateur non créé." >&2
fi

# Les fichiers de cache générés par les commandes ci-dessus doivent rester lisibles par Apache
chown -R www-data:www-data var

exec apache2-foreground

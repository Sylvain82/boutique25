# Déployer la boutique sur Render (gratuit)

Le site (Symfony 7.1 + PHP 8.3) tourne dans un conteneur Docker, comme StatFoot.
Il utilise **PostgreSQL**. Render ne propose pas MySQL.

## 1. Créer la base PostgreSQL

Deux possibilités gratuites :

**Option A – Neon (recommandée)** : d'après la [FAQ Neon](https://neon.com/faqs/free-plan-limits-and-quotas), l'offre gratuite donne 1 Go par projet ; la base se met en veille après 5 minutes d'inactivité, mais les données ne sont pas supprimées (réveil automatique à la première visite). Vérifie les conditions actuelles sur leur site avant de t'inscrire.

1. Crée un compte sur neon.com, puis un projet (région Europe, par exemple Francfort).
2. Copie la « connection string » : `postgresql://utilisateur:motdepasse@ep-xxxx.neon.tech/neondb?sslmode=require`

**Option B – Base Render** : plus simple, mais elle **expire 30 jours après sa création** (14 jours de grâce, puis suppression) d'après la [doc Render](https://render.com/docs/free). Réservée aux essais.
Crée-la dans Render (« New + > PostgreSQL », plan Free) et copie l'« Internal Database URL ».

## 2. Mettre le projet sur GitHub

Remplace **tout le contenu** du dépôt `boutique25` par celui du zip (pas seulement quelques fichiers : l'ancienne migration MySQL doit disparaître). Les fichiers importants : `Dockerfile`, `render.yaml`, `docker/start.sh`, `migrations/`.

## 3. Créer le service sur Render

1. Render > « New + » > **Blueprint** > choisis le dépôt. Render lit `render.yaml`.
2. Il demande **DATABASE_URL** : colle l'adresse de l'étape 1.
3. `APP_SECRET` est généré automatiquement. `SEED_DEMO=1` ajoute 14 produits de démonstration si la base est vide.
4. Lance le déploiement (le premier build dure quelques minutes). Le site est ensuite sur `https://boutique-xxxx.onrender.com`.

Au démarrage, le conteneur crée les tables tout seul (`doctrine:migrations:migrate`). Rien à lancer à la main.

## Gérer les produits

Il n'y a pas encore d'écran d'administration. Pour ajouter des produits, il faut passer par la base (par exemple l'éditeur SQL de Neon : table `produit`, colonnes `type`, `nom`, `marque`, `prix` en euros, `promotion` vrai/faux). Pour ne plus ajouter les produits de démo, mets `SEED_DEMO` à `0` dans Render.

## Limites de l'offre gratuite Render

- Le site s'endort après 15 min sans visite ; le réveil prend environ une minute.
- Le disque est éphémère : n'enregistre pas d'images ou de fichiers envoyés par les visiteurs sur le serveur.

## En local

```
docker compose up -d database      # PostgreSQL (port affiché par « docker compose ps »)
# adapter DATABASE_URL dans .env.local si besoin, puis :
composer install
php bin/console doctrine:migrations:migrate
php bin/console app:produits:seed
symfony server:start               # ou php -S 127.0.0.1:8000 -t public
```

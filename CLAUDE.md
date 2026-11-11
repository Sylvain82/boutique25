# Boutique25 — contexte du projet

Petit site de boutique perso de Sylvain (technicien méthodes, pas développeur PHP de métier : expliquer simplement, en français, étape par étape). Projet de démonstration / portfolio.

## Stack

- Symfony 7.1, PHP 8.3 (>= 8.2), Twig, Bootstrap 5.3 chargé par CDN dans `templates/base.html.twig`.
- Doctrine ORM 3 / DBAL 3 sur **PostgreSQL 16** (base hébergée chez **Neon**). Pas de MySQL : l'ancienne migration MySQL a été supprimée.
- Pas de Stimulus / Turbo / importmap (retirés). Le CSS propre au site est dans `assets/styles/app.css`, servi par AssetMapper (`asset('styles/app.css')`, compilé au build avec `asset-map:compile`).
- Hébergement : **Render** (offre gratuite, service Docker, région Frankfurt). Déploiement via `render.yaml` (Blueprint).

## Ce qui existe

- `/` : accueil avec les 4 premières promotions.
- `/produits` : liste avec recherche (nom ou marque), filtre par type, case « promos ». Contrôleur `ProduitController`, requêtes dans `ProduitRepository::search()` et `findTypes()`.
- `/panier` : panier en session (pas de compte ni de table). Service `App\Service\Panier` (stocke seulement [id => quantité], relit nom/prix en base), `PanierController` (ajouter / modifier / retirer / vider, en POST avec jeton CSRF `panier`), fonction Twig `panier_nombre()` pour le badge du menu (`App\Twig\PanierExtension`). Bouton « Ajouter au panier » sur chaque carte produit. Messages flash affichés dans `base.html.twig`. Quantité max 99.
- `/health` : page de santé pour Render (ne touche pas la base).
- Entité `Produit` : id, type, nom, marque, prix (entier, en euros), promotion (bool).
- Commande `php bin/console app:produits:seed` : ajoute 14 produits de démo seulement si la table est vide.
- Il n'y a **pas** encore : administration des produits, commandes (le bouton « Commander » est désactivé), comptes clients, images de produits.

## Déploiement (résumé, détails dans `DEPLOIEMENT.md`)

- `Dockerfile` : PHP 8.3 + Apache. Les URL sont routées vers `index.php` par `FallbackResource` dans la config Apache (ne pas dépendre de `public/.htaccess`).
- `docker/start.sh` : au démarrage, lance `doctrine:migrations:migrate`, puis le seed si `SEED_DEMO=1`, puis Apache. L'échec du seed n'empêche pas le démarrage.
- Variables d'environnement Render : `APP_ENV=prod`, `APP_SECRET` (généré), `DATABASE_URL` (chaîne Neon `postgresql://…?sslmode=require`, à coller dans Render), `SEED_DEMO`, `MESSENGER_TRANSPORT_DSN`.
- **Ne jamais** écrire la chaîne de connexion Neon dans un fichier versionné ni la coller dans une conversation. En local, elle va dans `.env.local` (ignoré par Git).

## Pièges déjà rencontrés (à ne pas refaire)

1. **Fichiers cachés non copiés** : quand les fichiers ont été copiés dans le dépôt GitHub, `.env` et `public/.htaccess` sont restés à l'ancienne version / manquants. Toujours vérifier les fichiers commençant par un point.
2. **Ancienne migration MySQL** laissée dans `migrations/` : elle fait échouer le démarrage sur PostgreSQL (`AUTO_INCREMENT`). Il ne doit rester que la migration PostgreSQL.
3. **Cache Doctrine préparé au build** : au `cache:warmup` du Dockerfile, `DATABASE_URL` n'existe pas encore ; Doctrine lisait le `.env` (alors MySQL) et choisissait une génération d'identifiants incompatible avec PostgreSQL. Corrigé : `#[ORM\GeneratedValue(strategy: 'SEQUENCE')]` + `SequenceGenerator('produit_id_seq')` dans `Produit`, et `DATABASE_URL` factice PostgreSQL au warmup dans le Dockerfile. Toute nouvelle entité doit suivre la même règle (séquence explicite).
4. **Téléchargement Composer échoué au build** (code 100) : panne réseau passagère ; le Dockerfile réessaie jusqu'à 4 fois.
5. **DNS** : sur les appareils de Sylvain, NextDNS peut bloquer `onrender.com` / `github.io` (« Adresse introuvable »). À vérifier avant de chercher un bug dans le site.

## Limites de l'offre gratuite

- Render : le site s'endort après 15 min sans visite (réveil ~1 min) ; disque éphémère (pas d'images envoyées par les visiteurs sur le serveur).
- Neon : base en veille après 5 min d'inactivité, 1 Go par projet ; les données restent.

## Commandes utiles en local

```
composer install
php bin/console doctrine:migrations:migrate
php bin/console app:produits:seed
php -S 127.0.0.1:8000 -t public      # ou : symfony server:start
php bin/console lint:twig templates
```

Nouvelle table ou colonne : modifier l'entité puis `php bin/console doctrine:migrations:diff`, relire la migration générée, la committer. Elle s'applique toute seule au prochain déploiement.

## Prochaines étapes envisagées

1. Administration des produits (ajouter / modifier / supprimer), protégée par connexion.
2. Commande (le panier en session existe déjà).
3. Images des produits (stockage externe, car le disque Render est éphémère).

## Conventions

- Textes de l'interface en français. Réponses et explications en français, simples, sans jargon inutile.
- Tester en mode production avant de livrer : `APP_ENV=prod`, `composer install --no-dev`, `asset-map:compile`, `cache:warmup`.

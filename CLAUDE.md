# Boutique25 — contexte du projet

Petit site de boutique perso de Sylvain (technicien méthodes, pas développeur PHP de métier : expliquer simplement, en français, étape par étape). Projet de démonstration / portfolio.

## Stack

- Symfony 7.4 (LTS), PHP 8.3 (>= 8.2), Twig, Bootstrap 5.3 chargé par CDN dans `templates/base.html.twig`.
- Doctrine ORM 3 / DBAL 3 sur **PostgreSQL 16** (base hébergée chez **Neon**). Pas de MySQL : l'ancienne migration MySQL a été supprimée.
- Pas de Stimulus / Turbo / importmap (retirés). Le CSS propre au site est dans `assets/styles/app.css`, servi par AssetMapper (`asset('styles/app.css')`, compilé au build avec `asset-map:compile`).
- Hébergement : **Render** (offre gratuite, service Docker, région Frankfurt). Déploiement via `render.yaml` (Blueprint).

## Ce qui existe

- `/` : accueil avec les 4 premières promotions.
- `/produits` : liste avec recherche (nom ou marque), filtre par type, case « promos ». Contrôleur `ProduitController`, requêtes dans `ProduitRepository::search()` et `findTypes()`.
- `/panier` : panier en session (pas de compte ni de table). Service `App\Service\Panier` (stocke seulement [id => quantité], relit nom/prix en base), `PanierController` (ajouter / modifier / retirer / vider, en POST avec jeton CSRF `panier`), fonction Twig `panier_nombre()` pour le badge du menu (`App\Twig\PanierExtension`). Bouton « Ajouter au panier » sur chaque carte produit. Messages flash affichés dans `base.html.twig`. Quantité max 99.
- `/commande` : parcours d'achat (`CommandeController`) : coordonnées (`CommandeType`) → création de `Commande` + `LigneCommande` (nom et prix copiés) en statut `en_attente` → `/commande/{reference}/paiement` → `payer` (POST, CSRF) → Stripe Checkout ou paiement simulé → `/commande/{reference}/confirmation` (revérifie la session auprès de Stripe, vide le panier une fois). Un visiteur ne voit que les commandes dont la référence est dans sa session (`mes_commandes`). Webhook `/paiement/stripe/webhook` (signature HMAC vérifiée dans `Paiement::lireWebhook`).
- Paiement : service `App\Service\Paiement`, appelle l'API Stripe avec `symfony/http-client` (pas de SDK Stripe). Variables `STRIPE_SECRET_KEY` (vide = paiement simulé ; clé restreinte `rk_…` conseillée, permission Checkout Sessions en écriture) et `STRIPE_WEBHOOK_SECRET`. Montants Stripe en centimes (`prix * 100`). En-tête `Stripe-Version` fixé (`Paiement::VERSION_API` = `2026-11-18.endive`), `integration_identifier`, `expires_at` = 1 h, jamais de `payment_method_types` (moyens de paiement dynamiques). Avant d'en créer une nouvelle, `payer` relit la session en cours et la réutilise si elle est ouverte. Clé d'idempotence `checkout-<référence>-<session précédente ou « initiale »>`. Webhook : `completed` / `async_payment_succeeded` → payée si `Paiement::sessionPayeePour()` ; `expired` / `async_payment_failed` → annulée si c'est la session en cours et que la commande est en attente. Tests : `tests/Service/PaiementTest.php` (`php bin/phpunit`).
- Administration `/admin` (ROLE_ADMIN, `access_control` + `#[IsGranted]`), contrôleurs dans `src/Controller/Admin/` : tableau de bord, produits (CRUD, `ProduitType`), commandes (liste, détail, statut), administrateurs (ajout, suppression sauf soi-même, changement de mot de passe, `AdminType`). Gabarits dans `templates/admin/` (mise en page `admin/_layout.html.twig`).
- Connexion `/connexion`, déconnexion `/deconnexion` (`SecuriteController`, `form_login` avec CSRF, 5 essais/min via `symfony/rate-limiter`). Entité `Utilisateur` (email, roles, password haché). Commande `php bin/console app:admin:creer email [--si-absent]` ; au démarrage, `start.sh` crée l'admin depuis `ADMIN_EMAIL` / `ADMIN_PASSWORD` s'il n'existe pas.
- Statuts de commande : `en_attente`, `payee`, `expediee`, `annulee` (constantes dans `Commande`).
- `/health` : page de santé pour Render (ne touche pas la base).
- Entités : `Produit` (id, type, nom, marque, prix entier en euros, promotion), `Utilisateur`, `Commande`, `LigneCommande` (produit mis à NULL si le produit est supprimé). Toutes avec séquence explicite.
- Commande `php bin/console app:produits:seed` : ajoute 14 produits de démo seulement si la table est vide.
- Email de confirmation : service `App\Service\ConfirmationCommande::confirmerPaiement()`, seul point d'entrée pour passer une commande en « payée » (paiement simulé, retour Stripe, webhook). `Commande::marquerPayee()` renvoie false si déjà payée, donc pas de double email. Gabarits `templates/emails/confirmation_commande.{html,txt}.twig`. Variables `MAILER_DSN` (`null://null` = désactivé, Brevo : `brevo+api://CLE@default` via `symfony/brevo-mailer`), `MAILER_FROM`, `MAILER_COPIE` (copie cachée). Envoi **synchrone** : le routage messenger de `SendEmailMessage` vers `async` a été retiré (pas de worker sur Render). Une erreur d'envoi est journalisée et ne bloque pas la commande.
- Photos des produits : entité `ProduitImage` (table séparée, BYTEA, OneToOne unidirectionnelle vers `Produit`, cascade à la suppression). `Produit::imageMaj` (date, vide = pas de photo) évite de charger les images dans les listes. Service `App\Service\ImageProduit` (GD : redressement EXIF, 800 px max, WebP qualité 80). Servies par `/produits/{id}/image?v=<timestamp>` (cache navigateur 1 an, immutable). Envoi dans le formulaire admin (`ProduitType`, champ non mappé `image` + case `supprimerImage`). Dockerfile : extensions `gd` (jpeg, webp) et `exif`, `upload_max_filesize=8M`.
- Illustrations des produits de démo : dessins SVG originaux dans `data/photos-demo/sources/` (générés par `data/photos-demo/generer.mjs`), rendus WebP dans `data/photos-demo/`. Commande `app:produits:photos` (lancée par `start.sh` si `SEED_DEMO=1`) : les associe aux produits de démo par nom exact, seulement s'ils n'ont pas de photo.
- Il n'y a **pas** encore : comptes clients, gestion des stocks, plusieurs photos par produit.

## Déploiement (résumé, détails dans `DEPLOIEMENT.md`)

- `Dockerfile` : PHP 8.3 + Apache. Les URL sont routées vers `index.php` par `FallbackResource` dans la config Apache (ne pas dépendre de `public/.htaccess`).
- `docker/start.sh` : au démarrage, lance `doctrine:migrations:migrate`, puis le seed si `SEED_DEMO=1`, puis Apache. L'échec du seed n'empêche pas le démarrage.
- Variables d'environnement Render : `APP_ENV=prod`, `APP_SECRET` (généré), `DATABASE_URL` (chaîne Neon `postgresql://…?sslmode=require`, à coller dans Render), `SEED_DEMO`, `MESSENGER_TRANSPORT_DSN`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, et à ajouter à la main pour les emails : `MAILER_DSN`, `MAILER_FROM`, `MAILER_COPIE`.
- **Ne jamais** écrire de clé Stripe ni de mot de passe dans un fichier versionné.
- **Ne jamais** écrire la chaîne de connexion Neon dans un fichier versionné ni la coller dans une conversation. En local, elle va dans `.env.local` (ignoré par Git).

## Pièges déjà rencontrés (à ne pas refaire)

1. **Fichiers cachés non copiés** : quand les fichiers ont été copiés dans le dépôt GitHub, `.env` et `public/.htaccess` sont restés à l'ancienne version / manquants. Toujours vérifier les fichiers commençant par un point.
2. **Ancienne migration MySQL** laissée dans `migrations/` : elle fait échouer le démarrage sur PostgreSQL (`AUTO_INCREMENT`). Il ne doit rester que la migration PostgreSQL.
3. **Cache Doctrine préparé au build** : au `cache:warmup` du Dockerfile, `DATABASE_URL` n'existe pas encore ; Doctrine lisait le `.env` (alors MySQL) et choisissait une génération d'identifiants incompatible avec PostgreSQL. Corrigé : `#[ORM\GeneratedValue(strategy: 'SEQUENCE')]` + `SequenceGenerator('produit_id_seq')` dans `Produit`, et `DATABASE_URL` factice PostgreSQL au warmup dans le Dockerfile. Toute nouvelle entité doit suivre la même règle (séquence explicite).
4. **Téléchargement Composer échoué au build** (code 100) : panne réseau passagère ; le Dockerfile réessaie jusqu'à 4 fois.
5. **Recette `csrf.yaml` de Symfony 7.2+** : la mise à jour a ajouté des jetons CSRF « sans état » (`stateless_token_ids`). Fichier supprimé volontairement : on garde les jetons classiques en session, testés avec tous les formulaires.
6. **Serveur PHP intégré et `APP_ENV`** : `APP_ENV=prod php -S …` ne transmet pas la variable à Symfony ; pour tester le mode production en local, mettre `APP_ENV=prod` / `APP_DEBUG=0` dans `.env.local`.
7. **`composer install` en root** : les plugins Composer (dont `symfony/runtime`) sont désactivés → « Symfony Runtime is missing ». Lancer avec `COMPOSER_ALLOW_SUPERUSER=1` (le Dockerfile le fait).
8. **DNS** : sur les appareils de Sylvain, NextDNS peut bloquer `onrender.com` / `github.io` (« Adresse introuvable »). À vérifier avant de chercher un bug dans le site.

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

1. Vérifier le premier déploiement après la mise à jour (build Docker avec gd/exif non testé hors Render).
2. Gestion des stocks, page de détail d'un produit.
3. Symfony 8 (demande PHP 8.4) quand ce sera utile : la 7.4 est maintenue jusqu'à fin 2028 (correctifs de sécurité jusqu'à fin 2029).

## Conventions

- Textes de l'interface en français. Réponses et explications en français, simples, sans jargon inutile.
- Tester en mode production avant de livrer : `APP_ENV=prod`, `composer install --no-dev`, `asset-map:compile`, `cache:warmup`.

# Déployer la boutique sur Render (gratuit)

Le site (Symfony 7.4 + PHP 8.3) tourne dans un conteneur Docker, comme StatFoot.
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

## Créer le premier administrateur

L'offre gratuite de Render ne donne pas accès à un terminal : on passe par deux variables d'environnement.

1. Dans Render > ton service > **Environment**, renseigne `ADMIN_EMAIL` (ton email) et `ADMIN_PASSWORD` (10 caractères minimum, un vrai mot de passe que tu n'utilises nulle part ailleurs).
2. Enregistre : Render redémarre le site, et le compte est créé au démarrage.
3. Va sur `https://ton-site.onrender.com/connexion` (lien « Espace administrateur » en bas de chaque page) et connecte-toi.
4. Par sécurité, **supprime ensuite `ADMIN_PASSWORD`** dans Render. Le compte reste en base. Un compte qui existe déjà n'est jamais modifié au démarrage : changer `ADMIN_PASSWORD` plus tard ne change donc pas le mot de passe. Pour le changer, utilise « Administrateurs > Changer mon mot de passe ».

Les autres administrateurs s'ajoutent depuis l'écran **Administration > Administrateurs**.

En local : `php bin/console app:admin:creer moi@exemple.fr` (le mot de passe est demandé sans s'afficher).

## Administration

Adresse : `/admin` (réservée aux administrateurs connectés).

- **Tableau de bord** : chiffre d'affaires, commandes à expédier, état du paiement.
- **Produits** : ajouter, modifier, supprimer, avec une **photo** (JPEG, PNG ou WebP, 8 Mo maximum). La photo est réduite à 800 px et enregistrée dans la base Neon (environ 5 à 60 Ko par photo), car le disque de Render est effacé à chaque redémarrage. Supprimer un produit ne touche pas les commandes déjà passées : elles gardent le nom et le prix.
- **Commandes** : liste filtrable par statut, détail (client, adresse, produits), changement de statut (payée → expédiée, annulée…).
- **Administrateurs** : ajouter / supprimer un compte, changer son mot de passe. On ne peut pas supprimer son propre compte.

Pour ne plus ajouter les produits de démo, mets `SEED_DEMO` à `0` dans Render.

## Paiement (Stripe)

Parcours client : Panier > Commander > coordonnées > paiement > confirmation.

**Sans configuration**, le paiement est **simulé** : un bouton « Simuler le paiement » valide la commande sans carte. C'est pratique pour une démonstration.

**Pour activer Stripe** (la carte est saisie sur une page sécurisée de Stripe et ne passe jamais par ton serveur) :

1. Crée un compte sur stripe.com. Il n'y a pas d'abonnement : Stripe prend une commission sur chaque paiement réel. Vérifie leurs tarifs actuels.
2. Travaille dans un **sandbox**, un environnement de test isolé que Stripe conseille plutôt que l'ancien « mode test » partagé : sélecteur de compte en haut à gauche > Sandboxes > Créer. Aucun argent réel n'y circule.
3. Crée une **clé restreinte** plutôt que la clé secrète : Développeurs > Clés API > **Créer une clé restreinte**. Donne-lui un nom (« boutique25 ») et une seule permission : **Checkout Sessions : écriture** (Write). Laisse tout le reste sur « Aucun ». Si la clé est volée, elle ne permet presque rien. La clé commence par `rk_test_…`.
4. Dans Render, ajoute la variable `STRIPE_SECRET_KEY` = cette clé. **Ne la mets jamais dans un fichier du projet ni dans une conversation.**
5. Teste un achat avec la carte `4242 4242 4242 4242`, une date d'expiration future et n'importe quel code à 3 chiffres.

**Obligatoire avant la mise en ligne : la notification de paiement (webhook).** C'est elle qui fait foi : sans elle, si un client paie puis ferme la page avant de revenir sur le site, sa commande reste « en attente » et il ne reçoit pas d'email. Elle est aussi indispensable pour les moyens de paiement différés (virement, prélèvement), confirmés plusieurs heures ou jours après.

1. Dans Stripe > Développeurs > Webhooks > **Ajouter une destination**, choisis l'adresse `https://boutique25.onrender.com/paiement/stripe/webhook`.
2. Coche ces 4 événements :
   - `checkout.session.completed` : paiement terminé ;
   - `checkout.session.async_payment_succeeded` : paiement différé encaissé ;
   - `checkout.session.async_payment_failed` : paiement différé refusé, la commande passe en « annulée » ;
   - `checkout.session.expired` : page de paiement abandonnée (au bout d'1 heure), la commande passe en « annulée ».
3. Copie le « secret de signature » `whsec_…`, puis ajoute-le dans Render comme `STRIPE_WEBHOOK_SECRET`. Le site rejette toute notification dont la signature n'est pas valide.

Le tableau de bord de l'administration indique si Stripe et le webhook sont actifs.

**Ce que fait le site** :
- Version de l'API Stripe fixée (`2026-11-18.endive`) : un changement de version sur ton compte ne modifie pas son fonctionnement.
- Un double clic sur « Payer » réutilise la même page de paiement : jamais deux paiements pour une commande.
- Les moyens de paiement proposés (carte, Apple Pay, etc.) se règlent dans le tableau de bord Stripe (Paramètres > Moyens de paiement), sans toucher au code.
- Les paiements du site sont étiquetés `boutique25-checkout-…` dans le tableau de bord Stripe.

**Passer en paiements réels** : active le compte Stripe (identité, IBAN…), crée en mode réel une clé restreinte `rk_live_…` avec la même permission et un webhook avec les mêmes 4 événements, puis remplace les deux variables dans Render. Pour un vrai commerce, il faut aussi des mentions légales et des CGV. Consulte la liste de vérification « Go live » de Stripe.

## Emails de confirmation de commande

Quand une commande est payée, le client reçoit un email récapitulatif. Une copie cachée peut aussi t'être envoyée. **Sans configuration, aucun email ne part** : la variable `MAILER_DSN` vaut `null://null`. Le tableau de bord de l'administration indique si les emails sont actifs.

Je conseille **Brevo** (service français). Il passe par une API web (HTTPS) plutôt que par le protocole d'envoi classique (SMTP), que les hébergeurs gratuits bloquent souvent. Vérifie leurs conditions actuelles : l'offre gratuite était limitée à 300 emails par jour.

1. Crée un compte sur brevo.com.
2. **Valide une adresse d'expédition** : Brevo > Expéditeurs, domaines > ajouter ton adresse, puis clique sur le lien de vérification reçu.
3. Crée une **clé API** : Brevo > SMTP & API > Clés API > Générer une clé (elle commence par `xkeysib-`).
4. Dans Render > Environment, ajoute :
   - `MAILER_DSN` = `brevo+api://LA_CLE@default` (remplace `LA_CLE` par ta clé, sans espace). **Ne mets jamais cette clé dans un fichier du projet ni dans une conversation.**
   - `MAILER_FROM` = l'adresse validée à l'étape 2
   - `MAILER_COPIE` = ton adresse, si tu veux recevoir une copie de chaque commande (facultatif)
5. Passe une commande de test : l'email doit arriver en quelques secondes. Sinon, regarde les journaux (« Logs ») de Render : chaque échec y apparaît sous la forme « Email de confirmation non envoyé ». Un email qui ne part pas n'empêche jamais la commande d'être validée.

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

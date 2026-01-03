# Crypto Portfolio - HETIC Web3

Application de gestion de portefeuille crypto (Symfony 6 + Vue 3/Vite). Ce guide explique comment installer, configurer et lancer le projet en local.

## Stack rapide
- Backend : PHP/Symfony, Doctrine ORM, MySQL/MariaDB (par defaut `.env`), HTTP sessions.
- Frontend : Vue 3, Vite, Chart.js.
- Authentification : login/register avec sessions serveur, roles `ROLE_USER` / `ROLE_ADMIN`.

## Prerequis
- PHP 8.2+, Composer.
- MySQL/MariaDB (ou Postgres si vous ajustez `DATABASE_URL`).
- Node.js 18+ et npm.
- Optionnel : `symfony` CLI pour lancer le serveur local facilement.

## Configuration
1) Copier/envoyer les variables :
   - Dupliquer `.env` vers `.env.local` et mettre vos valeurs locales.
   - `DATABASE_URL` : adapter a votre base (ex MySQL locale ou Postgres si vous utilisez Docker).
   - `APP_ENV=dev` en local.
   - `COINCAP_API_KEY` : cle API pour les cours en direct (optionnel mais recommande).  
2) Creer la base et appliquer les migrations :
   ```sh
   composer install
   php bin/console doctrine:database:create    # si la base n'existe pas
   php bin/console doctrine:migrations:migrate
   ```

## Lancement
### Backend (Symfony)
```sh
# Depuis la racine du projet
symfony serve -d          # ou php -S localhost:8000 -t public
```
Par defaut l'API ecoute sur `http://127.0.0.1:8000`. Les routes sont sous `/api/*` et proteges par session.

### Frontend (Vue 3 / Vite)
```sh
cd frontend
npm install
npm run dev -- --host     # servira sur http://localhost:5173 par defaut
```
Le frontend appelle l'API Symfony (meme origine en dev si vous servez le frontend via le backend proxy ou configurez un proxy Vite).

## Administration
- Les comptes sont crees via `/api/register`. Pour donner les droits admin, mettez `ROLE_ADMIN` dans la colonne `roles` de l'utilisateur (JSON) ou ajoutez-le via votre outil SQL.

## Donnees et prix
- Les cours crypto sont recuperes via CoinCap (avec fallback CoinGecko). `COINCAP_API_KEY` ameliore la fiabilite.
- L'historique est rempli automatiquement lors des requetes prix et via backfill (`POST /api/prices/backfill` avec un admin).

## Tests
- Backend : `php bin/phpunit` (config par defaut).  
- Frontend : non configure pour les tests unitaires; lancez `npm run build` pour verifier le bundling.

## Docker (optionnel)
- Un `compose.yaml` est fourni pour une base Postgres. Adaptez `DATABASE_URL` en consequence avant de lancer `docker compose up -d database`.

## Points de contact rapides
- Portefeuille : `/api/portfolio` (CRUD, session requise)
- Transactions : `/api/transactions`
- Prix : `/api/prices` et `/api/prices/history`
- Admin : `/api/admin/*` (ROLE_ADMIN requis)

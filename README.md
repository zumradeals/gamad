# GAMAD — Réseau social d'action

Un réseau social d'action ouvert à tous : on y apporte ce que l'on sait faire, on y apprend ce qui manque, on agit avec d'autres.

Avant toute intervention, lire `CLAUDE.md` puis les documents de `docs/` (`PRODUIT.md`, `DESIGN.md`, `DECISIONS.md`, `LOTS.md`).

## Installation locale

Prérequis : PHP 8.3 ou plus (extensions `pdo_pgsql`, `intl`, `mbstring`), Composer, Node 22, PostgreSQL 16.

```sh
createdb gamad && createdb gamad_test        # avec un rôle « gamad » (mot de passe « gamad » en local)
composer install
cp .env.example .env && php artisan key:generate
npm install && npm run build
php artisan migrate --seed                   # le seeder de développement ne s'exécute qu'en environnement local
```

## Lancement

```sh
php artisan serve            # http://localhost:8000
npm run dev                  # facultatif : rechargement à chaud du CSS
```

En développement, les e-mails ne sont pas envoyés : ils sont écrits dans `storage/logs/laravel.log`.

## Tests et qualité

```sh
php artisan test             # Pest, base PostgreSQL « gamad_test »
vendor/bin/pint --test       # style du code (retirer --test pour corriger)
```

## Super administrateur

```sh
php artisan gamad:super-admin adresse@exemple.org --raison="Président de GAMAD"
```

Le compte doit déjà exister. L'option `--raison` est obligatoire ; l'acte et sa raison sont inscrits au journal d'administration.

Pour essayer le lot sur un téléphone : `docs/TESTER.md`.

## Mise en ligne

Le site de test `reseau.dgafrique.com` s'installe avec `deploy/install.sh` et se met à jour tout seul à chaque fusion sur `main` :
voir `deploy/LISEZMOI.md` (administration du serveur). Les réglages métier (courriel, nom, limites) se font dans l'application,
page Paramètres, jamais dans le fichier `.env`. Essai sur téléphone : `docs/TESTER.md`.

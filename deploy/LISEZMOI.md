# deploy/ — mise en ligne de GAMAD

Ce dossier contient les scripts qui installent et font vivre le site de test `reseau.dgafrique.com` (D-050).
Il s'adresse à la personne qui administre le serveur ; le Président n'a besoin que de `docs/TESTER.md`.

Règle d'or (D-050) : **aucun de ces scripts ne modifie les autres services du serveur.**

| Script | Rôle | Lancé par |
| --- | --- | --- |
| `diagnostic.sh` | Lit la configuration du serveur, ne modifie rien (phase A). | Une personne, avant l'installation |
| `install.sh` | Installe tout, une seule fois. Reprend là où il s'est arrêté s'il échoue. | Une personne (`curl … \| sudo bash`) |
| `deploy.sh` | Déploiement automatique, installé sous le nom `gamad-deploy`. | Un minuteur systemd, toutes les 5 minutes, sous le compte dédié |
| `retour-arriere.sh` | Revient à la version précédente, installé sous le nom `gamad-reseau-retour-arriere`. | Une personne, en cas de problème |
| `sauvegarde.sh` | Sauvegarde de la base, installé dans `/usr/local/lib/gamad-reseau/`. | Un minuteur systemd, chaque nuit |
| `desinstaller.sh` | Retire ce que l'installation a créé, et seulement cela. | Une personne |

## Installation

```
curl -fsSL https://raw.githubusercontent.com/zumradeals/gamad/main/deploy/install.sh | sudo bash
```

Avant toute modification, le script contrôle le serveur et **refuse de continuer** si : la configuration actuelle de Nginx ou de
PHP-FPM est déjà en erreur, Node est trop ancien, le DNS ne pointe pas vers le serveur, ou l'un des noms qu'il veut créer est
déjà pris. Pour voir ce qu'il ferait sans rien modifier, ajoutez `-s -- --simulation` après `bash` :

```
curl -fsSL https://raw.githubusercontent.com/zumradeals/gamad/main/deploy/install.sh | sudo bash -s -- --simulation
```

Ce qu'il crée, aux noms dédiés :

| Élément | Nom |
| --- | --- |
| Utilisateur système | `gamad-reseau` (sans shell, sans mot de passe) |
| Dossier | `/srv/gamad-reseau` (versions dans `releases/`, lien `current`, partage dans `shared/`) |
| Base et rôle PostgreSQL | `gamad_reseau` / `gamad_reseau` (mot de passe aléatoire, base fermée aux autres rôles) |
| Pool PHP-FPM | `/etc/php/<version>/fpm/pool.d/gamad-reseau.conf`, socket `/run/php/gamad-reseau.sock` |
| Site Nginx | `/etc/nginx/sites-available/reseau.dgafrique.com` (+ lien dans `sites-enabled`) |
| Certificat | `/etc/letsencrypt/live/reseau.dgafrique.com` (renouvelé par certbot) |
| Unités systemd | `gamad-reseau-deploy.{service,timer}`, `gamad-reseau-sauvegarde.{service,timer}` |
| Réglages techniques | `/etc/gamad-reseau/deploy.conf` (sans secret) et `/etc/gamad-reseau/etat` (liste de ce qui a été créé) |
| Journaux | `/var/log/gamad-reseau/` (`installation.log`, `deploy.log`, `sauvegarde.log`, `php-fpm.log`) |
| Sauvegardes | `/var/backups/gamad-reseau/` : base, `.env`, photos de profil (lisible par l'administrateur seul) |

Extensions PHP exigées (le script installe seulement celles qui manquent) : `ctype curl dom fileinfo gd intl mbstring openssl pdo_pgsql
tokenizer xml zip`. **`gd`** sert à traiter les photos de profil (Lot 1). Sur un serveur installé avant le Lot 1, le déploiement automatique
met le code à jour mais n'installe aucun paquet : sans `gd`, l'écran « Modifier mon profil » n'offre simplement pas l'envoi de photo (les
initiales s'affichent). Pour l'activer, relancez l'installation (elle ajoute `php<version>-gd` et recharge PHP-FPM) ou, à la main :
`sudo apt install php8.4-gd && sudo systemctl reload php8.4-fpm` (adaptez la version).

Nginx et PHP-FPM sont seulement **rechargés en douceur** (aucune requête en cours n'est coupée), après `nginx -t` et `php-fpm -t`.
Le pare-feu n'est pas modifié. Le fichier `.env` est écrit par le script, avec le seul amorçage technique (D-049) :
mode de production, clé de chiffrement, adresse, connexion à la base.

## Déploiement automatique

Toutes les 5 minutes, `gamad-deploy` compare le dernier commit de `main` à celui qui est en ligne. S'il y a du nouveau :

1. nouvelle version dans `releases/<date>-<commit>` ; dépendances PHP (`composer`) ; front (`npm ci`, `npm run build`) ;
2. sauvegarde de la base (`sauvegardes-deploiement/`, 5 conservées) puis migrations ;
3. bascule du lien `current` (instantanée), puis vérification que `/up` et `/` répondent ;
4. en cas d'échec **avant** la bascule : rien ne change en ligne ; **après** : retour automatique à la version précédente.

L'échec est écrit dans `/var/log/gamad-reseau/deploy.log`, avec les dernières lignes de la sortie. Un même commit défaillant est
tenté 3 fois au plus. Les 5 dernières versions sont conservées. Tout s'exécute sous le compte dédié, jamais en administrateur.

Voir l'état : `sudo -u gamad-reseau /srv/gamad-reseau/bin/gamad-deploy --statut`.
Forcer un déploiement : `sudo -u gamad-reseau /srv/gamad-reseau/bin/gamad-deploy --forcer`.

> Les migrations de base de données doivent rester compatibles avec la version précédente du code : pendant quelques secondes,
> l'ancienne version tourne sur le nouveau schéma. Un retour arrière ne défait pas les migrations.

## Retour arrière

```
sudo gamad-reseau-retour-arriere            # version juste avant celle qui est en ligne
sudo gamad-reseau-retour-arriere --liste    # versions conservées
sudo gamad-reseau-retour-arriere NOM        # une version précise
```

Seul le lien `current` change. Le déploiement automatique est ensuite **mis en pause tant que `main` n'a pas de nouveau commit**,
pour ne pas redéployer la version défaillante. Si le site ne répond pas avec la version cible, le retour est annulé.

## Sauvegardes

Chaque nuit à 3 h 30 (heure du serveur) : `pg_dump` de la base `gamad_reseau` (format personnalisé), vérifié par `pg_restore --list`,
plus une copie du `.env` (la clé de chiffrement qu'il contient est indispensable pour relire les mots de passe enregistrés dans
Paramètres), et les photos de profil (`storage/app/private/profile-photos` du dossier partagé) dans une archive
`photos-AAAA-MM-JJ_HHMMSS.tar.gz`. Les 7 plus récentes de chaque sorte sont conservées. Sans photo, ou si le dossier n'existe pas
encore, il n'y a simplement pas d'archive de photos et la sauvegarde réussit. Pour restaurer :

```
sudo -u postgres pg_restore --clean --if-exists --no-owner -d gamad_reseau /var/backups/gamad-reseau/gamad_reseau-AAAA-MM-JJ_HHMMSS.dump
sudo tar -xzf /var/backups/gamad-reseau/photos-AAAA-MM-JJ_HHMMSS.tar.gz -C /srv/gamad-reseau/shared/storage/app/private && sudo chown -R gamad-reseau:gamad-reseau /srv/gamad-reseau/shared/storage/app/private/profile-photos
```

## Désinstallation

```
sudo bash /usr/local/lib/gamad-reseau/desinstaller.sh
```

Elle retire seulement ce qui figure dans `/etc/gamad-reseau/etat`. La base n'est supprimée que sur une confirmation séparée
(`SUPPRIMER LA BASE`) ; les sauvegardes et les journaux sont conservés.

## Sécurité du déploiement automatique

Le dépôt est public et le serveur n'a aucun secret à configurer. Conséquence : **toute personne qui peut écrire sur `main` peut
faire exécuter du code sur le serveur**, sous le compte dédié `gamad-reseau` (jamais en administrateur, sans mot de passe sudo).
Protégez donc la branche `main` (revue obligatoire, pas de poussée directe de code) : c'est déjà la règle du projet, le code ne
passe que par une Pull Request.

## Règles d'écriture des scripts

Ces règles sont vérifiées par `tests/Feature/Deploy/` (elles s'exécutent à chaque Pull Request) :

- **Un message d'arrêt ne s'affiche jamais sans arrêt réel.** Les pièges d'erreur (`trap … ERR`) ne font rien hors du shell principal
  (substitutions `$( … )` et sous-shells) ; `arreter()` arrête le script principal même appelée depuis un sous-shell.
- **Aucune commande pouvant échouer dans un message** (`ok`, `info`, `arreter`, `journal`, `echo`…) : on calcule la valeur avant,
  dans une variable, avec `|| true` si l'échec est sans gravité, et sans `|| true` si l'échec doit arrêter le script.
- Le code du dépôt (`/srv/gamad-reseau/repo`) appartient au compte dédié : toute commande `git` y est lancée avec
  `runuser -u gamad-reseau --` (sinon git refuse : « dubious ownership »).
- Pour tester un script `install.sh` sans l'exécuter : `GAMAD_SOURCE_ONLY=1 source deploy/install.sh` charge ses fonctions seulement.

#!/usr/bin/env bash
#
# GAMAD — déploiement automatique (Lot 0.1).
#
# Installé par deploy/install.sh dans /srv/gamad-reseau/bin/gamad-deploy et lancé toutes les 5 minutes par un
# minuteur systemd, SOUS LE COMPTE DÉDIÉ (jamais en administrateur). Aucun secret à configurer : le dépôt est public.
#
# À chaque lancement :
#   1. compare le dernier commit de la branche main au commit en ligne ; s'il n'y a rien de nouveau, s'arrête ;
#   2. prépare la nouvelle version dans un dossier à part (releases/) : code, dépendances, front compilé ;
#   3. sauvegarde la base, applique les migrations ;
#   4. bascule par lien symbolique, vérifie que le site répond ; sinon revient à la version précédente.
# En cas d'échec à n'importe quelle étape AVANT la bascule, la version en ligne reste strictement inchangée ;
# l'échec est écrit dans le journal. Les 5 dernières versions sont conservées.
#
# Options : --statut (affiche l'état, ne change rien)   --forcer (redéploie même sans nouveau commit)

set -Eeuo pipefail
umask 022

CONF="${GAMAD_CONF:-/etc/gamad-reseau/deploy.conf}"
# shellcheck disable=SC1090
. "$CONF"
# Variables attendues dans $CONF : APP_DIR DOMAIN PHP_BIN REPO_URL BRANCH KEEP_RELEASES LOG_FILE

export PATH="/usr/local/bin:/usr/bin:/bin:/snap/bin"
export HOME="$APP_DIR"
export COMPOSER_HOME="$APP_DIR/.composer" COMPOSER_NO_INTERACTION=1 COMPOSER_ALLOW_SUPERUSER=0
export npm_config_cache="$APP_DIR/.npm" CI=true NO_COLOR=1 FORCE_COLOR=0 TERM=dumb

SHARED="$APP_DIR/shared"
ETAT="$SHARED/etat"
ENV_FILE="$SHARED/.env"
MAX_ECHECS=3
FORCER=0
STATUT=0

for argument in "$@"; do
    case "$argument" in
        --forcer) FORCER=1 ;;
        --statut) STATUT=1 ;;
        *) echo "Option inconnue : $argument" >&2; exit 2 ;;
    esac
done

journal() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG_FILE"; }

env_get() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2-; }

sha_en_ligne() { [ -r "$APP_DIR/current/.sha" ] && cat "$APP_DIR/current/.sha" || true; }

# ------------------------------------------------------------------ statut
if [ "$STATUT" -eq 1 ]; then
    echo "Version en ligne    : $(readlink -e "$APP_DIR/current" 2>/dev/null | xargs -r basename || true)"
    echo "Commit en ligne     : $(sha_en_ligne)"
    echo "Dernier commit main : $(git ls-remote "$REPO_URL" "refs/heads/$BRANCH" 2>/dev/null | cut -f1)"
    echo "Gel après retour    : $(cat "$ETAT/gel-sha" 2>/dev/null || echo aucun)"
    echo "Versions conservées :"; ls -1t "$APP_DIR/releases" 2>/dev/null | sed 's/^/  /'
    exit 0
fi

mkdir -p "$ETAT" "$ETAT/echecs" "$APP_DIR/releases" "$APP_DIR/sauvegardes-deploiement"

# Un seul déploiement à la fois.
exec 9>"$SHARED/.verrou"
flock -n 9 || exit 0

# ------------------------------------------------- y a-t-il du nouveau ?
if ! DISTANT="$(git ls-remote "$REPO_URL" "refs/heads/$BRANCH" 2>/dev/null | cut -f1)" || [ -z "$DISTANT" ]; then
    # Dépôt injoignable : on le note au plus une fois par heure, sans bruit.
    if [ ! -e "$ETAT/reseau-signale" ] || [ -n "$(find "$ETAT/reseau-signale" -mmin +60 2>/dev/null)" ]; then
        journal "Dépôt injoignable ($REPO_URL) : rien à faire, nouvel essai au prochain passage."
        : >"$ETAT/reseau-signale"
    fi
    exit 0
fi
rm -f "$ETAT/reseau-signale"

EN_LIGNE="$(sha_en_ligne)"

if [ "$FORCER" -eq 0 ]; then
    [ "$DISTANT" = "$EN_LIGNE" ] && exit 0

    if [ -r "$ETAT/gel-sha" ] && [ "$(cat "$ETAT/gel-sha")" = "$DISTANT" ]; then
        # Retour arrière demandé : cette version n'est pas redéployée tant que main n'a pas avancé.
        exit 0
    fi

    ECHECS="$(cat "$ETAT/echecs/$DISTANT" 2>/dev/null || echo 0)"
    if [ "$ECHECS" -ge "$MAX_ECHECS" ]; then
        # Trois échecs sur ce même commit : on attend un nouveau commit (ou --forcer) au lieu de boucler.
        exit 0
    fi
fi

# --------------------------------------------------------- déploiement
ETAPE="récupération du code"
RELEASE_DIR=""
BASCULE=0
# readlink -e : vide si « current » n'existe pas (readlink -f renverrait le chemin lui-même).
PRECEDENT="$(readlink -e "$APP_DIR/current" 2>/dev/null || true)"
PRECEDENT_NOM="aucune"
[ -n "$PRECEDENT" ] && PRECEDENT_NOM="$(basename "$PRECEDENT")"
JOURNAL_BUILD=""

nettoyer_echec() {
    if [ "$BASCULE" -eq 1 ]; then
        if [ -n "$PRECEDENT" ] && [ -d "$PRECEDENT" ]; then
            ln -sfn "$PRECEDENT" "$APP_DIR/current.new" && mv -T "$APP_DIR/current.new" "$APP_DIR/current"
            journal "Retour automatique à la version précédente : $(basename "$PRECEDENT")."
        else
            rm -f "$APP_DIR/current"
        fi
    fi
    [ -n "$RELEASE_DIR" ] && [ -d "$RELEASE_DIR" ] && rm -rf "$RELEASE_DIR"
    return 0
}

en_cas_d_echec() {
    local code=$?
    trap - ERR
    journal "ÉCHEC du déploiement de ${SHA:-$DISTANT} pendant : $ETAPE (code $code). Version en ligne : $PRECEDENT_NOM."
    if [ -n "$JOURNAL_BUILD" ] && [ -s "$JOURNAL_BUILD" ]; then
        { echo "    --- dernières lignes de la sortie ---"; tail -n 25 "$JOURNAL_BUILD" | sed 's/^/    /'; } >>"$LOG_FILE"
    fi
    echo $(( $(cat "$ETAT/echecs/${SHA:-$DISTANT}" 2>/dev/null || echo 0) + 1 )) >"$ETAT/echecs/${SHA:-$DISTANT}"
    nettoyer_echec
    exit 1
}
trap en_cas_d_echec ERR

exec_logue() { "$@" >>"$JOURNAL_BUILD" 2>&1; }

journal "Nouveau commit ${DISTANT:0:7} sur $BRANCH : début du déploiement."

DEPOT="$APP_DIR/repo"
if [ ! -d "$DEPOT/.git" ]; then
    git clone --quiet --depth 1 --branch "$BRANCH" --no-tags "$REPO_URL" "$DEPOT"
fi
git -C "$DEPOT" fetch --quiet --depth 1 --no-tags origin "$BRANCH"
SHA="$(git -C "$DEPOT" rev-parse FETCH_HEAD)"

NOM="$(date +%Y%m%d%H%M%S)-${SHA:0:7}"
RELEASE_DIR="$APP_DIR/releases/$NOM"
JOURNAL_BUILD="$APP_DIR/releases/$NOM.log"
mkdir -p "$RELEASE_DIR"
: >"$JOURNAL_BUILD"

git -C "$DEPOT" archive "$SHA" | tar -x --no-same-owner -C "$RELEASE_DIR"
printf '%s\n' "$SHA" >"$RELEASE_DIR/.sha"

# Données partagées entre les versions : réglages techniques (.env) et dossier storage (journaux, caches).
rm -rf "$RELEASE_DIR/storage"
ln -s "$SHARED/storage" "$RELEASE_DIR/storage"
ln -s "$ENV_FILE" "$RELEASE_DIR/.env"
cd "$RELEASE_DIR"

ETAPE="installation des dépendances PHP (composer)"
exec_logue "$PHP_BIN" "$(command -v composer)" install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-ansi

ETAPE="installation des dépendances du front (npm)"
exec_logue npm ci --no-audit --no-fund

ETAPE="compilation du front (npm run build)"
exec_logue npm run build
rm -rf "$RELEASE_DIR/node_modules"

ETAPE="préparation des caches"
exec_logue "$PHP_BIN" artisan view:cache --no-ansi
exec_logue "$PHP_BIN" artisan event:cache --no-ansi

ETAPE="sauvegarde de la base avant migration"
SAUVEGARDE="$APP_DIR/sauvegardes-deploiement/avant-$NOM.dump"
PGPASSWORD="$(env_get DB_PASSWORD)" exec_logue pg_dump --format=custom --no-owner \
    --host "$(env_get DB_HOST)" --port "$(env_get DB_PORT)" --username "$(env_get DB_USERNAME)" \
    --file "$SAUVEGARDE" "$(env_get DB_DATABASE)"
chmod 600 "$SAUVEGARDE"
# On ne garde que les 5 dernières sauvegardes de déploiement.
ls -1t "$APP_DIR/sauvegardes-deploiement"/avant-*.dump 2>/dev/null | tail -n +6 | xargs -r rm -f

ETAPE="migrations de la base de données"
exec_logue "$PHP_BIN" artisan migrate --force --no-ansi

# ----------------------------------------------------------- bascule
ETAPE="bascule vers la nouvelle version"
ln -sfn "$RELEASE_DIR" "$APP_DIR/current.new"
mv -T "$APP_DIR/current.new" "$APP_DIR/current"
BASCULE=1

ETAPE="vérification que le site répond"
BASE="${HEALTH_BASE:-https://$DOMAIN}"
RESOLVE=()
[ -z "${HEALTH_BASE:-}" ] && RESOLVE=(--resolve "$DOMAIN:443:127.0.0.1")
OK=0
for essai in 1 2 3 4 5 6; do
    sleep 2
    UP="$(curl -ksS --noproxy '*' -o /dev/null -m 15 -w '%{http_code}' "${RESOLVE[@]}" "$BASE/up" 2>>"$JOURNAL_BUILD" || true)"
    ACCUEIL="$(curl -ksS --noproxy '*' -o /dev/null -m 15 -w '%{http_code}' "${RESOLVE[@]}" "$BASE/" 2>>"$JOURNAL_BUILD" || true)"
    if [ "$UP" = "200" ] && [ "$ACCUEIL" = "200" ]; then OK=1; break; fi
    echo "essai $essai : /up=$UP /=$ACCUEIL" >>"$JOURNAL_BUILD"
done
[ "$OK" -eq 1 ]

# ------------------------------------------------------------ succès
trap - ERR
journal "Version $NOM (commit ${SHA:0:7}) en ligne. Précédente : $PRECEDENT_NOM."

# Le nettoyage ne doit jamais faire échouer un déploiement réussi : chaque commande est tolérante.
rm -f "$ETAT/echecs/$SHA" "$ETAT/gel-sha"
if [ -n "$PRECEDENT" ]; then basename "$PRECEDENT" >"$ETAT/precedente" || true; fi

# Compteurs d'échec des anciens commits : on garde les 20 plus récents.
{ ls -1t "$ETAT/echecs" 2>/dev/null | tail -n +21 | while read -r ancien; do rm -f "$ETAT/echecs/$ancien"; done; } || true

# Les journaux de construction des versions échouées n'ont plus de dossier : on garde les 10 plus récents.
{ ls -1t "$APP_DIR"/releases/*.log 2>/dev/null | tail -n +11 | xargs -r rm -f; } || true

# On garde la version en ligne et les plus récentes, 5 versions au total.
{ ls -1t "$APP_DIR/releases" | grep -v '\.log$' | grep -vxF "$NOM" | tail -n +"$KEEP_RELEASES" || true; } | while read -r ancien; do
    rm -rf "${APP_DIR:?}/releases/$ancien" "$APP_DIR/releases/$ancien.log"
done || true

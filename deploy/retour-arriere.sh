#!/usr/bin/env bash
#
# GAMAD — retour à la version précédente (Lot 0.1).
#
# Installé par deploy/install.sh sous le nom « gamad-reseau-retour-arriere ».
#
#   sudo gamad-reseau-retour-arriere            revient à la version juste avant celle qui est en ligne
#   sudo gamad-reseau-retour-arriere NOM        revient à une version précise (voir --liste)
#   sudo gamad-reseau-retour-arriere --liste    liste les versions conservées, sans rien changer
#
# Ce script ne change que le lien « current » (bascule instantanée). Il ne touche ni à la base de données (les
# migrations déjà appliquées ne sont PAS annulées), ni aux autres sites du serveur.
# Après un retour arrière, le déploiement automatique est mis en pause tant que main n'a pas de nouveau commit :
# sinon la version défaillante serait redéployée 5 minutes plus tard.

set -Eeuo pipefail

CONF="${GAMAD_CONF:-/etc/gamad-reseau/deploy.conf}"
# shellcheck disable=SC1090
. "$CONF"
# Variables attendues : APP_DIR APP_USER DOMAIN LOG_FILE

# Les fichiers de l'application appartiennent au compte dédié : on agit sous ce compte.
if [ "$(id -u)" -eq 0 ]; then
    exec runuser -u "$APP_USER" -- env GAMAD_CONF="$CONF" "$0" "$@"
fi

ETAT="$APP_DIR/shared/etat"
journal() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG_FILE"; }

versions() { find "$APP_DIR/releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort; }

EN_LIGNE="$(basename "$(readlink -e "$APP_DIR/current" 2>/dev/null || echo "")")"

if [ "${1:-}" = "--liste" ]; then
    echo "Versions conservées (la plus récente en bas) :"
    versions | while read -r v; do
        if [ "$v" = "$EN_LIGNE" ]; then echo "  $v   <- en ligne"; else echo "  $v"; fi
    done
    exit 0
fi

[ -n "$EN_LIGNE" ] || { echo "Aucune version n'est en ligne : rien à annuler." >&2; exit 1; }

CIBLE="${1:-}"
if [ -z "$CIBLE" ]; then
    CIBLE="$(versions | grep -B1 -xF "$EN_LIGNE" | head -n 1 || true)"
    if [ -z "$CIBLE" ] || [ "$CIBLE" = "$EN_LIGNE" ]; then
        echo "Aucune version plus ancienne n'est conservée : retour arrière impossible." >&2
        exit 1
    fi
fi

[ -d "$APP_DIR/releases/$CIBLE" ] || { echo "Version introuvable : $CIBLE (voir --liste)." >&2; exit 1; }
[ "$CIBLE" != "$EN_LIGNE" ] || { echo "Cette version est déjà en ligne." >&2; exit 1; }

# Pas de déploiement automatique pendant la bascule.
exec 9>"$APP_DIR/shared/.verrou"
flock -w 120 9 || { echo "Un déploiement est en cours depuis plus de 2 minutes : réessayez plus tard." >&2; exit 1; }

SHA_QUITTE="$(cat "$APP_DIR/releases/$EN_LIGNE/.sha" 2>/dev/null || true)"

ln -sfn "$APP_DIR/releases/$CIBLE" "$APP_DIR/current.new"
mv -T "$APP_DIR/current.new" "$APP_DIR/current"

BASE="${HEALTH_BASE:-https://$DOMAIN}"
RESOLVE=()
[ -z "${HEALTH_BASE:-}" ] && RESOLVE=(--resolve "$DOMAIN:443:127.0.0.1")
OK=0
for essai in 1 2 3 4 5 6; do
    sleep 2
    UP="$(curl -ksS --noproxy '*' -o /dev/null -m 15 -w '%{http_code}' "${RESOLVE[@]}" "$BASE/up" 2>/dev/null || true)"
    ACCUEIL="$(curl -ksS --noproxy '*' -o /dev/null -m 15 -w '%{http_code}' "${RESOLVE[@]}" "$BASE/" 2>/dev/null || true)"
    if [ "$UP" = "200" ] && [ "$ACCUEIL" = "200" ]; then OK=1; break; fi
done

if [ "$OK" -ne 1 ]; then
    ln -sfn "$APP_DIR/releases/$EN_LIGNE" "$APP_DIR/current.new"
    mv -T "$APP_DIR/current.new" "$APP_DIR/current"
    journal "Retour arrière vers $CIBLE ANNULÉ : le site ne répond pas avec cette version. $EN_LIGNE reste en ligne."
    echo "La version $CIBLE ne répond pas correctement : retour annulé, $EN_LIGNE reste en ligne." >&2
    exit 1
fi

# Gèle le déploiement automatique sur le commit quitté, jusqu'à ce que main avance.
mkdir -p "$ETAT"
if [ -n "$SHA_QUITTE" ]; then printf '%s\n' "$SHA_QUITTE" >"$ETAT/gel-sha"; fi

journal "RETOUR ARRIÈRE : $EN_LIGNE -> $CIBLE (demandé manuellement). Déploiement automatique en pause jusqu'au prochain commit sur main."
echo "Retour arrière effectué : $EN_LIGNE -> $CIBLE."
echo "- La base de données n'a pas été modifiée (les migrations déjà appliquées restent appliquées)."
echo "- Le déploiement automatique est en pause jusqu'au prochain commit sur main."

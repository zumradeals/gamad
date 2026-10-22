#!/usr/bin/env bash
#
# GAMAD — désinstallation (Lot 0.1).
#
# Retire UNIQUEMENT ce que deploy/install.sh a créé, d'après son fichier d'état /etc/gamad-reseau/etat :
# unités systemd, site Nginx, pool PHP-FPM, certificat, dossier de l'application, utilisateur dédié.
# Les autres sites, bases, pools et services du serveur ne sont jamais touchés.
#
# La base de données « gamad_reseau » n'est supprimée que sur une confirmation séparée.
# Les sauvegardes (/var/backups/gamad-reseau) et les journaux (/var/log/gamad-reseau) sont conservés.
#
#   sudo bash /usr/local/lib/gamad-reseau/desinstaller.sh

set -Eeuo pipefail

ETAT_DIR="/etc/gamad-reseau"
ETAT_FILE="$ETAT_DIR/etat"
CONF_FILE="$ETAT_DIR/deploy.conf"

[ "$(id -u)" -eq 0 ] || { echo "Ce script doit être lancé en administrateur (sudo)." >&2; exit 1; }
[ -f "$ETAT_FILE" ] || { echo "Aucune installation de GAMAD détectée (pas de $ETAT_FILE) : rien à retirer."; exit 0; }

APP_USER="gamad-reseau"; DB_NAME="gamad_reseau"; DB_USER="gamad_reseau"; APP_DIR="/srv/gamad-reseau"
DOMAINE="${GAMAD_DOMAINE:-reseau.dgafrique.com}"; POOL_NOM="gamad-reseau"
LIB_DIR="/usr/local/lib/gamad-reseau"; ACME_ROOT="/var/www/gamad-reseau-acme"; UNITES="/etc/systemd/system"
if [ -f "$CONF_FILE" ]; then
    # shellcheck disable=SC1090
    . "$CONF_FILE"
    DOMAINE="${DOMAIN:-$DOMAINE}"
fi

a() { grep -qxF "$1" "$ETAT_FILE"; }
ok() { printf '  ✔ %s\n' "$*"; }
lire() { local r=""; if [ -r /dev/tty ]; then read -r -p "$1" r </dev/tty; fi; printf '%s' "$r"; }

echo "GAMAD — désinstallation de $DOMAINE"
echo "Ce qui a été créé par l'installation (et sera retiré) :"
sed 's/^/  - /' "$ETAT_FILE"

if [ "${GAMAD_OUI:-0}" != "1" ]; then
    [ "$(lire $'\nTapez SUPPRIMER pour retirer ces éléments : ')" = "SUPPRIMER" ] || { echo "Annulé : rien n'a été modifié."; exit 1; }
fi

SUPPRIMER_BASE=0
if a "base" || a "role"; then
    if [ "${GAMAD_SUPPRIMER_BASE:-0}" = "1" ] || [ "$(lire $'\nSupprimer AUSSI la base de données « '"$DB_NAME"$' » et toutes ses données ? Tapez SUPPRIMER LA BASE (ou Entrée pour la conserver) : ')" = "SUPPRIMER LA BASE" ]; then
        SUPPRIMER_BASE=1
    fi
fi

# --- déploiement automatique et sauvegardes
if a "minuteurs" || a "unites"; then
    systemctl disable --now gamad-reseau-deploy.timer gamad-reseau-sauvegarde.timer >/dev/null 2>&1 || true
    systemctl stop gamad-reseau-deploy.service gamad-reseau-sauvegarde.service >/dev/null 2>&1 || true
    rm -f "$UNITES"/gamad-reseau-deploy.service "$UNITES"/gamad-reseau-deploy.timer \
          "$UNITES"/gamad-reseau-sauvegarde.service "$UNITES"/gamad-reseau-sauvegarde.timer
    rm -rf "$UNITES"/gamad-reseau-deploy.service.d "$UNITES"/gamad-reseau-sauvegarde.service.d
    systemctl daemon-reload
    ok "déploiement automatique et sauvegardes arrêtés"
fi

# --- site Nginx (rechargé seulement si la configuration reste valide)
if a "vhost-http" || a "vhost-https"; then
    rm -f "/etc/nginx/sites-enabled/$DOMAINE" "/etc/nginx/sites-available/$DOMAINE" "/etc/nginx/sites-available/$DOMAINE.http"
    if nginx -t >/dev/null 2>&1; then systemctl reload nginx; ok "site Nginx retiré (rechargement doux)"; else echo "  ! Nginx est en erreur pour une autre raison : je ne le recharge pas." >&2; fi
fi

# --- pool PHP-FPM
if a "pool"; then
    for fichier in /etc/php/*/fpm/pool.d/"$POOL_NOM".conf; do
        [ -e "$fichier" ] || continue
        version="$(echo "$fichier" | sed -E 's#/etc/php/([0-9.]+)/.*#\1#')"
        rm -f "$fichier"
        if "/usr/sbin/php-fpm$version" -t >/dev/null 2>&1; then systemctl reload "php$version-fpm"; fi
    done
    ok "pool PHP-FPM retiré"
fi

# --- certificat
if a "certificat" && [ -e "/etc/letsencrypt/live/$DOMAINE" ]; then
    certbot delete --cert-name "$DOMAINE" --non-interactive >/dev/null 2>&1 || rm -rf "/etc/letsencrypt/live/$DOMAINE" "/etc/letsencrypt/archive/$DOMAINE" "/etc/letsencrypt/renewal/$DOMAINE.conf"
    ok "certificat retiré"
fi

# --- base de données (confirmation séparée)
if [ "$SUPPRIMER_BASE" -eq 1 ]; then
    a "base" && runuser -u postgres -- psql -X -Atq -c "DROP DATABASE IF EXISTS $DB_NAME"
    a "role" && runuser -u postgres -- psql -X -Atq -c "DROP ROLE IF EXISTS $DB_USER"
    ok "base et rôle PostgreSQL supprimés"
elif a "base" || a "role"; then
    echo "  - base « $DB_NAME » et rôle « $DB_USER » CONSERVÉS (le mot de passe de ce rôle sera inutilisable sans le .env supprimé)."
fi

# --- application et fichiers annexes
rm -f /usr/local/sbin/gamad-reseau-retour-arriere /etc/logrotate.d/gamad-reseau
a "dossiers" && { rm -rf "${APP_DIR:?}" "${ACME_ROOT:?}"; ok "dossier $APP_DIR retiré"; }
a "utilisateur" && { userdel "$APP_USER" 2>/dev/null || true; ok "utilisateur $APP_USER retiré"; }

rm -rf "${LIB_DIR:?}" "$ETAT_DIR"
echo
echo "Désinstallation terminée. Conservés : /var/backups/gamad-reseau (sauvegardes) et /var/log/gamad-reseau (journaux)."

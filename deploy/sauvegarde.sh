#!/usr/bin/env bash
#
# GAMAD — sauvegarde quotidienne de la base (Lot 0.1).
#
# Installé par deploy/install.sh dans /usr/local/lib/gamad-reseau/sauvegarde.sh et lancé chaque nuit par un
# minuteur systemd, en administrateur. Il sauvegarde uniquement la base de GAMAD, plus une copie du fichier .env
# (la clé de chiffrement qu'il contient est indispensable pour relire les mots de passe enregistrés dans Paramètres).
# Les sauvegardes sont lisibles par l'administrateur seul. Les 7 plus récentes sont conservées.

set -Eeuo pipefail
umask 077

CONF="${GAMAD_CONF:-/etc/gamad-reseau/deploy.conf}"
# shellcheck disable=SC1090
. "$CONF"
# Variables attendues : APP_DIR DB_NAME BACKUP_DIR LOG_FILE_BACKUP BACKUP_KEEP

journal() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG_FILE_BACKUP"; }
trap 'journal "ÉCHEC de la sauvegarde (ligne $LINENO)."; exit 1' ERR

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

HORODATAGE="$(date +%Y-%m-%d_%H%M%S)"
FICHIER="$BACKUP_DIR/$DB_NAME-$HORODATAGE.dump"

# pg_dump s'exécute sous le compte « postgres » (authentification locale), jamais avec un mot de passe.
runuser -u postgres -- pg_dump --format=custom --no-owner "$DB_NAME" >"$FICHIER"

# Une sauvegarde qu'on ne peut pas relire ne vaut rien : on vérifie son contenu.
pg_restore --list "$FICHIER" >/dev/null

cp "$APP_DIR/shared/.env" "$BACKUP_DIR/env-$HORODATAGE"

# Conserve les $BACKUP_KEEP plus récentes de chaque sorte.
for motif in "$DB_NAME-*.dump" "env-*"; do
    { ls -1t "$BACKUP_DIR"/$motif 2>/dev/null || true; } | tail -n +"$((BACKUP_KEEP + 1))" | xargs -r rm -f
done

journal "Sauvegarde réussie : $(basename "$FICHIER") ($(du -h "$FICHIER" | cut -f1)). $(ls -1 "$BACKUP_DIR"/"$DB_NAME"-*.dump | wc -l) conservée(s)."

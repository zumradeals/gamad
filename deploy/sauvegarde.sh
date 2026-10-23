#!/usr/bin/env bash
#
# GAMAD — sauvegarde quotidienne (Lot 0.1, photos de profil depuis le Lot 1).
#
# Installé par deploy/install.sh dans /usr/local/lib/gamad-reseau/sauvegarde.sh et lancé chaque nuit par un
# minuteur systemd, en administrateur. Il sauvegarde uniquement ce qui appartient à GAMAD : la base, une copie du fichier
# .env (la clé de chiffrement qu'il contient est indispensable pour relire les mots de passe enregistrés dans Paramètres)
# et les photos de profil (storage/app/private/profile-photos du dossier partagé), dans une archive datée.
# Les sauvegardes sont lisibles par l'administrateur seul. Les 7 plus récentes de chaque sorte sont conservées.
# Pas de photo, ou dossier absent : ce n'est pas une erreur, il n'y a simplement rien à archiver.

set -Eeuo pipefail
umask 077

CONF="${GAMAD_CONF:-/etc/gamad-reseau/deploy.conf}"
# shellcheck disable=SC1090
. "$CONF"
# Variables attendues : APP_DIR DB_NAME BACKUP_DIR LOG_FILE_BACKUP BACKUP_KEEP

journal() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG_FILE_BACKUP"; }
en_cas_d_echec() {
    local ligne=$1
    # Dans un sous-shell ou une substitution $(...), l'échec est géré par le script principal : rien à journaliser ici.
    [ "$BASHPID" = "$$" ] || exit 1
    trap - ERR
    journal "ÉCHEC de la sauvegarde (ligne $ligne)."
    exit 1
}
trap 'en_cas_d_echec $LINENO' ERR

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

HORODATAGE="$(date +%Y-%m-%d_%H%M%S)"
FICHIER="$BACKUP_DIR/$DB_NAME-$HORODATAGE.dump"

# pg_dump s'exécute sous le compte « postgres » (authentification locale), jamais avec un mot de passe.
runuser -u postgres -- pg_dump --format=custom --no-owner "$DB_NAME" >"$FICHIER"

# Une sauvegarde qu'on ne peut pas relire ne vaut rien : on vérifie son contenu.
pg_restore --list "$FICHIER" >/dev/null

cp "$APP_DIR/shared/.env" "$BACKUP_DIR/env-$HORODATAGE"

# Photos de profil : une archive datée, seulement s'il y en a. Un dossier absent ou sans fichier ne fait pas échouer la
# sauvegarde. Une photo remplacée ou effacée pendant la lecture (code de sortie 1 de tar) n'est pas non plus une erreur ;
# tout autre échec (disque plein, archive illisible) en est une.
PHOTOS_RACINE="$APP_DIR/shared/storage/app/private"
PHOTOS_DOSSIER="$PHOTOS_RACINE/profile-photos"
PHOTOS_FICHIER="$BACKUP_DIR/photos-$HORODATAGE.tar.gz"
PHOTOS_ETAT="Aucune photo à archiver."
premiere_photo=""
if [ -d "$PHOTOS_DOSSIER" ]; then
    premiere_photo="$(find "$PHOTOS_DOSSIER" -type f -print -quit 2>/dev/null || true)"
fi
if [ -n "$premiere_photo" ]; then
    if tar -czf "$PHOTOS_FICHIER" -C "$PHOTOS_RACINE" profile-photos; then
        :
    else
        code_tar=$?
        if [ "$code_tar" -ne 1 ]; then
            rm -f "$PHOTOS_FICHIER"
            false
        fi
    fi
    tar -tzf "$PHOTOS_FICHIER" >/dev/null
    PHOTOS_ETAT="Photos archivées : $(basename "$PHOTOS_FICHIER")."
fi

# Conserve les $BACKUP_KEEP plus récentes de chaque sorte.
for motif in "$DB_NAME-*.dump" "env-*" "photos-*.tar.gz"; do
    { ls -1t "$BACKUP_DIR"/$motif 2>/dev/null || true; } | tail -n +"$((BACKUP_KEEP + 1))" | xargs -r rm -f
done

# Informations de confort : leur échec ne doit pas faire passer pour raté une sauvegarde réussie.
TAILLE="$(du -h "$FICHIER" | cut -f1 || true)"
NOMBRE="$(ls -1 "$BACKUP_DIR"/"$DB_NAME"-*.dump | wc -l || true)"
journal "Sauvegarde réussie : $(basename "$FICHIER") ($TAILLE). $NOMBRE conservée(s). $PHOTOS_ETAT"

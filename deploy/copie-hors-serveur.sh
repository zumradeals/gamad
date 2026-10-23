#!/usr/bin/env bash
#
# GAMAD — copie hors serveur chiffrée (Lot 5c, D-147).
#
# Installé par deploy/install.sh dans /usr/local/lib/gamad-reseau/copie-hors-serveur.sh, avec la commande
# « gamad-reseau-copie-hors-serveur », et lancé chaque nuit (après la sauvegarde locale) par un minuteur systemd.
#
# Il copie la sauvegarde locale la plus récente (base, .env, photos de profil) vers un stockage objet compatible S3 qui
# n'appartient pas au serveur, à travers rclone. Les fichiers sont chiffrés AVANT de quitter le serveur (rclone « crypt » :
# contenu et noms de fichiers) : le fournisseur du stockage ne lit rien. La clé de chiffrement est une phrase de secours que
# le Président conserve hors du serveur : sans elle, personne ne peut relire les copies.
#
# Les accès au stockage ne sont ni dans le dépôt ni dans ce script : le Président les saisit dans Espace → Paramètres → Copie
# hors serveur (D-155). Ils sont chiffrés en base ; à chaque lancement, ce script demande à l'application sa configuration rclone
# (php artisan gamad:copie-configuration), la range dans un fichier temporaire à droits restreints et le supprime à la fin.
#
# Commandes :
#   copier               (par défaut, lancée chaque nuit) copie et vérifie la dernière sauvegarde, puis retire les copies
#                        de plus de 15 jours ; rien n'est supprimé avant que la nouvelle copie soit vérifiée.
#   etat                 dit si la copie est configurée, la dernière réussite, le dernier échec, le dernier essai.
#   telecharger DOSSIER  rapatrie la dernière copie (déchiffrée) dans un dossier neuf, pour restaurer à la main.
#   essai-restauration   rapatrie la dernière copie, la restaure dans une base temporaire, compte ce qu'elle contient,
#                        puis supprime la base temporaire. Ne touche jamais à la base de production.
#
# L'état (dernière copie réussie, dernier essai, dernier incident) est aussi écrit dans un petit fichier lisible par l'application,
# qui l'affiche dans Espace → Paramètres → Copie hors serveur.

set -Eeuo pipefail
umask 077

CONF="${GAMAD_CONF:-/etc/gamad-reseau/deploy.conf}"
# shellcheck disable=SC1090
. "$CONF"
# Variables attendues : DB_NAME BACKUP_DIR. Facultatives : LOG_FILE_OFFSITE OFFSITE_KEEP_DAYS.

LOG_FILE_OFFSITE="${LOG_FILE_OFFSITE:-/var/log/gamad-reseau/copie-hors-serveur.log}"
OFFSITE_KEEP_DAYS="${OFFSITE_KEEP_DAYS:-15}"
# Fichier d'état lu par l'application (une ligne « clé=valeur » par information).
STATE_FILE="${GAMAD_BACKUP_STATE_FILE:-/var/lib/gamad-reseau/copie-etat}"
# GAMAD_RCLONE_CONF (essais) désigne un fichier de configuration rclone déjà prêt ; sinon il est demandé à l'application.
RCLONE_CONF="${GAMAD_RCLONE_CONF:-}"
CONFIGURE="non"
# Une sauvegarde locale plus ancienne que cela (en jours) n'est pas copiée : c'est la sauvegarde qui est en panne.
LOCALE_MAX_AGE_DAYS=2
DISTANT="gamad-copie:"
COMMANDE="${1:-copier}"
TMP=""

journal() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG_FILE_OFFSITE"; }
dire() { printf '%s\n' "$*"; }
CONF_TEMP=""
nettoyer() {
    if [ -n "$TMP" ]; then rm -rf "$TMP"; fi
    if [ -n "$CONF_TEMP" ]; then rm -f "$CONF_TEMP"; fi
}
trap nettoyer EXIT
en_cas_d_echec() {
    local ligne=$1
    # Dans un sous-shell ou une substitution $(...), l'échec est géré par le script principal : rien à journaliser ici.
    [ "$BASHPID" = "$$" ] || exit 1
    trap - ERR
    journal "ÉCHEC ($COMMANDE, ligne $ligne)."
    etat_ecrire dernier_echec "$(date -Iseconds)|ÉCHEC ($COMMANDE, ligne $ligne), voir $LOG_FILE_OFFSITE" || true
    printf 'ÉCHEC (%s, ligne %s). Détails : %s\n' "$COMMANDE" "$ligne" "$LOG_FILE_OFFSITE" >&2
    exit 1
}
trap 'en_cas_d_echec $LINENO' ERR

# Arrêt volontaire, avec un message qui dit quoi faire. Le message est écrit au journal et à l'écran.
refuser() {
    trap - ERR
    journal "REFUS ($COMMANDE) : $*"
    case "$COMMANDE" in
        copier | telecharger | essai-restauration) etat_ecrire dernier_echec "$(date -Iseconds)|$(printf '%s' "$*" | tr -d '\n\r|"\\' | cut -c1-200)" || true ;;
    esac
    printf '%s\n' "$*" >&2
    exit 1
}

mkdir -p "$(dirname "$LOG_FILE_OFFSITE")"

# Délais courts : un stockage injoignable doit donner une réponse nette, pas une attente de plusieurs minutes.
rc() { rclone --config "$RCLONE_CONF" --retries 3 --low-level-retries 3 --contimeout 15s --timeout 120s "$@"; }

# Écrit une information dans le fichier d'état (remplace l'ancienne valeur de la même clé). Sans dossier d'état, on ne fait rien :
# l'état est une commodité d'affichage, jamais une raison de faire échouer la copie.
etat_ecrire() {
    local cle=$1 valeur=$2 dossier tmp
    dossier="$(dirname "$STATE_FILE")"
    [ -d "$dossier" ] && [ -w "$dossier" ] || return 0
    tmp="$(mktemp "$dossier/.etat.XXXXXX")"
    { grep -v "^$cle=" "$STATE_FILE" 2>/dev/null || true; printf '%s=%s\n' "$cle" "$valeur"; } >"$tmp"
    chmod 644 "$tmp"
    mv -f "$tmp" "$STATE_FILE"
}

# Prépare la configuration rclone : fichier fourni (essais) ou demandée à l'application. CONFIGURE vaut « oui » ou « non ».
charger_configuration() {
    if [ -n "$RCLONE_CONF" ]; then
        if [ -f "$RCLONE_CONF" ]; then CONFIGURE="oui"; fi
        return 0
    fi
    local code=0
    CONF_TEMP="$(mktemp)"
    RCLONE_CONF="$CONF_TEMP"
    runuser -u "${APP_USER:-gamad-reseau}" -- "${PHP_BIN:-php}" "$APP_DIR/current/artisan" gamad:copie-configuration >"$CONF_TEMP" 2>/dev/null || code=$?
    case "$code" in
        0) CONFIGURE="oui" ;;
        2) CONFIGURE="non" ;;
        *) refuser "Impossible de lire la configuration de la copie dans l'application (code $code) : voir $APP_DIR/current/storage/logs." ;;
    esac
}

exiger_rclone() {
    command -v rclone >/dev/null 2>&1 || refuser "rclone n'est pas installé sur ce serveur. Relancez la ligne d'installation de GAMAD, ou installez-le avec : sudo apt-get install -y rclone"
}

exiger_configuration() {
    [ "$CONFIGURE" = "oui" ] || refuser "La copie hors serveur n'est pas configurée : renseignez les accès dans Espace → Paramètres → Copie hors serveur."
}

# Dernier fichier d'une sorte dans le dossier de sauvegarde locale (les noms portent la date et l'heure).
dernier_local() {
    local motif=$1
    { ls -1t "$BACKUP_DIR"/$motif 2>/dev/null || true; } | head -n 1
}

# Dernier fichier d'une sorte sur le stockage distant (les noms déchiffrés portent la date et l'heure : l'ordre est l'ordre alphabétique).
dernier_distant() {
    local dossier=$1
    { rc lsf --files-only "${DISTANT}${dossier}" 2>/dev/null || true; } | sort | tail -n 1
}

# ───────────────────────────────────────────── copier

copier() {
    if [ "$CONFIGURE" != "oui" ]; then
        journal "Copie hors serveur non configurée : rien n'a été copié. Renseignez les accès dans Espace → Paramètres → Copie hors serveur."
        dire "La copie hors serveur n'est pas configurée : rien n'a été copié."
        return 0
    fi
    exiger_rclone

    local dump env photos
    dump="$(dernier_local "$DB_NAME-*.dump")"
    [ -n "$dump" ] || refuser "Aucune sauvegarde locale à copier dans $BACKUP_DIR : la sauvegarde nocturne est en panne."
    if [ -n "$(find "$dump" -mtime +"$LOCALE_MAX_AGE_DAYS" -print 2>/dev/null || true)" ]; then
        refuser "La dernière sauvegarde locale ($(basename "$dump")) date de plus de $LOCALE_MAX_AGE_DAYS jours : la sauvegarde nocturne est en panne, rien n'est copié."
    fi
    env="$(dernier_local "env-*")"
    photos="$(dernier_local "photos-*.tar.gz")"
    [ -n "$env" ] || refuser "Aucune copie du fichier .env dans $BACKUP_DIR : la sauvegarde nocturne est incomplète."

    TMP="$(mktemp -d)"
    mkdir "$TMP/base" "$TMP/env" "$TMP/photos"
    cp "$dump" "$TMP/base/"
    cp "$env" "$TMP/env/"
    if [ -n "$photos" ]; then cp "$photos" "$TMP/photos/"; fi

    # Copie chiffrée, puis relecture : la copie n'est « réussie » que si chaque fichier distant redonne le même contenu.
    rc copy "$TMP" "$DISTANT"
    rc cryptcheck "$TMP" "$DISTANT" --one-way

    # Rien n'est supprimé avant que la nouvelle copie soit vérifiée.
    local sorte
    for sorte in base env photos; do
        rc delete "${DISTANT}${sorte}" --min-age "${OFFSITE_KEEP_DAYS}d" 2>/dev/null || true
    done

    local noms
    noms="$(basename "$dump"), $(basename "$env")"
    if [ -n "$photos" ]; then noms="$noms, $(basename "$photos")"; fi
    journal "Copie hors serveur réussie et vérifiée : $noms."
    etat_ecrire derniere_reussite "$(date -Iseconds)"
    dire "Copie hors serveur réussie et vérifiée : $noms."
}

# ───────────────────────────────────────────── telecharger, essai de restauration

telecharger() {
    exiger_rclone
    exiger_configuration

    local cible=${1:-}
    [ -n "$cible" ] || refuser "Indiquez le dossier où déposer la copie : telecharger /chemin/vers/un/dossier"
    if [ -e "$cible" ] && [ -n "$(ls -A "$cible" 2>/dev/null || true)" ]; then
        refuser "Le dossier $cible n'est pas vide : donnez un dossier neuf."
    fi
    mkdir -p "$cible"

    local dump env photos
    dump="$(dernier_distant base)"
    env="$(dernier_distant env)"
    photos="$(dernier_distant photos)"
    [ -n "$dump" ] || refuser "Aucune copie de la base lisible sur le stockage distant : lancez d'abord « copier », ou vérifiez que la phrase de secours est bien celle utilisée pour les copies."
    [ -n "$env" ] || refuser "Aucune copie du fichier .env sur le stockage distant."

    rc copyto "${DISTANT}base/$dump" "$cible/$dump"
    rc copyto "${DISTANT}env/$env" "$cible/$env"
    if [ -n "$photos" ]; then rc copyto "${DISTANT}photos/$photos" "$cible/$photos"; fi

    # Ce qu'on rapatrie doit être lisible : sinon la copie ne vaut rien.
    pg_restore --list "$cible/$dump" >/dev/null
    grep -q '^APP_KEY=' "$cible/$env"
    if [ -n "$photos" ]; then tar -tzf "$cible/$photos" >/dev/null; fi

    DUMP_TELECHARGE="$cible/$dump"
    NOM_DUMP="$dump"
    NOM_ENV="$env"
    NOM_PHOTOS="$photos"
    journal "Copie rapatriée et lisible : $dump, $env${photos:+, $photos}."
}

essai_restauration() {
    exiger_rclone
    exiger_configuration

    local base_essai="${DB_NAME}_essai_restauration"
    [ "$base_essai" != "$DB_NAME" ] || refuser "Le nom de la base d'essai ne peut pas être celui de la base de production."

    TMP="$(mktemp -d)"
    DUMP_TELECHARGE="" NOM_DUMP="" NOM_ENV="" NOM_PHOTOS=""
    telecharger "$TMP/copie"

    runuser -u postgres -- dropdb --if-exists "$base_essai"
    runuser -u postgres -- createdb "$base_essai"
    local tables migrations personnes
    # Le dump est lu par l'entrée standard : le compte « postgres » n'a pas accès au dossier temporaire de l'administrateur.
    if ! runuser -u postgres -- pg_restore --no-owner -d "$base_essai" <"$DUMP_TELECHARGE"; then
        runuser -u postgres -- dropdb --if-exists "$base_essai" || true
        refuser "La restauration dans la base d'essai a échoué : la copie est illisible ou incomplète."
    fi
    tables="$(runuser -u postgres -- psql -tA -d "$base_essai" -c "select count(*) from information_schema.tables where table_schema = 'public'")"
    migrations="$(runuser -u postgres -- psql -tA -d "$base_essai" -c "select count(*) from migrations")"
    personnes="$(runuser -u postgres -- psql -tA -d "$base_essai" -c "select count(*) from users")"
    runuser -u postgres -- dropdb "$base_essai"

    if [ "${tables:-0}" -lt 1 ] || [ "${migrations:-0}" -lt 1 ]; then
        refuser "La base restaurée est vide ($tables tables, $migrations migrations) : la copie ne vaut rien."
    fi

    journal "ESSAI DE RESTAURATION RÉUSSI : $NOM_DUMP restauré dans une base temporaire ($tables tables, $migrations migrations, $personnes personnes), puis supprimée ; $NOM_ENV lisible${NOM_PHOTOS:+ ; $NOM_PHOTOS lisible}."
    etat_ecrire dernier_essai "$(date -Iseconds)"
    dire "ESSAI DE RESTAURATION RÉUSSI : $NOM_DUMP"
    dire "  base restaurée : $tables tables, $migrations migrations, $personnes personnes (base d'essai supprimée)"
    dire "  fichier .env lisible : $NOM_ENV"
    if [ -n "$NOM_PHOTOS" ]; then dire "  photos lisibles : $NOM_PHOTOS"; fi
}

# ───────────────────────────────────────────── etat

derniere_ligne() {
    local motif=$1
    { grep -E "$motif" "$LOG_FILE_OFFSITE" 2>/dev/null || true; } | tail -n 1
}

etat() {
    if [ "$CONFIGURE" != "oui" ]; then
        dire "Copie hors serveur : NON CONFIGURÉE. Aucune copie n'existe hors du serveur."
        dire "Pour la configurer : Espace → Paramètres → Copie hors serveur."
        return 0
    fi
    local reussite echec essai
    reussite="$(derniere_ligne 'Copie hors serveur réussie')"
    echec="$(derniere_ligne 'ÉCHEC|REFUS \((copier|telecharger|essai-restauration)\)')"
    essai="$(derniere_ligne 'ESSAI DE RESTAURATION RÉUSSI')"
    dire "Copie hors serveur : configurée."
    dire "Dernière réussite : ${reussite:-aucune pour le moment}"
    if [ -n "$echec" ] && [ -n "$reussite" ] && [[ "$echec" < "$reussite" ]]; then
        dire "Dernier échec (depuis : une copie a réussi) : $echec"
    else
        dire "Dernier échec ou refus : ${echec:-aucun}"
    fi
    dire "Dernier essai de restauration : ${essai:-JAMAIS FAIT — lancez : sudo gamad-reseau-copie-hors-serveur essai-restauration}"
}

case "$COMMANDE" in
    copier | etat | telecharger | essai-restauration) ;;
    *) refuser "Commande inconnue : $COMMANDE. Commandes : copier, etat, telecharger, essai-restauration." ;;
esac

charger_configuration

case "$COMMANDE" in
    copier) copier ;;
    etat) etat ;;
    telecharger) telecharger "${2:-}" ;;
    essai-restauration) essai_restauration ;;
esac

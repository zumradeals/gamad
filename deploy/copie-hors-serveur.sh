#!/usr/bin/env bash
#
# GAMAD — copie hors serveur chiffrée (Lot 5c, D-147).
#
# Installé par deploy/install.sh dans /usr/local/lib/gamad-reseau/copie-hors-serveur.sh, avec la commande
# « gamad-reseau-copie-hors-serveur », et lancé chaque nuit (après la sauvegarde locale) par un minuteur systemd.
#
# Il copie la sauvegarde locale la plus récente (base, .env, photos de profil) vers un stockage objet compatible S3 qui
# n'appartient pas au serveur, à travers rclone. Les fichiers sont chiffrés AVANT de quitter le serveur (rclone « crypt » :
# contenu et noms de fichiers) : le fournisseur du stockage ne lit rien. La clé de chiffrement est dérivée d'une phrase de
# secours que le Président conserve hors du serveur : sans elle, personne ne peut relire les copies.
#
# Les accès au stockage ne sont jamais dans le dépôt : ils sont saisis sur le serveur par « configurer » et gardés dans
# /etc/gamad-reseau/rclone.conf, lisible par l'administrateur seul.
#
# Commandes :
#   copier               (par défaut, lancée chaque nuit) copie et vérifie la dernière sauvegarde, puis retire les copies
#                        de plus de 15 jours ; rien n'est supprimé avant que la nouvelle copie soit vérifiée.
#   configurer           enregistre le stockage, génère la phrase de secours, teste l'écriture puis la relecture.
#   etat                 dit si la copie est configurée, la dernière réussite, le dernier échec, le dernier essai.
#   telecharger DOSSIER  rapatrie la dernière copie (déchiffrée) dans un dossier neuf, pour restaurer à la main.
#   essai-restauration   rapatrie la dernière copie, la restaure dans une base temporaire, compte ce qu'elle contient,
#                        puis supprime la base temporaire. Ne touche jamais à la base de production.

set -Eeuo pipefail
umask 077

CONF="${GAMAD_CONF:-/etc/gamad-reseau/deploy.conf}"
# shellcheck disable=SC1090
. "$CONF"
# Variables attendues : DB_NAME BACKUP_DIR. Facultatives : LOG_FILE_OFFSITE OFFSITE_KEEP_DAYS.

LOG_FILE_OFFSITE="${LOG_FILE_OFFSITE:-/var/log/gamad-reseau/copie-hors-serveur.log}"
OFFSITE_KEEP_DAYS="${OFFSITE_KEEP_DAYS:-15}"
RCLONE_CONF="${GAMAD_RCLONE_CONF:-/etc/gamad-reseau/rclone.conf}"
# Une sauvegarde locale plus ancienne que cela (en jours) n'est pas copiée : c'est la sauvegarde qui est en panne.
LOCALE_MAX_AGE_DAYS=2
DISTANT="gamad-copie:"
COMMANDE="${1:-copier}"
TMP=""

journal() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG_FILE_OFFSITE"; }
dire() { printf '%s\n' "$*"; }
nettoyer() {
    if [ -n "$TMP" ]; then rm -rf "$TMP"; fi
}
trap nettoyer EXIT
en_cas_d_echec() {
    local ligne=$1
    # Dans un sous-shell ou une substitution $(...), l'échec est géré par le script principal : rien à journaliser ici.
    [ "$BASHPID" = "$$" ] || exit 1
    trap - ERR
    journal "ÉCHEC ($COMMANDE, ligne $ligne)."
    printf 'ÉCHEC (%s, ligne %s). Détails : %s\n' "$COMMANDE" "$ligne" "$LOG_FILE_OFFSITE" >&2
    exit 1
}
trap 'en_cas_d_echec $LINENO' ERR

# Arrêt volontaire, avec un message qui dit quoi faire. Le message est écrit au journal et à l'écran.
refuser() {
    trap - ERR
    journal "REFUS ($COMMANDE) : $*"
    printf '%s\n' "$*" >&2
    exit 1
}

mkdir -p "$(dirname "$LOG_FILE_OFFSITE")"

# Délais courts : un stockage injoignable doit donner une réponse nette, pas une attente de plusieurs minutes.
rc() { rclone --config "$RCLONE_CONF" --retries 3 --low-level-retries 3 --contimeout 15s --timeout 120s "$@"; }

exiger_rclone() {
    command -v rclone >/dev/null 2>&1 || refuser "rclone n'est pas installé sur ce serveur. Relancez la ligne d'installation de GAMAD, ou installez-le avec : sudo apt-get install -y rclone"
}

exiger_configuration() {
    [ -f "$RCLONE_CONF" ] || refuser "La copie hors serveur n'est pas configurée. Lancez : sudo gamad-reseau-copie-hors-serveur configurer"
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
    if [ ! -f "$RCLONE_CONF" ]; then
        journal "Copie hors serveur non configurée : rien n'a été copié. Lancez : sudo gamad-reseau-copie-hors-serveur configurer"
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
    dire "Copie hors serveur réussie et vérifiée : $noms."
}

# ───────────────────────────────────────────── configurer

demander() {
    local nom=$1 question=$2 secret=${3:-non} valeur
    valeur="${!nom:-}"
    if [ -z "$valeur" ]; then
        [ -t 0 ] || refuser "Information manquante : $question (variable $nom)."
        if [ "$secret" = "oui" ]; then
            read -r -s -p "$question : " valeur
            printf '\n' >&2
        else
            read -r -p "$question : " valeur
        fi
    fi
    printf -v "$nom" '%s' "$valeur"
}

generer_phrase() {
    # 32 caractères alphanumériques (environ 190 bits), par groupes de 4 pour être recopiés sans erreur.
    local brut
    brut="$(head -c 48 /dev/urandom | base64 | tr -d '=+/\n' | cut -c1-32)"
    printf '%s' "$brut" | sed -E 's/(.{4})/\1-/g; s/-$//'
}

configurer() {
    exiger_rclone

    if [ -f "$RCLONE_CONF" ] && [ "${2:-}" != "--forcer" ]; then
        refuser "La copie hors serveur est déjà configurée. La reconfigurer avec une autre phrase de secours rendrait illisibles les copies déjà faites. Pour la reconfigurer quand même : sudo gamad-reseau-copie-hors-serveur configurer --forcer"
    fi

    local GAMAD_S3_ENDPOINT="${GAMAD_S3_ENDPOINT:-}" GAMAD_S3_BUCKET="${GAMAD_S3_BUCKET:-}" GAMAD_S3_ACCESS_KEY="${GAMAD_S3_ACCESS_KEY:-}" GAMAD_S3_SECRET_KEY="${GAMAD_S3_SECRET_KEY:-}" GAMAD_S3_REGION="${GAMAD_S3_REGION:-}"
    demander GAMAD_S3_ENDPOINT "Adresse du stockage (https://…)"
    demander GAMAD_S3_BUCKET "Nom du bucket"
    demander GAMAD_S3_ACCESS_KEY "Clé d'accès"
    demander GAMAD_S3_SECRET_KEY "Clé secrète (invisible pendant la saisie)" oui

    case "$GAMAD_S3_ENDPOINT" in
        https://*) ;;
        # Seul un stockage sur ce même serveur (essais) peut se passer de https.
        http://127.0.0.1[:/]* | http://localhost[:/]*) ;;
        *) refuser "L'adresse du stockage doit commencer par https:// : une copie ne doit pas voyager sans chiffrement de la connexion." ;;
    esac
    [[ "$GAMAD_S3_BUCKET" =~ ^[a-z0-9][a-z0-9.-]{1,62}$ ]] || refuser "Le nom du bucket n'est pas valide (lettres minuscules, chiffres, points et tirets, de 2 à 63 caractères)."
    case "$GAMAD_S3_ACCESS_KEY$GAMAD_S3_SECRET_KEY" in
        *[[:space:]]*) refuser "Les clés d'accès ne doivent contenir aucun espace." ;;
    esac

    # Phrase de secours : fournie (nouveau serveur, mêmes copies à relire) ou générée.
    local phrase="${GAMAD_PHRASE_SECOURS:-}" generee="non"
    if [ -z "$phrase" ]; then
        phrase="$(generer_phrase)"
        generee="oui"
    fi
    [ "${#phrase}" -ge 16 ] || refuser "La phrase de secours est trop courte (16 caractères au moins)."

    local mot1 sel mot2
    mot1="$(printf '%s' "$phrase" | rclone obscure -)"
    sel="$(printf '%s' "gamad-sel:$phrase" | sha256sum | cut -c1-40)"
    mot2="$(printf '%s' "$sel" | rclone obscure -)"

    local ancien=""
    if [ -f "$RCLONE_CONF" ]; then
        ancien="$(mktemp)"
        cp "$RCLONE_CONF" "$ancien"
    fi
    mkdir -p "$(dirname "$RCLONE_CONF")"
    {
        printf '[gamad-s3]\ntype = s3\nprovider = Other\nenv_auth = false\n'
        printf 'access_key_id = %s\nsecret_access_key = %s\nendpoint = %s\n' "$GAMAD_S3_ACCESS_KEY" "$GAMAD_S3_SECRET_KEY" "$GAMAD_S3_ENDPOINT"
        if [ -n "$GAMAD_S3_REGION" ]; then printf 'region = %s\n' "$GAMAD_S3_REGION"; fi
        printf '\n[gamad-copie]\ntype = crypt\nremote = gamad-s3:%s/gamad-reseau\n' "$GAMAD_S3_BUCKET"
        printf 'filename_encryption = standard\ndirectory_name_encryption = true\npassword = %s\npassword2 = %s\n' "$mot1" "$mot2"
    } >"$RCLONE_CONF"
    chmod 600 "$RCLONE_CONF"

    # Essai réel : écrire, relire et effacer un petit fichier à travers le chiffrement. Sans cela, la configuration n'est pas gardée.
    local temoin="gamad-essai-$(date +%s)" lu="" erreur=""
    if erreur="$(printf '%s' "$temoin" | rc rcat "${DISTANT}essai/connexion.txt" 2>&1)"; then
        lu="$(rc cat "${DISTANT}essai/connexion.txt" 2>/dev/null || true)"
        rc deletefile "${DISTANT}essai/connexion.txt" 2>/dev/null || true
    fi
    if [ "$lu" != "$temoin" ]; then
        if [ -n "$ancien" ]; then cp "$ancien" "$RCLONE_CONF"; rm -f "$ancien"; else rm -f "$RCLONE_CONF"; fi
        # La réponse de rclone ne contient jamais les clés : elle explique le refus (accès, bucket, réseau).
        journal "Essai d'écriture refusé par le stockage : $(printf '%s' "$erreur" | tail -n 1)"
        refuser "Le stockage n'a pas accepté l'essai d'écriture et de relecture : vérifiez l'adresse, le nom du bucket et les clés. Rien n'a été enregistré."
    fi
    if [ -n "$ancien" ]; then rm -f "$ancien"; fi

    journal "Copie hors serveur configurée : stockage $GAMAD_S3_ENDPOINT, bucket $GAMAD_S3_BUCKET. Essai d'écriture et de relecture réussi."
    dire "Copie hors serveur configurée : l'essai d'écriture et de relecture a réussi."
    if [ "$generee" = "oui" ]; then
        dire ""
        dire "PHRASE DE SECOURS — à noter maintenant, hors de ce serveur (gestionnaire de mots de passe, papier en lieu sûr) :"
        dire ""
        dire "    $phrase"
        dire ""
        dire "Elle ne sera plus jamais affichée. Sans elle, les copies sont illisibles pour tout le monde, y compris pour GAMAD."
        dire "Avec elle, un nouveau serveur peut relire toutes les copies : configurer → essai-restauration."
    fi
    dire ""
    dire "Étape suivante : sudo gamad-reseau-copie-hors-serveur copier   (puis essai-restauration)"
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
    if [ ! -f "$RCLONE_CONF" ]; then
        dire "Copie hors serveur : NON CONFIGURÉE. Aucune copie n'existe hors du serveur."
        dire "Pour la configurer : sudo gamad-reseau-copie-hors-serveur configurer"
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
    copier) copier ;;
    configurer) configurer "$@" ;;
    etat) etat ;;
    telecharger) telecharger "${2:-}" ;;
    essai-restauration) essai_restauration ;;
    *) refuser "Commande inconnue : $COMMANDE. Commandes : copier, configurer, etat, telecharger, essai-restauration." ;;
esac

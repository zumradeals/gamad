#!/usr/bin/env bash
#
# GAMAD — installation sur le serveur (Lot 0.1), à lancer UNE SEULE FOIS :
#
#   curl -fsSL https://raw.githubusercontent.com/zumradeals/gamad/main/deploy/install.sh | sudo bash
#
# Ce que fait ce script, et seulement cela :
#   - vérifie le serveur AVANT toute modification et refuse de continuer au moindre doute ;
#   - n'installe que les outils qui manquent ; ne met à jour ni ne supprime aucun paquet existant ;
#   - crée ses propres éléments, aux noms dédiés : utilisateur « gamad-reseau », base et rôle PostgreSQL
#     « gamad_reseau », pool PHP-FPM « gamad-reseau », site Nginx « reseau.dgafrique.com », certificat HTTPS ;
#   - ne modifie AUCUN fichier de configuration d'un autre site, ne touche ni au pare-feu ni aux autres services
#     (Nginx et PHP-FPM sont seulement rechargés en douceur, après vérification de leur configuration) ;
#   - écrit le fichier .env tout seul (amorçage technique : base de données et clé de chiffrement) ;
#   - demande le nom, l'e-mail et le mot de passe du Président, et crée son compte (vérifié, super administrateur) ;
#   - met en place les sauvegardes quotidiennes de la base et le déploiement automatique ;
#   - affiche un résumé en français, sans aucun secret.
#
# Si une étape échoue, le script s'arrête proprement et dit laquelle ; relancer la même ligne reprend là où il
# s'est arrêté. Pour voir ce qu'il ferait sans rien modifier : GAMAD_SIMULATION=1 (juste avant « sudo »).
# Pour tout défaire : deploy/desinstaller.sh.

set -Eeuo pipefail
umask 022

# --------------------------------------------------------------- constantes
DOMAINE="${GAMAD_DOMAINE:-reseau.dgafrique.com}"
DEPOT="${GAMAD_DEPOT:-https://github.com/zumradeals/gamad.git}"
BRANCHE="${GAMAD_BRANCHE:-main}"
SIMULATION="${GAMAD_SIMULATION:-0}"
MODE_TEST="${GAMAD_TEST_MODE:-0}"   # réservé aux tests automatiques : certificat auto-signé, saisies par variables

APP_USER="gamad-reseau"
DB_NAME="gamad_reseau"
DB_USER="gamad_reseau"
APP_DIR="/srv/gamad-reseau"
ETAT_DIR="/etc/gamad-reseau"
ETAT_FILE="$ETAT_DIR/etat"
CONF_FILE="$ETAT_DIR/deploy.conf"
LOG_DIR="/var/log/gamad-reseau"
BACKUP_DIR="/var/backups/gamad-reseau"
LIB_DIR="/usr/local/lib/gamad-reseau"
ACME_ROOT="/var/www/gamad-reseau-acme"
POOL_NOM="gamad-reseau"
SOCKET="/run/php/gamad-reseau.sock"
VHOST="/etc/nginx/sites-available/$DOMAINE"
VHOST_LIEN="/etc/nginx/sites-enabled/$DOMAINE"
UNITES="/etc/systemd/system"

PHP_V=""
PG_PORT=""
NGINX_USER="www-data"
ETAPE="préparation"
PRESIDENT_NOM=""
PRESIDENT_EMAIL=""
PRESIDENT_MDP=""
DB_MDP=""
A_INSTALLER=()

# ------------------------------------------------------------------ affichage
info()   { printf '  %s\n' "$*"; }
ok()     { printf '  ✔ %s\n' "$*"; }
avertir(){ printf '  ! %s\n' "$*"; }
titre()  { ETAPE="$1"; printf '\n== %s ==\n' "$1"; }

arreter() {
    printf '\n✘ %s\n' "$1" >&2
    shift
    for ligne in "$@"; do printf '  %s\n' "$ligne" >&2; done
    if [ -f "$ETAT_FILE" ]; then
        printf '\n  Seuls les éléments listés dans %s ont été créés ; aucun autre site ni service n'"'"'a été touché.\n' "$ETAT_FILE" >&2
    else
        printf '\n  Rien n'"'"'a été modifié sur le serveur.\n' >&2
    fi
    exit 1
}

sur_erreur() {
    local code=$?
    trap - ERR
    printf '\n✘ L'"'"'installation s'"'"'est arrêtée pendant l'"'"'étape : %s (code %s).\n' "$ETAPE" "$code" >&2
    printf '  Les autres sites du serveur n'"'"'ont pas été touchés.\n' >&2
    printf '  Détails : %s/installation.log\n' "$LOG_DIR" >&2
    printf '  Après correction, relancez la même ligne : l'"'"'installation reprend là où elle s'"'"'est arrêtée.\n' >&2
    printf '  Pour tout défaire : sudo bash %s/desinstaller.sh\n' "$LIB_DIR" >&2
    exit "$code"
}
trap sur_erreur ERR

# --------------------------------------------------------------------- état
etat_a()      { [ -f "$ETAT_FILE" ] && grep -qxF "$1" "$ETAT_FILE"; }
etat_marquer(){ mkdir -p "$ETAT_DIR"; chmod 755 "$ETAT_DIR"; etat_a "$1" || printf '%s\n' "$1" >>"$ETAT_FILE"; }

execute() {   # description ; commande... — en simulation, affiche seulement ce qui serait fait
    local description="$1"; shift
    if [ "$SIMULATION" = "1" ]; then info "[simulation] $description"; return 0; fi
    "$@"
}

pg() { runuser -u postgres -- psql -X -v ON_ERROR_STOP=1 -Atq "$@"; }

rendre() {   # remplace les marqueurs @@NOM@@ d'un modèle lu sur l'entrée standard
    sed -e "s#@@DOMAINE@@#$DOMAINE#g" -e "s#@@APP_DIR@@#$APP_DIR#g" -e "s#@@APP_USER@@#$APP_USER#g" \
        -e "s#@@SOCKET@@#$SOCKET#g" -e "s#@@ACME_ROOT@@#$ACME_ROOT#g" -e "s#@@PHP_V@@#$PHP_V#g" \
        -e "s#@@POOL_NOM@@#$POOL_NOM#g" -e "s#@@NGINX_USER@@#$NGINX_USER#g" -e "s#@@LOG_DIR@@#$LOG_DIR#g" \
        -e "s#@@LIB_DIR@@#$LIB_DIR#g"
}

# ------------------------------------------------------------- paquets (garde)
apt_installer() {   # paquets... — n'installe que ce qui manque, refuse toute mise à jour ou suppression
    local manquants=() p simulation
    for p in "$@"; do dpkg -s "$p" >/dev/null 2>&1 || manquants+=("$p"); done
    [ "${#manquants[@]}" -eq 0 ] && return 0

    if [ "$SIMULATION" = "1" ]; then info "[simulation] installerait : ${manquants[*]}"; return 0; fi

    apt-get update -qq >/dev/null
    simulation="$(apt-get -s install --no-install-recommends --no-upgrade "${manquants[@]}" 2>&1)" \
        || arreter "Impossible d'installer : ${manquants[*]}" "Le gestionnaire de paquets répond : $(printf '%s' "$simulation" | tail -n 3 | tr '\n' ' ')"
    if grep -qE '^Remv ' <<<"$simulation"; then
        arreter "Installer ${manquants[*]} supprimerait un paquet existant : je ne le fais pas." "$(grep -E '^Remv ' <<<"$simulation" | head -n 3)"
    fi
    if grep -qE '^Inst [^ ]+ \[' <<<"$simulation"; then
        arreter "Installer ${manquants[*]} mettrait à jour un paquet existant : je ne le fais pas." "$(grep -E '^Inst [^ ]+ \[' <<<"$simulation" | head -n 3)"
    fi
    DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l NEEDRESTART_SUSPEND=1 \
        apt-get install -y -qq --no-install-recommends --no-upgrade "${manquants[@]}" </dev/null >/dev/null
    ok "installé : ${manquants[*]}"
}

# ====================================================================== 1. contrôles
controles() {
    titre "Contrôles préalables (aucune modification)"

    [ "$(id -u)" -eq 0 ] || arreter "Ce script doit être lancé en administrateur (sudo)."
    [ -d /run/systemd/system ] || arreter "systemd est requis (déploiement automatique et sauvegardes)."

    . /etc/os-release
    if [ "${ID:-}" != "ubuntu" ] && [ "${GAMAD_FORCER:-0}" != "1" ]; then
        arreter "Système non prévu : ${PRETTY_NAME:-inconnu}. Ce script est écrit pour Ubuntu." "Pour passer outre à vos risques : GAMAD_FORCER=1."
    fi
    ok "Système : ${PRETTY_NAME:-inconnu}"

    local libre_ko
    libre_ko="$(df -Pk "$(dirname "$APP_DIR")" | awk 'NR==2 {print $4}')"
    [ "${libre_ko:-0}" -gt 2000000 ] || arreter "Moins de 2 Go libres pour $(dirname "$APP_DIR") : espace insuffisant."
    ok "Espace disque suffisant"

    # --- Node : jamais installé automatiquement (la version des dépôts Ubuntu est trop ancienne).
    if ! command -v node >/dev/null 2>&1; then
        arreter "Node.js est absent. Il faut Node 20.19 ou plus (ou 22.12 ou plus) pour compiler le front." "Je ne l'installe pas moi-même, pour ne pas toucher à vos dépôts de paquets."
    fi
    local node_v node_major node_minor
    node_v="$(node --version | sed 's/^v//')"; node_major="${node_v%%.*}"; node_minor="$(cut -d. -f2 <<<"$node_v")"
    if [ "$node_major" -lt 20 ] || { [ "$node_major" -eq 20 ] && [ "$node_minor" -lt 19 ]; } || { [ "$node_major" -eq 21 ]; } || { [ "$node_major" -eq 22 ] && [ "$node_minor" -lt 12 ]; }; then
        arreter "Node $node_v est trop ancien pour compiler le front (il faut 20.19+, ou 22.12+)."
    fi
    ok "Node $node_v"

    # --- PHP : version la plus récente déjà installée avec PHP-FPM.
    local v
    for v in 8.4 8.3; do
        if [ -x "/usr/sbin/php-fpm$v" ] && [ -x "/usr/bin/php$v" ]; then PHP_V="$v"; break; fi
    done
    if [ -z "$PHP_V" ]; then
        PHP_V="8.3"
        if ! apt-cache policy "php$PHP_V-fpm" 2>/dev/null | grep -q 'Candidate: [0-9]'; then
            arreter "Aucun PHP 8.3 ou 8.4 avec PHP-FPM n'est installé, et PHP 8.3 n'est pas disponible dans vos dépôts." "Installez PHP 8.3 ou 8.4 (avec PHP-FPM), puis relancez."
        fi
        A_INSTALLER+=("php$PHP_V-fpm" "php$PHP_V-cli" "php$PHP_V-common")
        avertir "PHP-FPM $PHP_V sera installé (absent du serveur)."
    else
        systemctl is-active --quiet "php$PHP_V-fpm" || arreter "Le service php$PHP_V-fpm est installé mais arrêté." "Démarrez-le, puis relancez."
        ok "PHP-FPM $PHP_V déjà présent et actif"
    fi

    # Extensions PHP nécessaires à Laravel, vérifiées sur la version choisie.
    if [ -x "/usr/sbin/php-fpm$PHP_V" ]; then
        local ext paquet modules manque_ext=()
        modules="$("/usr/sbin/php-fpm$PHP_V" -m 2>/dev/null)"
        for ext in ctype curl dom fileinfo intl mbstring openssl pdo_pgsql tokenizer xml zip; do
            grep -qix "$ext" <<<"$modules" && continue
            case "$ext" in
                ctype|fileinfo|tokenizer|openssl) paquet="php$PHP_V-common" ;;
                curl) paquet="php$PHP_V-curl" ;;
                dom|xml) paquet="php$PHP_V-xml" ;;
                intl) paquet="php$PHP_V-intl" ;;
                mbstring) paquet="php$PHP_V-mbstring" ;;
                pdo_pgsql) paquet="php$PHP_V-pgsql" ;;
                zip) paquet="php$PHP_V-zip" ;;
            esac
            manque_ext+=("$ext"); A_INSTALLER+=("$paquet")
        done
        [ "${#manque_ext[@]}" -eq 0 ] && ok "Extensions PHP nécessaires : toutes présentes" || avertir "Extensions PHP à installer : ${manque_ext[*]}"
    fi

    # --- Outils : seuls ceux qui manquent sont installés.
    command -v nginx >/dev/null 2>&1 || A_INSTALLER+=(nginx)
    command -v certbot >/dev/null 2>&1 || A_INSTALLER+=(certbot)
    command -v psql >/dev/null 2>&1 || A_INSTALLER+=(postgresql-client)
    command -v pg_dump >/dev/null 2>&1 || A_INSTALLER+=(postgresql-client)
    command -v git >/dev/null 2>&1 || A_INSTALLER+=(git)
    command -v curl >/dev/null 2>&1 || A_INSTALLER+=(curl)
    command -v unzip >/dev/null 2>&1 || A_INSTALLER+=(unzip)
    command -v openssl >/dev/null 2>&1 || A_INSTALLER+=(openssl)
    command -v composer >/dev/null 2>&1 || A_INSTALLER+=(composer)
    command -v flock >/dev/null 2>&1 || A_INSTALLER+=(util-linux)
    if ! command -v pg_lsclusters >/dev/null 2>&1 || [ -z "$(pg_lsclusters --no-header 2>/dev/null)" ]; then A_INSTALLER+=(postgresql); fi
    [ "${#A_INSTALLER[@]}" -gt 0 ] && info "À installer (ce qui manque seulement) : $(printf '%s\n' "${A_INSTALLER[@]}" | sort -u | tr '\n' ' ')"

    # --- Configuration actuelle : elle doit être saine AVANT que je touche à quoi que ce soit.
    if command -v nginx >/dev/null 2>&1; then
        nginx -t >/dev/null 2>&1 || arreter "La configuration actuelle de Nginx est déjà en erreur (nginx -t échoue)." "Je ne touche à rien tant qu'elle n'est pas corrigée : sinon je ne pourrais pas savoir si l'erreur vient de moi."
        ok "Configuration Nginx actuelle valide"
        NGINX_USER="$(awk '/^[[:space:]]*user[[:space:]]/ {gsub(";",""); print $2; exit}' /etc/nginx/nginx.conf 2>/dev/null || true)"
        NGINX_USER="${NGINX_USER:-www-data}"
    fi
    if [ -x "/usr/sbin/php-fpm$PHP_V" ]; then
        "/usr/sbin/php-fpm$PHP_V" -t >/dev/null 2>&1 || arreter "La configuration actuelle de PHP-FPM $PHP_V est déjà en erreur." "Je ne touche à rien tant qu'elle n'est pas corrigée."
        ok "Configuration PHP-FPM $PHP_V actuelle valide"
    fi

    # --- PostgreSQL
    if command -v pg_lsclusters >/dev/null 2>&1 && [ -n "$(pg_lsclusters --no-header 2>/dev/null)" ]; then
        pg_lsclusters --no-header | awk '{print $4}' | grep -qx online || arreter "Le serveur PostgreSQL est installé mais arrêté." "Démarrez-le, puis relancez."
        PG_PORT="$(pg -c 'show port')" || arreter "Impossible de me connecter à PostgreSQL en tant qu'administrateur local."
        ok "PostgreSQL à l'écoute sur le port $PG_PORT"
    fi

    # --- DNS : le certificat HTTPS exige que le domaine pointe vers ce serveur.
    local ips ip_publique=""
    # getent échoue (code 2) quand le nom ne se résout pas : ce n'est pas une erreur du script, mais un fait à signaler.
    ips="$({ getent ahostsv4 "$DOMAINE" 2>/dev/null || true; } | awk '{print $1}' | sort -u | tr '\n' ' ')"
    [ -n "$ips" ] || arreter "$DOMAINE ne se résout pas : créez l'enregistrement DNS vers l'adresse de ce serveur, attendez sa propagation, puis relancez."
    if [ "$MODE_TEST" != "1" ] && command -v curl >/dev/null 2>&1; then ip_publique="$(curl -fsS -m 8 https://api.ipify.org 2>/dev/null || true)"; fi
    if [ -n "$ip_publique" ]; then
        case " $ips " in
            *" $ip_publique "*) ok "$DOMAINE pointe bien vers ce serveur ($ip_publique)" ;;
            *) arreter "$DOMAINE pointe vers $ips, mais ce serveur est $ip_publique." "Corrigez l'enregistrement DNS, puis relancez." ;;
        esac
    else
        avertir "Adresse publique du serveur non vérifiée (pas de réseau sortant ou mode test)."
    fi

    # --- Collisions : aucun de mes noms ne doit déjà servir à autre chose.
    local collisions=()
    etat_a "utilisateur" || { id "$APP_USER" >/dev/null 2>&1 && collisions+=("utilisateur système « $APP_USER »"); }
    etat_a "dossiers" || { [ -e "$APP_DIR" ] && collisions+=("dossier $APP_DIR"); }
    if [ -n "$PG_PORT" ]; then
        etat_a "role" || { [ "$(pg -c "select 1 from pg_roles where rolname='$DB_USER'")" = "1" ] && collisions+=("rôle PostgreSQL « $DB_USER »"); }
        etat_a "base" || { [ "$(pg -c "select 1 from pg_database where datname='$DB_NAME'")" = "1" ] && collisions+=("base PostgreSQL « $DB_NAME »"); }
    fi
    etat_a "pool" || { compgen -G "/etc/php/*/fpm/pool.d/$POOL_NOM.conf" >/dev/null && collisions+=("pool PHP-FPM « $POOL_NOM »"); }
    etat_a "vhost-http" || { { [ -e "$VHOST" ] || [ -e "$VHOST_LIEN" ]; } && collisions+=("site Nginx $DOMAINE"); }
    etat_a "certificat" || { [ -e "/etc/letsencrypt/live/$DOMAINE" ] && collisions+=("certificat Let's Encrypt de $DOMAINE"); }
    etat_a "unites" || { compgen -G "$UNITES/gamad-reseau-*" >/dev/null && collisions+=("unités systemd gamad-reseau-*"); }
    if [ "${#collisions[@]}" -gt 0 ]; then
        arreter "Ces noms sont déjà utilisés et ne viennent pas de cette installation :" "${collisions[@]/#/- }" "Je ne les écrase jamais. Libérez-les ou changez-les, puis relancez."
    fi
    ok "Aucun nom n'entre en collision avec l'existant"
}

# ================================================================ 2. saisies
lire() {   # invite variable
    local invite="$1" variable="$2"
    [ -r /dev/tty ] || arreter "Pas de terminal : je ne peux pas poser mes questions." "Lancez la commande depuis une console ouverte sur le serveur."
    read -r -p "$invite" "$variable" </dev/tty
}
lire_secret() {
    local invite="$1" variable="$2"
    read -r -s -p "$invite" "$variable" </dev/tty
    printf '\n' >/dev/tty
}

saisies() {
    etat_a "president" && return 0
    titre "Compte du Président"

    if [ "$MODE_TEST" = "1" ]; then
        PRESIDENT_NOM="${GAMAD_TEST_NOM:?}"; PRESIDENT_EMAIL="${GAMAD_TEST_EMAIL:?}"; PRESIDENT_MDP="${GAMAD_TEST_MDP:?}"
        return 0
    fi
    if [ "$SIMULATION" = "1" ]; then PRESIDENT_NOM="(simulation)"; PRESIDENT_EMAIL="president@exemple.org"; return 0; fi

    info "Ces informations créent votre compte de super administrateur. Le mot de passe n'est ni affiché, ni enregistré en clair."
    local essai=0
    while [ -z "$PRESIDENT_NOM" ] && [ "$essai" -lt 5 ]; do
        lire "  Votre nom affiché : " PRESIDENT_NOM; essai=$((essai + 1))
    done
    [ -n "$PRESIDENT_NOM" ] || arreter "Nom non renseigné."
    essai=0
    until [[ "$PRESIDENT_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; do
        [ "$essai" -lt 5 ] || arreter "Adresse e-mail non valide."
        lire "  Votre adresse e-mail : " PRESIDENT_EMAIL; essai=$((essai + 1))
    done
    local confirmation=""
    essai=0
    while true; do
        [ "$essai" -lt 5 ] || arreter "Mot de passe non confirmé."
        lire_secret "  Mot de passe (8 caractères au minimum, 12 recommandés) : " PRESIDENT_MDP
        lire_secret "  Retapez le mot de passe : " confirmation
        essai=$((essai + 1))
        if [ "${#PRESIDENT_MDP}" -lt 8 ]; then avertir "Au moins 8 caractères."; continue; fi
        if [ "$PRESIDENT_MDP" != "$confirmation" ]; then avertir "Les deux saisies ne sont pas identiques."; continue; fi
        break
    done
}

# ============================================================== 3. récapitulatif
recapitulatif() {
    titre "Ce que je vais faire"
    cat <<E
  Site                : https://$DOMAINE (certificat HTTPS gratuit Let's Encrypt)
  Compte du Président : ${PRESIDENT_EMAIL:+$PRESIDENT_NOM <$PRESIDENT_EMAIL>}${PRESIDENT_EMAIL:-déjà créé}

  Je vais CRÉER, aux noms ci-dessous, et rien d'autre :
    - un utilisateur système dédié        : $APP_USER
    - une base et un rôle PostgreSQL      : $DB_NAME / $DB_USER (mot de passe aléatoire)
    - un pool PHP-FPM $PHP_V dédié        : $POOL_NOM
    - un site Nginx dédié                 : $DOMAINE
    - le dossier de l'application         : $APP_DIR
    - le déploiement automatique (toutes les 5 minutes) et la sauvegarde quotidienne de la base (7 jours)

  Je ne modifierai AUCUN autre site, base, pool ni service. Nginx et PHP-FPM seront seulement rechargés
  en douceur, après vérification de leur configuration. Pare-feu : inchangé.
E
    if [ "$SIMULATION" = "1" ]; then
        printf '\n  SIMULATION : rien n'"'"'est modifié. Relancez sans GAMAD_SIMULATION=1 pour installer.\n'
        return 0
    fi
    if [ "$MODE_TEST" = "1" ] || [ "${GAMAD_OUI:-0}" = "1" ]; then return 0; fi
    local reponse=""
    lire "
  Tapez OUI pour continuer : " reponse
    [ "$reponse" = "OUI" ] || arreter "Installation annulée : rien n'a été modifié."
}

# ================================================================= 4. paquets
etape_paquets() {
    titre "Outils manquants"
    if [ "${#A_INSTALLER[@]}" -eq 0 ]; then ok "Rien à installer : tous les outils sont déjà présents"; return 0; fi
    # shellcheck disable=SC2046
    apt_installer $(printf '%s\n' "${A_INSTALLER[@]}" | sort -u)
    if [ "$SIMULATION" != "1" ]; then
        # Les paquets installés peuvent créer la grappe PostgreSQL ou démarrer des services : on relit les faits.
        PG_PORT="$(pg -c 'show port')" || arreter "PostgreSQL ne répond pas après son installation."
        NGINX_USER="$(awk '/^[[:space:]]*user[[:space:]]/ {gsub(";",""); print $2; exit}' /etc/nginx/nginx.conf 2>/dev/null || true)"
        NGINX_USER="${NGINX_USER:-www-data}"
    fi
}

# ================================================================ 5. utilisateur
etape_utilisateur() {
    titre "Utilisateur système et dossiers"
    if ! etat_a "utilisateur"; then
        execute "créerait l'utilisateur $APP_USER" \
            useradd --system --user-group --no-create-home --home-dir "$APP_DIR" --shell /usr/sbin/nologin "$APP_USER"
        [ "$SIMULATION" = "1" ] || { etat_marquer "utilisateur"; ok "utilisateur $APP_USER créé"; }
    else ok "utilisateur $APP_USER déjà créé par cette installation"; fi

    [ "$SIMULATION" = "1" ] && return 0

    install -d -m 755 -o "$APP_USER" -g "$APP_USER" "$APP_DIR" "$APP_DIR/releases" "$APP_DIR/shared" "$APP_DIR/sauvegardes-deploiement"
    install -d -m 775 -o "$APP_USER" -g "$APP_USER" \
        "$APP_DIR/shared/storage" "$APP_DIR/shared/storage/app" "$APP_DIR/shared/storage/app/public" \
        "$APP_DIR/shared/storage/framework" "$APP_DIR/shared/storage/framework/cache" "$APP_DIR/shared/storage/framework/cache/data" \
        "$APP_DIR/shared/storage/framework/sessions" "$APP_DIR/shared/storage/framework/views" "$APP_DIR/shared/storage/logs"
    install -d -m 755 -o root -g root "$APP_DIR/bin" "$LIB_DIR"
    install -d -m 755 -o "$APP_USER" -g "$APP_USER" "$LOG_DIR"
    install -d -m 755 -o root -g root "$ACME_ROOT"
    etat_marquer "dossiers"
    ok "dossiers prêts"

    # Les outils doivent être utilisables par le compte dédié (par exemple, Node installé hors des chemins système).
    local outil chemin_deploiement="/usr/local/bin:/usr/bin:/bin:/snap/bin"
    for outil in node npm git composer "php$PHP_V"; do
        runuser -u "$APP_USER" -- env PATH="$chemin_deploiement" "$outil" --version >/dev/null 2>&1 \
            || arreter "Le compte « $APP_USER » ne peut pas lancer « $outil »." "Il doit être installé dans un chemin accessible à tous les comptes (par exemple /usr/bin ou /usr/local/bin)."
    done
    # Node doit aussi pouvoir ÉCRIRE dans le dossier de l'application (un Node installé par snap en est empêché).
    runuser -u "$APP_USER" -- env PATH="$chemin_deploiement" sh -c 'cd "$1" && node -e "require(\"fs\").writeFileSync(\".essai-node\", \"ok\")" && rm -f .essai-node' _ "$APP_DIR" >/dev/null 2>&1 \
        || arreter "Node ne peut pas écrire dans $APP_DIR avec le compte « $APP_USER »." "C'est typique d'un Node installé par snap : installez Node depuis un paquet système ou un binaire officiel dans /usr/local, puis relancez."
    ok "outils accessibles et utilisables par le compte dédié"
}

# ================================================================ 6. PostgreSQL
etape_base() {
    titre "Base de données PostgreSQL dédiée"
    [ "$SIMULATION" = "1" ] && { info "[simulation] créerait le rôle $DB_USER et la base $DB_NAME (aucune autre base touchée)"; return 0; }

    local env="$APP_DIR/shared/.env" nouveau_mdp=0
    if etat_a "env" && [ -f "$env" ]; then
        # Relance : le mot de passe existant est conservé (jamais renouvelé sans raison).
        DB_MDP="$(grep -E '^DB_PASSWORD=' "$env" | head -n 1 | cut -d= -f2-)"
    else
        DB_MDP="$(openssl rand -hex 24)"; nouveau_mdp=1
    fi

    if ! etat_a "role"; then
        # Le mot de passe passe par l'entrée standard : il n'apparaît jamais dans la liste des processus.
        printf "CREATE ROLE %s LOGIN PASSWORD '%s';\n" "$DB_USER" "$DB_MDP" | pg
        etat_marquer "role"; ok "rôle $DB_USER créé"
    elif [ "$nouveau_mdp" -eq 1 ]; then
        printf "ALTER ROLE %s PASSWORD '%s';\n" "$DB_USER" "$DB_MDP" | pg
        ok "rôle $DB_USER déjà créé ; nouveau mot de passe aléatoire posé"
    else
        ok "rôle $DB_USER déjà créé"
    fi
    if ! etat_a "base"; then
        pg -c "CREATE DATABASE $DB_NAME OWNER $DB_USER ENCODING 'UTF8' TEMPLATE template0"
        pg -c "REVOKE ALL ON DATABASE $DB_NAME FROM PUBLIC"
        etat_marquer "base"; ok "base $DB_NAME créée (fermée aux autres rôles)"
    else ok "base $DB_NAME déjà créée"; fi

    if ! PGPASSWORD="$DB_MDP" psql -X -Atq -h 127.0.0.1 -p "$PG_PORT" -U "$DB_USER" -d "$DB_NAME" -c 'select 1' >/dev/null 2>&1; then
        arreter "PostgreSQL refuse la connexion locale par mot de passe pour le rôle $DB_USER." \
            "Sa configuration (pg_hba.conf) ne le permet pas. Je ne la modifie pas : ce serveur héberge d'autres bases." \
            "Pour annuler ce qui a été créé : sudo bash $LIB_DIR/desinstaller.sh (il ne supprime que mes propres éléments)."
    fi
    ok "connexion locale par mot de passe vérifiée"
}

# ================================================================== 7. code
etape_depot() {
    titre "Récupération du code"
    [ "$SIMULATION" = "1" ] && { info "[simulation] clonerait $DEPOT (branche $BRANCHE) dans $APP_DIR/repo"; return 0; }
    if [ ! -d "$APP_DIR/repo/.git" ]; then
        runuser -u "$APP_USER" -- git clone --quiet --depth 1 --branch "$BRANCHE" --no-tags "$DEPOT" "$APP_DIR/repo" \
            || arreter "Impossible de récupérer le code depuis $DEPOT (branche $BRANCHE)." "Vérifiez que le serveur accède à Internet."
    else
        # Relance de l'installation : on se remet sur le dernier commit, source des scripts installés plus bas.
        runuser -u "$APP_USER" -- git -C "$APP_DIR/repo" fetch --quiet --depth 1 --no-tags origin "$BRANCHE" \
            && runuser -u "$APP_USER" -- git -C "$APP_DIR/repo" reset --quiet --hard FETCH_HEAD \
            || arreter "Impossible de mettre à jour le code depuis $DEPOT."
    fi
    etat_marquer "depot"; ok "code récupéré ($(git -C "$APP_DIR/repo" rev-parse --short HEAD))"
}

# ================================================================== 8. .env
etape_env() {
    titre "Fichier .env (écrit automatiquement)"
    [ "$SIMULATION" = "1" ] && { info "[simulation] écrirait $APP_DIR/shared/.env (amorçage technique seulement)"; return 0; }
    local env="$APP_DIR/shared/.env" cle
    if etat_a "env" && [ -f "$env" ]; then
        # La clé de chiffrement ne change JAMAIS après la première écriture ; seul le mot de passe de la base est remis à jour.
        cle="$(grep -E '^APP_KEY=' "$env" | head -n 1 | cut -d= -f2-)"
    else
        cle="base64:$(openssl rand -base64 32)"
    fi
    ( umask 077; cat >"$env" <<E
APP_ENV=production
APP_DEBUG=false
APP_KEY=$cle
APP_URL=https://$DOMAINE
LOG_STACK=daily
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=$PG_PORT
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_MDP
E
    )
    chown "$APP_USER:$APP_USER" "$env"; chmod 600 "$env"
    etat_marquer "env"; ok ".env écrit (lisible par le seul compte $APP_USER)"
}

# ============================================================= 9. PHP-FPM, Nginx
etape_pool() {
    titre "Pool PHP-FPM dédié"
    local fichier="/etc/php/$PHP_V/fpm/pool.d/$POOL_NOM.conf"
    [ "$SIMULATION" = "1" ] && { info "[simulation] écrirait $fichier puis rechargerait php$PHP_V-fpm après vérification"; return 0; }
    if etat_a "pool" && [ -f "$fichier" ] && [ -S "$SOCKET" ]; then ok "pool $POOL_NOM déjà actif"; return 0; fi
    cat <<'E' | rendre >"$fichier"
; GAMAD — pool dédié (créé par deploy/install.sh). Ne concerne que reseau.dgafrique.com.
[@@POOL_NOM@@]
user = @@APP_USER@@
group = @@APP_USER@@
listen = @@SOCKET@@
listen.owner = @@NGINX_USER@@
listen.group = @@NGINX_USER@@
listen.mode = 0660
pm = ondemand
pm.max_children = 10
pm.process_idle_timeout = 30s
pm.max_requests = 500
request_terminate_timeout = 60s
catch_workers_output = yes
security.limit_extensions = .php
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 5M
php_admin_value[post_max_size] = 6M
php_admin_value[error_log] = @@LOG_DIR@@/php-fpm.log
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
E
    if ! "/usr/sbin/php-fpm$PHP_V" -t >/dev/null 2>&1; then
        rm -f "$fichier"
        arreter "La configuration PHP-FPM est invalide avec mon pool : je l'ai retiré, rien n'a été rechargé."
    fi
    etat_marquer "pool"
    systemctl reload "php$PHP_V-fpm"
    local i
    for i in 1 2 3 4 5 6 7 8 9 10; do [ -S "$SOCKET" ] && break; sleep 1; done
    [ -S "$SOCKET" ] || arreter "Le pool PHP-FPM ne s'est pas activé (socket $SOCKET absent)."
    ok "pool $POOL_NOM actif (rechargement doux de php$PHP_V-fpm)"
}

# Les lignes « listen [::] » (IPv6) ne sont écrites que si ce serveur sait faire de l'IPv6 : sinon Nginx refuserait de démarrer.
sans_ipv6_si_absent() {
    if [ -s /proc/net/if_inet6 ]; then cat; else grep -v 'listen \[::\]'; fi
}

ecrire_vhost() {   # http | https
    local phase="$1"
    if [ "$phase" = "http" ]; then
        cat <<'E' | rendre | sans_ipv6_si_absent >"$VHOST"
# GAMAD — site de @@DOMAINE@@ (créé par deploy/install.sh) : phase d'obtention du certificat.
server {
    listen 80;
    listen [::]:80;
    server_name @@DOMAINE@@;

    location /.well-known/acme-challenge/ {
        root @@ACME_ROOT@@;
        default_type "text/plain";
        try_files $uri =404;
    }
    location / {
        default_type "text/plain";
        return 503 "Installation en cours.\n";
    }
}
E
    else
        cat <<'E' | rendre | sans_ipv6_si_absent >"$VHOST"
# GAMAD — site de @@DOMAINE@@ (créé par deploy/install.sh). Ne concerne que ce nom de domaine.
server {
    listen 80;
    listen [::]:80;
    server_name @@DOMAINE@@;

    location /.well-known/acme-challenge/ {
        root @@ACME_ROOT@@;
        default_type "text/plain";
        try_files $uri =404;
    }
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    server_name @@DOMAINE@@;

    ssl_certificate     /etc/letsencrypt/live/@@DOMAINE@@/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/@@DOMAINE@@/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL_gamad_reseau:2m;
    ssl_session_tickets off;

    # « current » est un lien symbolique vers la version en ligne ; $realpath_root évite tout rechargement de PHP
    # lors d'une mise à jour : chaque version a son propre chemin réel.
    root @@APP_DIR@@/current/public;
    index index.php;
    charset utf-8;
    client_max_body_size 5m;

    add_header Strict-Transport-Security "max-age=15552000" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-Robots-Tag "noindex, nofollow" always;

    access_log /var/log/nginx/@@DOMAINE@@.access.log;
    error_log  /var/log/nginx/@@DOMAINE@@.error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location = /index.php {
        include fastcgi_params;
        fastcgi_pass unix:@@SOCKET@@;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTPS on;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }
    location ~ \.php$ {
        return 404;
    }
    location ~ /\.(?!well-known) {
        deny all;
    }
}
E
    fi
}

retirer_vhost() { rm -f "$VHOST_LIEN" "$VHOST"; }

etape_vhost_http() {
    titre "Site Nginx (phase certificat)"
    [ "$SIMULATION" = "1" ] && { info "[simulation] écrirait $VHOST et le lien dans sites-enabled, puis rechargerait Nginx après nginx -t"; return 0; }
    # Une fois le certificat obtenu, la phase « certificat » est dépassée : la réécrire couperait le HTTPS.
    if etat_a "certificat" || { etat_a "vhost-http" && [ -f "$VHOST" ]; }; then ok "site $DOMAINE déjà configuré"; return 0; fi
    ecrire_vhost http
    ln -sfn "$VHOST" "$VHOST_LIEN"
    if ! nginx -t >/dev/null 2>&1; then
        retirer_vhost
        arreter "La configuration Nginx est invalide avec mon site : je l'ai retiré, rien n'a été rechargé." "$(nginx -t 2>&1 | tail -n 3)"
    fi
    etat_marquer "vhost-http"
    systemctl reload nginx
    ok "site $DOMAINE créé (rechargement doux de Nginx)"
}

# ============================================================= 10. certificat
etape_certificat() {
    titre "Certificat HTTPS"
    if etat_a "certificat" && [ -e "/etc/letsencrypt/live/$DOMAINE/fullchain.pem" ]; then ok "certificat déjà obtenu"; return 0; fi
    [ "$SIMULATION" = "1" ] && { info "[simulation] obtiendrait le certificat Let's Encrypt de $DOMAINE (certbot certonly --webroot)"; return 0; }

    if [ "$MODE_TEST" = "1" ]; then
        # Mode test uniquement : certificat auto-signé à l'emplacement habituel (pas d'accès à Let's Encrypt).
        install -d -m 755 "/etc/letsencrypt/live/$DOMAINE"
        openssl req -x509 -nodes -newkey rsa:2048 -days 30 -subj "/CN=$DOMAINE" \
            -keyout "/etc/letsencrypt/live/$DOMAINE/privkey.pem" -out "/etc/letsencrypt/live/$DOMAINE/fullchain.pem" 2>/dev/null
        etat_marquer "certificat"; avertir "MODE TEST : certificat auto-signé"
        return 0
    fi

    local options=(--non-interactive --agree-tos --no-eff-email)
    # Un compte Let's Encrypt existe déjà sur ce serveur : on le réutilise ; sinon, on crée le compte avec l'e-mail du Président.
    if [ -z "$(ls -A /etc/letsencrypt/accounts 2>/dev/null)" ]; then
        if [ -n "$PRESIDENT_EMAIL" ]; then options+=(--email "$PRESIDENT_EMAIL"); else options+=(--register-unsafely-without-email); fi
    fi
    if ! certbot certonly --webroot -w "$ACME_ROOT" -d "$DOMAINE" "${options[@]}" --deploy-hook "systemctl reload nginx" >>"$LOG_DIR/installation.log" 2>&1; then
        arreter "Let's Encrypt n'a pas délivré le certificat de $DOMAINE." \
            "Causes fréquentes : le port 80 n'est pas joignable depuis Internet, ou le DNS n'est pas encore propagé." \
            "Détails : $LOG_DIR/installation.log. Après correction, relancez la même ligne."
    fi
    etat_marquer "certificat"
    ok "certificat obtenu (renouvellement automatique par certbot)"
    certbot renew --cert-name "$DOMAINE" --dry-run >>"$LOG_DIR/installation.log" 2>&1 \
        && ok "renouvellement automatique vérifié" \
        || avertir "Le test de renouvellement a échoué : voir $LOG_DIR/installation.log."
}

etape_vhost_https() {
    titre "Site Nginx (HTTPS)"
    [ "$SIMULATION" = "1" ] && { info "[simulation] activerait HTTPS dans $VHOST, après nginx -t"; return 0; }
    if etat_a "vhost-https" && grep -q 'listen 443' "$VHOST" 2>/dev/null; then ok "HTTPS déjà actif"; return 0; fi
    [ -f "$VHOST" ] || ecrire_vhost http
    ln -sfn "$VHOST" "$VHOST_LIEN"
    cp "$VHOST" "$VHOST.http"
    ecrire_vhost https
    if ! nginx -t >/dev/null 2>&1; then
        mv "$VHOST.http" "$VHOST"
        arreter "La configuration Nginx HTTPS est invalide : je suis revenu à la phase précédente, rien n'a été rechargé." "$(nginx -t 2>&1 | tail -n 3)"
    fi
    rm -f "$VHOST.http"
    etat_marquer "vhost-https"
    systemctl reload nginx
    ok "HTTPS actif sur $DOMAINE"
}

# ============================================== 11. scripts, minuteurs, première version
etape_scripts() {
    titre "Scripts, déploiement automatique et sauvegardes"
    [ "$SIMULATION" = "1" ] && { info "[simulation] installerait gamad-deploy, le retour arrière, la sauvegarde, 4 unités systemd et la rotation des journaux"; return 0; }

    local src="$APP_DIR/repo/deploy" f
    for f in deploy.sh retour-arriere.sh sauvegarde.sh desinstaller.sh; do
        [ -f "$src/$f" ] || arreter "Le dépôt ne contient pas deploy/$f : impossible de continuer."
    done
    install -m 755 -o root -g root "$src/deploy.sh" "$APP_DIR/bin/gamad-deploy"
    install -m 755 -o root -g root "$src/retour-arriere.sh" /usr/local/sbin/gamad-reseau-retour-arriere
    install -m 755 -o root -g root "$src/sauvegarde.sh" "$LIB_DIR/sauvegarde.sh"
    install -m 755 -o root -g root "$src/desinstaller.sh" "$LIB_DIR/desinstaller.sh"

    local php_bin="/usr/bin/php$PHP_V"
    cat >"$CONF_FILE" <<E
# GAMAD — réglages techniques des scripts (aucun secret). Écrit par deploy/install.sh.
APP_DIR=$APP_DIR
APP_USER=$APP_USER
DOMAIN=$DOMAINE
PHP_BIN=$php_bin
REPO_URL=$DEPOT
BRANCH=$BRANCHE
KEEP_RELEASES=5
LOG_FILE=$LOG_DIR/deploy.log
DB_NAME=$DB_NAME
BACKUP_DIR=$BACKUP_DIR
LOG_FILE_BACKUP=$LOG_DIR/sauvegarde.log
BACKUP_KEEP=7
E
    [ -n "${GAMAD_HEALTH_BASE:-}" ] && echo "HEALTH_BASE=$GAMAD_HEALTH_BASE" >>"$CONF_FILE"
    chmod 644 "$CONF_FILE"

    cat <<'E' | rendre >"$UNITES/gamad-reseau-deploy.service"
[Unit]
Description=GAMAD — déploiement automatique de @@DOMAINE@@
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=@@APP_USER@@
Group=@@APP_USER@@
WorkingDirectory=@@APP_DIR@@
ExecStart=@@APP_DIR@@/bin/gamad-deploy
Nice=10
IOSchedulingClass=idle
TimeoutStartSec=30min
NoNewPrivileges=yes
PrivateTmp=yes
E
    cat <<'E' >"$UNITES/gamad-reseau-deploy.timer"
[Unit]
Description=GAMAD — cherche une nouvelle version toutes les 5 minutes

[Timer]
OnActiveSec=1min
OnBootSec=3min
OnUnitInactiveSec=5min
AccuracySec=30s
RandomizedDelaySec=15s

[Install]
WantedBy=timers.target
E
    cat <<'E' | rendre >"$UNITES/gamad-reseau-sauvegarde.service"
[Unit]
Description=GAMAD — sauvegarde quotidienne de la base

[Service]
Type=oneshot
ExecStart=@@LIB_DIR@@/sauvegarde.sh
Nice=15
IOSchedulingClass=idle
E
    cat <<'E' >"$UNITES/gamad-reseau-sauvegarde.timer"
[Unit]
Description=GAMAD — sauvegarde de la base chaque nuit

[Timer]
OnCalendar=*-*-* 03:30:00
Persistent=true
RandomizedDelaySec=10min

[Install]
WantedBy=timers.target
E
    cat >/etc/logrotate.d/gamad-reseau <<E
$LOG_DIR/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    su $APP_USER $APP_USER
}
E
    install -d -m 700 -o root -g root "$BACKUP_DIR"
    systemctl daemon-reload
    etat_marquer "unites"
    ok "scripts et unités systemd installés (pas encore activés)"
}

etape_premiere_version() {
    titre "Première version de l'application"
    [ "$SIMULATION" = "1" ] && { info "[simulation] construirait la première version (composer, npm, migrations) puis la mettrait en ligne"; return 0; }
    if etat_a "premiere-version" && [ -L "$APP_DIR/current" ]; then ok "une version est déjà en ligne"; return 0; fi
    info "Construction en cours (installation des dépendances et compilation : quelques minutes)…"
    if ! runuser -u "$APP_USER" -- "$APP_DIR/bin/gamad-deploy" --forcer; then
        arreter "La construction de la première version a échoué." "Voir : $LOG_DIR/deploy.log" "$(tail -n 12 "$LOG_DIR/deploy.log" 2>/dev/null | sed 's/^/    /')"
    fi
    etat_marquer "premiere-version"
    ok "première version en ligne : $(basename "$(readlink -f "$APP_DIR/current")")"
}

etape_president() {
    titre "Compte du Président"
    [ "$SIMULATION" = "1" ] && { info "[simulation] créerait le compte de $PRESIDENT_EMAIL (vérifié, super administrateur), inscrit au journal (acteur « Installation »)"; return 0; }
    if etat_a "president"; then ok "compte déjà créé"; return 0; fi
    # Le mot de passe passe par l'entrée standard (printf est interne à bash) : jamais dans la liste des processus.
    if ! printf '%s\n' "$PRESIDENT_MDP" | runuser -u "$APP_USER" -- env SUDO_USER="${SUDO_USER:-}" \
            "/usr/bin/php$PHP_V" "$APP_DIR/current/artisan" gamad:installer-president --name="$PRESIDENT_NOM" --email="$PRESIDENT_EMAIL"; then
        arreter "Le compte du Président n'a pas pu être créé." "Relancez la même ligne : seule cette étape sera reprise."
    fi
    PRESIDENT_MDP=""
    etat_marquer "president"
}

etape_minuteurs() {
    titre "Activation du déploiement automatique et des sauvegardes"
    [ "$SIMULATION" = "1" ] && { info "[simulation] activerait les deux minuteurs et lancerait une première sauvegarde"; return 0; }
    systemctl enable --now gamad-reseau-deploy.timer gamad-reseau-sauvegarde.timer >/dev/null 2>&1
    ok "déploiement automatique actif (toutes les 5 minutes)"
    if systemctl start gamad-reseau-sauvegarde.service; then ok "première sauvegarde réussie : $(ls -1t "$BACKUP_DIR"/"$DB_NAME"-*.dump | head -n 1 | xargs -r basename)"; else avertir "La première sauvegarde a échoué : voir $LOG_DIR/sauvegarde.log."; fi
    etat_marquer "minuteurs"
}

# ================================================================== 12. résumé
verification_finale() {
    titre "Vérification finale"
    [ "$SIMULATION" = "1" ] && return 0
    local code
    code="$(curl -ksS --noproxy '*' -o /dev/null -m 20 -w '%{http_code}' --resolve "$DOMAINE:443:127.0.0.1" "https://$DOMAINE/" || true)"
    [ "$code" = "200" ] && ok "https://$DOMAINE répond (code 200)" || avertir "https://$DOMAINE a répondu $code : voir $LOG_DIR/deploy.log."
}

resume() {
    [ "$SIMULATION" = "1" ] && return 0
    cat <<E

====================================================================
  INSTALLATION TERMINÉE
====================================================================

  Site de test        : https://$DOMAINE
  Compte du Président : ${PRESIDENT_EMAIL:-(déjà créé)} (super administrateur, e-mail vérifié)

  Prochaine étape, sur le site : connectez-vous, puis Espace > Paramètres > Courriel
  pour renseigner le serveur SMTP et envoyer un e-mail de test.

  Ce qui a été créé (et rien d'autre) :
    - utilisateur système        : $APP_USER
    - base / rôle PostgreSQL     : $DB_NAME / $DB_USER
    - pool PHP-FPM $PHP_V          : $POOL_NOM
    - site Nginx                 : $DOMAINE
    - dossier de l'application   : $APP_DIR
    - déploiement automatique    : vérifie main toutes les 5 minutes
    - sauvegarde de la base      : chaque nuit, 7 jours conservés, dans $BACKUP_DIR

  Journaux :
    - installation  : $LOG_DIR/installation.log
    - déploiements  : $LOG_DIR/deploy.log
    - sauvegardes   : $LOG_DIR/sauvegarde.log

  En cas de problème après une mise à jour :
    sudo gamad-reseau-retour-arriere        (revient à la version précédente)
    sudo bash $LIB_DIR/desinstaller.sh      (retire tout ce que j'ai créé)

  Aucun mot de passe n'est affiché ici : le mot de passe de la base est dans $APP_DIR/shared/.env
  (lisible par le seul compte $APP_USER), et il n'est jamais à modifier à la main.
E
}

# ====================================================================== main
main() {
    printf 'GAMAD — installation de %s\n' "$DOMAINE"
    [ "$SIMULATION" = "1" ] && printf '(SIMULATION : rien ne sera modifié)\n'

    controles
    saisies
    recapitulatif

    if [ "$SIMULATION" != "1" ]; then
        install -d -m 755 "$ETAT_DIR" "$LOG_DIR"
        exec > >(tee -a "$LOG_DIR/installation.log") 2>&1
        printf '\n--- installation du %s ---\n' "$(date '+%Y-%m-%d %H:%M:%S')"
    fi

    etape_paquets
    etape_utilisateur
    etape_base
    etape_depot
    etape_env
    etape_pool
    etape_vhost_http
    etape_certificat
    etape_vhost_https
    etape_scripts
    etape_premiere_version
    etape_president
    etape_minuteurs
    verification_finale
    resume
}

# L'entrée standard est fermée : le script (lu par « curl | sudo bash ») ne peut pas être consommé par une commande,
# et les questions sont posées directement sur le terminal.
main "$@" </dev/null

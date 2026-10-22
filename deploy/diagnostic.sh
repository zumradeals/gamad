#!/usr/bin/env bash
#
# GAMAD — diagnostic du serveur avant installation (Lot 0.1, phase A).
#
# SCRIPT EN LECTURE SEULE : il n'installe rien, ne modifie aucun fichier, ne redémarre aucun service,
# n'écrit rien sur le disque. Il affiche seulement des informations de configuration.
#
# Garanties de confidentialité :
#   - il n'ouvre JAMAIS un fichier .env, un fichier de mots de passe ou une clé ;
#   - dans les configurations des sites, il ne lit que les lignes de noms de domaine ;
#   - il n'affiche pas les lignes de commande des processus ni les variables d'environnement ;
#   - toute la sortie passe en plus par un filtre qui masque tout ce qui ressemble à un secret.
#
# Usage (sur le serveur, en administrateur) :  sudo bash deploy/diagnostic.sh

set -u
export LC_ALL=C.UTF-8 LANG=C.UTF-8
export PATH="$PATH:/usr/sbin:/sbin:/usr/local/sbin"

DOMAINE="reseau.dgafrique.com"

a() { command -v "$1" >/dev/null 2>&1; }
titre() { printf '\n== %s ==\n' "$1"; }
ok() { printf '  [présent]  %s\n' "$1"; }
non() { printf '  [absent]   %s\n' "$1"; }
info() { printf '  %s\n' "$1"; }
indente() { sed 's/^/    /'; }
# Exécute une commande en tant qu'un autre utilisateur, sans rien écrire.
en_tant_que() {
    local u="$1"; shift
    if [ "$(id -u)" -eq 0 ] && a runuser; then
        runuser -u "$u" -- "$@" 2>/dev/null
    else
        return 1
    fi
}

diagnostic() {
    printf 'GAMAD — diagnostic du serveur (lecture seule)\n'
    printf 'Date : %s\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')"
    if [ "$(id -u)" -ne 0 ]; then
        info "ATTENTION : lancé sans sudo. Certaines informations (bases PostgreSQL, pare-feu, certificats) seront incomplètes."
    fi

    # ---------------------------------------------------------------- Système
    titre "Système"
    if [ -r /etc/os-release ]; then
        ( . /etc/os-release; info "Distribution : ${PRETTY_NAME:-inconnue}" )
    fi
    info "Noyau        : $(uname -sr) ($(uname -m))"
    info "Nom de la machine : $(hostname 2>/dev/null)"
    if a systemctl && [ -d /run/systemd/system ]; then
        info "systemd      : actif"
    else
        info "systemd      : non détecté (le déploiement automatique en a besoin)"
    fi
    for o in sudo git curl unzip openssl rsync; do
        if a "$o"; then ok "outil $o"; else non "outil $o"; fi
    done

    # ------------------------------------------------------ Serveurs web
    titre "Serveurs web et conteneurs"
    local web=0
    if a nginx; then
        web=1; ok "Nginx : $(nginx -v 2>&1 | head -1)"
        info "  état : $(systemctl is-active nginx 2>/dev/null || echo inconnu)"
    else non "Nginx"; fi
    if a apache2 || a httpd; then
        web=1; ok "Apache : $( (apache2 -v 2>/dev/null || httpd -v 2>/dev/null) | head -1)"
        info "  état : $(systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null || echo inconnu)"
    else non "Apache"; fi
    if a caddy; then
        web=1; ok "Caddy : $(caddy version 2>/dev/null | head -1)"
        info "  état : $(systemctl is-active caddy 2>/dev/null || echo inconnu)"
    else non "Caddy"; fi
    if a traefik; then web=1; ok "Traefik : présent"; else non "Traefik"; fi
    if a docker; then
        ok "Docker : $(docker --version 2>/dev/null)"
        info "  état : $(systemctl is-active docker 2>/dev/null || echo inconnu)"
        if docker info >/dev/null 2>&1; then
            info "  conteneurs en cours (nom, image, ports publiés) :"
            docker ps --format '{{.Names}} | {{.Image}} | {{.Ports}}' 2>/dev/null | indente
        fi
    else non "Docker"; fi
    [ "$web" -eq 0 ] && info "Aucun serveur web classique détecté."

    titre "Noms de domaine déjà configurés (noms seulement)"
    local trouve=0
    if [ -d /etc/nginx ]; then
        info "Nginx — fichiers de sites activés :"
        ls -1 /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null | indente
        info "Nginx — lignes server_name :"
        # Lignes commentées ignorées ; seule la directive server_name est extraite, même écrite sur une seule ligne.
        grep -Rh -v '^[[:space:]]*#' /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null \
            | grep -oE 'server_name[[:space:]]+[^;]+;' | sort -u | indente
        info "Nginx — sites qui prennent la place par défaut (default_server) :"
        grep -RlE 'listen[^;]*default_server' /etc/nginx/sites-enabled /etc/nginx/conf.d 2>/dev/null | indente
        trouve=1
    fi
    if [ -d /etc/apache2 ]; then
        info "Apache — ServerName / ServerAlias :"
        grep -RhEi '^[[:space:]]*Server(Name|Alias)[[:space:]]' /etc/apache2/sites-enabled 2>/dev/null \
            | sed -E 's/^[[:space:]]+//' | sort -u | indente
        trouve=1
    fi
    if [ -f /etc/caddy/Caddyfile ]; then
        info "Caddy — adresses de sites (lignes d'en-tête seulement) :"
        grep -E '^[^[:space:]#}][^{]*\{[[:space:]]*$' /etc/caddy/Caddyfile 2>/dev/null | sed -E 's/[[:space:]]*\{[[:space:]]*$//' | indente
        trouve=1
    fi
    [ "$trouve" -eq 0 ] && info "Aucune configuration de site trouvée à l'emplacement habituel."
    if [ -d /etc/letsencrypt/live ]; then
        info "Certificats Let's Encrypt existants (noms des dossiers) :"
        ls -1 /etc/letsencrypt/live 2>/dev/null | grep -v '^README$' | indente
    fi

    # ------------------------------------------------------------- PHP
    titre "PHP et PHP-FPM"
    if a php; then ok "PHP en ligne de commande : $(php -v 2>/dev/null | head -1)"; else non "PHP en ligne de commande"; fi
    if [ -d /etc/php ]; then
        info "Versions de PHP installées (dossiers /etc/php) : $(ls -1 /etc/php 2>/dev/null | tr '\n' ' ')"
        for v in /etc/php/*; do
            [ -d "$v/fpm" ] || continue
            local ver; ver=$(basename "$v")
            info "PHP-FPM $ver : service php$ver-fpm = $(systemctl is-active "php$ver-fpm" 2>/dev/null || echo inconnu)"
            info "  pools (nom, utilisateur, socket ou port) :"
            local f
            for f in "$v"/fpm/pool.d/*.conf; do
                [ -f "$f" ] || continue
                # Seules les lignes [nom], user, listen sont lues : jamais les lignes env[...].
                awk '
                    /^\[[^]]+\]/ { nom=$0 }
                    /^[[:space:]]*user[[:space:]]*=/ { u=$0; sub(/^[^=]*=[[:space:]]*/, "", u) }
                    /^[[:space:]]*listen[[:space:]]*=/ { l=$0; sub(/^[^=]*=[[:space:]]*/, "", l) }
                    END { printf "    %s | utilisateur=%s | listen=%s\n", nom, u, l }
                ' "$f"
            done
            local manque=""
            if [ -x "/usr/bin/php$ver" ]; then
                for e in pdo_pgsql mbstring intl xml curl zip; do
                    "/usr/bin/php$ver" -m 2>/dev/null | grep -qi "^$e$" || manque="$manque $e"
                done
                if [ -z "$manque" ]; then info "  extensions utiles pour GAMAD : toutes présentes"; else info "  extensions utiles manquantes :$manque"; fi
            fi
        done
    else
        non "PHP-FPM (aucun dossier /etc/php)"
    fi

    # ------------------------------------------------------ PostgreSQL
    titre "PostgreSQL"
    if a psql; then ok "Client : $(psql --version 2>/dev/null)"; else non "Client psql"; fi
    if a pg_lsclusters; then
        info "Grappes (version, nom, port, état) :"
        pg_lsclusters 2>/dev/null | indente
    fi
    local bases
    if bases=$(en_tant_que postgres psql -Atqc "select datname from pg_database where not datistemplate order by 1") && [ -n "$bases" ]; then
        info "Bases de données (noms seulement, aucune donnée lue) :"
        printf '%s\n' "$bases" | indente
        info "Version du serveur : $(en_tant_que postgres psql -Atqc 'show server_version' | head -1)"
    elif a psql; then
        info "Impossible de lister les bases (serveur arrêté, ou diagnostic lancé sans sudo)."
    else
        non "Serveur PostgreSQL"
    fi

    # -------------------------------------------------- Outils de build
    titre "Composer, Node, Certbot"
    if a composer; then ok "Composer : $(COMPOSER_ALLOW_SUPERUSER=1 composer --version --no-interaction 2>/dev/null | head -1)"; else non "Composer"; fi
    if a node; then ok "Node : $(node --version 2>/dev/null) (npm $(npm --version 2>/dev/null))"; else non "Node"; fi
    if a certbot; then
        ok "Certbot : $(certbot --version 2>&1 | head -1)"
    else
        non "Certbot"
    fi
    if a snap; then info "snap : présent"; fi

    # ----------------------------------------------------------- Réseau
    titre "Ports à l'écoute (processus par leur nom)"
    local p ligne hex
    for p in 80 443 5432; do
        if a ss; then
            # Adresse d'écoute, puis nom du processus s'il est visible (jamais sa ligne de commande).
            ligne=$(ss -H -ltnp 2>/dev/null | awk -v port=":$p" '$4 ~ port"$" { proc = "(processus non visible)"; if (match($0, /users:\(\("[^"]*"/)) { proc = substr($0, RSTART + 9, RLENGTH - 10) } print $4 " — " proc }' | sort -u)
            if [ -n "$ligne" ]; then
                info "Port $p : à l'écoute"
                printf '%s\n' "$ligne" | indente
            else
                info "Port $p : personne n'écoute"
            fi
        else
            # Repli sans l'outil « ss » : lecture directe de la table des sockets du noyau (état 0A = à l'écoute).
            hex=$(printf '%04X' "$p")
            if grep -qiE "^[[:space:]]*[0-9]+: [0-9A-F]+:$hex [0-9A-F]+:[0-9A-F]+ 0A" /proc/net/tcp /proc/net/tcp6 2>/dev/null; then
                info "Port $p : à l'écoute (processus non identifiable : l'outil ss est absent)"
            else
                info "Port $p : personne n'écoute"
            fi
        fi
    done

    titre "Pare-feu"
    if a ufw; then
        local etat_ufw; etat_ufw=$(ufw status 2>/dev/null)
        if [ -n "$etat_ufw" ]; then
            info "ufw : $(printf '%s\n' "$etat_ufw" | head -1)"
            printf '%s\n' "$etat_ufw" | sed -n '2,20p' | indente
        else
            info "ufw : installé, mais son état n'est pas lisible (droits insuffisants)"
        fi
    else
        info "ufw : absent"
    fi
    if a firewall-cmd; then info "firewalld : $(firewall-cmd --state 2>/dev/null || echo inactif)"; fi
    if a nft; then info "nftables : $(nft list ruleset 2>/dev/null | wc -l) lignes de règles"; fi
    if a iptables; then
        local pol; pol=$(iptables -S 2>/dev/null | grep -E '^-P')
        if [ -n "$pol" ]; then
            info "iptables — politiques par défaut :"
            printf '%s\n' "$pol" | indente
        else
            info "iptables : présent, règles non lisibles (droits insuffisants)"
        fi
    fi

    # ------------------------------------------------------- Ressources
    titre "Espace disque et mémoire"
    df -hP / /var /srv /home 2>/dev/null | awk '!vu[$6]++' | indente
    free -h 2>/dev/null | indente
    info "Processeurs : $(nproc 2>/dev/null)"
    info "Charge moyenne : $(cut -d' ' -f1-3 /proc/loadavg 2>/dev/null)"

    titre "Services actifs (noms seulement)"
    if a systemctl && [ -d /run/systemd/system ]; then
        systemctl list-units --type=service --state=running --no-legend --plain 2>/dev/null | awk '{print $1}' | indente
    else
        info "systemd non détecté."
    fi

    # --------------------------------------------------------------- DNS
    titre "Résolution DNS de $DOMAINE"
    local ips=""
    if a getent; then ips=$(getent ahostsv4 "$DOMAINE" 2>/dev/null | awk '{print $1}' | sort -u | tr '\n' ' '); fi
    if [ -n "$ips" ]; then info "$DOMAINE pointe vers : $ips"; else info "$DOMAINE ne se résout pas (enregistrement DNS absent ou pas encore propagé)."; fi
    local ip_publique=""
    if a curl && [ "${DIAG_SANS_RESEAU:-0}" != "1" ]; then
        ip_publique=$(curl -fsS -m 6 https://api.ipify.org 2>/dev/null || true)
    fi
    if [ -n "$ip_publique" ]; then
        info "Adresse IP publique de ce serveur : $ip_publique"
        case " $ips " in
            *" $ip_publique "*) info "Le DNS pointe bien vers ce serveur." ;;
            *) [ -n "$ips" ] && info "ATTENTION : le DNS ne pointe pas vers l'adresse de ce serveur." ;;
        esac
    else
        info "Adresse IP publique : non déterminée (pas de réseau sortant, ou DIAG_SANS_RESEAU=1)."
    fi

    # ----------------------------------------- Noms réservés pour GAMAD
    titre "Noms que l'installation voudrait utiliser (vérification de collision)"
    id gamad >/dev/null 2>&1 && info "Utilisateur système « gamad » : DÉJÀ UTILISÉ" || info "Utilisateur système « gamad » : libre"
    [ -e /srv/gamad ] && info "Dossier /srv/gamad : DÉJÀ PRÉSENT" || info "Dossier /srv/gamad : libre"
    if [ -n "${bases:-}" ] && printf '%s\n' "$bases" | grep -qx gamad; then
        info "Base PostgreSQL « gamad » : DÉJÀ UTILISÉE"
    else
        info "Base PostgreSQL « gamad » : libre (ou non vérifiable)"
    fi
    ls /etc/php/*/fpm/pool.d/gamad.conf >/dev/null 2>&1 && info "Pool PHP-FPM « gamad » : DÉJÀ UTILISÉ" || info "Pool PHP-FPM « gamad » : libre"

    printf '\n== Fin du diagnostic ==\n'
    printf 'Rien n'"'"'a été installé, modifié ni redémarré. Copiez tout ce texte et envoyez-le.\n'
}

# Filtre de sécurité : masque tout ce qui ressemble à un secret, même si une commande en laissait passer un.
# L'entrée standard est fermée : le script reste sûr même lancé par « curl … | sudo bash ».
diagnostic </dev/null 2>&1 | sed -E 's/((pass(word|wd)?|secret|token|api[_-]?key|private[_-]?key|app[_-]?key)[A-Za-z_]*[[:space:]]*[=:][[:space:]]*).*/\1[masqué]/I'

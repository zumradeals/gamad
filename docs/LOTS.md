# LOTS — plan de construction

Un lot à la fois, de la base de données jusqu'à l'écran du téléphone. Un lot n'est commencé que lorsque le
précédent a été essayé et validé par le Président sur un téléphone. Seul le Président change le statut d'un
lot en « Terminé ».

| Lot | Nom | Statut |
| --- | --- | --- |
| 0 | Socle | **Terminé** (essai du Président réussi sur reseau.dgafrique.com le 2026-10-22) |
| 0.1 | Mise en ligne et paramètres | **Terminé** (essai du Président réussi sur reseau.dgafrique.com le 2026-10-22) |
| 1 | Personne | **Terminé** (marqué terminé le 2026-10-23 sur instruction du Président) |
| 2 | Fil global et gestes | **Terminé** (PR 2a, 2b et 2c fusionnées, marqué terminé par le Président le 2026-10-23) |
| 3 | ZUMRA | **Terminé** (PR 3a, 3b et 3c fusionnées, marqué terminé le 2026-10-23 sur instruction du Président) |
| 4 | Projets | **Terminé** (PR 4a, 4b et 4c fusionnées, marqué terminé le 2026-10-23 sur instruction du Président) |
| 5 | Ouverture (pilote) | **Terminé** (PR 5a, 5b et 5c fusionnées, marqué terminé le 2026-10-23 sur instruction du Président ; restent à faire par lui : l'essai de restauration sur son stockage réel et le pilote fermé) |
| 5.1 | Carrefour Besoins et Découvrir | **Terminé** (PR 5.1 fusionnée, marqué terminé le 2026-10-23 sur instruction du Président) |
| 6 | Contributions mensuelles et portefeuille ZAHAB | **En cours** (PR 6a) |

**Condition d'ouverture publique (D-078).** Avant toute ouverture publique de la plateforme, la fonction « Signaler » et la
modération (Lot 2) doivent être livrées et essayées. D'ici là, la plateforme reste en essai fermé.

---

## Lot 0 — Socle (Terminé)

**But :** un dépôt propre où une personne peut créer un compte et se connecter sur téléphone, dans le gabarit
visuel validé, avec un super administrateur dont les actes sont tracés.

**Périmètre :**

1. Projet Laravel (dernière version stable), PostgreSQL, Livewire 3, Alpine.js, Tailwind CSS, Pest.
   Version de Laravel consignée dans `DECISIONS.md`.
2. Configuration : `APP_LOCALE=fr`, messages de validation en français, fuseau `Africa/Abidjan`.
3. Authentification (D-015, D-016) : inscription (nom affiché, e-mail, mot de passe), connexion, déconnexion,
   mot de passe oublié, vérification de l'e-mail. Utiliser le kit de démarrage officiel Livewire de Laravel
   ou Breeze (pile Livewire), restylé selon `DESIGN.md`. Courriels en pilote `log` en développement.
   L'authentification est isolée derrière les mécanismes standard de Laravel (guard), sans code spécifique
   dispersé, pour permettre une fédération plus tard.
4. Identifiants : UUID pour les tables exposées publiquement (`users` compris).
5. Rôle de plateforme sur `users` : `member` (par défaut), `moderator`, `super_admin`. Gate `super-admin`
   et `moderate`. Le super administrateur est créé par une commande Artisan
   `gamad:super-admin {email}` qui promeut un compte existant ; jamais par un mot de passe écrit dans le code.
6. Journal d'administration : table `admin_actions` (id, `actor_id`, `action`, `subject_type`, `subject_id`,
   `reason`, `payload` JSON, `created_at`). Service `AdminJournal::record(...)`. Le modèle refuse toute
   modification et toute suppression (exception levée). La commande `gamad:super-admin` y inscrit sa promotion.
7. Jetons de design de `DESIGN.md` §1–3 déclarés dans Tailwind ; police Plus Jakarta Sans auto-hébergée.
8. Gabarit d'application :
   - téléphone : barre du haut + barre de navigation du bas (Fil · Découvrir · Agir · ZUMRA · Espace) ;
   - ordinateur : barre du haut (Fil · Personnes · Besoins · Projets · ZUMRA, recherche, Agir, avatar).
   Les entrées de navigation viennent d'un fichier de configuration avec un drapeau `enabled`. **En lot 0,
   seules Fil et Espace sont activées** ; les autres sont masquées (pas de liens morts).
9. Écrans du lot 0 :
   - pages d'authentification au style GAMAD ;
   - Fil : état vide « Le Fil se remplira bientôt. Commencez par compléter votre profil. » (le lien vers le
     profil n'apparaît qu'au lot 1 ; en lot 0, le texte seul) ;
   - Espace : nom, e-mail (visible par la personne seule), bouton Déconnexion, suppression du compte avec
     confirmation ;
   - page d'accueil publique minimale : logo, devise, une phrase, boutons « Créer un compte » et « Se
     connecter ». Pas d'illustration inventée.
10. Intégration continue GitHub Actions : installation, `php artisan test` avec un service PostgreSQL, Pint.
11. `README.md` court (installation locale, lancement, tests) et `docs/TESTER.md` : comment le Président
    essaie le lot sur son téléphone.

**Hors périmètre :** profils, capacités, ZUMRA, publications, gestes, notifications, paiements, données
d'exemple en production.

**Terminé quand :**

- une personne crée un compte, vérifie son e-mail, se connecte et se déconnecte sur un téléphone ;
- la navigation n'affiche que Fil et Espace, au style des maquettes, à 390 px et sur ordinateur ;
- la commande `gamad:super-admin` promeut un compte et l'inscrit au journal ;
- un test prouve qu'une entrée du journal ne peut être ni modifiée ni supprimée ;
- un test prouve qu'un membre ordinaire n'a pas accès aux Gates `super-admin` et `moderate` ;
- tous les tests et l'intégration continue passent.

---

## Lot 0.1 — Mise en ligne et paramètres (Terminé)

**But :** mettre le socle en ligne sur `reseau.dgafrique.com` (D-050) pour que le Président l'essaie sur son
téléphone, avec des réglages métier modifiables sans jamais ouvrir un fichier `.env` (D-049).

**Départ :** ce lot a commencé à la fusion du Lot 0 dans `main` (D-052). Il avance par phases, avec
un arrêt obligatoire après la phase A.

**Phase A — Diagnostic (à faire en premier, rien d'autre) :**

- `deploy/diagnostic.sh` : script **en lecture seule** (aucune installation, modification ni redémarrage).
  Il affiche : version d'Ubuntu ; serveur web présent (Nginx, Apache, Caddy, Docker…) et noms de domaine déjà
  configurés ; versions de PHP et pools PHP-FPM existants ; version de PostgreSQL et liste des bases (sans
  aucune donnée) ; Composer et Node présents ou non ; Certbot présent ou non ; ports 80, 443 et 5432 à
  l'écoute ; état du pare-feu ; espace disque et mémoire ; résolution DNS de `reseau.dgafrique.com`.
  Il n'affiche **jamais** le contenu d'un fichier `.env`, d'un mot de passe ou d'une clé.
- Livrable : une seule ligne à copier dans la console du VPS (téléchargement depuis la branche `main` du dépôt
  public, puis exécution avec `sudo`). Arrêt ensuite : le Président renvoie le résultat.

**Phase B — après analyse du diagnostic seulement :**

1. `deploy/install.sh`, lancé une seule fois avec une seule ligne :
   - n'installe que ce qui manque ; ne met à jour ni ne supprime aucun paquet existant ; ne touche à aucune
     configuration d'un autre site ; ne modifie pas le pare-feu ;
   - crée un utilisateur système dédié, une base PostgreSQL et un utilisateur dédiés (mot de passe aléatoire),
     un pool PHP-FPM dédié, un bloc serveur web dédié à `reseau.dgafrique.com` ; vérifie la configuration
     (par exemple `nginx -t`) avant tout rechargement et s'arrête proprement en cas d'erreur ;
   - obtient le certificat HTTPS ;
   - écrit le `.env` automatiquement (amorçage technique seulement) ;
   - demande interactivement le nom, l'e-mail et le mot de passe du Président, et crée directement son compte
     vérifié avec le rôle super administrateur, inscrit au journal (acteur « Installation », avec machine et
     compte système) ;
   - met en place des sauvegardes quotidiennes de la base (7 jours conservés) ;
   - affiche à la fin un résumé clair en français, sans aucun secret.
2. Déploiement automatique sans secret à configurer : un minuteur systemd vérifie toutes les 5 minutes la
   branche `main` du dépôt public ; s'il y a un nouveau commit : nouvelle version dans un dossier de versions,
   installation, compilation du front, migrations, bascule par lien symbolique, 5 versions conservées. En cas
   d'échec, la version en ligne reste inchangée et l'échec est écrit dans un journal. `deploy/retour-arriere.sh`
   revient à la version précédente.
3. Page « Paramètres » (super administrateur seulement, Gate `super-admin`), accessible depuis Espace :
   - Courriel : serveur SMTP, port, chiffrement, identifiant, mot de passe, adresse et nom d'expéditeur,
     bouton « Envoyer un e-mail de test » à l'adresse du super administrateur ;
   - Plateforme : nom, adresse publique ;
   - Sécurité : limites d'inscription et de connexion.
   Table `settings` ; mots de passe chiffrés avec la clé de l'application et jamais réaffichés (champ vide =
   inchangé) ; valeurs appliquées à la configuration au démarrage ; chaque modification inscrite au journal
   d'administration avec l'ancienne et la nouvelle valeur, sauf les secrets (seulement « modifié »).
4. `docs/TESTER.md` réécrit pour un essai sur `reseau.dgafrique.com`, sans aucune ligne de commande.

**Contraintes :** l'installation ne modifie jamais les autres services du VPS (D-050). Les scripts de `deploy/`
sont testés dans un conteneur Ubuntu avant livraison. Branche `lot-0-1-mise-en-ligne` ; Pull Request avec
rapport ; pas de fusion par Claude.

**Hors périmètre :** profils, ZUMRA, publications, paiements, tout autre module des lots 1 à 6.

**Terminé quand :**

- `reseau.dgafrique.com` répond en HTTPS avec le socle, et une fusion sur `main` met le site à jour sans
  intervention ;
- le compte du Président existe, vérifié, super administrateur, inscrit au journal ;
- la page Paramètres est refusée à un membre et à un modérateur, chiffre les secrets, journalise sans secret,
  et l'e-mail de test arrive ;
- le Président essaie le lot sur son téléphone en suivant `docs/TESTER.md`, sans ligne de commande ;
- tous les tests et l'intégration continue passent.

---

## Lot 1 — Personne (Terminé)

Maquettes : écrans 5, 6 et 7 de `docs/DESIGN.md`. Décisions : D-065 à D-070.

**Périmètre :**

1. Données : présentation (280 caractères), pays, ville, visibilité (public / inscrits), photo ; capacités
   partagées avec forme normalisée unique ; liens personne–capacité avec le sens « apporte » ou « apprend »
   (10 au plus chacun) ; domaines (17, avec ordre et état actif) et liens personne–domaine (3 au plus) ;
   pays et villes. Les villes ivoiriennes sont fournies par un seeder qui cite sa source dans un
   commentaire ; aucun nom n'est inventé.
2. Écran « Modifier mon profil » (Espace › Mon profil), écran 6 : suggestions de capacités déjà utilisées,
   création d'une nouvelle capacité, retrait, validation (2 à 60 caractères), photo traitée comme D-070,
   enregistrement avec message de confirmation.
3. Écran « Monde d'une personne » (`/personnes/{identifiant}`), écran 5. Au Lot 1, seul l'onglet
   « Apporte · Apprend » existe ; Fil, ZUMRA, Remerciements, Suivre et « Inviter dans ma ZUMRA » sont masqués
   (Lots 2 et 3). Le propriétaire voit « Modifier mon profil ». Toucher une capacité ouvre le Carrefour
   Personnes filtré sur elle.
4. Écran « Carrefour Personnes » (`/personnes`), écran 7 : recherche par capacité ou nom (sans tenir compte
   de la casse ni des accents), bascule « peut l'apporter » / « veut l'apprendre », filtres ville et
   domaine, pagination, état vide qui invite à compléter son profil. Un visiteur sans compte ne voit que
   les profils publics ; un profil « inscrits seulement » n'existe pas pour lui (404). Le bouton « Inviter »
   est masqué jusqu'au Lot 3.
5. Navigation : « Découvrir » (téléphone) et « Personnes » (ordinateur) mènent au Carrefour Personnes ; les
   onglets Besoins et Projets de Découvrir restent masqués. L'état vide du Fil renvoie vers « Compléter mon
   profil ».
6. Paramètres du super administrateur : sections « Domaines » et « Territoires » (ajouter, renommer,
   désactiver ; jamais supprimer), chaque acte inscrit au journal d'administration.
7. Tests : critères de fin, visibilité, limites (10 capacités, 3 domaines), normalisation (« Comptabilité »,
   « comptabilite », « COMPTABILITÉ » = une seule capacité), suppression des métadonnées de la photo, accès
   réservé aux sections Domaines et Territoires.

**Hors périmètre :** ZUMRA, publications, gestes, Suivre, Remerciements, invitations, notifications,
messagerie, paiements, données d'exemple en production.

**Terminé quand :**

- deux comptes de test se trouvent l'un l'autre par capacité et par ville, dans les deux sens (apporter et
  apprendre) ;
- un profil « inscrits seulement » est invisible pour un visiteur sans compte, visible une fois connecté ;
- le parcours complet fonctionne à 390 px et sur ordinateur, fidèle aux maquettes ;
- l'intégration continue est verte et `docs/TESTER.md` décrit l'essai du Lot 1 sur reseau.dgafrique.com.

---

## Lot 2 — Fil global et gestes (Terminé)

Maquettes : écrans 1 et 4 (Fil), puis 8 à 16 de `docs/DESIGN.md`. Décisions : D-079 à D-090. Livré en trois Pull Requests
successives, chacune relue par l'architecte et fusionnée par le Président avant la suivante : **2a**, **2b**, **2c**.

**Périmètre :**

*2a — Publier, trouver, proposer un geste*

1. Publications typées : J'ai besoin, Je partage un savoir, Je veux apprendre, Je propose mon aide (personne seulement,
   D-081) ; texte brut de 500 caractères au plus, sans lien cliquable ni image (D-086) ; visibilité Public ou Inscrits
   seulement (D-085).
2. « Agir » activé dans la navigation : choisir le type (écran 8), publier (écran 9).
3. Fil réel, téléphone et ordinateur (écrans 1 et 4, avec seulement du contenu réel) : ordre chronologique inverse, aucun
   algorithme, filtres Tout, Besoins, Savoirs ; seules les publications visibles pour celui qui regarde (règle côté
   serveur, D-087). « Commenter » et « Partager » masqués (D-090).
4. Gestes (écran 10) : Je peux aider, Je veux apprendre, Je peux transmettre, Ça m'intéresse ; un mot facultatif de 280
   caractères ; un geste par personne et par publication, retirable tant qu'il n'est pas accepté ; pas de geste sur sa
   propre publication (D-082).
5. Mon besoin (écran 11) : propositions reçues, Accepter et Décliner ; états ouvert, en cours, résolu, fermé sans suite ;
   Marquer résolu et Fermer sans suite par l'auteur seul (D-083). Sans remerciement ni conversation.

*2b — Parler, remercier, être prévenu*

6. Conversation privée ouverte par un geste accepté (écran 12), rafraîchie toutes les 10 secondes, sans WebSocket ; 1 000
   caractères par message.
7. Remerciement (écran 13) : public sur le profil de la personne qui a aidé, choisie parmi les gestes acceptés ou aucune ;
   masquable par elle ; section « Remerciements reçus » activée sur l'écran 5 (D-084).
8. Notifications dans l'application, cloche, « Tout marquer lu » (écran 15), sans e-mail ni téléphone d'autrui.
9. « À faire maintenant » dans Espace (écran 14) : chaque ligne n'existe que s'il y a vraiment une action à faire.

*2c — Signaler et modérer*

10. Signaler (écran 16) ; file de signalements pour le super administrateur et les modérateurs ; Masquer et Rétablir, chaque
    acte inscrit au journal d'administration ; l'auteur d'une publication masquée voit le motif et peut écrire une
    explication avant toute sanction (D-089). Ces deux écrans n'ont pas de maquette : construits sobres, avec les
    composants existants.
11. Protection contre les abus : limites de débit côté serveur (valeurs consignées dans le rapport, D-086).

**Hors périmètre :** Avancée de projet, Appel à rejoindre, publication au nom d'une ZUMRA (lots 3 et 4), « Commenter »,
« Partager », images et liens cliquables dans les publications, paiements, e-mails de notification.

**Terminé quand :**

- le parcours « J'ai besoin » → « Je peux aider » → acceptation → conversation → remerciement passe de bout en bout sur
  téléphone ;
- le parcours « Je partage un savoir » → « Je veux apprendre » → apprenant accepté passe de bout en bout sur téléphone ;
- un signalement va jusqu'au masquage ;
- l'intégration continue est verte et `docs/TESTER.md` décrit l'essai de chaque Pull Request.

---

## Lot 3 — ZUMRA (Terminé)

Maquettes : Carrefour ZUMRA et Monde d'une ZUMRA (`docs/maquettes/`, D-011). Décisions : D-003, D-006, D-009, D-110 à D-117.
Livré en trois Pull Requests successives, chacune relue par l'architecte et fusionnée par le Président avant la suivante :
**3a**, **3b**, **3c**. Un module non encore construit est masqué (règle « pas de faux produit ») : en particulier les projets
(Lot 4), le compteur de projets, « Suivre » et « Inviter dans ma ZUMRA » sur le profil d'une personne tant que 3b n'est pas livré.

**Périmètre :**

*3a — Créer et trouver*

1. Créer une ZUMRA : toute personne connectée et vérifiée. Nom (unique sans tenir compte de la casse, des accents ni des
   espaces), **un seul domaine** (liste de référence), ville (liste de référence, facultative), format (en ligne, sur place ou
   hybride), mission (texte brut de 500 caractères au plus). La personne devient **fondateur** et premier membre. États : active ;
   les états reconnue (3c), suspendue et archivée ne sont pas construits.
2. Carrefour ZUMRA (une seule vue réutilisant le gabarit du Carrefour Personnes) : recherche par nom ou mission, filtres Domaines
   et Villes, « Mes ZUMRA », « Créer une ZUMRA ». Ordre alphabétique (D-114). Chaque carte montre seulement des chiffres réels : le
   nombre de membres. Les compteurs de projets et de savoirs partagés, et le filtre « Reconnues par GAMAD », sont masqués jusqu'à
   ce qu'ils soient réels.
3. Monde d'une ZUMRA : en-tête (initiales, nom, domaine · ville · format, état, mission, nombre de membres, date de création) ;
   onglet **Membres** (les autres onglets viennent avec 3b et 3c). Public pour l'en-tête, Membres pour les personnes connectées et
   vérifiées, avec la règle de visibilité de chaque profil (D-112).
4. Rôles : fondateur, responsable, membre (D-111). En 3a, seul le fondateur existe.
5. Modifier sa ZUMRA : le fondateur et les responsables modifient la mission, la ville et le format ; le nom et le domaine sont
   fixes (D-114).
6. Chaque règle côté serveur (Policies), limites de débit à la création (D-117), tests, mutations.

*3b — Entrer*

7. Demande d'adhésion (« Je veux rejoindre », mot facultatif de 280 caractères) : une demande en cours par personne et par
   ZUMRA, retirable ; le fondateur ou un responsable l'accepte ou la décline (D-110).
8. Invitation par capacité : un responsable trouve des personnes par ce qu'elles peuvent apporter (Carrefour Personnes) et les
   invite, avec un mot facultatif ; la personne accepte ou refuse ; l'invitation expire après 14 jours (D-110).
9. Membres : quitter la ZUMRA, retirer un membre (le fondateur ne peut pas être retiré), nommer et retirer un responsable (le
   fondateur seul, D-111). Fondateur qui supprime son compte : D-115.
10. Notifications et « À faire maintenant » : demandes à traiter, invitations reçues, réponses.

*3c — Vivre et reconnaître*

11. Publier au nom d'une ZUMRA (fondateur et responsables) : J'ai besoin, Je partage un savoir, Je propose mon aide, et le type
    **Appel à rejoindre** (D-116) ; niveau de visibilité « ZUMRA seulement » en plus de Public et Inscrits seulement (D-112) ;
    filtre « ZUMRA » du Fil global (D-098).
12. Onglets **Fil** (mini-fil de la ZUMRA), **Formation** (savoirs partagés par la ZUMRA, ce que ses membres savent faire et veulent
    apprendre, avec la règle de visibilité de chaque profil) et **Discussion** (fil simple réservé aux membres, 1 000 caractères
    par message, D-021).
13. Besoins de ZUMRA : gestes, acceptation et état gérés par les responsables ; remerciement par le responsable qui résout (D-116).
14. Reconnaissance par le super administrateur : badge « Reconnue par GAMAD », retrait possible, chaque acte inscrit au journal
    d'administration ; filtre « Reconnues par GAMAD » du Carrefour.

**Hors périmètre :** projets et leur onglet (Lot 4), « Suivre », contributions et paiements, chat en direct, ZUMRA suspendue ou
archivée, images, liens cliquables, e-mails de notification.

**Terminé quand :**

- une ZUMRA réelle invite trois personnes trouvées par capacité, publie dans son mini-fil, puis est reconnue par le super
  administrateur, de bout en bout sur téléphone ;
- un visiteur sans compte comprend ce que fait une ZUMRA en lisant son Monde public ;
- la Discussion d'une ZUMRA n'est lisible que par ses membres ;
- l'intégration continue est verte et `docs/TESTER.md` décrit l'essai de chaque Pull Request.

---

## Lot 4 — Projets (Terminé)

Maquettes : aucune fournie ; écrans dessinés à partir du gabarit Monde (D-129). Décisions : D-005, D-126 à D-129.
Livré en trois Pull Requests, chacune relue puis fusionnée avant la suivante (D-126).

**Périmètre :**

*PR 4a — Créer et trouver*
1. Projet (`Project`) rattaché à une seule ZUMRA : nom, but, état (idée · en cours · terminé · abandonné), domaine et lieu repris de la ZUMRA.
2. Création et modification par le fondateur et les responsables de la ZUMRA (D-127), avec journal des changements d'état.
3. Monde du projet : en-tête (nom, ZUMRA, état) et onglet Avancement ; page publique lisible sans compte.
4. Onglet Projets de la ZUMRA et compte réel de projets dans son en-tête.
5. Carrefour Projets (recherche, filtres domaine, ville et état), accessible depuis la barre d'ordinateur et le Carrefour ZUMRA.

*PR 4b — Avancer*
6. Étapes d'un projet (à faire · en cours · franchie), ordonnées, gérées par les responsables.
7. Avancées (`project_update`) et réalisations (`achievement`) publiées au nom du projet, onglet Fil du projet, présentes dans le Fil global avec le filtre « Projets » (reporté depuis la 4a : sans publication de projet, ce filtre serait vide).
8. Avancement affiché à partir des étapes réelles, jamais d'un pourcentage inventé.

*PR 4c — Équipe, besoins et soutiens*
9. Équipe : membres de la ZUMRA désignés ou admis sur demande « Je veux participer » (D-128), onglet Équipe.
10. Besoins du projet : besoins publiés au nom du projet, onglet Besoins.
11. Geste « Je soutiens » sur une avancée : engagement réel compté (« 4 personnes soutiennent »), jamais un « j'aime ».
12. Notifications et « À faire maintenant » des responsables du projet.

**Hors périmètre :** investissement, paiement, startup issue d'un projet (voir `docs/PLUS-TARD.md`).

**Terminé quand** un visiteur sans compte comprend où en est un projet réel en moins d'une minute.

## Lot 5 — Ouverture (Terminé)

Décisions : D-078, D-144 à D-147. Livré en trois Pull Requests, chacune relue puis fusionnée avant la suivante (D-144).

**Périmètre :**

*PR 5a — Accueil public, charte et mentions légales*
1. Page d'accueil publique complète pour un visiteur : ce qu'est GAMAD, comment cela marche (personne, ZUMRA, projets, Fil), boutons « Créer un compte » et « Se connecter » ; seuls des chiffres et exemples réels y figurent (jamais de donnée inventée).
2. Charte de conduite et mentions légales : projets de textes rédigés par Claude, champs à compléter par le Président (éditeur, contact, hébergeur) ; pages et liens du pied de page masqués tant que le Président n'a pas adopté les textes (D-146).
3. Acceptation de la charte à l'inscription une fois adoptée : case à cocher, date enregistrée.

*PR 5b — Outil de modération*
4. Écran de lecture du journal d'administration, pour le super administrateur et les modérateurs (D-145).
5. Suspension et rétablissement d'une personne, avec un motif, tracés au journal, après le droit de s'expliquer.
6. Notification à la modération quand l'auteur d'une publication masquée s'explique.

*PR 5c — Exploitation*
7. Copie nocturne chiffrée des sauvegardes (base, `.env`, photos de profil) vers un stockage objet compatible S3, avec essai de restauration documenté et réussi (D-147).
8. Test en connexion faible : poids des pages, chargement sur une connexion lente simulée, correctifs des pages trop lourdes.
9. Préparation du pilote fermé (30 à 50 personnes et 3 ZUMRA) : guide de déroulement dans `docs/TESTER.md`, liste de vérification avant ouverture.

**Condition d'ouverture publique (D-078) :** « Signaler » et la modération du Lot 2 sont livrés ; ils doivent avoir été essayés pendant le pilote avant toute ouverture publique. L'ouverture publique elle-même est une décision du Président.

**Terminé quand** le pilote fermé tourne avec la charte adoptée et une sauvegarde hors serveur restaurée avec succès.

## Lot 5.1 — Carrefour Besoins et Découvrir (Terminé)

Décidé par le Président le 2026-10-23, avant le Lot 6 (D-160). Une seule Pull Request. Maquette : capture de la fenêtre « Découvrir » de l'ancien projet, fournie par le Président (fenêtre « Que cherchez-vous ? » avec trois choix).

**Périmètre :**
1. Carrefour Besoins (`/besoins`), ouvert aux visiteurs comme les autres Carrefours, sur le même gabarit : les besoins publiés par une personne, une ZUMRA ou un projet (PRODUIT §2 le prévoyait sans qu'aucun lot ne le construise). Chaque besoin affiche qui l'émet ; recherche par texte ou capacité ; filtres état (ouverts par défaut), domaine, ville, émetteur (personne, ZUMRA, projet) ; « Voir le besoin » mène à la publication, où se trouve le geste.
2. Barre d'ordinateur : Fil, Personnes, **Besoins**, Projets, ZUMRA (l'entrée Besoins, masquée jusqu'ici, est activée).
3. Téléphone : « Découvrir » ouvre une fenêtre « Que cherchez-vous ? » avec trois choix, **Personnes, Besoins, Projets**, chacun menant à son Carrefour. Sans JavaScript, « Découvrir » mène à une page `/decouvrir` qui montre les mêmes trois choix.
4. L'entrée « Besoins » du Fil (colonne « Explorer GAMAD ») rejoint Personnes, ZUMRA et Projets.

**Hors périmètre :** les organisations comme émetteurs de besoins (D-017 : « une prochaine fois », décision du Président) ; la recherche générale ; un classement de besoins.

**Terminé quand** un visiteur et une personne connectée trouvent un besoin émis par une personne, une ZUMRA ou un projet depuis le Carrefour Besoins ; le menu de l'ordinateur affiche cinq entrées ; la fenêtre « Découvrir » du téléphone ouvre les trois Carrefours ; `php artisan test` et la CI passent.

## Lot 6 — Contributions mensuelles et portefeuille ZAHAB (En cours)

Décisions : D-004, D-163 à D-174 ; D-019 est remplacée par D-166 ; D-171 est tranchée par D-172. Lancé sur « Passe au Lot 6 » du Président le 2026-10-23.

**Conditions avant l'argent réel (PR 6b et suivantes) :** avis juridique sur le crédit prépayé interne et les fonds détenus pour autrui ; entité juridique bénéficiaire fournie par le Président (D-172) ; compte marchand GeniusPay ouvert par le Président et lecture de sa documentation officielle (D-169). La PR 6a n'en a besoin d'aucune : aucun argent n'y circule. `docs/PRODUIT.md` est aligné (version 1.1).

**Périmètre prévu (trois Pull Requests, chacune relue puis fusionnée avant la suivante) :**

*PR 6a — Portefeuilles et registre (sans argent réel)*
1. Portefeuille ZAHAB d'une personne et d'une ZUMRA ; registre des mouvements en ajout seul (contrainte de base comme le journal d'administration) ; solde calculé depuis le registre, jamais saisi.
2. Écrans « Mon portefeuille » et « Portefeuille de la ZUMRA » : solde, mouvements, états vides. Aucun transfert entre utilisateurs, aucun retrait.
3. Droits : un portefeuille de personne ne se lit que par elle ; celui d'une ZUMRA, par son fondateur et ses responsables. Réglages dans Espace → Paramètres → Portefeuille : activation (masqué par défaut), plafonds de solde. Suppression d'un compte ou d'une ZUMRA refusée tant que son solde n'est pas nul (D-172). Chaque réglage modifié est inscrit au journal d'administration.

*PR 6a et PR 6b fusionnées le 2026-10-23. PR 6c en cours sur ordre du Président (« Fusionne si tout est vert et démarre la 6c »). PR 6b : elle a été lancée sur ordre du Président (« fusionne et démarre la 6b », clés à saisir dans l'espace admin) ; documentation GeniusPay fournie par le Président.*

*PR 6b — Recharge par GeniusPay (mode d'essai d'abord)*
4. Clés GeniusPay dans Espace → Paramètres ; création du paiement, page hébergée, rappel serveur de confirmation, crédit seulement après confirmation, rapprochement avec le relevé.
5. Plafonds de recharge et de dépense ; reçus ; remboursements et litiges tracés. Le mode réel n'est activé qu'après l'avis juridique et sur décision du Président.

*PR 6c — Contribution, engagement et avantages*
6. Contribution mensuelle (minimum 500 FCFA, montant libre), rappel mensuel avec demande de paiement, fonds de contribution distinct du fonds des portefeuilles.
7. États « Contributeur » et « ZUMRA engagée », badges (facultatif pour la personne), rapport d'impact, outils avancés de ZUMRA, éligibilité au financement (engagée et reconnue), affectation validée par le Président, tableau des contributions pour le Président, rapport public sans noms.

**Hors périmètre :** transferts entre utilisateurs, retraits, achats (aucun catalogue), assistant IA, paliers ou palmarès de contributeurs, toute visibilité achetée.

**Terminé quand** une personne et une ZUMRA rechargent un portefeuille et contribuent en mode d'essai, le registre se réconcilie avec le relevé du prestataire, aucun mouvement ne peut être modifié ni effacé, le Président affecte le fonds de contribution et voit le tableau, `php artisan test` et la CI passent, et l'avis juridique est obtenu avant tout argent réel.

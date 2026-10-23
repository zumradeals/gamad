# LOTS — plan de construction

Un lot à la fois, de la base de données jusqu'à l'écran du téléphone. Un lot n'est commencé que lorsque le
précédent a été essayé et validé par le Président sur un téléphone. Seul le Président change le statut d'un
lot en « Terminé ».

| Lot | Nom | Statut |
| --- | --- | --- |
| 0 | Socle | **Terminé** (essai du Président réussi sur reseau.dgafrique.com le 2026-10-22) |
| 0.1 | Mise en ligne et paramètres | **Terminé** (essai du Président réussi sur reseau.dgafrique.com le 2026-10-22) |
| 1 | Personne | **En cours** |
| 2 | Fil global et gestes | **En cours** |
| 3 | ZUMRA | À venir |
| 4 | Projets | À venir |
| 5 | Ouverture (pilote) | À venir |
| 6 | Contributions mensuelles | À venir |

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

## Lot 1 — Personne (En cours)

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

## Lot 2 — Fil global et gestes (En cours)

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

## Lot 3 — ZUMRA (À venir)

Carrefour ZUMRA ; création ; rôles ; invitation par capacité ; demande d'adhésion ; Monde d'une ZUMRA avec
onglets Formation, Fil, Projets, Membres, Discussion ; besoins de ZUMRA ; reconnaissance par le super
administrateur, tracée.
Terminé quand une ZUMRA réelle invite trois personnes trouvées par capacité, publie dans son mini-fil, puis
est reconnue.

## Lot 4 — Projets (À venir)

Projet dans une ZUMRA ; Monde du projet et page publique ; équipe, étapes, réalisations, besoins, soutiens ;
Carrefour Projets.
Terminé quand un visiteur sans compte comprend où en est un projet réel en moins d'une minute.

## Lot 5 — Ouverture (À venir)

Page d'accueil publique complète, charte de conduite, mentions légales, outil de modération, test en connexion
faible ; sauvegardes copiées hors du serveur (stockage externe), avec essai de restauration, y compris la sauvegarde
hors serveur des photos de profil ; pilote fermé (taille suggérée : 30 à 50 personnes et 3 ZUMRA) puis ouverture, qui reste
soumise à la condition d'ouverture publique (D-078 : « Signaler » et modération du Lot 2 livrés et essayés).

## Lot 6 — Contributions mensuelles (À venir)

Prestataire de paiement (Mobile Money), montants, avantages validés (D-019), reçus, tableau des contributions
pour le Président. Peut passer avant le lot 5 sur décision du Président.

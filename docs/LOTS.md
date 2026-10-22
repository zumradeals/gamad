# LOTS — plan de construction

Un lot à la fois, de la base de données jusqu'à l'écran du téléphone. Un lot n'est commencé que lorsque le
précédent a été essayé et validé par le Président sur un téléphone. Seul le Président change le statut d'un
lot en « Terminé ».

| Lot | Nom | Statut |
| --- | --- | --- |
| 0 | Socle | **En cours** |
| 1 | Personne | À venir |
| 2 | Fil global et gestes | À venir |
| 3 | ZUMRA | À venir |
| 4 | Projets | À venir |
| 5 | Ouverture (pilote) | À venir |
| 6 | Contributions mensuelles | À venir |

---

## Lot 0 — Socle (En cours)

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

## Lot 1 — Personne (À venir)

Profil « Je peux apporter / Je veux apprendre », capacités partagées, ville et domaines ; Monde d'une
personne ; Carrefour Personnes avec recherche par capacité et ville.
Terminé quand deux comptes de test se trouvent par capacité et par ville.

## Lot 2 — Fil global et gestes (À venir)

Publications typées, gestes, acceptation, conversation privée, besoin résolu avec remerciement ; première
version de « À faire maintenant » dans Espace ; notifications dans l'application ; signalement et masquage.
Terminé quand deux parcours passent de bout en bout sur téléphone : « J'ai besoin » → « Je peux aider » →
remerciement ; « Je partage un savoir » → « Je veux apprendre » → apprenant accepté.

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
faible ; pilote fermé (taille suggérée : 30 à 50 personnes et 3 ZUMRA) puis ouverture.

## Lot 6 — Contributions mensuelles (À venir)

Prestataire de paiement (Mobile Money), montants, avantages validés (D-019), reçus, tableau des contributions
pour le Président. Peut passer avant le lot 5 sur décision du Président.

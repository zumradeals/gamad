# Instruction pour Claude Code — Lot 0 : Socle

Copie tout le texte sous la ligne dans Claude Code, ouvert sur le nouveau dépôt.

---

Tu interviens sur le nouveau dépôt du réseau social d'action GAMAD. Le dépôt contient pour l'instant
uniquement de la documentation. Ta mission est de réaliser le **lot 0 (Socle)**, et rien d'autre.

## 1. Lecture obligatoire, dans cet ordre

1. `CLAUDE.md`
2. `docs/LOTS.md` (section « Lot 0 — Socle »)
3. `docs/PRODUIT.md`
4. `docs/DESIGN.md`, puis les maquettes de `docs/maquettes/` (surtout `Main.dc.html`)
5. `docs/DECISIONS.md`

Avant d'écrire du code, réponds-moi en 10 lignes au plus : ce que tu as compris du lot 0, la liste des
fichiers et tables que tu vas créer, et toute contradiction ou question que tu as relevée. Attends ma
réponse « Go » avant de commencer.

## 2. Ce que tu dois livrer

Exactement le périmètre de la section « Lot 0 » de `docs/LOTS.md`, points 1 à 11 :

- projet Laravel + PostgreSQL + Livewire 3 + Alpine.js + Tailwind + Pest, en français, fuseau Africa/Abidjan ;
- authentification complète (inscription, connexion, déconnexion, mot de passe oublié, vérification de
  l'e-mail), restylée selon `docs/DESIGN.md` ;
- identifiants UUID ; rôle de plateforme `member` / `moderator` / `super_admin` ; Gates `super-admin` et
  `moderate` ;
- commande `php artisan gamad:super-admin {email}` ;
- journal d'administration `admin_actions`, impossible à modifier ou supprimer par l'application ;
- jetons de design dans Tailwind, police Plus Jakarta Sans auto-hébergée ;
- gabarit téléphone (barre du bas) et ordinateur (barre du haut), navigation pilotée par configuration,
  seules les entrées **Fil** et **Espace** activées ;
- écrans : authentification, Fil (état vide), Espace (déconnexion, suppression du compte), accueil public
  minimal ;
- intégration continue GitHub Actions (tests avec PostgreSQL, Pint) ;
- `README.md` court et `docs/TESTER.md`.

## 3. Règles à respecter

- Ne construis **rien** qui soit hors du lot 0 : ni profil, ni ZUMRA, ni publication, ni paiement.
- Aucun lien ou bouton sans comportement réel. Les modules futurs sont masqués.
- Aucune donnée d'exemple en production. Un seeder de développement est permis s'il est nommé
  `DevelopmentSeeder` et jamais appelé en production.
- Respecte au pixel près l'esprit des maquettes : couleurs, police, rayons, cibles de 44 px, barre du bas avec
  le bouton Agir jaune au centre (masqué en lot 0 car « Agir » n'est pas encore activé : la barre du bas
  affiche alors seulement Fil et Espace, centrés).
- Chaque règle d'autorisation et le caractère non modifiable du journal ont leurs tests.
- Travaille sur une branche `lot-0-socle`, en petits commits clairs en français.

## 4. Critères de fin (tous obligatoires)

- Une personne crée un compte, vérifie son e-mail, se connecte et se déconnecte sur un téléphone.
- La navigation n'affiche que Fil et Espace, au style des maquettes, à 390 px et sur ordinateur.
- `gamad:super-admin` promeut un compte et l'inscrit au journal.
- Un test prouve qu'une entrée du journal ne peut être ni modifiée ni supprimée.
- Un test prouve qu'un membre ordinaire n'a pas accès aux Gates `super-admin` et `moderate`.
- `php artisan test`, Pint et l'intégration continue passent.

## 5. Quand tu as fini

1. Ouvre une Pull Request de `lot-0-socle` vers `main`. Ne fusionne pas.
2. Rédige le rapport de lot selon `prompts/RAPPORT-DE-LOT.md`, dans la description de la Pull Request.
3. Ajoute dans `docs/DECISIONS.md` une entrée « À confirmer » pour la version de Laravel installée et pour
   tout choix technique que tu as dû faire.
4. Ne modifie pas le statut du lot dans `docs/LOTS.md`.

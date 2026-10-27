# Recette ciblée de ZUMRA — 26 octobre 2026

> **Nature du document.** Compte rendu de recette, rédigé par Claude Code sur instruction du Président, **avant toute ouverture publique de ZUMRA**.
> Ce n'est ni une décision ni un lot. Les décisions demandées sont listées au §4. Le module ZUMRA reste **désactivé par défaut** ; rien n'a été ouvert.

## 1. Méthode

- Base de recette locale isolée (jamais la production), module ZUMRA réglé « public » sur cette base seulement.
- Parcours joué **dans un vrai navigateur**, avec de vraies personnes de recette, sur **ordinateur (1 440 px) et téléphone (390 px)** : création, demande d'adhésion, acceptation, nomination, poste, acceptation du poste, invitation, sortie, tentative de sortie du fondateur.
- Balayage visuel de la page ZUMRA (visiteur, membre, fondatrice), du Carrefour, de « Mes ZUMRA », de la création, de la modification et de l'invitation à **320, 390, 768, 1 280 et 1 440 px** : aucun débordement horizontal, aucune cible tactile trop petite, aucune erreur JavaScript, une seule balise `<main>` par page.
- Tests automatisés ajoutés : `tests/Feature/Recette/RecetteZumraTest.php` (8 tests, parcours actif de production). Captures : `captures/recette-zumra/`.

## 2. Résultats par parcours

| Parcours | Ordinateur | Téléphone | Constat |
|---|---|---|---|
| **Création libre** | Réussi | Réussi | Aucune validation préalable ; la personne devient fondatrice. Ni projet, ni financement, ni nombre minimal de membres. Nom déjà pris refusé ; limite de 3 créations par jour. Adresse e-mail vérifiée exigée. |
| **Adhésion** | Réussi | Réussi | Demande avec mot facultatif → le fondateur la voit et l'accepte → la personne est notifiée et devient membre. |
| **Responsabilités** | Réussi | Réussi | Le fondateur nomme un responsable et lui propose un poste ; la personne l'accepte dans « Mes ZUMRA ». Seul un responsable gère ; un membre ordinaire ne voit ni modification ni invitation (404). |
| **Invitation** | Réussi | Réussi | Page d'invitation par capacité ouverte aux responsables. |
| **Sortie** | Réussi | Réussi | Un membre ou un responsable quitte (confirmation en deux temps) et peut redemander. Le fondateur n'a aucun bouton de sortie. |
| **Fermeture du module** | Réussi | — | Désactivé : 302 vers Personnes (Carrefour), 404 (page, Mes ZUMRA) pour tous, membres existants compris ; les données sont conservées. |
| **Modération** | **Incomplet** | **Incomplet** | Voir A3 et A4. |

## 3. Anomalies et limites constatées

| # | Constat | Gravité | Traitement |
|---|---|---|---|
| **A1** | Le bouton « Propositions reçues (n) » d'une page ZUMRA (ou projet, entreprise, profil) comptait **toutes** les propositions que la personne représente, pas celles de cette ZUMRA : « (2) » sur une ZUMRA neuve. Défaut de mon lot C1. | Moyenne (information fausse) | **Corrigé** dans cette PR, avec test. |
| **A2** | **Publier au nom d'une ZUMRA est refusé (403)**, même au fondateur, module ouvert : la publication collective reste gelée par D-183 et D-205 ne l'a pas rouverte. Pourtant la page affiche « Les publications de la ZUMRA apparaîtront ici » et possède des onglets Formation et Fil. Seule la Discussion des membres fonctionne. | **Haute** (page vide qui promet un contenu impossible) | **Décision requise** (§4-1). |
| **A3** | **On ne peut pas signaler une ZUMRA** (nom, mission, comportement) : les signalements existent pour les publications, les commentaires, les entreprises et la confiance, pas pour une ZUMRA. Aucune action de modération sur une ZUMRA (masquer, suspendre) ; seul le Président peut retirer la reconnaissance. | **Haute** (la feuille de route compte sur l'intervention de GAMAD « en cas de signalement ou d'abus ») | **Décision requise** (§4-2). |
| **A4** | La **Discussion des membres** n'a aucun « Signaler », et la modération ne la lit pas (privée). Un message abusif ne peut donc pas être porté à la modération. | Moyenne | Inclus dans §4-2. |
| **A5** | **Impasse du fondateur** : il ne peut ni quitter, ni transférer la fondation, ni fermer la ZUMRA. Le transfert n'existe qu'à la suppression de son compte (D-115). | Moyenne | **Décision requise** (§4-3). |
| **A6** | L'adhésion se fait **par demande acceptée** (D-110), non par adhésion libre. La feuille de route (PR #99) dit « les membres peuvent rejoindre et quitter » sans trancher. | À clarifier | **Décision requise** (§4-4). |

## 4. Décisions demandées avant l'ouverture publique

1. **Publications collectives** (A2) : les rouvrir avec ZUMRA (recommandé : sans quoi les onglets Formation et Fil restent vides), ou masquer ces onglets tant qu'elles sont gelées.
2. **Modération d'une ZUMRA** (A3, A4) : ajouter « Signaler cette ZUMRA » (raisons, file de modération existante) et une action « Suspendre » (retirée du Carrefour, page en 404 sauf pour ses membres, motif et journal) ; ajouter « Signaler » aux messages de la Discussion, lus par la modération seulement s'ils sont signalés.
3. **Sortie du fondateur** (A5) : permettre de transférer la fondation à un responsable, et de fermer une ZUMRA vide ou sans autre membre.
4. **Adhésion** (A6) : conserver la demande acceptée (recommandé au lancement, pour protéger les groupes) ou ajouter une adhésion libre choisie par le fondateur.

## 5. Recommandation

**Ne pas ouvrir ZUMRA au public avant les décisions 1 et 2.** Les parcours de création, d'adhésion, de responsabilités et de sortie sont solides sur ordinateur et téléphone ; l'absence de modération d'une ZUMRA et les onglets vides sont les seuls obstacles sérieux. L'ouverture en **test privé** (testeurs) reste possible dès maintenant.

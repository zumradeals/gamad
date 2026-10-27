# Recette ciblée ZUMRA — points A2 à A6 (27 octobre 2026)

**Statut : projet de travail, à présenter au Président. Aucune ouverture publique de ZUMRA n'a été faite** (module fermé par défaut ; les essais ont eu lieu sur une base locale de recette, jamais sur la production).

## Méthode
Parcours réel dans un navigateur (Playwright) sur ordinateur (1280 px) et mobile (390 px), cinq identités : fondatrice, membre, personne extérieure, modérateur. Pour chaque écran : absence de défilement horizontal, cibles tactiles d'au moins 44 px, captures dans `captures/recette-zumra-2/`. Total : **124 contrôles, 124 réussis, 0 échec** (62 sur ordinateur, 62 sur mobile). Les erreurs de console relevées sont uniquement les « page introuvable » attendus (ZUMRA suspendue ou fermée vue par le public).

## Résultats par point
| Point | Décision du Président | Constat |
|---|---|---|
| A2 | Publications collectives fonctionnelles | Le membre publie un savoir (apparaît dans la Formation et le Fil) ; le besoin lui est refusé ; la fondatrice voit les quatre types. |
| A3 | Signaler une ZUMRA, suspension par la modération | Signalement envoyé, visible dans la file « ZUMRA » sans identité du signalant ; suspension : introuvable pour le public, absente du Carrefour, visible des membres avec le motif ; rétablissement. |
| A4 | Signaler les messages de Discussion | « Signaler » sur le message d'un autre ; la modération lit ce message seul ; masqué, il est remplacé par une mention. |
| A5 | Fondateur : quitter après transmission | « Passer la main » : fondation confiée, « Quitter la ZUMRA » proposé ensuite, le membre devient fondateur ; fondateur seul : fermer puis rouvrir (introuvable pour le public pendant la fermeture). |
| A6 | Adhésion libre par défaut | Libre cochée par défaut, « Rejoindre la ZUMRA » entre tout de suite ; mode « Sur approbation » : « Demander à rejoindre ». |

## Défaut trouvé par la recette et corrigé
L'écran de publication au nom d'une ZUMRA (ou d'un projet) ne fonctionnait pas dans le navigateur : l'identifiant Livewire se posait sur l'en-tête de la page et non sur le formulaire, le bouton « Publier » n'envoyait rien. Les tests automatiques ne pouvaient pas le voir. Corrigé (une racine unique) et couvert par un test ; texte adapté pour un membre (« Vous publiez dans… sous votre nom »).

## Points restant à votre appréciation
1. Les modalités des décisions D-214, D-215 et D-216 sont « À confirmer » (principes décidés par vous) : membre = savoir ou offre d'aide ; suspension : membres gardent la lecture sans agir ; fermeture réservée au fondateur seul membre ; ZUMRA existantes conservées « sur approbation ».
2. Non traité, volontairement : retrait par les responsables d'une publication d'un membre (la modération peut masquer via le signalement général) ; notification par e-mail (aucune).
3. Ouverture publique : à décider par vous ; je recommande d'ouvrir d'abord en **test privé** avec Projets désactivé explicitement (D-213).

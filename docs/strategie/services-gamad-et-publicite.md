# Doctrine stratégique — Services GAMAD et GAMAD Publicité

Statut : **décisions D-209, D-210 et D-211 (docs/DECISIONS.md), soumises au Président pour adoption**. 26 octobre 2026. Documentation uniquement :
aucun développement, aucune migration. En cas de divergence, `docs/DECISIONS.md` fait autorité sur ce texte.

## Vision économique
GAMAD est une entreprise numérique commerciale à mission sociale. Elle finance ses missions et projets par ses activités et pourra, à terme, investir
volontairement dans des entreprises et projets prometteurs. La présence d'un projet sur le réseau ne confère aucun droit de propriété à GAMAD.

## Services GAMAD (D-209)
**Services GAMAD** est l'unique porte d'entrée, depuis **Mon espace** (groupe « Gestion »), vers les départements numériques, internes, externes ou
hybrides. Aucun onglet principal par département ; la navigation globale ne change pas. Un service n'y figure que lorsqu'il est **disponible et
activé** ; sinon il n'est pas montré. Chaque service est examiné et conçu avant son éventuelle implémentation.

Inventaire initial : GAMAD Publicité (prioritaire), GAMAD Social (financement participatif à étudier, distinct de Contributions C1), Services
Professionnels, Développement, Investissement, Travel, ainsi que d'autres pistes futures.

## GAMAD Publicité (D-210, D-211)
Régie publicitaire **transversale**, d'un fonctionnement général comparable à Facebook Ads, mais plus pertinente grâce au réseau social d'action et à
son moteur de matching. Elle n'invente pas de modèle complexe pour se différencier : la valeur distinctive est la qualité du matching.

Socle fonctionnel :
1. **Annonceurs** : au départ, les personnes et les entreprises vérifiées ; les ZUMRA les rejoindront lorsqu'elles seront disponibles.
2. L'annonceur définit contenu, objectif, audience, budget et durée.
3. **Modération préalable** : aucune annonce n'est diffusée avant d'avoir été examinée.
4. Le matching exploite les informations du profil dont l'usage publicitaire est **expressément consenti** (compétences, activités, centres d'intérêt,
   localité, besoins exprimés). Le ciblage personnalisé repose sur un **consentement distinct, désactivé par défaut, révocable**. Les publicités
   contextuelles non personnalisées restent envisageables sans ce consentement.
5. **Cartes sponsorisées discrètes**, toujours signalées « Sponsorisé », dans le Fil et, plus tard, dans d'autres espaces pertinents. Jamais dans les
   conversations privées. Le sponsoring ne modifie jamais le classement naturel du Fil ni du matching.
6. L'annonceur suit les résultats (diffusion, interactions, contacts, dépenses), sans nom ni donnée personnelle de membre.

Limites : ne pas vendre ni exporter la base personnelle des membres ; ne pas exploiter de données sensibles ni de conversations privées ; filtrer les
publicités interdites ou réglementées. Aucun programme de rémunération des membres, de partage de revenus ou de micro-récompenses dans cette première
version. **Le modèle d'encaissement n'est pas décidé** : il sera examiné avant toute implémentation ; rien n'est développé d'ici là.

## Méthode
Avant le développement de chaque service : définir le besoin, les utilisateurs, le parcours, le modèle économique, les risques et le mode d'intégration.
Prochaine étape après validation : maquettes de GAMAD Publicité autour de trois parcours simples (créer une campagne, gérer ses campagnes, consulter
les résultats).

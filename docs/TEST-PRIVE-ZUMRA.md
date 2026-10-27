# Test privé de ZUMRA — procédure d'activation (27 octobre 2026)

**Statut : procédure de travail. L'activation se fait par le Président depuis l'espace d'administration ; elle n'est pas faite par le code ni par un déploiement.** Aucune donnée existante n'est modifiée ou réinitialisée. ZUMRA reste fermée au public.

## État de l'application après les PR #101 à #105 (vérifié par test automatique)
| Réglage (Paramètres → ZUMRA / Publicité) | Valeur par défaut | Effet |
|---|---|---|
| État du module ZUMRA | Désactivé | ZUMRA n'existe pour personne, super administrateur compris (404). |
| État du module Projets | **Suit le module ZUMRA** | Dès que ZUMRA s'ouvre, Projets s'ouvre avec elle. **À mettre sur « Désactivé » avant d'ouvrir ZUMRA.** |
| Diffusion payante des annonces | Fermée | Aucune annonce affichée. |

Un déploiement ne change aucun de ces réglages (ils sont en base, modifiables seulement depuis Paramètres, avec écriture au journal d'administration).

## Ordre à suivre (Président, super administrateur)
1. **Projets d'abord** : Paramètres → ZUMRA → « État du module Projets » = **Désactivé**. Enregistrer.
2. **Testeurs** : « Comptes testeurs (adresses e-mail) » = adresses des personnes choisies (adresse vérifiée exigée).
3. **ZUMRA** : « État du module ZUMRA » = **Test privé**. Enregistrer.
4. Contrôler : une personne non testeuse obtient « page introuvable » sur `/zumra` ; un testeur voit le Carrefour ZUMRA ; aucun onglet « Projets » n'apparaît ; « Diffusion payante des annonces » reste « Fermée ».

## Retour arrière
« État du module ZUMRA » = Désactivé : tout redevient invisible, rien n'est supprimé (publications, adhésions, signalements, suspensions conservés).

## Particularités à connaître
- Les ZUMRA créées avant le 27 octobre gardent l'adhésion **sur approbation** ; les nouvelles sont en adhésion **libre** par défaut.
- Pendant le test privé, la modération (modérateurs et super administrateur) voit la file « ZUMRA » dans ses outils.
- Sans accès à la production, cette procédure n'a pas pu être exécutée par Claude : elle est documentée, et la configuration cible est couverte par `tests/Feature/Recette/TestPriveZumraTest.php`.

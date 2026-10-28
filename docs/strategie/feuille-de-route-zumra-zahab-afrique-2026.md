# Feuille de route GAMAD — ZUMRA, Projets, Contributions, Zahab Wallet, Afrique et langues

> **Version actualisée le 27 octobre 2026** (lot L0), à partir de la version du 26 octobre. Statut : **projet de travail soumis au Président**, non adopté. Ce document **n'autorise ni activation, ni ouverture publique, ni paiement réel, ni déploiement**. Il ne tranche aucune des contradictions signalées : elles restent visibles et attendent l'arbitrage du Président. Les décisions officielles sont celles de `docs/DECISIONS.md` ; en cas de désaccord, ce sont elles qui font foi.
>
> Légende : ✅ **Réalisé** (vérifié dans le dépôt le 27 octobre) · 🔎 **À vérifier** · ⚖️ **À décider** (Président) · 🛠️ **À développer**.
>
> **Limite de la vérification** : l'état du dépôt est vérifié ; **l'état des réglages de production ne l'est pas** et ne doit pas être déduit des valeurs par défaut du code.

## 1. Vision et ordre de déploiement
GAMAD est un réseau panafricain d'action, ouvert aux pays africains dès l'ouverture publique. La Côte d'Ivoire est la base opérationnelle, **pas une restriction d'inscription**. On n'oriente pas artificiellement l'adoption vers certains pays ; on observe les usages réels : membres actifs, ZUMRA actives, collaborations, entreprises et opportunités, rétention. *(Inchangé.)*

Ordre proposé le 26 octobre : (1) recette ciblée puis ouverture de ZUMRA ; (2) audit du module Projets ; (3) Zahab Wallet V1 ; (4) Contributions financières ; Publicité en parallèle.

**État au 27 octobre :** (1) la recette et les corrections sont faites, l'ouverture reste à décider ✅/⚖️ ; (2) l'audit est fait ✅ ; (3) et (4) sont bloquées par des arbitrages (§6). **Ordre recommandé pour la suite** (⚖️, voir §9) : géographie des pays africains → test privé ZUMRA et correctifs → fondation de l'internationalisation → Projets et Contributions non financières → cadrage du Zahab Wallet, **avant toute ligne de code d'argent**.

## 2. ZUMRA
Décision fonctionnelle conservée : groupe de personnes autour d'un domaine d'activité concret ; création libre sans validation préalable systématique ; pas d'obligation de projet, de financement ni de nombre minimal de membres ; GAMAD intervient sur signalement ou abus.

| Élément | État | Référence |
|---|---|---|
| Création libre, limite de créations par jour, nom unique | ✅ | D-205, recette du 26/10 |
| Adhésion **libre et immédiate par défaut** ; mode « sur approbation » au choix du responsable ; les ZUMRA existantes restent sur approbation | ✅ | D-216 |
| Publications collectives : responsables (4 types), membres (savoir, offre d'aide) | ✅ | D-214 |
| Signalement d'une ZUMRA, suspension et rétablissement par la modération, signalement et masquage de messages de Discussion (la Discussion reste privée) | ✅ | D-215 |
| Le fondateur confie la fondation puis quitte ; fermeture d'une ZUMRA dont il est le seul membre | ✅ | D-216 |
| Recette navigateur A2 à A6 (ordinateur et mobile) : 124 contrôles réussis | ✅ | `docs/RECETTE-ZUMRA-A2-A6-2026-10-27.md` |
| Module **désactivé par défaut** ; procédure de test privé écrite | ✅ | `docs/TEST-PRIVE-ZUMRA.md` |
| **Activation du test privé en production** (Projets d'abord sur « Désactivé ») | ⚖️ — acte du Président depuis Paramètres | — |
| Choix des testeurs, durée et critères de succès du test privé | ⚖️ | — |
| Ouverture publique de ZUMRA | ⚖️ — non demandée | — |
| Retrait par un responsable d'une publication de membre (la modération peut masquer après signalement) | 🛠️ | non traité |
| Intégration aux parcours Personnes, Accueil, Mon espace | 🔎 « Mes ZUMRA » existe ; le reste est à auditer | — |
| Filtres par pays et ville du Carrefour ZUMRA | ✅ pour la Côte d'Ivoire ; dépend du §7 pour les autres pays | D-162 |

## 3. Projets
| Élément | État | Référence |
|---|---|---|
| Module Projets (porteur, accueil par une ZUMRA, vitrine publique, confidentialité) | ✅ | D-206 |
| Constat de la v. du 26/10 (« activé par défaut, sans désactivation ») | ✅ **vérifié** : fermé par défaut dans le code, mais **sans réglage propre** — il suivait le module ZUMRA | D-213 |
| Interrupteur à quatre états (suit ZUMRA par défaut, désactivé, test privé, public), journal d'administration, aucune donnée supprimée | ✅ | D-213, PR #102 |
| Désactivation explicite de Projets lors de l'ouverture de ZUMRA | ⚖️ — voulue par le Président ; **à exécuter dans Paramètres** | `docs/TEST-PRIVE-ZUMRA.md` |
| Publications au nom d'un projet | en pause (D-183), non rouvertes | — |
| Test privé puis ouverture publique de Projets | ⚖️ | — |

## 4. Contributions
La v. du 26/10 demandait de conserver les contributions **financières** désactivées et de distinguer les contributions non financières. Le dépôt contient deux choses différentes :

| Élément | État | Référence |
|---|---|---|
| **Contributions GAMAD « besoins et entraide volontaire »** (modèle B) : besoins, propositions en quatre formes (financière **déclarative**, compétences, temps et service, matérielle), réalisation déclarée puis confirmée ; **aucun paiement** ; module désactivé par défaut | ✅ | D-207 (C1) |
| **Ancien Lot 6** : contributions mensuelles, fonds de contribution, affectations, validations par le Président ; code conservé et gelé | ✅ conservé, gelé | D-163, D-165, D-178, D-179, D-183 |
| Statut du code de l'ancien Lot 6 (conserver gelé, archiver, ou refondre une fois le Wallet décidé) | ⚖️ | — |
| Test privé puis ouverture des contributions **non financières** | ⚖️ | — |
| Périmètre distinct entre Contributions, GAMAD Social et crowdfunding, à **écrire avant toute implémentation** | ⚖️ / 🛠️ (document) | — |
| Vigilance : la forme « financière » de C1 est une intention déclarée, jamais un paiement ; son libellé doit rester sans ambiguïté | 🔎 | D-207 |

## 5. GAMAD Publicité (rappel, hors périmètre de cette feuille)
✅ V1 réalisée (D-209 à D-212). **Diffusion payante fermée par défaut** ; aucune activation avant validation du dispositif d'encaissement. L'architecture financière commune du §6 est à concevoir **sans** dépendre de la Publicité.

## 6. Zahab Wallet — Service GAMAD transversal
### 6.1 Ce que la v. du 26 octobre propose
Nom officiel **Zahab Wallet**, via **Mon espace > Services GAMAD** ; personnes **et** ZUMRA, **sur activation volontaire** ; V1 minimale : solde, historique, **recharge et retrait par Mobile Money via GeniusPay**, **aucun transfert entre wallets, prêt ni crédit** ; pour une ZUMRA : titulaire légal des fonds et responsables habilités à identifier ; **vérifications avant argent réel** (capacités de GeniusPay : encaissement, décaissement, statuts, webhooks, remboursements, rapprochement ; pays ; identification ; conservation des fonds ; obligations UEMOA et autres juridictions) ; une intégration d'essai n'autorise pas la production.

### 6.2 Ce que le dépôt contient aujourd'hui ✅ (code gelé par D-183)
Portefeuilles ZAHAB pour personnes et ZUMRA ; **registre en ajout seul** (la base refuse modification et effacement) ; plafonds de solde ; recharge par GeniusPay **en mode d'essai** avec rappel serveur signé, confirmation côté prestataire et rapprochement ; seuil de validation des dépenses d'une ZUMRA ; page des paiements du Président. **Aucun retrait n'est implémenté** (le client GeniusPay ne sait que créer un paiement, le lire, lire le compte et vérifier une signature). Le catalogue Services GAMAD (D-209) ne contient que la Publicité mais est prévu pour accueillir d'autres services. Réglage « Portefeuilles » : désactivé par défaut dans le code ; **valeur de production non vérifiée**.

### 6.3 Contradictions entre la feuille de route et les décisions en vigueur — **non tranchées**
| # | Feuille de route (26/10) | Décision en vigueur | Question pour le Président |
|---|---|---|---|
| 1 | **Retrait** par Mobile Money en V1 | D-164 : « aucun retrait » en V1 ; D-172 : remboursement au numéro d'origine, frais déduits, sinon remboursement manuel tracé | Le retrait remplace-t-il le remboursement prévu, ou s'y ajoute-t-il ? |
| 2 | Wallet **Mobile Money**, aucun transfert | D-164 : ZAHAB = **crédit prépayé interne**, 1 ZAHAB = 1 FCFA, « se dépense librement dans le réseau » (contributions, plus tard achats), aucun transfert | L'unité reste-t-elle le ZAHAB (1:1 FCFA) ou devient-elle le FCFA seul ? Une dépense dans le réseau reste-t-elle prévue ? |
| 3 | Activation **volontaire**, aucune création automatique | D-174/D-183 : ouverture de portefeuille masquée tant que le Président n'active pas ; création à l'activation du service | Confirmer que l'activation volontaire remplace l'ouverture globale par le Président, ou s'y superpose |
| 4 | ZUMRA : titulaire légal à identifier | D-168/D-172 : portefeuille de ZUMRA géré par fondateur et responsables, validation du Président au-delà d'un seuil, remboursement aux personnes qui ont rechargé en cas d'archivage | Qui est titulaire légal d'un wallet de ZUMRA (une ZUMRA n'est pas une personne morale) ? |
| 5 | Mode réel après validation technique, opérationnelle et réglementaire | D-164 : avis juridique **et** décision de mise en service ; D-177 (mode réel, « Terminer les essais ») est **« À confirmer »**, non « Décidée » | Confirmer ou remplacer D-177 ; qui commande l'avis juridique ? |
| 6 | Architecture financière commune aux services futurs | D-165 : deux fonds séparés (contribution, portefeuilles) ; D-179 : versement des affectations **hors plateforme** | Les deux fonds sont-ils maintenus ? |

### 6.4 État des vérifications exigées avant argent réel
| Vérification | État |
|---|---|
| Capacité de décaissement / retrait de GeniusPay | 🔎 **non confirmée**. Aucune documentation officielle de retrait n'a été trouvée ; la [page du module WooCommerce](https://wordpress.org/plugins/geniuspay-for-woocommerce/) mentionne XOF, XAF, EUR, USD et quatre opérateurs (informations anciennes) sans parler de décaissement. À demander au prestataire (contact indiqué : pay@genius.ci) |
| Disponibilité par pays et par opérateur | 🔎 non vérifiée |
| Remboursements, statuts, webhooks de retrait | 🔎 non vérifiés |
| Qualification réglementaire d'un crédit prépayé ou d'un wallet (UEMOA et autres juridictions) | ⚖️ **avis juridique requis**, non obtenu ; aucune conclusion n'est avancée ici |
| Entité bénéficiaire et conservation des fonds | ⚖️ à désigner par le Président (D-172) |
| Identification des personnes (retrait) | ⚖️ à décider |

### 6.5 Risques propres au retrait (nouveaux par rapport à la recharge)
Fraude et prise de contrôle de compte (authentification renforcée à prévoir), vérification que le numéro appartient au titulaire, plafonds et délais, rapprochement des décaissements, échecs et retours de paiement, blanchiment. Ces risques ne se règlent pas par du code seul.

## 7. Géographie — pays africains dès l'ouverture
Le détail est dans **`docs/strategie/cadrage-geographique-g0.md`** (lot G0). Résumé :
- ✅ État réel : **seule la Côte d'Ivoire est chargée** (jusqu'à 46 lieux d'origine : 31 chefs-lieux de région, 2 districts autonomes, 13 communes d'Abidjan, plus les ajouts faits dans Paramètres). Modèle plat : pays, ville, et un texte libre « zone ». **Pas de niveau quartier, pas de coordonnées.** Le **pays n'est pas demandé à l'inscription** (il se renseigne dans le profil).
- 🔎 La liste ivoirienne était « à faire contrôler par le Président avant l'ouverture » (commentaire du chargeur) : contrôle non attesté.
- 🛠️ Rendre sélectionnables les pays africains avec des unités administratives variables selon les pays, saisie assistée, provenance de chaque donnée, sans inventer de noms.
- ⚖️ Définition de la liste (« 54 » = États membres de l'ONU situés en Afrique ; l'Union africaine en compte 55 avec la République arabe sahraouie démocratique), sources autorisées, pays à l'inscription ou dans le profil.
- La disponibilité d'un pays dans l'interface **ne vaut pas** disponibilité des services financiers dans ce pays.

## 8. Internationalisation (FR, EN, AR, PT)
- ✅ État réel : **167 vues** Blade au texte français en dur ; un seul appel de traduction ; fichier `lang/fr.json` minimal ; fuseau `Africa/Abidjan` ; aucun support de l'écriture de droite à gauche (RTL) ; contrainte de poids CSS de 14 Kio compressé (CLAUDE.md) à surveiller pour le RTL.
- 🛠️ À construire : choix de la langue par personne (avec repli), attributs de langue et de direction, extraction progressive des textes par zone, puis anglais, portugais, arabe avec RTL complet. Les textes produits par le code (phrases de notification, libellés d'états, messages de validation) sont à traiter aussi.
- ⚖️ Ordre des langues, traduction humaine ou assistée, **traduction des textes juridiques** (charte, mentions) à faire valider par une personne compétente. Les publications des membres restent dans leur langue ; une traduction automatique éventuelle est un chantier distinct.
- Dépend du §7 pour la localisation des noms de lieux.

## 9. Séquencement proposé (⚖️ à valider)
Lots courts, indépendants, vérifiables. « Sans risque » = documentation ou développement local sans effet sur la production ni sur l'argent ; **tout chargement de données ou changement de réglage en production exige l'autorisation préalable du Président**.

| Lot | Contenu | Prérequis | Sans risque ? | Autorisation requise |
|---|---|---|---|---|
| **L0** | Cette actualisation (docs) | — | oui | fusion de la PR #99 |
| **G0** | Cadrage géographique (docs) | — | oui | arbitrage des décisions du §10 de cette feuille et du §11 de G0 |
| **Z-test** | Test privé ZUMRA en production | Projets « Désactivé » **avant** ZUMRA « Test privé » | — | **acte du Président** dans Paramètres |
| **G1** | Évolution additive du référentiel géographique + import **simulé** en base de développement | G0 arbitré | oui | migration en production |
| **G2** | Pays à l'inscription et sélecteurs avec recherche (remplacent les listes déroulantes) | G1 | oui (développement) | mise en production |
| **G3** | Chargement progressif des pays (pays, régions, villes principales) | G1, sources vérifiées | non (données) | **chaque chargement** |
| **I0–I1** | Choix d'outil et fondation i18n sur deux écrans pilotes | décisions §10 | oui | mise en production |
| **Z-suite** | Correctifs issus du test privé, retrait par un responsable d'une publication de membre | Z-test | oui | ouverture publique |
| **P-C** | Test privé de Projets, puis des Contributions non financières ; écrit du périmètre Contributions / GAMAD Social | Z-suite | oui (docs) | **toute activation** |
| **W0–W1** | Arbitrage des contradictions du §6.3, questionnaire GeniusPay, audit technique de l'existant, avis juridique | décisions §10 | oui (docs) | **toute suite** |
| **W2+** | Entrée Services GAMAD en essai, retrait, wallet de ZUMRA | W0–W1 **et** avis juridique | non | **chaque étape** |

**Dépendances :** G → (ouverture publique, filtres ZUMRA, Publicité ciblée par ville, matching) ; I1 → I2 → langues ; W0 → W1 → W2 ; Contributions financières → Wallet. **Les chantiers G, I, Z et W sont indépendants entre eux dans leurs premiers lots.**

**Critères de validation communs :** suite de tests et CI verte ; aucune migration destructive ; aucune donnée existante perdue ; aucun réglage de production modifié par un déploiement ; recette navigateur sur ordinateur et mobile pour tout écran modifié ; compte rendu au Président avant toute ouverture.

## 10. Décisions attendues du Président
1. **Document** : adopter cette feuille de route comme référence et fusionner la PR #99 (non fait).
2. **Test privé ZUMRA** : testeurs, durée, critères de succès ; exécution de la procédure `docs/TEST-PRIVE-ZUMRA.md`.
3. **Projets et Contributions non financières** : ordre et conditions des tests privés ; statut du code de l'ancien Lot 6.
4. **Zahab Wallet** : les six contradictions du §6.3 ; entité juridique ; avis juridique ; identification pour le retrait.
5. **Géographie** : définition des pays ; sources et licences ; niveau initial ; pays à l'inscription ; contrôle de la liste ivoirienne (voir G0 §11).
6. **Internationalisation** : ordre des langues ; traduction humaine ou assistée ; textes juridiques.

## 11. Garde-fous (inchangés)
Aucune ouverture publique de ZUMRA ; aucune activation de Projets ou de Contributions sans décision explicite ; aucun paiement réel ni Zahab Wallet en production ; aucune modification de données de production ; ni GAMAD Core ni SSO ; pas de bêta privée pour le moment ; aucune fusion ni déploiement sans autorisation.

## 12. Critères de réussite finaux
- Une personne de n'importe quel pays retenu peut s'inscrire et renseigner sa localité, même si un quartier n'est pas référencé.
- Une ZUMRA se crée librement ; rejoindre, quitter, permissions et signalements sont testés ✅.
- L'état réel d'activation de Projets est vérifiable et administrable ✅.
- Aucun flux financier réel n'est accessible sans les validations et intégrations requises.
- Les quatre langues sont prévues dans l'architecture ; l'arabe fonctionne en RTL lors de son déploiement.

## Historique
- 26 octobre 2026 : version initiale (orientations fonctionnelles).
- 27 octobre 2026 : actualisation L0 — état réel du dépôt, ajout des contradictions du Zahab Wallet, séquencement, décisions attendues ; ajout du cadrage géographique G0.

# Feuille de route GAMAD — décisions du 26 octobre 2026

> Statut : orientations fonctionnelles validées en discussion, à planifier et vérifier techniquement. **Cette feuille de route n'autorise ni activation automatique, ni paiement réel, ni déploiement en production.** Document indépendant de la PR #93 GAMAD Publicité.

## 1. Vision et ordre de déploiement
GAMAD est un réseau panafricain d'action, ouvert aux 54 pays africains dès l'ouverture publique. La Côte d'Ivoire est la base opérationnelle, **pas une restriction d'inscription**. Ne pas prédire ni favoriser artificiellement les pays qui connaîtront la plus forte adoption pendant les douze premiers mois. Observer les usages réels : membres actifs, ZUMRA actives, collaborations, entreprises et opportunités, rétention.

Ordre proposé : (1) recette ciblée puis ouverture de ZUMRA ; (2) audit du module Projets et de son contrôle d'activation ; (3) Zahab Wallet V1, sous réserve de conformité et de validation de GeniusPay ; (4) Contributions financières seulement lorsque leurs dépendances sont opérationnelles. Poursuivre séparément GAMAD Publicité.

## 2. ZUMRA — décision fonctionnelle
- Une ZUMRA est un **groupe de personnes constitué autour d'un domaine d'activité concret**, et non obligatoirement une entreprise, un projet ou un simple groupe de discussion.
- Tout membre peut créer librement une ZUMRA, **sans validation préalable systématique**, comme pour un groupe social classique. Les membres peuvent rejoindre et quitter ; les responsables organisent leur groupe.
- Pas d'obligation de projet, financement, nombre minimal de membres, ni classement complexe au lancement. Les groupes engagés émergent naturellement.
- GAMAD intervient en cas de signalement ou d'abus. La modération n'est pas une autorisation préalable générale.
- Intégrer ZUMRA naturellement aux parcours Personnes, Accueil, Mon espace et, ultérieurement, Projets ; éviter de recréer une navigation complexe.
- **Avant activation publique :** recette ciblée de création, adhésion, sortie, responsabilités, permissions, modération et mobile ; préserver les données existantes.

## 3. Projets — anomalie à examiner
L'interface semble présenter Projets comme activé par défaut sans option de désactivation. **Constat utilisateur à vérifier dans le code et les réglages**, pas diagnostic confirmé. Auditer le mécanisme existant et prévoir si nécessaire un interrupteur d'activation cohérent avec les autres modules, sans supprimer les données ni désactiver arbitrairement des projets existants. Décider ensuite de l'ouverture publique.

## 4. Contributions
Conserver **les contributions financières désactivées** tant que le portefeuille, les flux de paiement, les confirmations, la traçabilité et les contrôles ne sont pas prêts. Distinguer les éventuelles contributions non financières. Ne pas confondre Contributions avec le futur GAMAD Social/crowdfunding : les périmètres devront être explicités avant implémentation.

## 5. Zahab Wallet — Service GAMAD transversal
- Nom officiel : **Zahab Wallet** ; accessible via **Mon espace > Services GAMAD**, selon les conventions d'accès existantes.
- Disponible pour les **personnes et les ZUMRA**, **sur activation volontaire du demandeur** ; aucune création automatique.
- V1 minimale : consultation du solde, historique, **recharge et retrait par Mobile Money via GeniusPay** ; **aucun transfert entre wallets**, prêt ou crédit.
- Pour une ZUMRA, identifier le titulaire légal des fonds et les responsables habilités ; une ZUMRA n'est pas automatiquement une personne morale.
- Vérifier **avant argent réel** les capacités effectives de GeniusPay (encaissement, décaissement/retrait, statuts, webhooks, remboursements, rapprochement), sa disponibilité par pays, l'identification, la conservation des fonds et les obligations applicables dans l'UEMOA et les autres juridictions.
- Une intégration sandbox ne constitue pas une autorisation de production. Ne pas ouvrir les transactions réelles avant validation technique, opérationnelle et réglementaire.
- Architecture financière commune réutilisable par les futurs services, dont Publicité et Contributions, sans anticiper l'histoire complète du portefeuille.

## 6. Géographie — 54 pays dès l'ouverture
- Rendre les **54 pays africains** sélectionnables à l'inscription, avec villes et quartiers/localités, à l'image de la couverture actuelle de Côte d'Ivoire.
- Adapter la hiérarchie administrative selon les pays : région/province, ville, commune, quartier ou localité ; ne pas imposer la structure ivoirienne partout.
- Réutiliser un référentiel géographique partagé dans inscription, profils, entreprises, ZUMRA, opportunités et matching.
- Conserver les données existantes, gérer les doublons, variantes orthographiques et changements de nom ; proposer une saisie assistée si un quartier manque, sans bloquer l'inscription.
- Prévoir une source de données géographiques fiable, sa licence, sa qualité et sa mise à jour. La disponibilité des pays dans l'interface ne vaut pas disponibilité de tous les services financiers dans ces pays.
- Pas de limitation d'inscription aux pays dits pilotes, ni de préférence géographique artificielle dans le matching.

## 7. Internationalisation
Quatre langues internationales ciblées : **français, anglais, arabe, portugais**. Préparer l'architecture de traduction puis déployer progressivement les interfaces. Pour l'arabe, prévoir une prise en charge **RTL** complète. Les publications des membres restent dans leur langue ; une traduction automatique éventuelle est un chantier distinct. La localisation des noms géographiques et des formats doit être prévue.

## 8. Pilotage et limites de la PR
- La présente PR est **documentaire uniquement** : aucun code, migration, activation, configuration de paiement, fusion automatique ni déploiement.
- Ne pas empiéter sur le chantier GAMAD Publicité / PR #93.
- Les points à auditer ne sont pas présentés comme déjà implémentés.
- Après stabilisation de Publicité, préparer des PR techniques ciblées : recette ZUMRA et activation ; audit interrupteur Projets ; couverture géographique ; fondations i18n ; cadrage technique et conformité Zahab Wallet.
- Les ouvertures de fonctionnalités et transactions financières feront l'objet de décisions explicites distinctes.

## 9. Critères de réussite
- Un membre de n'importe lequel des 54 pays peut s'inscrire et renseigner sa localité, même si un quartier n'est pas référencé.
- Une ZUMRA peut être créée librement, rejoindre/quitter fonctionne, les permissions et signalements sont testés.
- L'état réel d'activation de Projets est vérifié et administrable si nécessaire.
- Aucun flux financier réel n'est accessible sans les validations et intégrations requises.
- Les quatre langues sont prévues dans l'architecture ; l'arabe fonctionne en RTL lors de son déploiement.

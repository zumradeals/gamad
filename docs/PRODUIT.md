# PRODUIT — Réseau social d'action GAMAD

Version 1.1 — 23 octobre 2026 (contributions et portefeuille ZAHAB, D-163 à D-173). Source : décisions du Président consignées dans docs/DECISIONS.md. Toute modification passe par une décision.

## Périmètre actuellement ouvert — D-183, D-184

Le parcours initial comprend **Fil, Agir et Mon espace**. La recherche de personnes et les profils restent
accessibles depuis le Fil, les capacités et les publications ; Personnes n’est plus une entrée permanente. Les besoins sont des publications
actives du Fil (filtre Besoins), sans Carrefour distinct dans le parcours. Les profils, capacités,
gestes, conversations privées, notifications, modération et protections existantes restent actifs.

Les ZUMRA, Projets, contributions et portefeuilles décrits plus bas constituent le système conservé,
**pas le parcours initial**. Leur découverte et les nouvelles utilisations sont gelées selon le contrat
`docs/RECENTRAGE.md`. Les activités existantes restent accessibles à leurs ayants droit, sans migration,
suppression ni changement de visibilité. Ce périmètre remplace les ouvertures prévues par les décisions
antérieures jusqu'à nouvelle décision du Président. Les règles métier de conservation restent applicables.

## 1. Principe

La personne est au centre. Quel que soit son statut, GAMAD l'accueille pour apporter ce qu'elle sait faire,
apprendre ce qu'elle ne sait pas encore, ou dire ce qui lui manque. On ne réagit pas par « j'aime » : on agit
par un geste (« Je peux aider », « Je veux apprendre »…).

Devise : **Formation — Travail — Adoration.** Dans une ZUMRA, la Formation vient en premier.

## 2. Architecture : trois briques, trois niveaux

| Brique | Rôle | Exemples |
| --- | --- | --- |
| **Carrefour** | Annuaire public avec recherche et filtres. Un seul gabarit réutilisé. | Carrefour Personnes, ZUMRA, Projets, Besoins |
| **Monde** | La page d'une entité : en-tête (identité, état, action principale) + 5 onglets au plus. | Monde d'une Personne, d'une ZUMRA, d'un Projet |
| **Fil** | Un seul composant qui affiche des publications. | Fil global ; mini-fil d'un monde = le même fil filtré |

**Règle des trois niveaux :** Carrefour → Monde → Élément (une publication, une étape, un savoir). Jamais plus
profond. Un onglet affiche une liste, jamais un monde caché dans un monde. Un projet est un monde à part, relié
à sa ZUMRA.

Onglets V1 :

| Monde | Onglets (dans cet ordre) |
| --- | --- |
| ZUMRA | Formation · Fil · Projets · Membres · Discussion |
| Projet | Avancement · Fil · Équipe · Besoins |
| Personne | Je peux apporter / Je veux apprendre · Fil · ZUMRA |

## 3. Glossaire (termes obligatoires)

| Terme (interface) | Nom technique | Définition |
| --- | --- | --- |
| Personne | `User` + `Profile` | Un compte individuel. Ne vaut pas adhésion au Mouvement GAMAD. |
| Je peux apporter | `offered_skills` | Les savoir-faire qu'une personne déclare pouvoir mettre au service des autres. |
| Je veux apprendre | `wanted_skills` | Ce qu'une personne déclare vouloir apprendre. |
| Capacité | `Skill` | Une étiquette de savoir-faire (ex. « Informatique »), partagée par tous. |
| ZUMRA | `Zumra` | Une communauté créée par toute personne autour d'un seul domaine. Écrit toujours en majuscules. |
| Fondateur | `role = founder` | La personne qui a créé la ZUMRA. |
| Responsable | `role = manager` | Un membre à qui le fondateur confie la gestion. |
| Membre (d'une ZUMRA) | `ZumraMembership` | Une personne admise dans une ZUMRA. À ne pas confondre avec « membre du Mouvement GAMAD », non géré ici. |
| Reconnaissance | `recognized_at`, `recognized_by` | L'acte par lequel le Président reconnaît officiellement une ZUMRA. Badge « Reconnue par GAMAD ». |
| Domaine | `Domain` | Le domaine d'action d'une ZUMRA ou d'une publication (liste de référence, voir §7). |
| Projet | `Project` | Une action structurée, toujours rattachée à une ZUMRA, avec page publique. |
| Étape | `ProjectStep` | Un jalon d'un projet : à faire, en cours, franchie. |
| Réalisation | `Publication` de type `achievement` | Ce qui a été fait, rendu visible. |
| Publication | `Publication` | Ce qui circule dans un fil. Publiée par une personne ou une ZUMRA. A toujours un type. |
| Type de publication | `PublicationType` (enum) | Voir §5. |
| Besoin | `Need` | Une publication « J'ai besoin » qui a un suivi d'état. |
| Savoir | `Publication` de type `knowledge` | Un savoir partagé : conseil, fiche, parcours de formation. |
| Geste d'action | `Gesture` | La réponse à une publication : Je peux aider, Je veux apprendre, Je peux transmettre, Ça m'intéresse, Je soutiens, Je veux participer, Je veux rejoindre. |
| Invitation | `Invitation` | Proposition de rejoindre une ZUMRA ou un projet, avec un rôle. |
| Conversation | `Conversation` | Échange privé entre deux personnes, ouvert par un geste accepté. |
| Discussion | `ZumraThread` | Fil de discussion réservé aux membres d'une ZUMRA. |
| Remerciement | `Gratitude` | Message public par lequel l'auteur d'un besoin dit qui l'a aidé. |
| Super administrateur | `platform_role = super_admin` | Le Président de GAMAD. |
| Modérateur | `platform_role = moderator` | Personne désignée par le super administrateur pour modérer. |
| Journal d'administration | `AdminAction` | Trace non modifiable des actes d'administration et de modération. |
| Signalement | `Report` | Alerte envoyée par une personne sur un contenu. |
| Pays | `Country` | Pays où une personne indique vivre. Liste tenue par le super administrateur. |
| Ville | `City` | Lieu rattaché à un seul pays. Liste tenue par le super administrateur. |
| Territoire | `Country` + `City` | Le pays et la ville qu'une personne affiche sur son profil, tous deux facultatifs. |
| Contribution | `Contribution` | Somme versée volontairement, par une personne ou une ZUMRA, au fonds de contribution de GAMAD. Montant libre, 500 FCFA au moins. Signe d'engagement. Ne s'appelle jamais « cotisation ». |
| ZAHAB | unité | L'unité des portefeuilles : 1 ZAHAB = 1 FCFA. |
| Portefeuille | `Wallet` | Le compte en ZAHAB d'une personne ou d'une ZUMRA. Il se recharge et se dépense dans le réseau ; en V1, ni transfert entre utilisateurs ni retrait. |
| Mouvement | `LedgerEntry` | Une ligne du registre d'un portefeuille. Jamais modifiée ni effacée. |
| Fonds de contribution | — | Les contributions reçues sur le compte GeniusPay de GAMAD ; il finance les ZUMRA engagées. Distinct du fonds des portefeuilles. |
| Fonds des portefeuilles | — | Les sommes rechargées dans les portefeuilles ; elles restent à leurs titulaires. |
| ZUMRA engagée | calculé | Une ZUMRA qui a contribué dans les 60 derniers jours. Badge « ZUMRA engagée », distinct de « Reconnue par GAMAD ». |
| Contributeur | calculé | Une personne qui a contribué dans les 60 derniers jours. Badge « Contributeur » facultatif, sans niveau ni montant. |

## 4. Objets, états et droits

| Objet | États | Qui décide |
| --- | --- | --- |
| Personne | active · suspendue · supprimée | La personne ; la modération pour la suspension |
| ZUMRA | active · reconnue · suspendue · archivée | Le fondateur et les responsables gèrent ; le super administrateur reconnaît, suspend |
| Projet | idée · en cours · terminé · abandonné | Les responsables désignés par la ZUMRA |
| Publication | publiée · masquée · supprimée | Son auteur ; la modération masque |
| Besoin | ouvert · en cours · résolu · fermé sans suite | Son auteur (personne, ou responsable de la ZUMRA) |
| Geste | envoyé · accepté · décliné · retiré | Celui qui agit l'envoie ; l'auteur de la publication accepte ou décline |
| Invitation | envoyée · acceptée · refusée · expirée | Un responsable l'envoie ; la personne répond |
| Portefeuille | ouvert · fermé (à la disparition de la personne ou de la ZUMRA, solde nul) | Son titulaire dépense (pour une ZUMRA : le fondateur et les responsables) ; le Président valide les dépenses de ZUMRA au-delà du seuil |
| Contribution | en attente · confirmée · remboursée | La personne ou la ZUMRA la verse ; le Président affecte le fonds de contribution |

Règles :

- Toute personne peut créer une ZUMRA. Une ZUMRA religieuse a exactement les mêmes droits qu'une autre.
- Une ZUMRA a un seul domaine.
- Un projet appartient toujours à une ZUMRA.
- Une personne ou une ZUMRA peut publier un besoin.
- Accepter un geste ouvre une conversation privée entre les deux personnes.
- Un besoin résolu peut porter un remerciement, visible sur le profil de la personne qui a aidé.
- Un portefeuille ne se lit que par son titulaire ; pour une ZUMRA, par son fondateur et ses responsables. Le Président voit des totaux, jamais le portefeuille d'une personne.
- Aucun transfert entre utilisateurs et aucun retrait en V1. Un solde n'est jamais confisqué : une personne ou une ZUMRA qui a un solde ne peut pas disparaître avant de l'avoir dépensé ou fait rembourser (D-172).
- Un projet n'est financé que par une ZUMRA à la fois engagée et reconnue (D-167).

## 5. Types de publication et gestes

| Type | Code | Qui publie | Geste principal | Geste secondaire |
| --- | --- | --- | --- | --- |
| J'ai besoin | `need` | Personne, ZUMRA | Je peux aider | — |
| Je partage un savoir | `knowledge` | Personne, ZUMRA | Je veux apprendre | — |
| Je veux apprendre | `learning_wish` | Personne | Je peux transmettre | — |
| Je propose mon aide | `offer` | Personne, ZUMRA | Ça m'intéresse | — |
| Avancée de projet | `project_update` | ZUMRA, pour son projet | Je soutiens | Je veux participer |
| Appel à rejoindre | `zumra_call` | ZUMRA | Je veux rejoindre | — |

Sur toute publication : Commenter, Partager, Signaler. **Aucun compteur d'approbation.** Seuls des engagements
réels peuvent être comptés (« 4 personnes veulent apprendre »).

## 6. Règles transverses

1. **Compte n'est pas adhésion.** S'inscrire ne fait de personne un membre du Mouvement GAMAD.
2. **L'argent n'achète jamais la visibilité ni le rang.** Il arrive par les contributions et les portefeuilles (Lot 6). Aucun argent réel n'entre avant l'avis juridique et la mise en service décidée par le Président (D-164, D-169).
3. **Visibilité :** public (sans compte) · réseau (personnes connectées) · ZUMRA (membres seulement).
4. **Pas de score humain.** Ni note, ni classement, ni « j'aime ».
5. **Pas de donnée inventée.** Un chiffre affiché vient de la base. Un écran vide propose une action.
6. **Modération dès le premier jour.** Signaler partout ; droit de l'auteur à s'expliquer avant une sanction.
7. **Le super administrateur est tracé.** Chaque acte est inscrit au journal d'administration.
8. **Données personnelles.** Téléphone et e-mail jamais affichés à autrui ; suppression du compte possible.
9. **Territoires normalisés.** Pays et ville choisis dans une liste.
10. **Une contribution est privée.** Son montant n'est jamais affiché à personne d'autre que son auteur et le Président ; le badge « Contributeur » est facultatif (D-170).

## 7. Domaines d'action (liste de référence)

Issue de l'article 3 du Règlement intérieur ; modifiable par le super administrateur.

Santé et Action sociale · Religion · Formation et Éducation · Technologie et Sciences naturelles ·
Économie et Finances · Construction et Industries · Promotion immobilière · Génie civil et BTP ·
Travail, Ressources humaines et Développement · Sécurité · Sport et Culture · Communication et Communauté ·
Transport et Automobile · Justice et Droits de l'homme · Affaires commerciales · Agriculture, Pêche et Élevage ·
Artisanat.

« Artisanat » ne figure pas dans l'article 3 ; il est ajouté à titre provisoire (D-014).

## Entraide accompagnée — D-184

- Un même champ du Fil permet de chercher les publications (texte, capacités, nom d’auteur) et de rejoindre la recherche de personnes avec le même terme. Les résultats gardent chacun leurs règles de visibilité ; aucun classement de personnes. L’ordre du Fil reste chronologique. La recherche de personnes permet aussi de trouver ceux qui ne publient pas.
- Un besoin peut être précisé par « Pour faire quoi ? » et « Où et quand ? ». Ces champs facultatifs sont assemblés dans le texte publié, toujours limité à 500 caractères, et annoncés comme publics selon la visibilité choisie. Le corps du besoin reste obligatoire. Aucun stockage parallèle de ces précisions.
- Un geste peut préciser une disponibilité et un format (à distance / sur place). Ces éléments sont assemblés dans le mot existant, limité à 280 caractères au total ; ils sont consultables par les gestionnaires de la publication et ne constituent pas une disponibilité automatique du profil.
- Dans une conversation existante, chaque participant peut proposer une prochaine action (280 caractères maximum), avec une date facultative, aujourd’hui ou plus tard en heure d’Abidjan. L’autre personne doit la confirmer ; l’auteur ne peut pas s’auto-confirmer. Une nouvelle proposition remplace l’action courante et annule toute confirmation précédente. Chacun peut retirer l’action.
- Les écritures sont autorisées seulement aux participants vérifiés pouvant encore écrire dans la conversation. Une version vérifiée sous verrou empêche une réponse d’ancienne page de confirmer ou remplacer une action modifiée entre-temps. Les changements apparaissent aussi comme messages privés de leur auteur ; aucun rappel, calendrier, tâche publique ou nouveau monde.
- L’action courante et sa date sont effacées si l’un des participants supprime son compte ; les messages suivent les règles existantes d’effacement de leur auteur. La confirmation ne recopie pas le contenu personnel proposé par l’autre.


## Profils, publications et liens personnels — D-186

Décision du Président du 24 octobre 2026, approuvée dans la conversation puis mise en œuvre par Codex.
Le périmètre R1 comprend désormais ces fonctionnalités, sans rouvrir les modules collectifs ou financiers.

- **Profil** : présentation libre facultative (1 200 caractères) en plus de la bio ; dix expériences au plus
  (intitulé 100, nature personnelle / professionnelle / associative-bénévole / apprentissage, période ou contexte 80,
  description 500). Informations déclarées par l’auteur, sans certification ni notation. Dix descriptions de savoir-faire
  au plus (une capacité apportée existante, explication 280, publication personnelle illustrative facultative).
  Trois publications personnelles à la une au plus. Les références sont relues avec les droits actuels du visiteur :
  aucun extrait ni fichier d’une publication masquée ou privée n’est révélé par sa sélection sur le profil.
- **Publier** : ajout de « Ma réalisation » (`personal_work`, distinct des anciennes réalisations de projets) et
  « Je partage » (`share`). Texte de 500 caractères conservé ; un fichier facultatif pour tout type personnel :
  JPEG/PNG/WebP ou PDF 5 Mo, MP4/WebM 10 Mo. Les images sont réencodées en JPEG, orientées, sans métadonnées,
  proportions conservées, côté maximal 2 048 pixels et entrée limitée à 25 mégapixels. Aucun transcodage vidéo,
  galerie, lecteur de formation ou espace de stockage général. Les PDF et vidéos conservent leurs métadonnées :
  l’auteur doit vérifier ce qu’il partage. Les PDF se téléchargent, jamais de HTML/SVG ou document bureautique actif.
  Fichiers sur disque local privé, servis par une route autorisée à chaque requête, y compris les requêtes vidéo Range.
  Suppression des fichiers au départ du compte ou de la publication ; sauvegarde quotidienne incluse.
- **Échanger** : geste des réalisations personnelles et partages, motif obligatoire (280 caractères au total).
  Le destinataire accepte ou décline par le parcours existant ; accepter ouvre une conversation rattachée à la
  publication, sans créer une relation durable. Aucun « j’aime », classement ou note.
- **Suivre** : abonnement unilatéral, réversible, sans notification ni permission de contact supplémentaire.
  « Mes suivis » montre chronologiquement les seules publications personnelles **publiques** des personnes suivies,
  même si le visiteur dispose par ailleurs d’accès plus larges. Pas de nombre public d’abonnés ni liste publique.
- **Entrer en relation** : demande avec mot obligatoire de 1 à 280 caractères. Une demande courante par paire,
  pas d’acceptation automatique des demandes croisées. Seul le destinataire accepte ou décline ; seul l’expéditeur
  retire une demande. Acceptation : relation réciproque et conversation immédiatement accessible avec « Écrire ».
  Notifications internes à la demande et à l’acceptation, jamais d’e-mail automatique. Limite de 20 demandes par
  personne et par 24 heures ; après retrait, refus ou suppression d’un lien, sept jours avant une nouvelle demande.
  Les décisions vérifient la version affichée et les écritures d’une paire sont sérialisées dans un ordre stable.
  Retirer la relation conserve la conversation déjà consentie ; une nouvelle relation réutilise cette conversation.
- **Mon espace → Mes liens** : demandes reçues/envoyées, relations, suivis et blocages, accessibles uniquement
  au propriétaire ; dix lignes par affichage, « Voir plus » jusqu’à 200. Aucun nouveau menu principal.
- **Bloquer** : retire suivis dans les deux sens et relation/demande courante ; empêche tout nouveau contact,
  geste accepté ou message dans les deux sens. Les profils/publications disparaissent de leurs recherches et URLs
  authentifiées respectives, fichiers inclus ; les accès nécessaires à la modération restent conservés. Les conversations déjà consenties restent lisibles. Le blocage ne
  retire pas un contenu public aux visiteurs déconnectés ; il ne remplace pas le signalement. Chaque personne ne
  peut lever que son propre blocage. Débloquer ne restaure ni suivi ni relation. Pas de notification du blocage.

Une migration additive crée trois tables privées (`person_follows`, `person_connections`, `person_blocks`),
quatre colonnes de profil, quatre colonnes de fichier sur les publications et une origine de conversation ; elle étend les contraintes de types
et notifications. Aucun enregistrement existant n’est réécrit. Un retour de migration refuse de supprimer les
nouveaux types tant que des publications existent. Aucune nouvelle dépendance.

Essai du Président : compléter son profil et choisir une publication à la une ; avec un second compte, suivre,
filtrer « Mes suivis », demander une relation puis accepter depuis Mon espace, écrire directement, retirer et
bloquer ; publier une image, une vidéo courte et un PDF ; contrôler avec un visiteur déconnecté que les contenus
réservés aux inscrits et leurs fichiers restent inaccessibles.


## Matching de proximité et conditions des occasions — D-187

### Vision
Un réseau d’entraide et de travail, avec une seule personne et plusieurs façons de participer. Proposer une
rencontre pertinente ne sélectionne personne pour une mission, ne garantit ni compétence ni disponibilité et
ne crée ni engagement ni paiement. Fil · Agir · Mon espace reste la navigation ; les gels D-183 restent actifs.

### Conception
- Participation volontaire : préférences privées modifiables dans Modifier mon profil (désactivées par défaut),
  intentions proposer ses compétences / transmettre / apprendre, conditions acceptées bénévole-gratuit /
  rémunéré-payant / à convenir, jusqu’à cinq localités choisies dans les villes/communes administrées, possibilité
  à distance, période facultative et jours de semaine facultatifs. Aucune géolocalisation, aucun quartier déduit,
  aucune préférence sensible inférée ; les localités non renseignées ne sont pas inventées. Les plages non
  renseignées sont annoncées « à préciser » ; les textes libres de disponibilité ne sont jamais interprétés.
- Les publications personnelles Besoin, Savoir, Apprendre et Offre peuvent activer leur participation au matching,
  avec au moins une capacité explicite. Besoin = recherche de prestation/aide ; Apprendre = recherche de
  transmission ; Savoir = offre de transmission ; Offre = prestation/aide ou formation, choisi explicitement.
  Nature et conditions financières sont distinctes. Les anciens contenus restent sans matching tant qu’ils ne
  sont pas explicitement renseignés. Partages/réalisations et publications collectives ne sont pas des offres.
- Conditions : bénévole/gratuit, rémunéré/payant, ou à convenir explicitement ; budget indicatif libre de 100
  caractères au plus, pour une condition rémunérée seulement. Aucun montant inféré, encaissement ou contrat.
  Une offre bénévole ne devient pas payante silencieusement. Pour les nouveaux gestes rémunérés sur un besoin,
  le bouton dit « Proposer mes services ». Le destinataire décide toujours avant la nouvelle conversation.
- Correspondances : capacité commune exacte (référentiel normalisé), intention complémentaire et conditions
  compatibles, localité identique dans les zones choisies ou distance acceptée des deux côtés, période et jours
  déclarés non contradictoires. Aucun score de personne ni classement payé ; ordre chronologique stable.
  Besoin/Apprendre rapprochent des profils ayant les capacités offertes ; Savoir/Offre de formation des profils
  souhaitant apprendre ; une Offre de prestation rapproche des Besoins compatibles. Les suggestions vont aux
  deux côtés lorsque leurs préférences sont activées. Une personne indisponible n’est pas proposée.
- Chaque carte explique les capacités, le lieu/distance, les conditions et les éventuelles périodes compatibles.
  Elle mène au profil ou à la publication, puis aux gestes existants ; jamais de contact, candidature, relation ou
  acceptation automatique. Ignorer/« Ne m’intéresse pas » est privé et persistant pour cette correspondance.
- Affichage intégré au Fil et à Mon espace, dix suggestions à la fois, « Voir plus » jusqu’à 100. Accès aux
  réglages depuis les états vides. Les droits sont revérifiés à chaque affichage et action, ainsi qu’à l’envoi
  d’une notification, y compris les blocages des deux côtés et les profils réservés aux inscrits. Aucun extrait
  de contenu interdit dans une notification de matching. Les exceptions de modération ne créent pas de matching.
- Notifications internes séparément consenties (désactivées par défaut), jamais d’e-mail/SMS. Une alerte générique
  non lue au plus, cinq nouvelles alertes au plus par jour d’Abidjan ; chaque correspondance ne notifie qu’une
  fois. Les écritures sont sérialisées sur le destinataire. Activer les notifications peut annoncer les
  correspondances actuelles non ignorées ; aucun rattrapage en boucle. Désactiver marque l’alerte courante de matching comme lue, sans effacer son historique ni remettre à zéro le plafond quotidien. Les notifications des autres usages ne sont pas modifiées.
- Une publication peut être fermée au matching par son gestionnaire. Besoins résolus/fermés, publications
  masquées, profils indisponibles/suspendus, comptes supprimés, périodes terminées et contenus non visibles
  cessent d’être proposés. Les détails et conversations antérieures restent soumis à leurs règles existantes.

### Implémentation et reprise
Une colonne JSON bornée pour les préférences du profil, une pour les conditions de la publication et une table
privée de correspondances (références, ignorance, notification ; aucun texte ou portrait copié). Les changements
sont additifs. Les suggestions sont recalculées depuis les données courantes, avec requêtes limitées aux capacités
concernées ; production des notifications lors des publications et changements de préférences, puis à la lecture
du Fil/Espace. Aucun service externe, modèle IA, dépendance ou nouvelle infrastructure. Les règles sont dans un
service commun et une Policy, les composants ne décident pas des autorisations.

Les contrats de mission, références de travail confirmées, facturation, commissions, wallet et nouveaux modules
restent hors de cette étape. Les propositions de l’ancien chat ne réactivent aucune fonction gelée. R1 reste
En cours jusqu’à la recette du Président. Recette : deux comptes, capacités communes, zones et conditions
compatibles puis incompatibles, notifications activées/désactivées, ignorance, clôture, blocage, visibilité et
expiration ; aucune nouvelle conversation avant acceptation.

## Profil personnel — présentation D-188
Intitulé facultatif de 100 caractères ; couverture issue d’une publication personnelle illustrée choisie,
sans copie de média ni changement de visibilité. Capacités, publications mises en avant et parcours déclaré
précèdent le fil des publications. Contrat, limites et état vide dans RECENTRAGE.md § Profil personnel lisible
en une minute. Ni diplôme obligatoire ni certification implicite.

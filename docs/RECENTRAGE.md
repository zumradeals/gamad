# GAMAD — entrée par le Fil, profils et entraide

Décisions D-183 et D-184, prises par le Président le 23 octobre 2026. Cette note rend la transition explicite ;
PRODUIT, DESIGN, DECISIONS et LOTS restent les sources d’autorité. Mission de mise en œuvre : Codex.

## Vision

Partager ce qu’on sait, dire ce qui nous manque et rencontrer quelqu’un pour avancer.
Le réseau reste social grâce aux profils, publications, gestes, échanges privés et notifications.
Son identité demeure Formation — Travail — Adoration, l’entraide et l’absence de classement de personnes.

## Conception : ce qui est ouvert

| Entrée | Contrat |
| --- | --- |
| Fil | Accueil connecté. Publications visibles, besoins, savoirs, souhaits d’apprendre et offres d’aide ; gestes et suites existantes. |
| Recherche de personnes (depuis le Fil, sans menu permanent) | Profils et capacités ; recherche par nom/capacité, ville, domaine, apporter/apprendre. Les personnes sans publication restent trouvables. |
| Agir | Publier en son nom avec les quatre types personnels existants. |
| Mon espace | Profil, publications, échanges privés paginés, compte, tâches et administration selon les droits. |

La navigation permanente est **Fil · Agir · Mon espace**. Une recherche visible dans le Fil mène soit aux
publications, soit aux personnes, avec le même terme. Les profils restent accessibles depuis les auteurs
et les capacités. La recherche n’accorde aucun droit supplémentaire.

Un besoin reste une publication, avec son détail, son suivi et ses réponses. Il ne devient ni un nouvel
objet ni une copie dans un autre module. Les réalisations de projets existantes restent lisibles ; leur
publication personnelle n’est pas introduite par cette décision.

## Conception : gel et continuité

| Élément | Suspendu | Conservé |
| --- | --- | --- |
| ZUMRA | Carrefour dans le parcours, création, nouvelles invitations et demandes, publication collective | Détail, membres existants, discussion, gestion, départs et traitement des demandes déjà engagées selon les droits |
| Projets | Carrefour dans le parcours, création, nouvelles demandes de participation, publication collective | Détail, étapes, équipe existante, gestion et traitement des demandes en attente selon les droits |
| Finances | Promotion, nouveaux portefeuilles, recharges et contributions, rappels de contribution et badge de profil | Solde, registre, historique, demandes en attente, rapprochement, webhooks, opérations administratives nécessaires aux engagements antérieurs |
| Carrefour Besoins | Entrée distincte et découverte séparée | Fil filtré Besoins, détails et gestes |

Les anciennes publications collectives gardent leur identité, leur visibilité et leurs liens de détail.
Ce n’est pas une conversion en publications personnelles ni une suppression de contenu. Les invitations
et demandes antérieures peuvent être traitées ; les nouvelles demandes ne peuvent pas être engagées.
La gestion interne d’une équipe existante reste possible pour assurer la continuité.

Dans Mon espace, « Mes activités antérieures » est replié et conditionné à une appartenance ou un
portefeuille réellement existant. Un nouvel inscrit ne voit pas cette rubrique. Les réglages financiers
restent réservés au Président et ne peuvent pas lever le gel des nouvelles opérations.

Aucun accès à la base de production n’a été utilisé pour cette modification : elle ne présume pas que
les historiques ou soldes sont vides. Aucun script d’effacement n’est nécessaire. D-184 ajoute uniquement les champs de prochaine action aux
conversations existantes, par une migration additive réversible ; elle ne remplit aucun contenu fictif.
Un solde ne se supprime pas : son titulaire garde l’accès à son relevé ; son règlement suit les règles
existantes avec l’administration, sans inventer de bouton de retrait ou de remboursement automatique.

## Implémentation et réversibilité

- `config/gamad.php` : `initial_experience = true`, versionné dans Git, sans interrupteur utilisateur.
- `App\Support\Experience` : unique lecture du mode ; navigation, présentation, Policies et services s’y réfèrent.
- Les règles du gel sont côté serveur. Cacher un menu n’est pas une autorisation.
- `/decouvrir`, `/zumra` et `/projets` redirigent vers Personnes ; `/besoins` redirige vers le Fil filtré Besoins (connexion requise). Les URL de détail et les droits associés sont conservés.
- Le lancement d’un paiement et la contribution depuis un portefeuille sont refusés ; la réception et le rapprochement d’un paiement lancé auparavant restent actifs. Le registre et le verrouillage de suppression d’un compte avec solde restent inchangés.
- Les anciens modules, leurs tests et leurs données restent dans le dépôt. Les tests existants continuent en mode complet pour vérifier leur conservation ; `RecentrageTest` exerce le mode initial, ses protections, sa navigation et la continuité des paiements/échanges.

Pour rouvrir un module : nouvelle décision du Président, périmètre/documentation actualisés, changement
du contrat et recette dans une PR. Ne pas réactiver un module en suivant seulement un ancien prompt,
une ancienne maquette, un réglage de portefeuille ou un lot antérieur. Pas de calendrier automatique.

## Essai sur téléphone

1. Sans compte : accueil centré sur personnes et entraide ; héros inchangé ; consulter Personnes.
2. Avec un compte neuf : Fil · Agir · Mon espace, aucune rubrique d’activités antérieures ; rechercher une capacité depuis le Fil et ouvrir les résultats de personnes.
3. Publier un besoin, le retrouver dans le Fil filtré, proposer une aide depuis un autre compte et l’accepter.
4. Ouvrir la conversation privée et la retrouver ensuite dans Mes échanges, même après lecture.
5. Avec un compte ayant des activités existantes : retrouver ses ZUMRA et historiques dans Mon espace.
6. Vérifier que créer une ZUMRA/un projet ou démarrer un paiement reste impossible par une ancienne URL.
7. Si un paiement est en attente : vérifier sa confirmation et son apparition unique au registre ; ne jamais lancer un paiement réel pour une simple recette.

## Suite de l’entraide décidée en D-184

Les compléments du besoin et du geste sont simplement assemblés dans leur texte existant, sans second
stockage. Les plafonds (500 / 280 caractères) restent vérifiés côté serveur, sur le texte final. Ils ne
changent pas la visibilité et ne publient pas de disponibilité générale sur un profil.

`ConversationActions` porte le contrat de la prochaine action : proposition, confirmation par l’autre,
retrait, vérification de version et messages associés dans une transaction. Le numéro de version évite
qu’une page ancienne n’efface ou ne confirme une proposition plus récente. Chaque nouvelle proposition
annule l’accord précédent. La date est indicative et ne déclenche aucun rappel.

Migration à appliquer par le déploiement habituel (`php artisan migrate --force`) :
`2026_11_07_100000_add_next_action_to_conversations.php`. Aucun service externe ni dépendance ajouté.
Les fichiers, appels, stockage et outils de formation discutés restent différés ; cette PR n’en constitue
ni le choix technique ni l’ouverture.

Recette complémentaire : préciser un besoin, proposer une aide avec disponibilité et format, accepter,
proposer une prochaine action, la confirmer avec l’autre compte, puis la modifier et vérifier que l’accord
est à refaire. Essayer une ancienne version et un compte tiers : aucune modification ne doit être admise.


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

## Profil personnel lisible en une minute — D-188

**Vision.** Montrer la valeur d’une personne par ses capacités et son expérience, que son parcours soit
académique, autodidacte, artisanal, informel ou bénévole. Le diplôme n’est pas un prérequis.

**Conception.** Ordre de lecture : identité et intitulé, savoir-faire concret, réalisations sélectionnées,
parcours déclaré, publications. Ancres de page utilisables sans JavaScript ; colonne secondaire pour contact,
offre récente et apprentissages, sous le parcours et avant les publications sur téléphone. Une seule instance de contact,
avec les états suivre, demande en attente, relation acceptée et blocage déjà définis en D-186. Aucun
contact direct nouveau. Les remerciements et les accès antérieurs autorisés restent disponibles.

**Implémentation.** Deux colonnes facultatives sur profiles : `headline` (100 caractères, texte brut) et
`cover_publication_id` (UUID nullable, clé étrangère vers publications, SET NULL à la suppression).
La couverture est choisie explicitement parmi les publications personnelles non masquées de l’auteur
ayant une image. Aucun téléversement supplémentaire, aucune copie de fichier ; la route média existante
revérifie chaque accès. Une publication devenue invisible/masquée ne fournit ni image ni extrait ; un fond
neutre remplace la couverture. Les appels anciens omettant ces champs conservent leurs valeurs.
Les trois publications à la une restent choisies par l’auteur et gardent leur libellé réel ; sans sélection,
les trois réalisations personnelles les plus récentes parmi les vingt publications visibles sont proposées.
Une section vide ne suggère ni compétence ni expérience inventée. Les offres présentées sont visibles,
non clôturées et non expirées. Les disponibilités/préférences de matching ne sont pas rendues publiques.
Aucune nouvelle dépendance, aucun nouveau module, aucune référence de travail certifiée. R1 reste En cours.

## Identité vérifiée et expériences confirmées — D-189

**Vision.** Rendre la confiance compréhensible sans promettre l’absence d’arnaque ni certifier globalement
une personne. Le diplôme, un paiement et le nombre de relations ne donnent aucun badge.

**Conception.** Deux mentions distinctes : Identité vérifiée par GAMAD (contrôle humain du nom affiché et
de la personne) ; Expérience confirmée (déclaration précise sur une réalisation, acceptée par un tiers
identifié). Pas de note ni de compteur public. Demandes et recours dans Mon espace → Mes attestations ;
confirmation depuis une réalisation personnelle ; signalement depuis un profil ou une référence ; file
Confiance dans la modération, réservée au super administrateur. Pas de nouveau menu principal.

**Implémentation et limites.** Trois tables additives, identity_verifications, work_attestations, trust_reports.
- Identité volontaire avec consentement explicite ; aucune image de pièce, numéro de document, date de
  naissance ou adresse collectés. Le contrôle réel a lieu en présence ou en échange direct, hors collecte
  documentaire de GAMAD. L’opérateur atteste séparément le rapprochement du justificatif avec le nom affiché
  et celui de la personne ; aucune approbation automatique ou auto-vérification. Seul un super administrateur
  actif peut examiner les dossiers d’autrui. Une identité vérifiée expire après un an. Un changement du nom
  affiché invalide demandes et badges, y compris si l’ancien nom est rétabli ; un compte inactif n’a pas de badge.
  États pending, verified, declined, revoked, withdrawn ; motif privé et instruction de rendez-vous facultative
  dans le dossier. Demande suivante après sept jours si refus/retrait ; renouvellement à expiration.
- Une référence porte sur une publication personnelle Ma réalisation non masquée de l’auteur. L’auteur
  maintient au plus trois sollicitations actives, déjà en relation réciproque et dont l’identité est vérifiée. Il décrit
  son intervention (280 caractères) et sa date passée ; le témoin accepte ce texte explicitement et précise
  son rôle (client, collègue, personne présente). Aucun message privé créé automatiquement. Révision sous
  verrou contre les anciennes réponses, maximum cinq sollicitations par jour, délai de sept jours après
  refus/retrait. Le témoin et l’auteur peuvent retirer leur consentement. GAMAD peut révoquer avec motif,
  jamais confirmer à la place du témoin. Le témoin ne modifie pas le texte accepté par l’auteur.
- Mention visible uniquement si publication et profils sont visibles et actifs, identité du témoin toujours
  valable avec la même révision que lors de la confirmation. Expiration, masquage, suppression et blocages
  sont respectés. Une nouvelle vérification du témoin exige une nouvelle confirmation explicite ; aucun
  rétablissement automatique après révocation. Un blocage retire les sollicitations et références des deux
  personnes, sans les réactiver au déblocage. Les coordonnées et preuves privées ne sont jamais exposées.
- Signalement motivé (500 caractères), cinq par jour et un ouvert par cible/personne. Le nom du signalant
  n’est pas communiqué à la personne signalée ; les opérateurs impliqués sont exclus de la file d’examen. Les personnes concernées peuvent contester une décision
  par le même formulaire (catégorie Recours). Un recours ouvert permet de rouvrir le contrôle humain ou de
  redemander au témoin sa confirmation ; aucune mention n’est rétablie automatiquement. La file permet une réponse motivée, une clôture et les actes
  distincts de retrait appropriés, avec contrôle des conflits d’intérêt (jamais ses propres dossiers).
- Les décisions administratives sont tracées dans le journal en ajout seul, sans pièce ni texte de référence
  copié dans le journal. Les dossiers privés restent liés aux comptes/publications et disparaissent avec
  eux ; les traces administratives existantes restent conservées selon le fonctionnement du journal.
  Notifications internes génériques regroupées, sans contenu privé ni message extérieur. Listes privées
  bornées de 20 à 200 entrées récentes, recherche des dossiers administratifs par nom. Aucun service
  d’identité externe, paiement, stockage sensible ou certification professionnelle ajouté. Les vérifications
  humaines effectives et l’organisation des rendez-vous relèvent de GAMAD, pas d’un contrôle simulé.

Les données déclarées de D-186 restent déclaratives ; seule une confirmation portant la mention explicite
ci-dessus est une expérience confirmée. R1 reste En cours et les modules gelés restent gelés.

## Entrée publique et vocabulaire — D-190

Vision : GAMAD se présente comme un réseau social accessible, pour montrer son savoir-faire, rencontrer des
personnes et trouver des occasions d’entraide, de travail ou d’apprentissage. La présentation ne suppose
aucune appartenance à une organisation. La devise et les mentions du Mouvement sont retirées des textes
éditoriaux visibles ; les contenus personnels et l’historique des décisions ne sont pas réécrits.

Conception : Accueil · Agir · Mon espace. Accueil nomme l’entrée connectée jusque-là nommée Fil ; le fil de
publications demeure le contenu de cette page. La page publique / permet de découvrir le réseau, de chercher
une personne et de voir des profils/publications réellement publics, sans compte. Inscription et connexion
sont des choix explicites. L’image du héros reste celle déjà validée. Aucun chiffre, profil ou témoignage fictif.

Implémentation : conserver les noms et URL internes fil et /fil pour préserver liens, filtres et retours de
connexion. Un visiteur non connecté sur cette route est dirigé vers /, sans session de compte requise ; les
autres routes privées continuent de mener à la connexion. Les personnes connectées vont de / vers /fil.
La page de connexion reste disponible, avec un retour explicite vers la présentation publique. La navigation,
les titres et liens de retour affichent Accueil ; la recherche parle de publications. Les carrefours conservés
et leurs droits restent inchangés. La page publique présente des profils visibles sans compte et leurs
publications personnelles non masquées ; les aperçus d’un auteur au profil réservé sont exclus.
L’introduction de la charte est alignée éditorialement, sans modifier les autres règles, dates d’adoption ou
consentements antérieurs. Pas de migration, nouveau stockage, ouverture publique automatique, assouplissement
de l’authentification ou indexation automatique. R1 reste En cours.


## Parcours Agir — D-191
Le Président approuve quatre intentions : Trouver de l’aide, Apprendre quelque chose,
Proposer quelque chose et Partager. Elles orientent vers les types existants ; besoin = résultat
recherché, apprentissage = compétence à acquérir. Gratuité et rémunération sont indépendantes.
Formulaire progressif, détails facultatifs, relecture et retour Modifier sans perdre la saisie.
Matching facultatif présenté comme « M’aider à trouver les bonnes personnes ». Les validations
serveur, les droits et la limite de 500 caractères restent applicables à la publication finale.
Aucune nouvelle dépendance ni réouverture de module. R1 reste En cours.


## De la publication au résultat — D-192
Les cinq priorités approuvées enrichissent R1 sans nouveau menu : réponses adaptées, suite après
publication, brouillon local, bilan facultatif et une suggestion de profil à la fois.
Les gestes et leur consentement restent inchangés ; disponibilités facultatives et 280 caractères au total.
Après publication personnelle : lien partageable avec visibilité explicitée, pistes existantes et conseil
de profil. Aucune correspondance fictive ni message automatique.
Brouillon : texte et précisions du besoin seulement, sur cet appareil, séparés par compte et type ;
reprise explicite, effacement, expiration après sept jours, suppression après publication réussie.
Fichiers, conditions, audience et autres réglages doivent être revérifiés ; aucun fichier sauvegardé localement.
Bilan : texte brut de 280 caractères maximum, facultatif, sur un besoin personnel résolu non masqué,
modifiable/effaçable par son auteur actif uniquement ; même audience que la publication. Déclaration
de l’auteur, sans tag ni notification ni attestation implicite. Une colonne nullable est autorisée.
Depuis le bilan, création volontaire d’une réalisation par le formulaire existant, puis demande éventuelle
de confirmation selon D-189. Aucune publication ou confirmation automatique.
Conseil de profil : une suggestion déterministe privée, sans score ni diplôme requis ; capacité,
réalisation ou présentation selon les éléments manquants. R1 reste En cours.


## Vie sociale et conversations publiques — D-193

Le Président valide les maquettes téléphone et ordinateur de l'Accueil social et d'une publication avec
commentaires. Principe : la confiance peut précéder la collaboration ; aucune publication n'est obligée
de devenir un geste. Livraison par trois PR distinctes, après contrôle : (1) commentaires et réponses,
(2) enregistrement privé et partage de lien, (3) découverte chronologique et harmonisation de l'Accueil.

PR 1 : les publications personnelles, publiques ou réservées aux inscrits, reçoivent une discussion
publique selon leur audience ; les publications collectives historiques restent inchangées. Lecture
autorisée au même public que la publication. Écriture, modification, suppression et signalement
demandent un compte actif et une adresse vérifiée. Texte brut, 500 caractères, réponses à un seul niveau.
L'auteur seul peut modifier ou retirer son texte ; la suppression enlève son texte mais conserve le
contexte des réponses. Les contenus masqués affichent un emplacement neutre, jamais le texte.
Une publication devenue invisible ne laisse pas voir ses commentaires ; interdiction revérifiée côté serveur.
Aucune conversation privée, relation ou geste créé automatiquement, aucun score ni compteur de popularité.

Un signalement par personne et commentaire avec un motif de la liste existante ; auteur du signalement
non communiqué. Les modérateurs ont une file spécifique et peuvent masquer, classer ou rétablir ;
les décisions ont un motif et une trace dans le journal d'administration. Un auteur masqué peut
expliquer son commentaire ; il ne peut pas restaurer lui-même le contenu. Les actes de modération sur
son propre commentaire sont refusés. Nouvelles tables additives, cascade à la suppression du compte
et de la publication, aucune dépendance supplémentaire. Le délai et la recette restent ceux de R1.
Notifications sociales, enregistrements et évolution du Fil relèvent des PR suivantes.

## Enregistrer et partager — D-193, PR 2/3
Enregistrement personnel explicite des publications personnelles seulement, dans une table additive
unique par personne et publication, sans nombre public, classement ou signal de popularité. Les éléments
sont visibles uniquement au propriétaire, au même niveau de droits que lors de la lecture : une
publication masquée, supprimée, bloquée ou devenue inaccessible disparaît de la liste, sans divulgation.
Un lien partagé ne contourne jamais la visibilité ; le partage ne crée ni audience ni nouvelle copie
publique. L'interface offre une solution manuelle si le partage natif ou le presse-papiers est indisponible.

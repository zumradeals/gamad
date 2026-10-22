# Réseau social d'action GAMAD — Fondations de conception v0.2

Oct 22, 2026 · @KONE DJAKARIDJA

Un réseau ouvert à tous où **la personne est au centre** : quel que soit son statut, chacun vient apporter ce qu'il sait faire, apprendre ce qu'il ne sait pas encore, ou dire ce qui lui manque. Les ZUMRA regroupent les personnes, les projets naissent dans les ZUMRA, et le Fil global montre ce qui se fait, avec des boutons d'action à la place des « j'aime ».

**Statut : version 0.2.** Elle intègre les décisions du Président de GAMAD du 22 octobre 2026 (section 10). Les points encore marqués « À valider » restent des propositions de travail.

## L'essentiel : trois briques, trois niveaux

GAMAD n'a pas besoin de dix modules profonds : il a besoin de **trois briques construites une seule fois** et réutilisées partout. Personne, ZUMRA et Projet sont des « mondes » bâtis sur le même gabarit ; seuls leurs onglets changent.

&#91;embedded content: architecture du socle · 3 briques réutilisables\]

**Règle d'or : trois niveaux au plus.** Carrefour → Monde → Élément. Un onglet affiche une liste, jamais un monde caché dans un monde. Un projet est un monde à part, relié à sa ZUMRA, et non un sous-dossier de celle-ci.

| Monde | En-tête | Onglets V1 (5 au plus) |
| --- | --- | --- |
| ZUMRA | Nom, domaine, territoire, badge « Reconnue », état, bouton Je veux rejoindre | **Formation** · Fil · Projets · Membres · Discussion |
| Projet | Nom, ZUMRA porteuse, état, étape en cours, bouton Je soutiens | Avancement · Fil · Équipe · Besoins |
| Personne | Nom, ville, bouton Inviter | Je peux apporter / Je veux apprendre · Fil · ZUMRA |

**Formation au premier rang.** Dans une ZUMRA, l'onglet Formation s'ouvre en premier, fidèle à la devise Formation – Travail – Adoration. En V1, il rassemble les publications « Je partage un savoir » de la ZUMRA et les membres qui ont répondu « Je veux apprendre ».

**Ce qui viendra plus tard s'ajoute sans casser le socle :**

- une **Organisation** ou une **startup** issue d'un projet = un nouveau type de monde, relié à sa ZUMRA d'origine ;
- un **outil spécialisé** (stockage de documents, gestion, vente…) = un nouvel onglet « Outils » branché sur un monde ;
- les **contributions**, les **événements**, le **chat en direct** = des éléments ou des onglets de plus.

Ces idées sont conservées dans un registre « Plus tard » du dépôt : rien n'est perdu, rien n'encombre la V1.

**Ce que la V1 doit faire ressentir :** un vrai réseau social d'action. On y entre par le Fil, on découvre des personnes, des besoins et des ZUMRA, on agit d'un geste.

## 1. Ancrage statutaire

La plateforme a un fondement explicite : le Règlement intérieur prévoit « une plateforme communautaire dynamique et interactive ». Les autres articles fixent ses limites.

| Texte | Ce qu'il dit | Conséquence pour la plateforme |
| --- | --- | --- |
| RI art. 13, al. 3-b | Plateforme communautaire qui contribue à l'amour, la connaissance et le rapprochement des membres et structures | Fondement du projet. L'ouverture au public étend ce texte : elle s'appuie sur le Préambule et l'art. 5 (voir ci-dessous) et a été décidée le 22 octobre 2026 (D-01) |
| Préambule ; Statuts art. 1 | GAMAD abrite toute personne quelle que soit sa localisation, sa race ou sa religion ; organisation d'action citoyenne, sociale et de masse | Ouverture au grand public cohérente avec la nature du Mouvement |
| Statuts art. 5 | Les réseaux sociaux sont un moyen d'action | La plateforme est un moyen d'action, pas une fin |
| Statuts art. 2 | Promeut union, paix, justice, solidarité, culture de l'amour ; combat corruption, racisme, mensonge, criminalité, terrorisme, violence | Charte de conduite et modération dès le premier jour |
| Statuts art. 7 ; RI art. 24 | Membres actifs, sympathisants, membres d'honneur | Un compte sur la plateforme n'est pas une adhésion. L'admission relève du CSD (art. 17 d) |
| Statuts art. 19 ; RI art. 19 | Les sections créent et encadrent des groupes de travail appelés Zumara ; chaque groupe a un domaine d'activité unique et spécifique | Une ZUMRA = un groupe à domaine unique. Sa reconnaissance officielle est donnée par le Président (D-03) |
| RI art. 3 | Liste non définitive des départements (Santé et Action sociale, Religion, Formation et Éducation, Technologie…) | Base de la liste des domaines d'action (D-06) |
| RI art. 13, al. 3-a | Centre de résolution des conflits entre membres | Signalement et médiation intégrés au produit |
| RI art. 21 | Aucune activité engageant financièrement le Mouvement sans décision préalable du Guide Suprême | Contributions dans un lot dédié et tracées ; aucune dépense engagée au nom du Mouvement via la plateforme (D-04) |

Deux points textuels à trancher : les Statuts écrivent « Zumara » et l'ancien produit « ZUMRA » (D-02) ; l'art. 3 du RI annonce une liste de départements « non définitive », donc la taxonomie doit rester modifiable.

## 2. Diagnostic de l'ancien dépôt

La confusion vient de la conception, pas du code : le moteur a été construit pour 84 « capacités » avant qu'un seul parcours utilisateur soit terminé. Mesures relevées dans `dgafrique-core-main` :

| Indicateur | Valeur |
| --- | --- |
| Capacités référencées (CAP-001 à CAP-084) | 84 |
| Modèles de données (Eloquent) | 82 |
| Migrations | 62 |
| Lignes PHP dans `app/` | environ 28 400 |
| Documents Markdown dans `docs/` | 95, environ 120 000 mots |
| Lectures obligatoires avant tout développement (AGENTS.md) | 11 documents |

**Quatre causes racines :**

1. **Des principes transformés en fonctionnalités.** Plusieurs « capacités » sont des idées, pas des fonctions : CAP-072 « La règle UX principale », CAP-081 « La grande différence avec un réseau social passif », CAP-082 « La grande différence avec LinkedIn ». Chaque IA les a traitées comme du périmètre à construire.
2. **Des concepts accumulés sans décision d'entrée.** Mission (12 modèles : affectation, blocage, dépendance, récurrence…), Transmission, Preuve (témoins, références), ZAHAB, Satellites, Project Brain, Accompagnement, Maturité, Parcours d'autonomie, Ledger. Aucun registre ne dit qui a décidé de les introduire.
3. **Des textes « canoniques » qui se contredisent.** La doctrine ZUMRA (adoptée le 14 août 2026) interdit d'afficher le nom GAMAD ; l'Expérience produit (décision du 8 septembre 2026) fait de GAMAD le nom visible. Les deux restent marquées canoniques. Le README s'ouvre sur deux bandeaux opposés : « moteur seul, ne pas restaurer le frontend » (6 sept.) puis « reprise autorisée » (7 sept.).
4. **Plus de documentation que de produit.** Une IA qui doit lire 11 documents et 120 000 mots avant d'agir finit par en suivre une partie et en contredire une autre.

**À retenir de l'ancien dépôt :** les intuitions de fond (la personne avant la structure, pas de score humain, preuve plutôt que déclaration, confidentialité par choix) et le test doctrinal d'une fonctionnalité. Le code n'est pas repris ; il reste consultable comme référence.

## 3. Positionnement

Sur LinkedIn, on vient montrer ce qu'on a déjà fait. Ici, **on vient avec ce qu'on est** : ce qu'on peut apporter, ce qu'on veut apprendre, ce qui nous manque. GAMAD accueille chaque personne, quel que soit son statut, et la met en mouvement avec d'autres.

|  | LinkedIn | Réseau social d'action GAMAD |
| --- | --- | --- |
| Ce qui est au centre | Le parcours professionnel | La personne et ce qu'elle peut apporter ou apprendre |
| Qui est accueilli | Surtout des salariés diplômés | Tout le monde, avec ou sans diplôme, en ville comme au village |
| Ce qui circule | Opinions, autopromotion | Offres d'aide, envies d'apprendre, besoins, avancées de projets, réalisations |
| Geste principal | J'aime, je commente | Je peux aider, je veux apprendre, je veux rejoindre, je soutiens |
| Collectif | Pages d'entreprise | ZUMRA : communautés vivantes avec leur propre fil, leurs échanges et leurs formations |
| Ce qui fait la réputation | Titres, abonnés, réactions | Ce que la personne a réellement apporté, constaté par d'autres |

**Promesse (à valider) :** « Des personnes. Des actions. Un impact réel. », déjà utilisée dans les écrans actuels.

**Hypothèses d'usage pour la V1 (à confirmer) :** premiers utilisateurs en Côte d'Ivoire, en français, sur téléphone, avec une connexion parfois faible. L'écran mobile est l'écran de référence.

## 4. Modèle de domaine V1

Sept objets principaux. Tout autre concept reste hors périmètre jusqu'à une décision écrite.

| Objet | Définition | États | Qui décide |
| --- | --- | --- | --- |
| **Personne** | Un compte individuel : qui je suis, où je vis, **ce que je peux apporter**, **ce que je veux apprendre** | actif · suspendu · supprimé | La personne ; la modération pour la suspension |
| **ZUMRA** | Une communauté créée par toute personne autour d'un domaine (y compris religieux), avec ses membres, son mini-fil, sa discussion privée et ses formations | active · reconnue officiellement · suspendue · archivée | Le fondateur gère ; le Président de GAMAD reconnaît |
| **Projet** | Une action structurée, **toujours rattachée à une ZUMRA**, avec une équipe, des étapes, des réalisations et une page publique | idée · en cours · terminé · abandonné | Les responsables désignés par la ZUMRA |
| **Publication** | Ce qui circule dans le Fil global ou le mini-fil d'une ZUMRA ; publiée par une personne ou une ZUMRA ; toujours d'un type déclaré (section 6) | publiée · masquée · supprimée | Son auteur ; la modération |
| **Besoin** | Une publication de type « J'ai besoin » qui a un suivi : compétence, coup de main, matériel, conseil, partenaire, formation ; porté par une personne ou une ZUMRA | ouvert · en cours · résolu · fermé sans suite | Son auteur |
| **Geste d'action** | La réponse à une publication : Je peux aider, Je veux apprendre, Je veux rejoindre, Je soutiens. Il crée une mise en relation | envoyé · accepté · décliné · retiré | Celui qui agit l'envoie ; l'auteur de la publication accepte ou décline |
| **Invitation** | Une proposition faite à une personne, souvent trouvée par ses capacités, de rejoindre une ZUMRA ou un projet avec un rôle | envoyée · acceptée · refusée · expirée | Un responsable l'envoie ; la personne répond |

**Objets de service :** Commentaire, Conversation (privée entre deux personnes, ou discussion des membres d'une ZUMRA), Suivi, Notification, Signalement, Journal d'administration (trace des actes du super administrateur).

**Objet prévu mais construit plus tard : Contribution mensuelle.** Facultative, versée par une personne ou une ZUMRA, ouvrant des avantages. Elle remplace l'adhésion de 500 FCFA, suspendue. Elle a son propre lot (section 8) car elle exige un prestataire de paiement et des règles d'avantages validées (D-13).

**Hors V1** (réintroduction par décision) : Mission, Transmission, Preuve formelle, ZAHAB, Satellites et GamaDrive, assistant IA de projet, recommandation algorithmique, score de maturité, accompagnement, Organisations, prise de participation par des investisseurs (D-14).

## 5. La personne au centre

&#91;embedded content: la personne au centre · du profil au Fil global\]

Chaque parcours part d'une personne et revient au Fil, où il attire d'autres personnes. Une personne peut aussi agir seule depuis le Fil, sans ZUMRA : proposer son aide, demander à apprendre, exprimer un besoin.

La boucle la plus courte reste celle du besoin : quelqu'un exprime ce qui lui manque, un autre répond « Je peux aider », l'auteur accepte, ils échangent en privé, puis l'auteur dit qui l'a aidé. Ce remerciement public construit la réputation de celui qui a aidé. La même mécanique vaut pour « Je veux apprendre » : celui qui partage un savoir accepte l'apprenant, et la formation réalisée devient visible.

## 6. Les six modules

Chaque module a un rôle unique. Si deux modules font la même chose, l'un des deux est en trop.

| Module | Rôle | Contenu V1 | Ne fait jamais |
| --- | --- | --- | --- |
| **Fil global** | Voir ce que fait le réseau et y répondre par un geste | Publications des personnes et des ZUMRA, chacune avec son type et son bouton d'action ; filtres par type ; d'abord ce que je suis, puis le reste du réseau | « J'aime », compteur de popularité, classement par buzz |
| **Personnes** | Trouver qui peut apporter quoi | Profil en deux colonnes : Je peux apporter / Je veux apprendre ; recherche par capacité, domaine, ville ; bouton « Inviter dans ma ZUMRA » | Note, score ou classement de personnes |
| **Besoins** | Voir tout ce qui manque, au même endroit | Besoins ouverts des personnes et des ZUMRA, filtrables par type, domaine, ville, état ; suivi jusqu'à la résolution | Promettre un financement |
| **Projets** | Montrer où en est une action, à tous | Annuaire ; page publique du projet : ZUMRA porteuse, objectif, équipe, étapes, réalisations, besoins ouverts, soutiens. Lisible par visiteurs, contributeurs et partenaires | Pourcentage d'avancement inventé |
| **ZUMRA** | Vivre en communauté autour d'un domaine | Annuaire ; page ZUMRA avec mini-fil (public ou réservé aux membres), membres et rôles, discussion privée des membres, savoirs partagés, projets, besoins ; badge « Reconnue par GAMAD » | Accepter un membre sans l'accord d'un responsable |
| **Mon espace** | Savoir quoi faire maintenant | En haut : ce qui attend ma réponse (gestes reçus, invitations, demandes d'adhésion, messages) ; puis mes publications, ZUMRA, projets, profil | Devenir un tableau de bord de tous les modules |

### Types de publication et gestes d'action

Toute publication déclare son type au moment où on l'écrit. Le type décide du bouton d'action affiché sous la publication.

| Type de publication | Qui publie | Bouton d'action | Ce que le geste déclenche |
| --- | --- | --- | --- |
| J'ai besoin | Personne, ZUMRA | Je peux aider | Proposition à accepter, puis conversation privée |
| Je partage un savoir (conseil, tutoriel, formation) | Personne, ZUMRA | Je veux apprendre | Demande à l'auteur, qui accepte l'apprenant |
| Je cherche à apprendre | Personne | Je peux transmettre | Mise en relation avec celui qui veut apprendre |
| Je propose mon aide ou un savoir-faire | Personne, ZUMRA | Ça m'intéresse | Mise en relation |
| Actualité ou réalisation de projet | ZUMRA, pour son projet | Je soutiens · Je veux participer | Suivi du projet ; demande à rejoindre l'équipe |
| Appel à rejoindre une ZUMRA | ZUMRA | Je veux rejoindre | Demande d'adhésion au responsable |

Sur toute publication : Commenter, Partager (WhatsApp compris), Signaler. Aucun compteur d'approbation. Seuls des engagements réels peuvent être comptés, par exemple « 4 personnes veulent apprendre ».

**Le bouton « Agir »**, présent partout, propose trois choix : Publier (on choisit d'abord le type), Créer une ZUMRA, Lancer un projet dans une de mes ZUMRA.

**Navigation.** Ordinateur : Fil · Personnes · Besoins · Projets · ZUMRA, plus Agir et l'avatar qui ouvre Mon espace. Téléphone : Fil · Découvrir · Agir · ZUMRA · Mon espace, où Découvrir regroupe Personnes, Besoins et Projets.

**Pas de bannière publicitaire une fois connecté.** Les grandes illustrations sont réservées à la page d'accueil publique ; dans le réseau, le contenu utile commence en haut de l'écran.

## 7. Règles transverses

Ces règles s'appliquent à tous les modules et passent avant toute demande d'écran.

1. **Compte n'est pas adhésion.** S'inscrire ne fait de personne un membre du Mouvement GAMAD. La qualité de membre (actif, sympathisant, d'honneur) relève des organes du Mouvement et n'est pas gérée en V1.
2. **L'argent arrive plus tard, et n'achète jamais la visibilité.** Aucun paiement dans les lots 0 à 5. Les contributions mensuelles ont leur propre lot. Leurs avantages ne peuvent jamais acheter une meilleure place dans le Fil, une reconnaissance de ZUMRA ou un pouvoir de décision.
3. **Toutes les ZUMRA ont les mêmes droits.** Une ZUMRA religieuse, culturelle, agricole ou technologique suit les mêmes règles. La charte impose le respect de toutes les croyances et interdit le dénigrement d'autrui.
4. **Trois niveaux de visibilité :** public (visible sans compte), réseau (personnes connectées), ZUMRA (membres seulement). Chaque publication affiche son niveau.
5. **Pas de score humain.** Aucune note, aucun classement, aucun « j'aime ». On montre des faits : aides apportées, savoirs transmis, projets menés, remerciements reçus.
6. **Pas de donnée inventée.** Un chiffre affiché vient de la base. Un écran vide propose une action concrète au lieu d'expliquer le concept.
7. **Modération dès le premier jour.** Charte de conduite fondée sur l'art. 2 des Statuts ; bouton Signaler partout ; modérateurs délégués par le super administrateur ; droit de l'auteur à s'expliquer avant une sanction (inspiré du RI art. 23).
8. **Le super administrateur est tracé.** Chaque acte du super administrateur (reconnaissance de ZUMRA, suspension, suppression, changement de règle) est inscrit dans un journal non modifiable : qui, quand, quoi, pourquoi.
9. **Données personnelles protégées.** Téléphone et e-mail jamais affichés ; contact par conversation privée ; suppression du compte possible par la personne.
10. **Territoires normalisés.** Pays et ville choisis dans une liste (l'écran actuel affiche « abidjan » et « Abidjan, Côte d'Ivoire » comme deux lieux).
11. **Vocabulaire fixe.** Un seul terme par concept, défini dans le glossaire du dépôt. Un nouveau terme exige une décision.

## 8. Plan de construction par lots

On construit un lot à la fois, de la base de données jusqu'à l'écran du téléphone. Un lot n'est commencé que lorsque le précédent a été essayé et validé par le Président sur un téléphone.

| Lot | Contenu | Terminé quand |
| --- | --- | --- |
| 0. Socle | Nouveau dépôt Laravel + Livewire + PostgreSQL ; CLAUDE.md, glossaire, journal de décisions ; inscription et connexion ; rôle super administrateur et journal d'administration ; gabarit mobile et ordinateur ; tests automatiques | Une personne crée un compte sur téléphone ; le super administrateur se connecte et ses actes sont tracés ; tous les tests passent |
| 1. Personne | Profil Je peux apporter / Je veux apprendre, ville, domaines ; annuaire Personnes avec recherche par capacité | Deux comptes de test se trouvent par capacité et par ville |
| 2. Fil global et gestes | Publications typées, boutons d'action, acceptation, conversation privée, besoin résolu avec remerciement ; première version de Mon espace ; notifications ; signalement | Deux parcours complets sur téléphone, testés de bout en bout : « J'ai besoin » → « Je peux aider » → remerciement ; « Je partage un savoir » → « Je veux apprendre » → apprenant accepté |
| 3. ZUMRA | Création, rôles, invitation par capacité, demande d'adhésion, mini-fil, discussion des membres, besoins de ZUMRA, reconnaissance par le Président | Une ZUMRA réelle invite trois personnes trouvées par capacité, publie dans son mini-fil, puis est reconnue |
| 4. Projets | Projet dans une ZUMRA ; page publique ; équipe, étapes, réalisations, besoins, soutiens | Un visiteur sans compte comprend où en est un projet réel en moins d'une minute |
| 5. Ouverture | Page d'accueil publique, charte de conduite, mentions légales, outil de modération, test en connexion faible ; pilote fermé puis ouverture | Le pilote (taille suggérée : 30 à 50 personnes et 3 ZUMRA) utilise le réseau sans assistance |
| 6. Contributions mensuelles | Prestataire de paiement (Mobile Money), montants, avantages validés, reçus, tableau des contributions pour le Président | Une contribution test est payée, reçue, tracée et ouvre l'avantage prévu |

Le lot 6 peut passer avant l'ouverture si vous le décidez. Je le place après pour que le réseau prouve d'abord sa valeur sans argent.

## 9. Gouvernance du nouveau dépôt

Quatre fichiers seulement font autorité pour Claude Code. Tout le reste est de la documentation de travail, jamais une source de règles.

| Fichier | Contenu | Taille maximale |
| --- | --- | --- |
| `CLAUDE.md` | Règles d'exécution, pile technique, interdictions, définition de « terminé » | 2 pages |
| `docs/PRODUIT.md` | Glossaire, six objets, états, droits, règles transverses (issu de ce document une fois validé) | 10 pages |
| `docs/DECISIONS.md` | Journal numéroté : date, décision, qui l'a prise, ce qu'elle remplace | Sans limite, une entrée par décision |
| `docs/LOTS.md` | Lot en cours, son périmètre exact, ses critères de fin, son état | 3 pages |

**Règles pour l'agent de développement :**

1. Un nouveau modèle, une nouvelle table, un nouveau terme ou un nouvel écran exige une entrée dans `DECISIONS.md` validée par le dirigeant.
2. Un lot ne touche que son périmètre ; tout écart est signalé, jamais réalisé en silence.
3. Un écran n'est livré qu'avec ses états vide, chargement et erreur, testé sur mobile.
4. Une décision nouvelle remplace explicitement l'ancienne : on ne laisse jamais deux textes contradictoires en vigueur.
5. Pas de bandeau d'historique dans le README ; l'historique vit dans `DECISIONS.md`.

**Répartition des rôles :** le Président de GAMAD décide ; Claude (cet espace) conçoit, rédige les règles et les instructions de lot, puis relit le résultat ; Claude Code exécute un lot à la fois.

**Pile technique :** PHP, Laravel, Livewire, Tailwind, PostgreSQL (préférence déjà établie). Authentification Laravel native en V1, isolée derrière une interface pour permettre plus tard une fédération avec GAMAD Core (D-09).

## 10. Décisions

Le 22 octobre 2026, le Président de GAMAD, Koné Djakaridja, a déclaré exercer, avec l'accord du Guide, l'autorité de concevoir et de décider pour GAMAD numérique, et être le super administrateur de la plateforme. Six décisions sont prises ; dix restent à valider.

| N° | Question | Décision prise ou recommandation | État |
| --- | --- | --- | --- |
| D-01 | Ouvrir la plateforme au grand public ? | **Décidé :** ouverte à tous, pas seulement aux membres de GAMAD | Validée |
| D-03 | Qui crée une ZUMRA, qui la reconnaît ? | **Décidé :** toute personne crée sa ZUMRA et y invite des personnes selon leurs capacités ; la reconnaissance officielle est donnée par le Président | Modifiée |
| D-04 | Adhésion de 500 FCFA ? | **Décidé :** suspendue. Remplacée par une contribution mensuelle facultative, versée par une personne ou une ZUMRA, qui ouvre des avantages | Modifiée |
| D-05 | Un projet doit-il appartenir à une ZUMRA ? | **Décidé :** oui, toujours. Chaque projet a une page publique (état, étapes, réalisations) | Modifiée |
| D-07 | Que peut-on publier dans le Fil ? | **Décidé :** personnes et ZUMRA publient ; boutons d'action (Je peux aider, Je veux apprendre…), aucun « j'aime ». Ma proposition d'application : publications typées (section 6) | Modifiée |
| D-11 | Place de la dimension religieuse ? | **Décidé :** toute personne peut créer une ZUMRA religieuse, avec les mêmes droits qu'une autre | Validée |
| D-02 | Graphie : « ZUMRA » ou « Zumara » (Statuts) ? | Une seule graphie partout, celle que vous jugez officielle | Validée |
| D-06 | Domaines d'action = départements du RI art. 3 ? | Oui, liste modifiable par le super administrateur | À valider |
| D-08 | Inscription par e-mail ou par téléphone ? | E-mail + mot de passe en V1 ; téléphone par SMS plus tard (coût par message) | Validée |
| D-09 | Brancher GAMAD Core dès la V1 ? | Non ; authentification native, isolée pour une fédération ultérieure | Validée |
| D-10 | Retirer de la V1 Mission, Transmission, ZAHAB, Satellites et les concepts de la section 4 ? | Oui ; réintroduction une par une, par décision | À valider |
| D-12 | Sort de l'ancien dépôt `dgafrique-core` ? | Archivé en lecture seule ; aucun code repris automatiquement | Validée |
| D-13 | Quels avantages pour les contributeurs ? | Des avantages de service : badge « Contributeur », outils avancés pour sa ZUMRA, accès prioritaire aux formations, rapport d'impact. Jamais de visibilité achetée ni de pouvoir de décision | À valider |
| D-14 | Que peut faire un investisseur sur la page projet ? | En V1 : lire la page et contacter l'équipe. Aucune sollicitation d'investissement (parts, prêts) sans avis juridique préalable, car ces opérations sont réglementées | À valider |
| D-15 | Discussion privée des membres d'une ZUMRA : chat en direct ou fil de discussion ? | Fil de discussion simple en V1 (moins coûteux, fonctionne en connexion faible) ; chat en direct plus tard | À valider |
| D-16 | Formaliser par écrit la délégation du Guide au Président pour GAMAD numérique ? | Oui : une décision écrite et datée, car les Statuts (art. 8) et le RI (art. 2) placent l'autorité finale auprès du Guide Suprême | À valider |

**Maquettes validées le 22 octobre 2026** : Fil global (téléphone et ordinateur), Carrefour ZUMRA, Monde d'une ZUMRA.

**Passage à la construction.** Ce document est traduit en fichiers de référence pour le nouveau dépôt : `CLAUDE.md`, `docs/PRODUIT.md`, `docs/DESIGN.md` (avec les maquettes), `docs/DECISIONS.md` (journal D-001 à D-023), `docs/LOTS.md`, `docs/PLUS-TARD.md`, ainsi que l'instruction du lot 0 pour Claude Code. À partir de maintenant, **ces fichiers font autorité pour le développement** ; ce document reste la trace du raisonnement.

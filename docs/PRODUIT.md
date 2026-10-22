# PRODUIT — Réseau social d'action GAMAD

Version 1.0 — 22 octobre 2026. Source : document « Fondations de conception v0.2 » et décisions du Président
(voir `DECISIONS.md`). Toute modification passe par une décision.

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

Règles :

- Toute personne peut créer une ZUMRA. Une ZUMRA religieuse a exactement les mêmes droits qu'une autre.
- Une ZUMRA a un seul domaine.
- Un projet appartient toujours à une ZUMRA.
- Une personne ou une ZUMRA peut publier un besoin.
- Accepter un geste ouvre une conversation privée entre les deux personnes.
- Un besoin résolu peut porter un remerciement, visible sur le profil de la personne qui a aidé.

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
2. **L'argent arrive plus tard et n'achète jamais la visibilité.** Aucun paiement avant le lot des contributions.
3. **Visibilité :** public (sans compte) · réseau (personnes connectées) · ZUMRA (membres seulement).
4. **Pas de score humain.** Ni note, ni classement, ni « j'aime ».
5. **Pas de donnée inventée.** Un chiffre affiché vient de la base. Un écran vide propose une action.
6. **Modération dès le premier jour.** Signaler partout ; droit de l'auteur à s'expliquer avant une sanction.
7. **Le super administrateur est tracé.** Chaque acte est inscrit au journal d'administration.
8. **Données personnelles.** Téléphone et e-mail jamais affichés à autrui ; suppression du compte possible.
9. **Territoires normalisés.** Pays et ville choisis dans une liste.

## 7. Domaines d'action (liste de référence)

Issue de l'article 3 du Règlement intérieur ; modifiable par le super administrateur.

Santé et Action sociale · Religion · Formation et Éducation · Technologie et Sciences naturelles ·
Économie et Finances · Construction et Industries · Promotion immobilière · Génie civil et BTP ·
Travail, Ressources humaines et Développement · Sécurité · Sport et Culture · Communication et Communauté ·
Transport et Automobile · Justice et Droits de l'homme · Affaires commerciales · Agriculture, Pêche et Élevage ·
Artisanat.

« Artisanat » ne figure pas dans l'article 3 ; il est ajouté à titre provisoire (D-014).

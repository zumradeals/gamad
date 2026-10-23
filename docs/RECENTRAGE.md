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

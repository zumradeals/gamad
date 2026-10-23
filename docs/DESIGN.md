# DESIGN — Réseau social d'action GAMAD

Version 1.0 — 22 octobre 2026. Les maquettes de référence, validées par le Président le 22 octobre 2026,
sont dans `docs/maquettes/` (fichiers HTML lisibles dans un éditeur de texte). Elles fixent l'apparence ;
leurs contenus d'exemple (Awa Traoré, Agri Bouaké, chiffres) sont fictifs et ne doivent jamais apparaître
en production. Elles sont écrites dans le format d'un outil de maquettage (balises `<x-dc>`, `<sc-for>`,
`<sc-if>`, styles en ligne) : reprends-en les couleurs, tailles, espacements et la structure visuelle, mais
reconstruis les écrans en Blade, Livewire et Tailwind ; ne copie pas ces balises.

| Maquette | Écran |
| --- | --- |
| `Main.dc.html` | Fil global, téléphone (écran de référence) |
| `Carrefour-ZUMRA.dc.html` | Carrefour ZUMRA, téléphone |
| `Monde-ZUMRA.dc.html` | Monde d'une ZUMRA, onglet Formation, téléphone |
| `Fil-ordinateur.dc.html` | Fil global, ordinateur (3 colonnes) |
| `Monde-Personne.dc.html` | Écran 5 — Monde d'une personne, téléphone (Lot 1, D-065) |
| `Modifier-profil.dc.html` | Écran 6 — Modifier mon profil, téléphone (Lot 1, D-065) |
| `Carrefour-Personnes.dc.html` | Écran 7 — Carrefour Personnes, avec le choix « peut l'apporter » / « veut l'apprendre », téléphone (Lot 1, D-065) |
| `Agir-choisir.dc.html` | Écran 8 — Agir : choisir le type de publication, téléphone (Lot 2, D-080) |
| `Agir-publier.dc.html` | Écran 9 — Publier un besoin, téléphone (Lot 2, D-080) |
| `Geste-proposer.dc.html` | Écran 10 — Je peux aider : proposer un geste, téléphone (Lot 2, D-080) |
| `Besoin-auteur.dc.html` | Écran 11 — Mon besoin : propositions reçues, téléphone (Lot 2, D-080) |
| `Conversation.dc.html` | Écran 12 — Conversation privée, téléphone (Lot 2, D-080) |
| `Besoin-resolu.dc.html` | Écran 13 — Besoin résolu et remerciement, téléphone (Lot 2, D-080) |
| `Espace-afaire.dc.html` | Écran 14 — Espace : À faire maintenant, téléphone (Lot 2, D-080) |
| `Notifications.dc.html` | Écran 15 — Notifications, téléphone (Lot 2, D-080) |
| `Signaler.dc.html` | Écran 16 — Signaler, téléphone (Lot 2, D-080) |

## 1. Jetons (à déclarer dans la configuration Tailwind)

| Jeton | Valeur | Usage |
| --- | --- | --- |
| `ink` | `#0E2A3F` | Texte principal, boutons principaux, en-têtes sombres |
| `muted` | `#4A5B68` | Texte secondaire, méta, icônes inactives |
| `ground` | `#F5F3EE` | Fond de l'application |
| `card` | `#FFFFFF` | Fond des cartes |
| `line` | `#E3E0D8` | Bordures de cartes et de barres |
| `line-soft` | `#EEEBE4` | Séparateurs internes |
| `chip-line` | `#D5D1C7` | Bordure des puces de filtre inactives |
| `accent` | `#F2C200` | Bouton Agir, action principale sur fond sombre, onglet actif (soulignement). Texte posé dessus : `ink` |
| `link` | `#0A5FA3` (survol `#08487A`) | Liens |
| `soft-blue` | `#EAF2FA` | Élément de navigation actif (ordinateur) |

Couleurs des types de publication (fond / texte) :

| Type | Fond | Texte |
| --- | --- | --- |
| J'ai besoin | `#FCE9D9` | `#8A3B00` |
| Je partage un savoir | `#DDF1E4` | `#1B5E33` |
| Je veux apprendre | `#DCEBF8` | `#0A4C85` |
| Avancée de projet | `#ECE6F8` | `#4B2F86` |
| Appel à rejoindre | `#FBF1C7` | `#6B5200` |
| Je propose mon aide | `#DFF0EF` | `#0F5A5A` |

Badge « Reconnue par GAMAD » : fond `#DDF1E4`, texte `#1B5E33`, icône coche.
État « Active · en attente de reconnaissance » : fond `#FBF1C7`, texte `#6B5200`.

## 2. Typographie

Police unique : **Plus Jakarta Sans** (graisses 400, 500, 600, 700, 800), auto-hébergée de préférence.

| Rôle | Taille / graisse |
| --- | --- |
| Logo « GAMAD » | 22 px / 800 |
| Titre de monde ou de carrefour | 24–30 px / 800 |
| Titre de section | 18 px / 800 |
| Nom d'auteur, titre de carte | 15–16 px / 700–800 |
| Corps | 15 px / 400, interligne 1,5 |
| Méta, libellés | 12–13 px / 600 |
| Badges | 12 px / 700 |

## 3. Formes et espacements

- Cartes : rayon 16 px, bordure 1 px `line`, marge intérieure 16 px (18 px sur ordinateur).
- Boutons : hauteur 44 px minimum, rayon 12 px. Principal : fond `ink`, texte blanc. Secondaire : bordure `ink`.
- Puces : rayon 999 px, hauteur 36 px.
- Avatar de personne : rond. Avatar de ZUMRA : carré arrondi (rayon 10–16 px), initiales blanches sur couleur.
- Gouttière téléphone : 16 px. Espace entre cartes : 12 px.
- Icônes : traits de 2 px, style « outline » (Heroicons outline ou équivalent libre), jamais d'émoji.

## 4. Composants

1. **Barre du haut (téléphone)** : logo + devise courte « Des personnes. Des actions. Un impact réel. »,
   boutons Rechercher et Notifications (44 px).
2. **Barre de navigation du bas (téléphone)** : Fil · Découvrir · Agir · ZUMRA · Espace. « Agir » est un rond
   jaune `accent` de 48 px, surélevé, au centre. Élément actif : texte `ink` en 700.
3. **Barre du haut (ordinateur)** : logo, Fil · Personnes · Besoins · Projets · ZUMRA, recherche, bouton Agir
   jaune, avatar qui ouvre Mon espace.
4. **Zone de publication** : avatar + champ « Que voulez-vous faire avancer ? » + raccourcis de type colorés.
5. **Carte de publication** : badge de type → auteur (avatar, nom, badge « Reconnue » le cas échéant, méta :
   « ZUMRA · Domaine · Ville · il y a 2 h ») → texte → lien de contexte (projet) → fait d'engagement
   éventuel → bouton(s) d'action pleine largeur → ligne Commenter · Partager · Signaler.
6. **Carte de carrefour ZUMRA** : avatar carré 52 px, nom, méta (domaine · ville · mode), badge de
   reconnaissance, mission, bandeau de trois chiffres réels (membres, projets, savoirs partagés), boutons
   Entrer / Je veux rejoindre.
7. **En-tête de monde** : fond `ink`, retour au carrefour, avatar, nom, méta, état, mission, action principale
   jaune + action secondaire. Bandeau de chiffres en carte blanche chevauchant l'en-tête.
8. **Onglets de monde** : barre blanche défilante, onglet actif souligné de 3 px `accent`. L'onglet
   Discussion porte un cadenas (réservé aux membres).
9. **Carte de projet** (Lot 4a, D-129, sans maquette) : badge d'état (Idée · En cours · Terminé · Abandonné, couleurs des
   jetons existants) → nom du projet → but → ligne ZUMRA (avatar carré 36 px, nom, domaine · ville) → bouton
   « Voir le projet ». Même gabarit que la carte de carrefour ZUMRA ; aucun chiffre inventé, aucun classement.
10. **Monde d'un projet** (Lot 4a) : même en-tête de monde que la ZUMRA (badge d'état, nom, lien vers la ZUMRA, but,
    « Modifier le projet » pour les gestionnaires), puis deux onglets (Lot 4b). **Avancement** : carte d'état avec
    « N étapes franchies sur M » et une barre qui en est le reflet exact (rien sans étape), carte « Étapes » (numéro, titre,
    badge À faire · En cours · Franchie ; pour les gestionnaires : liste d'état, Monter, Descendre, Retirer avec
    confirmation, champ « Nouvelle étape »), « Histoire du projet », « Changer l'état du projet ». **Fil** : cartes de
    publication des avancées et réalisations (badges violet et vert des jetons existants, lien « Projet : … »), liens
    « Publier une avancée » et « Publier une réalisation » pour les gestionnaires. Page publique. Quatre onglets en tout
    (Lot 4c) : **Équipe** (« N personnes participent », liste des personnes pour les inscrits, encadré « Participer à ce
    projet » avec le mot facultatif, états « Demande envoyée » / « Vous êtes dans l'équipe » ; pour les gestionnaires,
    « Demandes de participation » avec Accepter · Décliner, « Ajouter des membres de la ZUMRA », Retirer de l'équipe avec
    confirmation) et **Besoins** (cartes de besoin, lien « Publier un besoin » pour les gestionnaires). Sur une avancée, le
    bouton « Je soutiens » (ou « Vous soutenez cette avancée » + « Retirer mon soutien ») et le fait « N personnes soutiennent ».
11. **Accueil public** (Lot 5a, D-148) : gabarit visiteur élargi (1 100 px) sans carte autour du contenu ; accroche en grand,
    deux boutons côte à côte sur ordinateur ; quatre cartes « Comment cela marche » (une colonne sur téléphone, deux puis
    quatre sur ordinateur) ; rangée de chiffres réels et cartes de ZUMRA et de projets (masquées sans donnée) ; carte « Ce
    que GAMAD ne fait pas » ; pied de page des textes adoptés.
12. **Pages de texte** (Lot 5a, D-149) : charte et mentions légales en articles de lecture (largeur de 920 px), titres
    numérotés ; un bandeau « Projet … non adopté » visible du seul Président avant l'adoption ; la date d'adoption sous
    le titre ensuite.

## 5. Règles d'interface

- Une fois connecté, **aucune bannière publicitaire** : le contenu utile commence en haut de l'écran. Les
  grandes illustrations sont réservées à la page d'accueil publique.
- L'écran de référence est le téléphone (390 px). L'ordinateur réorganise en colonnes ; il n'ajoute pas de
  fonctions. Sur ordinateur (D-143), le contenu prend la largeur de 1 240 px du Fil, avec des marges de 24 px :
  les listes de cartes sur 2 puis 3 colonnes, les Mondes sur deux colonnes ou en grilles ; seuls les formulaires et
  écrans de saisie gardent une largeur de lecture de 920 px.
- Un module non construit est **masqué** dans la navigation, jamais simulé.
- Chaque liste a un état vide qui propose une action concrète (« Créer ma ZUMRA », « Compléter mon profil »).

## 6. Refonte du Fil validée le 23 octobre 2026 (D-151)

La nouvelle composition remplace la présentation des écrans 1 et 4 pour le Fil. Les règles métier et les états restent ceux des lots livrés.

- Grand écran, dès 1 280 px : trois colonnes dans le conteneur de 1 240 px, marges de 24 px, gouttières de 20 px. Profil de 228 px à gauche ; Fil central flexible ; exploration de 276 px à droite.
- De 1 024 à 1 279 px : profil et Fil. En dessous : Fil seul, barre de navigation basse et filtres défilants ; aucune colonne masquée ne contient une fonction accessible uniquement depuis le Fil.
- Profil : couverture décorative bleu profond, avatar réel ou initiales chevauchants, nom, ville et présentation si renseignées ; capacités réelles et lien vers le profil. Raccourcis vers Modifier mon profil, Mes ZUMRA et À faire maintenant.
- Centre : zone de publication à trois raccourcis de type, filtres sur fond blanc avec soulignement actif, ordre chronologique explicite. Cartes avec auteur, type, texte, faits d'engagement réels et actions compactes ; Signaler reste accessible. Le composant de publication conserve son ancien rendu hors du Fil.
- Droite : liens vers les trois Carrefours et invitation à Agir. Aucune recommandation, statistique ou donnée fictive.
- Barre d'application commune : icônes et libellés, onglet actif souligné, Notifications, Mon espace, Agir. Devise Formation — Travail — Adoration sous le logo. Le menu ordinateur apparaît à 1 024 px pour éviter le débordement sur tablette.
- Cartes du Fil : rayon 12 px, bordure `line`, ombre très discrète ; police et couleurs du référentiel conservées. Cibles tactiles de 44 px minimum.

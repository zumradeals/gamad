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
| Je propose mon aide | `#F5F3EE` (bordure `line`) | `#0E2A3F` |

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

## 5. Règles d'interface

- Une fois connecté, **aucune bannière publicitaire** : le contenu utile commence en haut de l'écran. Les
  grandes illustrations sont réservées à la page d'accueil publique.
- L'écran de référence est le téléphone (390 px). L'ordinateur réorganise en colonnes ; il n'ajoute pas de
  fonctions.
- Un module non construit est **masqué** dans la navigation, jamais simulé.
- Chaque liste a un état vide qui propose une action concrète (« Créer ma ZUMRA », « Compléter mon profil »).

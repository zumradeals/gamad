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
13. **Outils de modération** (Lot 5b, D-152 à D-154) : une barre commune « Publications · Personnes · Journal » en haut des trois
    écrans (puces de 44 px). *Personnes* : avis en cours (motif, explication ou délai, motif de suspension, boutons
    « Suspendre la personne » seulement quand la décision est possible), recherche par nom, personnes suspendues avec
    « Rétablir le compte ». *Journal* : liste de cartes d'une phrase, filtre par type, pagination Précédent/Suivant.
    *Avis de la modération* (côté personne) : motif, délai, champ d'explication. *Compte suspendu* : écran sobre du gabarit
    visiteur, motif, déconnexion.
14. **Copie hors serveur** (Lot 5c, D-156) : écran de Paramètres en cartes : carte d'état (configurée ou non, dernière copie
    réussie, dernier essai de restauration, alerte rouge au-delà de 36 h), formulaire des accès (champs de clés en mot de passe,
    bouton « Enregistrer et tester la connexion »), encadré de la phrase de secours (bordure épaisse, police à chasse fixe,
    « J'ai noté la phrase »), puis réaffichage de la phrase sur mot de passe.
15. **Carrefour Besoins et fenêtre Découvrir** (Lot 5.1, D-160, D-161) : Carrefour Besoins sur le gabarit des autres Carrefours (bandeau sombre, recherche, quatre menus : état, émetteur, domaine, ville) avec les cartes de publication en mode « parcourir » (un seul bouton « Voir le besoin »). « Découvrir » (téléphone) : fenêtre montante arrondie « Que cherchez-vous ? » (poignée, titre, croix de 48 px, trois cartes de 84 px : icône sur fond jaune, titre, description, chevron), fond assombri, focus gardé dans la fenêtre, Échap et croix ferment ; même contenu en page `/decouvrir`.

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

## 7. Mon Espace validé le 23 octobre 2026 (D-155)

- Conteneur de 1 240 px conservé. Dès 1 024 px, menu personnel de 228 px à gauche et contenu flexible à droite, gouttière de 24 px ; profil réel ou initiales sur une couverture décorative, ancres vers les sections et déconnexion.
- À droite : titre « Mon espace », accès « Voir mon profil », « À faire maintenant » avec lignes réelles ou état « Vous êtes à jour » ; cartes « Mon profil » et « Mes publications » côte à côte dès 1 280 px.
- Profil : capacités réelles Je peux apporter / Je veux apprendre, texte d’aide si vide, Modifier mon profil. Publications : liste des dix dernières avec états et engagements, ou invitation à la première action. Le bouton jaune est **Agir**, vers le parcours existant, conformément à la correction du Président.
- Mon compte : nom affiché et enregistrement, adresse e-mail du propriétaire seulement, indication de confidentialité. Modération et administration conservent leurs contrôles d’accès et les fonctions livrées au Lot 5b.
- Suppression du compte dans une section native repliée, avec l’avertissement définitif et la confirmation existante par mot de passe. Une erreur de mot de passe la laisse ouverte avec le message d’erreur.
- Mobile : une colonne, titre puis ancres défilantes, mêmes sections, déconnexion dans Mon compte ; navigation basse conservée. Ancres décalées sous la barre supérieure fixe, cibles de 44 px et états de chargement des formulaires conservés.
- Couleurs, couverture et cartes reprennent les styles du Fil validé. L’onglet Mon espace de la barre supérieure est identifié comme actif.

## 8. Accueil public validé le 23 octobre 2026 (D-159)

- Gabarit visiteur dédié, indépendant de la connexion et des pages légales : largeur 1 240 px, marges de 16 px sur téléphone et 24 px sur ordinateur. Navigation Accueil / Comment ça marche / Explorer, Se connecter et Créer un compte ; menu natif repliable sur mobile.
- Héros : libellé informatif **Le Réseau GAMAD**, accroche et présentation décidées conservées, inscription jaune et connexion secondaire. Photo illustrative de la maquette conservée, sans nouvelle génération, dans `public/images/gamad-accueil-hero.webp` ; légende des gestes d’action présentée comme illustration, sans bouton sans effet.
- Quatre étapes : Partagez vos capacités, Trouvez votre ZUMRA, Faites avancer un projet, Passez à l’action. Puis accès aux trois Carrefours existants.
- La section Ce qui se passe en ce moment conserve les chiffres réels et les trois ZUMRA et projets les plus récents ; aucun nom de la maquette n’est écrit dans l’application. Sans données, cette section disparaît ; les liens d’exploration restent disponibles.
- Engagements dans un bandeau bleu profond : pas de classement, vie privée, compte distinct de l’adhésion au Mouvement. Invitation finale à créer un compte, logo et devise en pied de page ; liens légaux seulement après adoption, selon la règle existante.
- Sur mobile : colonnes empilées, boutons tactiles et menu utilisable sans JavaScript. Le complément JavaScript ferme le menu après un choix. Aucun script Livewire n’est nécessaire à cet accueil statique ; les polices et la photo sont servies localement.

## 9. Carrefour ZUMRA éditorial (D-162)

- Héros clair : accroche « Ensemble, faites grandir vos idées. » et illustration locale, distincte des communautés réelles.
- Recherche par nom ou mission ; pays, ville dépendante, domaine, format, reconnaissance et remise à zéro. Les contrôles sont visibles et empilés sur téléphone, horizontaux sur ordinateur.
- Mes ZUMRA : bandeau compact avec rôle réel et lien de création.
- Cartes : deux colonnes à partir de 1024 px ; une en dessous. Couverture graphique avec initiales, domaine, nom, mission, localisation et pays si renseignés, format, membres réels, Découvrir et accès au parcours d’adhésion existant. Les photographies de la maquette sont illustratives et ne deviennent pas des photos de communautés.
- États vide, sans résultat, chargement et pagination conservés ; aucun classement ni fausse commande de tri.

## 10. Portefeuille ZAHAB (Lot 6a, D-174)

- Page étroite, comme Paramètres : lien de retour (Mon espace, ou la ZUMRA), titre « Mon portefeuille » ou « Portefeuille de la ZUMRA », mention de confidentialité avec cadenas.
- Carte « Solde » : montant en grands caractères suivi de ZAHAB, rappel « 1 ZAHAB = 1 FCFA » et de l'absence de transfert et de retrait. Aucun bouton d'action tant que la recharge n'existe pas.
- Carte « Mouvements » : nature, date à l'heure d'Abidjan, montant signé (+ ou −) et solde après le mouvement ; vingt par page ; état vide « Aucun mouvement pour l'instant. » ; indicateur de chargement à la pagination.
- Accès : carte « Mon portefeuille » dans Mon espace ; bouton « Portefeuille de la ZUMRA » à côté de « Modifier la ZUMRA » pour le fondateur et les responsables. Rien de tout cela n'apparaît tant que le Président n'a pas ouvert les portefeuilles.
- Réglages (Paramètres → Portefeuilles) : un menu Fermés / Ouverts et deux plafonds numériques, sur le gabarit des autres pages de réglages.

## 11. Recharge et paiements (Lot 6b, D-176, D-177)

- Page du portefeuille : bandeau jaune « Paiement en attente de confirmation » (mise à jour toute seule toutes les 10 secondes), confirmation ou échec au retour de la page de paiement, puis carte « Recharger » : en mode d'essai, mention « le paiement est simulé, aucun argent réel n'est débité » ; un seul champ, le montant, avec ses bornes ; erreurs sous le champ. La carte n'existe pas tant que les clés du mode courant manquent.
- Paramètres → Paiement : état des clés, adresse du webhook à copier, mode (essai ou réel, avec case de confirmation au passage au réel), clés (les secrets ne sont jamais réaffichés), bornes de recharge, bouton « Essayer la connexion ».
- Paiements (Président) : rapprochement (confirmées, créditées, remboursées, débitées, en attente, à examiner) avec un message d'accord ou d'écart, « Rapprocher maintenant », liste « À examiner » encadrée de rouge, « Terminer les essais » avec confirmation, liste filtrable des recharges sans nom.

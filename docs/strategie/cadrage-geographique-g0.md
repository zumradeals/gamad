# Cadrage géographique — lot G0 (27 octobre 2026)

> Statut : **projet de travail soumis au Président**, document de cadrage **sans aucun changement de code, migration, import ni modification de production**. Il prépare les lots G1 à G3 de `feuille-de-route-zumra-zahab-afrique-2026.md`, qui **ne sont pas lancés**. Rien ici n'est adopté tant que le Président n'a pas arbitré (§11).

## 1. Objectif
Permettre à une personne de n'importe quel pays africain retenu de s'inscrire et de renseigner sa localité, avec une hiérarchie administrative qui varie selon les pays, **sans inventer de donnée** et **sans perdre ni altérer les données ivoiriennes existantes**.

## 2. État réel du dépôt (vérifié le 27 octobre 2026)
- Tables `countries` (nom, normalisé, actif, ordre) et `cities` (pays, nom, **`area` en texte libre**, normalisé, actif). Contrainte d'unicité **(pays, nom normalisé)** ; le nom normalisé est celui du libellé complet (« Abidjan, Cocody »).
- Données chargées : **la Côte d'Ivoire seule** (jusqu'à 46 lieux d'origine : 31 chefs-lieux de région, 2 districts autonomes, 13 communes d'Abidjan), plus ce que le super administrateur a ajouté dans Paramètres › Territoires. Le chargeur n'agit que si les listes sont vides. Il précise que la liste **« est à faire contrôler par le Président avant l'ouverture »** : aucun contrôle n'est attesté dans le dépôt.
- `profiles`, `zumras`, `publications`, `opportunities`, `projects`, besoins, cibles de la Publicité et zones de matching référencent `cities` (et `profiles` aussi `countries`). Le matching compare la même ville ou les zones choisies ; **il n'y a ni quartier, ni coordonnées, ni distance calculée**.
- **Le pays n'est pas demandé à l'inscription** ; il se renseigne dans « Modifier mon profil » (liste déroulante pays puis ville).
- Les listes sont des listes déroulantes complètes : inadaptées à des milliers de lieux.

## 3. Périmètre : de quels pays parle-t-on ?
« 54 pays africains » correspond au nombre d'**États membres de l'ONU situés en Afrique**. L'Union africaine compte **55 membres**, car elle inclut la République arabe sahraouie démocratique, que l'ONU ne reconnaît pas comme État (source : [Africa Check](https://africacheck.org/reports/how-many-countries-in-africa-how-hard-can-the-question-be/)). Certains territoires non souverains rattachés à des États non africains existent aussi en Afrique ou à proximité.
**Recommandation :** liste officielle de GAMAD = les 54 États membres de l'ONU en Afrique ; tout autre territoire est une entrée **désactivée par défaut**, activable sur décision du Président. La liste signée par le Président est le critère de validation (§12). ⚖️

## 4. Sources géographiques comparées
> **Réserve de méthode.** Les licences et couvertures ci-dessous viennent de recherches web du 27 octobre 2026 ; elles n'ont **pas** été relues sur les pages officielles de chaque source. Chaque ligne indique son état de vérification. **Avant G1, chaque licence doit être reconfirmée sur sa page officielle**, datée, et conservée dans le dépôt.

| Source | Licence et conditions | Couverture et détail | Mise à jour | Limites | Verdict |
|---|---|---|---|---|---|
| **GeoNames** (gazetier) | Creative Commons Attribution : attribution à GeoNames exigée. La [page officielle](https://geonames.org/about.html) mentionne une licence CC BY ; la version (3.0 ou 4.0) est **contradictoire selon les sources — à vérifier** | Lieux habités et unités administratives de niveaux 1 à 2 au moins, noms alternatifs multilingues, population, coordonnées ; fichiers par seuil de population | **Export quotidien** (page officielle) | Données collaboratives, qualité inégale selon les pays ; niveaux administratifs 3 et plus rares ; quartiers peu couverts ; homonymes à désambiguïser | **Candidate principale** pour pays, régions et villes, avec contrôle par échantillon |
| **geoBoundaries** | **CC BY 4.0** pour la version 6.0.0 d'après le [catalogue Earth Engine](https://developers.google.cn/earth-engine/datasets/catalog/WM_geoLab_geoBoundaries_600_ADM0?hl=en) ; chaque pays porte ses propres métadonnées de source, à contrôler | Limites ADM0 à ADM2 (parfois plus) pour 199 entités ; **géométries**, pas des listes de lieux | Version 6.0.0 vue datée de septembre 2023 ; **version actuelle non vérifiée** | Pas de villes ni de quartiers ; limites contestées arbitrées selon une convention | Utile pour la **hiérarchie et les noms de niveaux 1 et 2**, si besoin de géométries ; sinon non nécessaire |
| **OpenStreetMap** | **ODbL** (base de données dérivée : attribution, partage à l'identique, déclaration des modifications) — licence connue, **à reconfirmer** ; règles d'usage de Nominatim restrictives (page officielle de l'OSM Foundation) | Quartiers (`place=suburb`, `neighbourhood`), villages, limites administratives variables | Continue | **Complétude très variable en Afrique** (non mesurée ici) ; erreurs de balisage possibles ; **ODbL impose des obligations** sur une base dérivée ; l'API publique Nominatim n'est pas destinée à un usage intensif | Source **complémentaire** pour quartiers, à n'utiliser qu'après étude juridique ODbL ; pas d'appel en direct à l'API publique |
| **Overture Maps — Divisions** | **ODbL** pour le thème des divisions d'après les [notes de version](https://overturemaps.org/overture-january-2024-release-notes/) (sources : OSM, Esri ; geoBoundaries pour le niveau 2 là où l'OSM manque) | Pays jusqu'aux quartiers, harmonisés | Versions régulières (cadence **à vérifier**) | Niveau 2 « pas encore complet dans le monde » d'après ces notes ; documentation de dates différentes, parfois contradictoires | Alternative à surveiller, mêmes réserves ODbL que l'OSM |
| **Natural Earth** | **Domaine public** (d'après les catalogues universitaires) | Pays ; unités de niveau 1 selon le fichier ; **couverture de niveau 1 incomplète selon l'échelle** (à vérifier sur le fichier le plus détaillé) ; ses attributs dépendent partiellement de GADM | Versions occasionnelles | Cartographie générale, pas de villes détaillées ni de quartiers ; lignes de frontière « de fait » | Sert à **contrôler** la liste des pays, pas à les peupler |
| **GADM** | Gratuit pour usage académique et **non commercial** ; **redistribution et usage commercial sans accord exclus** ([résumé](https://en.wikipedia.org/wiki/GADM), à reconfirmer sur gadm.org) | Niveaux 0 à 5 très détaillés | Versions occasionnelles | **Incompatible avec un produit GAMAD** sans permission écrite | **Écartée** |
| **OCHA COD-AB / HDX** | Licence des jeux **non confirmée** par la recherche (à lire sur HDX) | Limites administratives de référence pour de nombreux pays africains, avec codes officiels (P-codes) ; niveaux jusqu'à 3 ou 4 selon le pays | Variable selon le pays ; certains changements non intégrés | Couverture inégale ; destiné à l'humanitaire | Source **de contrôle** pour la hiérarchie officielle de certains pays |
| **Wikidata** | Données **CC0** (domaine public) — licence connue, **à reconfirmer** | Entités administratives et localités avec identifiants croisés | Continue | Complétude et qualité variables ; interrogations à cadrer ; ne remplace pas une liste officielle | Utile pour **noms multilingues et rapprochements** (identifiants) |
| **Unicode CLDR** | Licence Unicode (**non relue** ici) | **Noms de pays localisés** dans de nombreuses langues (utile pour FR, EN, AR, PT) | Versions régulières | Pas de villes ni de régions | **Candidate** pour les noms de pays localisés (lot i18n) |
| **ISO 3166** (codes pays, subdivisions) | Les droits sont conservés par l'ISO ; la politique de réutilisation de la liste est **peu claire** d'après les sources trouvées | Codes des pays et subdivisions | Mises à jour périodiques | Réutilisation à clarifier ; **ne pas copier la liste ISO elle-même** sans vérification | Passer par les codes portés par GeoNames, Wikidata ou CLDR, après avis |
| **Sources officielles nationales** (instituts de statistique, décrets) | Variables ; textes légaux souvent réutilisables | Référence légale (ex. décret ivoirien de 2011 pour les régions) | Selon les réformes | Accès difficile, formats hétérogènes, pas toujours en ligne | **Référence d'autorité** pour contrôler un pays, jamais importées en masse |

**Combinaison recommandée (sous réserve de vérification des licences) :** GeoNames comme base unique d'import (CC BY, simple à respecter) ; contrôle croisé par échantillon avec les limites officielles (COD-AB, sources nationales) ; Wikidata pour les identifiants et noms multilingues ; CLDR pour les noms de pays localisés ; **OSM/Overture reportés** tant que l'étude juridique ODbL n'est pas faite ; **GADM exclue**.
**Principe :** chaque ligne importée porte sa **source, son identifiant de source, la date d'import et la version** ; aucun nom n'est inventé ni « complété » à la main sans trace.

## 5. Modèle hiérarchique recommandé

### 5.1 Contraintes
Les pays n'ont ni les mêmes niveaux ni les mêmes noms de niveaux (ex. région, département, commune ; État, zone de gouvernement local ; comté ; province, district). Les homonymes sont fréquents (une même localité dans plusieurs régions). Tout ce qui existe (profils, ZUMRA, publications, opportunités, projets, besoins, Publicité, matching) référence `cities`. La Côte d'Ivoire ne doit pas être touchée.

### 5.2 Option recommandée : extension **additive** du référentiel existant (A)
Conserver `countries` et `cities` comme **référentiel des lieux sélectionnables** ; ajouter, sans rien supprimer :
- **`admin_units`** — unités administratives au-dessus des villes : `id`, `country_id`, `parent_id` (nullable), `level` (1, 2, 3…), `level_key` (clé du type : région, province, État, comté, district…), `name`, `normalized`, `source`, `source_id`, `active`. Un nom de niveau propre à chaque pays est décrit dans `country_admin_levels` (`country_id`, `level`, `level_key`, étiquettes FR/EN/AR/PT).
- **Colonnes ajoutées à `cities`** (toutes nullables) : `admin_unit_id`, `kind` (ville, commune, localité, quartier), `parent_city_id` (un quartier sous sa ville), `status` (`reference` | `proposed` | `unverified`), `source`, `source_id`, `latitude`/`longitude` **de centre de ville seulement** (jamais d'adresse), `population` (facultatif), `merged_into_id`.
- **`place_names`** — noms par langue (`place_type`, `place_id`, `locale`, `name`) pour FR, EN, AR, PT et noms locaux ; repli sur le nom de source.
- **`place_suggestions`** — localités proposées par des personnes (voir §7).
- **Unicité** : la clé actuelle (pays, nom normalisé) **bloque les homonymes** d'un même pays ; elle serait remplacée en G1 par une clé tenant compte de l'unité parente, avec un libellé désambiguïsé à l'écran (« Nom, Région »). Point de vigilance de G1.

**Pourquoi (A) :** aucune clé étrangère existante ne change ; migration purement additive ; retour arrière simple ; compatible avec la saisie assistée. **Alternatives écartées :** (B) refonte en une table unique `places` à arbre général : plus élégante mais impose de migrer toutes les références ; (C) une table par niveau : trop rigide, ne convient pas à des hiérarchies différentes selon les pays.

### 5.3 Hiérarchie affichée à la personne
Pays → (région ou équivalent, facultatif) → ville ou commune → quartier ou localité (facultatif). La personne choisit jusqu'où elle veut descendre. Le matching et les filtres existants continuent de s'appuyer sur la **ville** ; le quartier n'ajoute qu'une précision d'affichage tant qu'une décision séparée ne dit pas le contraire.

### 5.4 Interface
Une liste de milliers de lieux exige une **recherche par saisie** (composant de choix avec recherche, accessible, cibles de 44 px, utilisable à 390 px de large) et non une liste déroulante. À budgéter dans G2 (poids du CSS et du JavaScript, plafond de 14 Kio de CSS compressé).

## 6. Préserver les données ivoiriennes
- Les lignes existantes de `countries` et `cities` **gardent leurs identifiants et leurs valeurs** ; aucune suppression, aucun renommage, aucun doublon créé.
- `area` (« Abidjan ») est **conservé tel quel** ; son rattachement futur à `parent_city_id` se fait par une correspondance **contrôlée et réversible**, jamais par déduction silencieuse.
- Les unités administratives ivoiriennes (régions) sont ajoutées **à côté**, à partir du décret déjà cité par le chargeur, et leur rattachement aux chefs-lieux existants est une table de correspondance relue par le Président.
- Tout import est **idempotent**, précédé d'une simulation (nombre de lignes créées, ignorées, en conflit, doublons) et d'une **sauvegarde** ; un import en production exige l'autorisation du Président.
- Test d'acceptation : avant/après, mêmes identifiants, mêmes libellés, mêmes références dans profils, ZUMRA, publications, opportunités.

## 7. Quartiers et localités absents : jamais inventés
1. **L'inscription n'est jamais bloquée** par un lieu manquant.
2. Si la localité est absente, la personne choisit son **lieu connu le plus proche** (région ou ville) puis écrit **« Ma localité n'est pas dans la liste »** et saisit son nom en texte libre (longueur limitée, normalisé, sans numéro de téléphone ni adresse précise).
3. Ce texte est conservé sur le profil, **affiché tel que saisi sous son lieu parent**, marqué comme **non vérifié**, et crée une **suggestion** (`place_suggestions` : pays, lieu parent, nom, auteur, date, état).
4. **Aucune donnée n'est créée dans le référentiel à partir d'une saisie libre automatiquement.** Une suggestion devient une entrée de référence seulement après relecture (modération ou super administrateur) ou après plusieurs saisies indépendantes concordantes, avec détection des doublons (nom normalisé et lieu parent) et fusion des variantes d'orthographe (`merged_into_id`).
5. Pas de coordonnées déduites ; pas d'adresse ; le matching n'utilise que le lieu de référence parent tant que la suggestion n'est pas acceptée.
6. Les changements de nom et les fusions sont tracés (journal d'administration) et réversibles.

## 8. Pays à l'inscription ou dans le profil ?

| | **(1) Obligatoire à l'inscription** | **(2) Seulement dans le profil (situation actuelle)** | **(3) Facultatif à l'inscription — recommandé** |
|---|---|---|---|
| Friction | + un champ de plus, risque d'abandon sur mobile | aucune | faible : un seul champ avec recherche, « Passer » possible |
| Qualité du matching et du Fil local | **meilleure** (pays connu dès le premier jour) | **faible** : beaucoup de profils sans lieu | bonne si le champ est incité |
| Langue, formats, disponibilité des services par pays | utilisable immédiatement | impossible tant que non renseigné | utilisable pour ceux qui répondent |
| Données personnelles | collecte systématique de la localisation (minimisation à justifier) | collecte volontaire | collecte volontaire, expliquée |
| Risque d'erreur ou de fraude (mauvais pays, VPN) | fort : blocages et réclamations | nul à l'inscription | modéré |
| Impact technique | formulaire d'inscription, règles de validation, tests ; un pays manquant bloquerait | aucun | formulaire d'inscription (champ facultatif), tests |
| Cohérence avec « aucune restriction par pays » | ✓ si le champ n'exclut personne, mais perçu comme un filtre | ✓ | ✓ |

**Recommandation (3), sous réserve de l'arbitrage :** demander le pays à l'inscription **sans le rendre obligatoire**, expliquer son usage (langue, lieux proches, propositions pertinentes), le rendre **indispensable seulement** pour les fonctions qui en dépendent (matching local, publication d'une opportunité, filtres par lieu). Aucune géolocalisation par adresse IP. ⚖️

## 9. Premier périmètre réaliste, évolutif
1. **Pays** : la liste signée par le Président (§3), noms localisés plus tard (CLDR).
2. **Unités de niveau 1** (régions, provinces, États…) pour chaque pays, depuis la base d'import retenue, avec contrôle croisé.
3. **Villes et localités principales** : capitale et lieux au-dessus d'un seuil de population **à mesurer** lors de la simulation du lot G1 (la volumétrie n'est pas connue à ce jour ; un seuil de 15 000 habitants est une hypothèse de départ, non une décision).
4. **Côte d'Ivoire inchangée**, puis enrichie après contrôle.
5. **Quartiers** : uniquement ceux déjà existants (communes d'Abidjan) ; les autres naissent des suggestions relues (§7) et, plus tard, d'une source quartiers validée juridiquement (OSM/Overture).
6. **Déploiement progressif par pays** : un pays n'est ouvert à la sélection qu'après un contrôle d'échantillon (§12) ; les autres restent désactivés mais préparés. Aucun pays n'est favorisé : le choix de l'ordre suit l'observation des usages et non une prédiction.

## 10. Risques
| Risque | Parade |
|---|---|
| Licences mal comprises (CC BY, ODbL, GADM) | Relecture des pages officielles datée dans le dépôt ; avis juridique pour l'ODbL ; attribution affichée |
| Qualité inégale (noms erronés, homonymes, doublons, orthographes multiples) | Provenance par ligne, simulation, contrôle par échantillon par pays, fusion tracée |
| Noms non latins (arabe, amazigh) et normalisation | Normalisation sensible à la langue dans G1 ; tests dédiés |
| Désaccords politiques sur des territoires | Liste de pays arbitrée par le Président ; libellés neutres ; entrées contestées désactivées |
| Alourdissement de l'interface | Recherche par saisie, chargement à la demande, mesure du poids avant G2 |
| Régression sur la Côte d'Ivoire | Migration additive ; test avant/après |
| Confusion « pays disponible » et « services financiers disponibles » | Séparer les réglages (géographie ≠ paiement) |
| Données personnelles de localisation | Pays facultatif, visibilité du profil respectée, aucune adresse ni coordonnée de personne |
| Import massif en production | Autorisation du Président, sauvegarde, idempotence, retour arrière |

## 11. Décisions requises du Président (⚖️)
1. **Liste des pays** : 54 États membres de l'ONU ; traitement du territoire sahraoui et des autres territoires.
2. **Sources** : autorisation de principe de la combinaison §4, sous réserve de la relecture des licences ; **exclusion de GADM** ; report de l'OSM/Overture.
3. **Modèle** : option (A) additive du §5.2.
4. **Pays à l'inscription** : option (3), ou (1), ou (2).
5. **Seuil de villes du premier périmètre** (après simulation) et **ordre d'ouverture des pays**.
6. **Contrôle de la liste ivoirienne** : désigner qui la valide.
7. **Suggestions de localités** : qui relit (modération ou super administrateur) et quelle règle de promotion.
8. **Contrôle par pays** : qui valide l'échantillon avant ouverture d'un pays (contacts locaux).

## 12. Critères de validation
**G0 (ce lot) est validé quand :** les décisions 1 à 4 sont consignées dans `docs/DECISIONS.md` ; la liste des pays est signée ; les licences retenues sont **relues sur leurs pages officielles** et archivées avec leur date ; le modèle est approuvé.
**G1 sera validé quand :** migration additive sans suppression ; mêmes identifiants et libellés ivoiriens avant et après ; simulation d'import reproductible (comptes, doublons, conflits) ; clé d'unicité corrigée pour les homonymes ; tests de chaque règle ; CI verte ; aucune écriture en production.
**G2 sera validé quand :** une personne de n'importe quel pays retenu s'inscrit et renseigne son lieu, y compris une localité absente de la liste ; aucun blocage ; utilisable à 390 px ; cibles de 44 px ; plafond de CSS respecté ; recette ordinateur et mobile.
**G3 sera validé, pays par pays, quand :** contrôle d'échantillon signé ; provenance présente sur chaque ligne ; import idempotent ; sauvegarde préalable ; autorisation du Président.

## 13. Ce que ce lot ne fait pas
Aucun code, aucune migration, aucun import, aucune modification de production, aucun paiement, aucune ouverture, aucune traduction. G1, G2 et G3 attendent l'arbitrage du Président.

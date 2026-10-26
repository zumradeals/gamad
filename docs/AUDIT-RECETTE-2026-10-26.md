# Audit transversal et recette de GAMAD — 26 octobre 2026

> **Nature du document.** Rapport d'audit et de recette, rédigé par Claude Code à la demande du Président. Ce n'est ni une décision
> (`docs/DECISIONS.md`) ni un lot (`docs/LOTS.md`) : **aucune correction n'a été faite**. Les recommandations restent des
> propositions de travail jusqu'à validation du Président.
>
> **Version auditée :** `main` au commit `92dacf4` (PR #69, module Opportunités). Branche du rapport : `audit-recette-gamad`.

---

## 0. Périmètre, méthode et limites

**Ce qui a été fait**

| Contrôle | Résultat |
|---|---|
| Suite complète Pest/Laravel | 1 621 tests, **1 619 réussis, 2 ignorés, 0 échec** (251 s) |
| Laravel Pint (`--test`) | Réussi |
| Build Vite | Réussi |
| Migrations PostgreSQL | 38 migrations, `migrate:fresh --seed` répété sans erreur, `rollback` + `migrate` de la dernière réversible, 0 en attente |
| Budget CSS (test D-157) | **12 191 octets compressés / 12 288** sur une construction propre (+ environ 40 octets observés en CI) |
| Suite complète en **mode « parcours initial »** (celui de la production) | 473 échecs, **tous** dans des tests de modules gelés ou de la navigation historique (voir A5) |
| Recette bout en bout (navigateur réel, 39 étapes × 2 tailles) | Ordinateur : 38 réussies, 1 échec confirmé ; téléphone 390 px : 38 réussies, 1 échec confirmé (le même, A2) |
| Balayage visuel (51 pages × ordinateur, 390 px, 360 px = 153 chargements) | Aucun débordement horizontal, aucune image cassée, aucune erreur JavaScript hors A3 |
| Mesure des requêtes SQL (8 pages, 4 puis 24 lignes de données) | Aucune croissance : pas de N+1 détecté |
| Revue de code ciblée | Rapprochement, autorisations, fichiers privés, notifications, recherche, doublons |

**Comment la recette a été conduite.** Sur une base PostgreSQL **isolée** (`gamad_recette`), créée pour l'occasion, avec 8 comptes
fictifs (`*@recette.test`), le serveur applicatif lancé en configuration de production du parcours (`gamad.initial_experience = true`),
et des scripts Playwright (versionnés en annexe). La base de production, ses comptes, ses réglages et les autres applications du
VPS n'ont **pas** été touchés. Aucune PR n'est fusionnée.

**Limites — vérifications impossibles ou non faites (déclarées honnêtement)**

1. **Le site déployé n'a pas été testé** : aucun accès au serveur ni à des comptes de test déployés. Seule information de production
   utilisée : le Président indique que `php artisan migrate:status` y est complet. Le comportement réel en production (performances sous
   charge, stockage, e-mail, planificateur) reste à vérifier sur place.
2. **Aucune mesure de charge** : les volumes testés sont de quelques dizaines de lignes. Les risques de performance ci-dessous sont des
   analyses de code, pas des mesures sous trafic.
3. **Paiements GeniusPay** et sauvegarde hors serveur : non exercés (modules financiers gelés, accès réels absents).
4. **Accessibilité** : contrôles automatisés (cibles tactiles, débordement) seulement ; pas de test avec lecteur d'écran.
5. Le test automatisé de budget CSS est **ignoré en local** quand le dossier `public/build` est présent (construction de développement
   plus volumineuse) ; la mesure ci-dessus vient d'une construction propre d'un arbre `git` vierge.

---

## A. État général de GAMAD

**Verdict : GAMAD est opérationnel et sain sur son périmètre actif. Aucune anomalie critique (P0).** Une anomalie importante (P1)
concerne la confidentialité des pièces jointes de candidature à la suppression d'un compte ; plusieurs défauts moyens (P2) touchent la
traçabilité visible, la cohérence du blocage et de la recherche, et la solidité du processus de test.

### Modules opérationnels (parcours complets réussis en navigateur réel)

| Module | État | Remarques |
|---|---|---|
| Accueil / Fil | Opérationnel | Filtres, publication, besoins, notifications, accès sans compte : OK |
| Personnes | Opérationnel | Recherche par capacité, profil public, demande de relation, acceptation, conversation : OK |
| Entreprises | Opérationnel | Annuaire (seules les entreprises vérifiées), page publique, « Nos opportunités », suspension : OK |
| Opportunités | Opérationnel | Catalogue, recherche sans accents, publication en trois étapes, candidature privée + PDF, suivi, contact, clôture : OK |
| Agir et entraide | Opérationnel | Menu direct, besoin en 3 étapes, réponse, acceptation, conversation, action confirmée, résolution : OK |
| Mon espace | Opérationnel | Candidatures et sauvegardes visibles, notifications ouvrant le bon contenu |
| Conversations, relations, notifications | Opérationnel | 100 % des types de notification s'ouvrent sans erreur (test de couverture de l'énumération) |
| Modération et administration | Opérationnel, **lecture du journal incomplète** | A2 |

### Partiellement opérationnel ou incomplet

- **Journal d'administration** : écrit correctement en base, mais ne **montre pas** les actes sur les entreprises ni sur les
  commentaires (A2).
- **Brouillon local d'Agir** : n'est pas effacé après une publication réussie (A3).
- **Blocage** : appliqué au Fil et aux relations, pas aux pages entreprise ni aux opportunités (A4).
- **Navigation mobile** : Personnes, Entreprises et Opportunités ne sont atteignables que par des raccourcis épars ; amélioration
  validée AM-1 (hamburger) à réaliser.

### Gelés, conservés et conformes à `RECENTRAGE.md`

ZUMRA, Projets, contributions et portefeuilles : `/zumra`, `/projets`, `/decouvrir` redirigent vers Personnes et `/besoins` vers le
Fil filtré (vérifié sur le serveur de recette) ; les URL de détail inexistantes répondent 404 ; le code et les données historiques sont
intacts. Les 473 tests qui échouent en mode initial sont **exclusivement** ceux de ces modules gelés ou de la navigation historique.

---

## B. Résultats de la recette

### B.1 Parcours bout en bout (navigateur réel, ordinateur 1 440 px **et** téléphone 390 px)

| # | Parcours | Étapes clés | Résultat |
|---|---|---|---|
| 1 | Accueil → Personnes → Profil → Demande de relation → Conversation | recherche « plomberie », demande avec mot, notification, acceptation, « Écrire », message reçu et lu | ✅ réussi |
| 2 | Accueil → Agir → Publication → Réponse → Acceptation → Action confirmée | besoin en 3 étapes, « Je peux aider », acceptation, conversation, action proposée puis confirmée, besoin résolu | ✅ réussi |
| 3 | Entreprises → Page entreprise → Services → Opportunités | annuaire sans entreprise non vérifiée, page, « Nos opportunités » | ✅ réussi |
| 4 | Entreprise vérifiée → Publication d'une opportunité → Gestion des candidatures | 3 étapes, aperçu, publication, réponses, PDF privé, « Marquer comme vue » | ✅ réussi |
| 5 | Personne → Recherche → Candidature → Suivi → Contact | « ingenieur electricien » trouve « Ingénieur électricien », candidature + PDF, doublon bloqué, état suivi, « Proposer un échange », notification | ✅ réussi |
| 6 | Notification → Contenu concerné | chaque notification de la recette ouvre le bon écran ; test automatique sur **tous** les types | ✅ réussi |
| 7 | Modification ou clôture d'un contenu → effets | clôture : disparaît du catalogue, de l'annuaire et de la page entreprise, « Clôturée » sur le détail ; renommage d'entreprise : recherche mise à jour | ✅ réussi |
| 8 | Suspension ou blocage → restrictions | suspension : page, offres, catalogue, « Mes opportunités » indisponibles, gestion refusée au propriétaire (403) ; blocage : profil inaccessible aux deux sens (404) | ✅ réussi, **avec les réserves A2 et A4** |

Accès interdits vérifiés : un tiers n'accède ni aux réponses d'une opportunité (403), ni à leur PDF (404) ; une personne physique
ne publie pas d'opportunité (403) ; une entreprise en attente ne peut pas publier ; une entreprise suspendue ne peut rien faire.
États vides vérifiés (catalogue, Mes opportunités, journal, notifications).

### B.2 Échecs et étapes de reproduction

| Étape | Échec | Reproduction |
|---|---|---|
| F8.6 (bureau et mobile) | Le journal n'affiche pas la suspension d'entreprise | Voir A2 |
| (hors parcours) | Brouillon local non effacé après publication | Voir A3 |

Les autres échecs rencontrés pendant la recette étaient des erreurs de mon propre script (sélecteur ambigu : le texte « Écrire » existe
aussi dans le menu Agir ; parcours téléphone en 2 étapes au lieu de 3). Elles ont été corrigées dans le script et ne sont **pas** des
défauts de l'application. Un échec de suppression d'entreprise relevé par un test automatique s'est révélé être un **artefact de la
transaction de test** (voir §C, « Non confirmé »).

### B.3 Recette visuelle

Pages chargées à 1 440, 390 et 360 px : 153 chargements sans débordement horizontal, sans image cassée, sans erreur de console (hors
A3). Seules anomalies : cibles tactiles inférieures à 44 px sur un lien d'invitation à se connecter et un lien d'état vide (A9), et les
pages `/charte` et `/mentions-legales` qui répondent **404** parce que les textes ne sont pas encore adoptés (D-146, comportement voulu).
Captures représentatives : `captures/audit/` (`pc-*`, `m390-*`, `m360-*`).

Comparaison aux maquettes du dépôt : les pages refondues pendant les vagues précédentes (Personnes, Entreprises, Fil, Mon espace,
profil public, page entreprise, Agir, Opportunités) restent cohérentes avec leur maquette ; l'écart connu de la maquette Opportunités
(parcours téléphone en 2 étapes) a été reproduit lors de la PR #69.

---

## C. Anomalies

Légende : **P0** critique (sécurité, perte de données) · **P1** importante (parcours bloqué ou donnée personnelle conservée à tort) ·
**P2** moyenne (fonctionnement dégradé) · **P3** mineure. Aucune P0 n'a été trouvée.

### P1

#### A1 — Les CV (PDF) joints aux candidatures survivent à la suppression des comptes
- **Constat confirmé** (test automatisé reproduisant le cas + fichiers orphelins observés sur disque après suppression réelle d'un compte) :
  quand une personne supprime son compte, ses réponses sont supprimées en base par cascade, **mais le PDF reste dans
  `storage/app/private/opportunity-responses/`**. Même effet quand une entreprise ou son propriétaire est supprimé : les opportunités et
  leurs réponses disparaissent de la base, pas les PDF des candidats.
- **Impact.** Données personnelles (CV) conservées après effacement du compte : contraire à l'esprit de D-097/D-184 (« ses données ne
  survivent pas au départ ») et au droit à l'effacement. Fichiers non exposés publiquement (accès fermé), mais jamais purgés.
- **Cause.** La suppression des fichiers se fait dans un événement d'objet (`Opportunity::deleting`) ; les cascades de clés étrangères
  de PostgreSQL ne le déclenchent pas. `User::deleting` (`app/Models/User.php:47`) nettoie photo, entreprises et médias de publication,
  mais pas les réponses du candidat ; `Company::deleting` ne parcourt pas les opportunités.
- **Fichiers :** `app/Models/User.php`, `app/Models/Company.php`, `app/Models/OpportunityResponse.php`, `app/Services/Opportunities.php`.
- **Correction recommandée.** Supprimer explicitement les pièces dans `User::deleting` (réponses de l'utilisateur) et dans
  `Company::deleting` (réponses de ses opportunités) ; commande d'entretien listant et purgeant les fichiers sans ligne en base ;
  corriger le test existant dont le titre promet « avec le compte du candidat » mais ne vérifie que la suppression de l'opportunité.

### P2

#### A2 — Le journal d'administration ne montre pas les actes sur les entreprises ni sur les commentaires
- **Constat confirmé en navigateur.** Après « Suspendre avec motif », `admin_actions` contient bien `companies.suspend` avec l'acteur,
  mais la page « Journal d'administration » (filtre « Tous ») affiche « Aucun acte à afficher ».
- **Cause.** `JournalReader::FAMILIES` (`app/Support/JournalReader.php:20`) ne connaît pas les préfixes `companies.` (validation,
  refus, suspension, signalements traités) ni `comment.` (masquer, rétablir, signalements classés) : écrits mais **illisibles**.
- **Impact.** Traçabilité exigée par `CLAUDE.md` (règle 8) et D-145 : les actes les plus sensibles de la vérification d'entreprises ne
  sont pas consultables dans l'application.
- **Correction.** Ajouter les familles « Entreprises » (super administrateur) et « Commentaires » (modération), leurs libellés, et un test
  par préfixe écrit (un test « toute action écrite appartient à une famille » éviterait la récidive).

#### A3 — Le brouillon local d'Agir n'est pas effacé après publication
- **Constat confirmé.** Après une publication réussie, `localStorage` contient toujours le brouillon et, en rouvrant le formulaire, le
  bandeau « Vous aviez commencé un texte. Le reprendre ? » propose le texte déjà publié. Erreur console :
  `SyntaxError: Unexpected token 'try'`.
- **Cause.** `resources/views/livewire/page-publication.blade.php:18` : `x-init="try { … } catch (e) {}"`. Alpine évalue une expression,
  pas une instruction : `try` n'est pas accepté. Le défaut existait avant la PR #68 (contrat D-192 : « suppression après publication
  réussie »).
- **Correction.** Enrober l'instruction (`(() => { try { … } catch (e) {} })()`) ou appeler une fonction `window.gamadDraftClear(clé)`
  définie dans `resources/js/app.js` ; ajouter un test de navigateur (ou au minimum une vérification du balisage).

#### A4 — Le blocage n'est pas appliqué de façon uniforme
- **Constat confirmé.** Awa bloque le propriétaire d'une entreprise : le Fil masque ses publications, mais la **page entreprise,
  l'annuaire, le catalogue et le détail de l'opportunité** restent visibles pour Awa. Dans l'autre sens, après blocage par le
  propriétaire, la réponse déjà reçue d'un candidat bloqué reste affichée au propriétaire. Awa n'a plus de bouton « Postuler » mais
  aucune explication.
- **Fichiers :** `app/Models/Opportunity.php` (`scopeOpen`, `visibleTo`), `app/Livewire/CompanyDirectory.php`,
  `app/Http/Controllers/CompanyController.php`, `OpportunityController::show`, `app/Policies/OpportunityPolicy.php`.
- **Décision requise du Président** : une entreprise est-elle une présence publique que l'on ne peut pas « bloquer », ou le blocage
  d'un propriétaire doit-il masquer l'entreprise et ses offres à la personne qui bloque ? Selon la réponse, appliquer
  `People::excludedIds` à ces requêtes ou documenter l'exception.

#### A5 — La suite de tests s'exécute par défaut dans le mode historique, pas dans celui de la production
- **Constat.** `tests/Pest.php:37` impose `gamad.initial_experience = false` à tous les tests ; seuls 12 fichiers choisissent le mode
  initial. Quand on inverse (expérience menée), 473 tests échouent — **tous** dans les modules gelés (Portefeuilles, Projets, ZUMRA,
  Carrefour Besoins), la navigation historique, la mise en page du mode historique ou des fixtures créant des publications collectives.
  Aucune régression cachée n'a été trouvée sur les modules actifs, mais les tests des modules actifs vérifient parfois le menu de
  l'ancien mode (par exemple les tests de navigation).
- **Risque.** Une régression propre au mode de production pourrait passer l'intégration continue.
- **Correction.** Inverser le défaut (mode initial), faire choisir le mode historique aux tests des modules gelés (assistant dédié) et
  conserver un petit jeu de tests de non-régression de l'ancien mode.

#### A6 — Le budget CSS est quasiment épuisé
- **Constat.** Le test D-157 impose 12 ko compressés. Mesures sur construction propre : `main` avant la PR #69 = 12 065 (98,2 %) ;
  `main` actuel = 12 191 (99,2 %) ; la CI ajoute environ 40 octets. La PR #69 a dû être raccourcie à la main pour passer.
- **Risque.** La prochaine fonctionnalité qui ajoute des styles fera échouer l'intégration continue.
- **Correction.** Soit décider (nouvelle décision, D-157) d'un budget plus large (par exemple 14 ko, toujours très en dessous des
  500 ko de page documentés), soit mutualiser les motifs répétés dans des composants `@apply` ; ne **pas** neutraliser le test.

#### A7 — La recherche textuelle n'est pas cohérente entre les modules
- **Constat (lecture de code).** Opportunités : insensible aux accents et à la casse (colonne `search_text`, D-198). Fil
  (`app/Livewire/Fil.php:146-156`) : le texte des publications et les noms d'entreprises utilisent `ilike` avec la saisie brute
  (**sensible aux accents**), alors que les capacités et les noms d'auteurs utilisent la clé normalisée. Annuaire des entreprises :
  `ilike` brut sur secteur et présentation. Personnes : capacités normalisées. Une même saisie « ingenieur » trouve donc un résultat
  dans un module et pas dans l'autre.
- **Correction.** Étendre le mécanisme `search_text` normalisé (ou activer l'extension PostgreSQL `unaccent` si l'hébergement le
  permet) à publications et entreprises ; voir §E, car c'est un **préalable** au moteur de découverte.

### P3

| ID | Constat | Fichiers | Correction recommandée |
|---|---|---|---|
| A8 | Le PDF de candidature est servi sans l'en-tête `Content-Security-Policy: default-src 'none'; sandbox` qu'utilisent les médias de publication (il a déjà `nosniff`, `attachment`, `no-store`) | `app/Http/Controllers/OpportunityController.php` (`attachment`) | Ajouter l'en-tête comme `PublicationMediaController` |
| A9 | Cibles tactiles < 44 px sur mobile : lien « Connectez-vous » (page publique d'une publication, invité) et lien « Parcourir les opportunités » (état vide de « Mes opportunités ») | `resources/views/livewire/page-publication.blade.php`, `resources/views/pages/opportunity-mine.blade.php` | Hauteur minimale de 44 px |
| A10 | Vocabulaire : l'annuaire Entreprises garde le mode « Recherche des compétences » et le badge du même nom, alors que le module s'appelle Opportunités | `resources/views/livewire/company-directory.blade.php`, `components/company-discovery-card.blade.php` | « Propose des opportunités » |
| A11 | Documentation : `docs/RECENTRAGE.md` n'a pas de contrat « Opportunités » ; le glossaire de `docs/PRODUIT.md` ne définit ni « Opportunité » ni « Réponse à une opportunité » (règle 6 de `CLAUDE.md`) | `docs/RECENTRAGE.md`, `docs/PRODUIT.md` | Ajouter le contrat et les deux termes |
| A12 | Duplications : la logique « personnes complémentaires » existe en trois copies (`Fil.php:227-265`, `CarrefourPersonnes.php:228-275`, `Espace.php` ~l. 210) ; le logo d'entreprise est recopié dans 5 vues alors que `x-company-logo` existe ; deux tables de signets distinctes (publications, opportunités) | voir §D | Service unique de complémentarité ; utiliser le composant |
| A13 | Deux envois simultanés d'une même réponse : la contrainte d'unicité provoque une erreur 500 au lieu d'un message clair (probabilité faible : limite de 20 envois par minute) | `app/Services/Opportunities.php` (`respond`) | Capturer l'exception d'unicité |
| A14 | Navigation mobile : pas d'accès direct à Personnes, Entreprises, Opportunités depuis la barre du haut hors une icône d'Opportunités ajoutée par la PR #69 | `resources/views/components/layouts/app.blade.php` | **AM-1** ci-dessous |
| A15 | Poids par page : Fil et Mon espace font ~30 requêtes SQL chacun (stable quand les données augmentent, donc pas de N+1, mais base élevée) | `Fil`, `Espace`, `notification-bell` | Mutualiser les requêtes du gabarit à l'occasion |
| A16 | `/charte` et `/mentions-legales` répondent 404 tant que les textes ne sont pas adoptés (D-146) | — | **Information** : à adopter avant l'ouverture publique (D-078) |
| A17 | Le sélecteur « Autres vues » du Fil affiche « — » sans libellé parlant | `resources/views/livewire/fil.blade.php` | Libellé explicite |

### Non confirmé (documenté pour éviter une fausse alerte)

- **« Suppression d'une entreprise impossible après réception d'une candidature »** : un test automatisé a échoué avec une violation de
  clé étrangère sur `user_notifications.opportunity_id`. Repris sur le **vrai** serveur de recette (5 essais, avec et sans transaction
  englobante) : suppression **réussie à chaque fois**. Cause de l'échec du test : PostgreSQL revérifie une clé étrangère sur une ligne
  créée dans la **même transaction** que la suppression, ce qui n'arrive que dans la transaction d'un test. Pas un défaut de
  production ; à garder en tête pour toute future purge groupée (nettoyer d'abord `opportunity_id` des notifications).

---

## AM-1 — Amélioration validée par le Président : menu hamburger mobile

**Demande validée (non réalisée pendant l'audit ; à traiter dans le premier lot de corrections).**

- Conserver impérativement la barre inférieure actuelle : **Accueil · Agir · Mon espace**.
- Ajouter dans l'en-tête, sur mobile, une icône **hamburger** ouvrant un menu : **Accueil, Personnes, Entreprises, Opportunités**.
- Menu élégant, compact, responsive, cohérent avec l'identité visuelle de GAMAD ; fermeture après sélection ; navigation accessible
  (clavier, `aria-expanded`, `Échap`, focus piégé) ; **ne pas réactiver** ZUMRA, Projets ni Finances.

**Points de conception relevés par l'audit (pour le lot)**

1. L'icône « Opportunités » ajoutée à l'en-tête mobile par la PR #69 devient **redondante** : à retirer quand le hamburger arrive.
2. Réutiliser `config/navigation.php` (`navigation.initial.desktop`) comme source unique des quatre entrées, pour que bureau et
   mobile ne divergent plus.
3. Cibles tactiles ≥ 44 px, test de non-débordement à 360 px, et test vérifiant l'absence de ZUMRA/Projets/Finances.
4. Le menu utilisateur de bureau (Mon espace, profil, déconnexion) n'a pas d'équivalent mobile hors la barre inférieure : prévoir si
   le Président le souhaite d'y répéter « Mon profil public ».

---

## D. Audit architectural

### D.1 Doublons

| Sujet | Où | Recommandation |
|---|---|---|
| Suggestions de personnes complémentaires | `Fil.php`, `CarrefourPersonnes.php`, `Espace.php` | Un service unique, testé une fois |
| Suggestions d'entreprises / d'opportunités par capacité | `CompanyDirectory.php`, `OpportunityCatalog.php::forYou` | Même service que ci-dessus, avec « sources » différentes |
| Rapprochement officiel D-187 | `Services/Matching.php` (378 lignes), seulement publications ↔ profils | Voir §E |
| Normalisation de recherche | quatre approches (clé normalisée, `ilike` brut, colonne `search_text`, `normalized` des villes) | Une seule stratégie |
| Signets | `PublicationBookmarks` et `opportunity_saves` | Acceptable aujourd'hui ; polymorphe si un troisième apparaît |
| Logo d'entreprise | 5 vues | Composant `x-company-logo` |

### D.2 Services réutilisables

`Normalizer` (clé et motif de recherche), `People` (relations, blocages : `excludedIds`), `Notifier`, `Publications`, `PublicationMedia`,
`ProfilePhoto::encode*`, `MatchingConditions` (conditions et libellés), `PersonalTrust` (identité vérifiée, attestations),
`CompanyBranding`, politiques `PublicationPolicy`, `ProfilePolicy`, `OpportunityPolicy`.

### D.3 Incohérences

- Deux modes d'expérience (initial et historique) cohabitent, avec le **mauvais** mode par défaut dans les tests (A5).
- Les opportunités d'entreprise ne passent pas par `Publication` : c'est voulu (D-198), mais le Fil n'annonce pas une nouvelle
  opportunité et `Matching` ne les connaît pas.
- Les compétences d'une opportunité sont du **texte libre** (JSON), alors que profils et publications utilisent la table `skills`
  normalisée : aucune jointure possible avec les personnes (voir §E).

### D.4 Risques techniques

| Risque | Gravité | Détail |
|---|---|---|
| Écriture à la lecture dans `Matching::suggestions` / `discover` | Moyenne | Chaque affichage de « Mes pistes » parcourt les publications candidates, évalue des politiques une à une (`Gate`), crée des lignes et peut notifier ; coût proportionnel au volume, hors file d'attente (`QUEUE_CONNECTION=sync`) |
| `Matching::publicationChanged` exécuté dans la requête de publication | Moyenne | Boucle par blocs de 100 profils dans la requête de l'auteur ; encapsulé dans `safely()`, mais ajoute de la latence quand le nombre de profils activés augmente |
| Recherche `LIKE '%…%'` sur colonnes texte sans index | Faible aujourd'hui | Parcours séquentiel ; acceptable jusqu'à quelques dizaines de milliers de lignes ; à revoir avec un index trigramme ou un moteur de recherche |
| Budget CSS | Moyenne | A6 |
| Transactions + clés étrangères multiples | Faible | Voir « Non confirmé » |

### D.5 Confidentialité et fichiers privés — bilan

- Médias de publication : autorisation `view` à chaque requête, `nosniff`, CSP `sandbox`, `no-store`. ✅
- Photos de profil : autorisation `view`, en-têtes corrects. ✅
- Logos et bannières d'entreprise : visibles seulement si l'entreprise est vérifiée ; documents administratifs réservés au super
  administrateur. ✅
- Pièces jointes de candidature : accès limité à l'auteur et à l'entreprise, jamais dans les pages publiques. ✅ ; **purge manquante (A1)** ;
  CSP à ajouter (A8).
- SQL : toutes les requêtes brutes relues sont paramétrées ; la seule sortie non échappée (`{!! !!}`) est un SVG interne fixe. ✅
- Routes : les routes privées sont toutes derrière `Authenticate` + `EnsureEmailIsVerified` ; les seules routes publiques sont celles
  attendues (accueil, annuaires, détails publics, médias autorisés, webhook GeniusPay signé et limité). ✅

---

## E. Préparation du moteur de découverte

*(Analyse seulement : aucun moteur n'est créé ni commencé.)*

### E.1 Ce qui fonctionne déjà

`Matching` (D-187) est complet pour son périmètre : préférences privées du profil, intentions, zones, disponibilités, conditions
bénévoles/rémunérées, raisons **explicables** (« Capacité commune : …, Localité choisie : … »), aucune note de personne, signature
unique par suggestion, ignorance privée, plafond de 5 alertes par jour, respect des blocages et de la visibilité (`Gate::view`).

### E.2 Réutilisable

- Le **contrat des raisons** (`reasons()` → liste de phrases) et le principe « aucune note ».
- Les préférences de profil (`matching_preferences`) et les filtres de visibilité déjà branchés sur les politiques.
- `Normalizer` et la table `skills` normalisée ; villes et domaines de référence.
- Les relations de suivi (`person_follows`, `company_follows`) — inexploitées pour la découverte.

### E.3 Redondant

Cinq implémentations de « suggestion » coexistent (Rapprochement D-187 ; Fil ; Carrefour Personnes ; annuaire Entreprises ;
« Selon vos capacités » des Opportunités) avec trois façons différentes de comparer les capacités (identifiants de capacités, noms
`ilike`, texte normalisé).

### E.4 Ce qui manque pour relier Personnes, Entreprises, Publications et Opportunités

1. **Un vocabulaire commun de capacités** : les opportunités n'utilisent pas la table `skills` (texte libre). Sans lien, impossible
   d'apparier proprement personnes ↔ opportunités.
2. **Une recherche normalisée unifiée** (A7).
3. **Une interface commune d'objet découvrable** (qui peut le voir, quelles capacités, quelle localité, quel domaine, quelles raisons).
4. **Un calcul hors requête** : aujourd'hui les raisons sont calculées à l'affichage.
5. **Des relations exploitées** : suivre une entreprise ne produit aucune découverte de ses nouvelles opportunités.
6. **Une règle de blocage uniforme** (A4).
7. **Une gouvernance** : D-187 limite les alertes aux publications avec rapprochement activé ; l'extension aux opportunités demande une
   décision (qui est notifié, avec quel plafond).

### E.5 Risques de confidentialité et de performance

- *Confidentialité* : ne jamais exposer les préférences privées de l'autre côté ; raisons limitées à des données déjà visibles par le
  destinataire ; ne pas déduire ni afficher de score ou de pourcentage ; respecter blocages, suspensions et visibilité des profils ;
  consigner ce qui est notifié.
- *Performance* : éviter l'évaluation de politiques ligne par ligne ; précalculer à l'écriture (profil, publication, opportunité),
  limiter la taille des lots, mettre en file d'attente (la file `sync` actuelle annule cet avantage).

### E.6 Architecture de consolidation recommandée (proposition)

```
app/Services/Discovery/            (lecture seule, aucune note)
  Contracts/Discoverable            id · type · visibleTo($viewer) · skillIds() · cityId() · domainId() · reasonsFor($viewer)
  Sources/ (Person, Company, Publication, Opportunity) — adaptateurs minces sur les modèles existants
  Reasons                          phrases explicables, factorisées (reprend le contrat de Matching)
  Eligibility                      blocages, suspensions, visibilité, préférences — un seul endroit
```

`Matching` (D-187) reste la source pour publications ↔ profils et alertes ; il s'appuie ensuite sur `Eligibility` et `Reasons`
partagés au lieu de les dupliquer. Les pages Fil, Personnes, Entreprises et Opportunités n'appellent plus que ce service.

### E.7 Périmètre minimal d'une future mission

1. **Préalable** : A7 (recherche normalisée), A4 (règle de blocage), A12 (un seul service de complémentarité).
2. **Table pivot** `opportunity_skill` vers `skills` (migration additive) + saisie assistée des compétences dans le formulaire
   d'opportunité (réutiliser le sélecteur de capacités des profils).
3. **Premier usage** : « Opportunités qui correspondent à mes capacités » et « Personnes qui correspondent à cette opportunité » (côté
   entreprise, en lecture, sans notification), avec raisons explicables et sans score.
4. **Hors périmètre de cette première mission** : alertes, apprentissage automatique, classement, nouvelles notifications.

---

## F. Plan de correction proposé (non implémenté — en attente de validation)

| Lot | Contenu | Priorité | Taille |
|---|---|---|---|
| **C1** | **AM-1** hamburger mobile (+ retirer l'icône redondante) ; cibles tactiles (A9) ; vocabulaire de l'annuaire (A10) | Haute | S |
| **C2** | Purge des pièces jointes à la suppression d'un compte ou d'une entreprise (A1), commande d'entretien des orphelins, correction du test trompeur | Haute (P1) | S |
| **C3** | Lecture du journal : familles « Entreprises » et « Commentaires » (A2) ; CSP du PDF de candidature (A8) | Haute | S |
| **C4** | Effacement du brouillon local après publication (A3) + test de navigateur | Moyenne | XS |
| **C5** | Processus de test : mode initial par défaut (A5) ; décision sur le budget CSS (A6) | Moyenne | M |
| **C6** | Règle de blocage uniforme (A4, décision requise) ; message clair en cas d'envoi simultané (A13) | Moyenne | S |
| **C7** | Recherche normalisée unifiée (A7) ; service unique de complémentarité (A12) | Moyenne (préalable au moteur) | M |
| **C8** | Documentation : contrat Opportunités dans `RECENTRAGE.md`, glossaire `PRODUIT.md` (A11) | Basse | XS |
| **D1** | Mission « moteur de découverte » minimale (§E.7), après C6 et C7 | Après validation | M |

Ordre proposé : C1 → C2 → C3 → C4 → C5 → C6 → C7 → C8 → D1.

---

## Annexes

- `captures/audit/` : captures à 1 440, 390 et 360 px (`pc-*`, `m390-*`, `m360-*`) et menu Agir sur les trois tailles.
- `docs/audit/2026-10-26/` : outils et résultats bruts, reproductibles sur une base isolée
  - `seed-recette.php.txt` — données fictives de recette ;
  - `flows.mjs.txt` — 39 étapes de parcours (variable `VP=mobile` pour 390 px) ;
  - `pages.mjs.txt` — balayage des 51 pages à 3 tailles ; `curated.mjs.txt` — captures ;
  - `AuditTest.php.txt` — tests de mesure (suppression de compte, types de notification, requêtes par page, blocage) ;
  - `repro-*.txt` — reproductions de A1, A3, A4 ;
  - `resultats-*.json` — résultats bruts.
- Environnement de recette : PHP 8.3, PostgreSQL 16 local, Chromium (Playwright), base `gamad_recette` supprimée à la fin de l'audit.

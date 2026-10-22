# CLAUDE.md — Réseau social d'action GAMAD

Ce fichier s'applique à tout le dépôt. Lis-le en entier avant chaque intervention.

## Ce que nous construisons

Un réseau social d'action ouvert à tous. La personne est au centre : elle vient apporter ce qu'elle sait
faire, apprendre ce qu'elle ne sait pas encore, ou dire ce qui lui manque. Les ZUMRA regroupent les
personnes ; les projets naissent dans les ZUMRA ; le Fil global montre ce qui se fait, avec des boutons
d'action à la place des « j'aime ».

## Les quatre seules sources d'autorité

1. `docs/PRODUIT.md` — glossaire, objets, états, droits, règles transverses.
2. `docs/DESIGN.md` — apparence, composants, écrans de référence (et `docs/maquettes/`).
3. `docs/DECISIONS.md` — journal des décisions. Une décision plus récente remplace l'ancienne.
4. `docs/LOTS.md` — le lot en cours, son périmètre exact et ses critères de fin.

Rien d'autre ne fait autorité : ni un ancien dépôt, ni un commentaire, ni une idée de `docs/PLUS-TARD.md`.
En cas de contradiction entre ces fichiers, arrête-toi et signale-la. Ne la résous jamais seul.

## Autorité humaine

Le Président de GAMAD décide. Il est super administrateur de la plateforme. Une proposition n'est
adoptée qu'une fois inscrite dans `docs/DECISIONS.md` avec le statut « Décidée ».

## Pile technique

- PHP 8.3 ou plus, Laravel (dernière version stable au démarrage, consignée dans `DECISIONS.md`).
- Livewire 3 + Alpine.js, Blade, Tailwind CSS. Pas de framework JavaScript séparé (ni React ni Vue).
- PostgreSQL. Tests avec Pest. Intégration continue avec GitHub Actions.
- Langue de l'interface : français. Fuseau horaire : `Africa/Abidjan`.
- Aucune bibliothèque payante. Toute nouvelle dépendance Composer ou npm est justifiée dans le rapport de lot.

## Règles d'exécution

1. **Un lot à la fois.** Tu ne travailles que sur le lot marqué « En cours » dans `docs/LOTS.md`.
2. **Rien hors périmètre.** Un nouveau modèle, une nouvelle table, un nouveau terme ou un nouvel écran qui
   n'est pas dans le lot exige une décision. Tu le proposes dans ton rapport ; tu ne le construis pas.
3. **Pas de faux produit.** Aucun bouton, lien ou section sans comportement réel. Un module non encore
   construit est masqué, pas simulé. Aucune donnée fictive dans les migrations ou les écrans de production ;
   les données d'exemple vont uniquement dans des seeders de développement clairement nommés.
4. **La règle métier vit côté serveur.** Les autorisations passent par des Policies et des Gates Laravel,
   jamais par le seul affichage.
5. **Chaque écran livré a ses états :** vide, chargement, erreur, et il est utilisable à 390 px de large.
   Cibles tactiles d'au moins 44 px. Contraste du texte d'au moins 4,5:1.
6. **Vocabulaire fixe.** Utilise exactement les termes du glossaire de `docs/PRODUIT.md`, dans le code
   (noms de classes en anglais autorisés, voir la table de correspondance) comme dans l'interface.
7. **Tests obligatoires.** Chaque règle métier et chaque autorisation a son test. Le lot n'est terminé que si
   `php artisan test` et l'intégration continue passent.
8. **Traçabilité.** Chaque acte du super administrateur ou d'un modérateur est inscrit dans le journal
   d'administration, qui ne peut être ni modifié ni effacé par l'application.
9. **Pas d'historique dans le README.** L'historique vit dans `docs/DECISIONS.md` et dans Git.

## Fin d'un lot

Tu ouvres une Pull Request et tu rédiges le rapport selon `prompts/RAPPORT-DE-LOT.md`. Tu ne fusionnes pas
toi-même dans `main`. Tu ne marques pas le lot « Terminé » : le Président le fait après l'avoir essayé sur
son téléphone.

## Interdictions

- Restaurer ou copier du code de l'ancien dépôt `dgafrique-core`.
- Introduire un compteur de « j'aime », une note ou un classement de personnes.
- Introduire un paiement avant le lot prévu pour les contributions.
- Afficher le téléphone ou l'e-mail d'une personne à quelqu'un d'autre.
- Désactiver un test pour faire passer l'intégration continue.

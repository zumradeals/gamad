# TESTER — essayer le lot en cours sur votre téléphone

Ce guide vous permet d'essayer GAMAD sur **https://reseau.dgafrique.com** avec votre téléphone, sans aucune ligne de commande
et sans ouvrir aucun fichier technique (D-049, D-050). Seul le Président marque un lot « Terminé » (`docs/LOTS.md`, D-052).

Ce qu'il vous faut : votre téléphone, la boîte de réception de `no-reply@dgafrique.com` (ou le moyen de lire ses courriels),
et une seconde adresse e-mail que vous savez lire, pour créer un compte de test.

## 1. Se connecter en super administrateur

Votre compte a été créé pendant l'installation, avec le nom, l'adresse e-mail et le mot de passe que vous avez saisis.
Il est déjà vérifié : vous n'avez pas de courriel à attendre.

1. Ouvrez **https://reseau.dgafrique.com**. Vous voyez l'accueil : le titre, l'accroche, « Créer un compte », « Se connecter », la devise en petit.
2. Touchez **Se connecter**, saisissez votre adresse e-mail (les majuscules n'ont pas d'importance) et votre mot de passe.
3. Vous arrivez sur le **Fil** : « Le Fil se remplira bientôt. »
4. En bas de l'écran, vous ne voyez que **Fil** et **Espace**. Rien d'autre.

## 2. Régler le courriel (page Paramètres)

Les réglages se font dans l'application, jamais dans un fichier.

1. Touchez **Espace**. Vous voyez une section **Administration** (les autres personnes ne la voient pas). Touchez **Paramètres**.
2. Section **Courriel**. Les valeurs sont celles du compte `no-reply@dgafrique.com` chez o2switch
   (dans cPanel, rubrique « Comptes e-mail », bouton « Configuration manuelle » : o2switch les affiche) :
   - **Serveur SMTP** : le serveur sortant indiqué par o2switch ;
   - **Port** et **Chiffrement** : 465 avec « SSL/TLS » (ou 587 avec « STARTTLS ») ;
   - **Identifiant** : `no-reply@dgafrique.com` ;
   - **Mot de passe** : celui de ce compte e-mail ;
   - **Adresse de l'expéditeur** : `no-reply@dgafrique.com` ; **Nom de l'expéditeur** : GAMAD.
3. Section **Plateforme** : le nom (GAMAD) et l'adresse publique `https://reseau.dgafrique.com`.
4. Section **Sécurité** : vous pouvez laisser 20 inscriptions par heure et par adresse IP, et 5 tentatives de connexion par minute.
5. Touchez **Enregistrer**. Un message confirme le nombre de modifications.
6. Touchez **Envoyer un e-mail de test**. Un courriel « Courriel de test — GAMAD » doit arriver sur **votre** adresse
   (regardez aussi les courriers indésirables).
   - Si l'envoi échoue, un message rouge explique pourquoi. **Copiez-le-moi tel quel** : il ne contient jamais votre mot de passe.

À savoir :
- Le mot de passe SMTP n'est **jamais réaffiché**. Un champ laissé vide le **conserve** ; la case « Retirer le mot de passe enregistré » l'efface.
- Chaque modification est inscrite au journal d'administration (l'ancienne et la nouvelle valeur ; pour un mot de passe, seulement « modifié »).
  Ce journal n'a pas encore d'écran : il est vérifié par les tests automatiques.

## 3. Essayer le parcours d'une personne ordinaire

1. **Espace → Se déconnecter.** Vous revenez à l'accueil.
2. **Créer un compte** avec votre **seconde adresse e-mail** : nom affiché, adresse, mot de passe (8 caractères au minimum), confirmation.
   L'écran vous demande de vérifier votre adresse.
3. Ouvrez le courriel reçu, touchez le lien de vérification : vous arrivez sur le Fil.
   Si le courriel n'arrive pas, touchez **Renvoyer le lien**.
4. Dans **Espace**, vous voyez votre nom et votre adresse e-mail, mais **pas** la section Administration.
   Vous pouvez y **modifier votre nom affiché** : changez-le dans le champ « Nom affiché », touchez **Enregistrer le nom** ;
   un message confirme, et le nouveau nom apparaît aussitôt. Un nom vide est refusé avec un message clair.
   Essayez d'ouvrir **https://reseau.dgafrique.com/espace/parametres** : l'accès est refusé (page « Accès refusé »).
5. **Se déconnecter**, puis **Se connecter** avec un mauvais mot de passe : un message clair s'affiche.
   Après 5 essais ratés en moins d'une minute, la connexion est bloquée un moment.
6. **Mot de passe oublié ?** : saisissez votre seconde adresse, ouvrez le courriel, choisissez un nouveau mot de passe, reconnectez-vous.
7. **Espace → Supprimer mon compte** : confirmez avec votre mot de passe. Le compte de test est supprimé **définitivement**
   (vous le retrouvez absent si vous tentez de vous reconnecter).

## 4. Essayer le Lot 1 « Personne » : profil, Monde d'une personne, Carrefour Personnes

Il vous faut deux comptes : le vôtre (super administrateur, appelé **compte 1**) et un compte de test créé avec votre seconde
adresse (**compte 2**, voir §3, étapes 1 à 3). Ne supprimez pas le compte 2 avant d'avoir fini cette partie.

**Mon profil.**

1. Compte 1 : **Espace → Modifier mon profil**. Vous voyez la photo (ou vos initiales), le nom, la présentation (compteur « n / 280 »),
   le pays et la ville, « Je peux apporter », « Je veux apprendre », les domaines et « Qui voit mon profil ? ».
2. Choisissez le pays **Côte d'Ivoire** puis une **ville**. Écrivez une présentation courte.
3. Dans **Je peux apporter**, tapez quelques lettres, par exemple « cout » : une ligne **Ajouter « cout »** apparaît, et sous elle les
   capacités que d'autres ont déjà indiquées, marquées « déjà utilisée ». Touchez-en une, ou écrivez la vôtre en entier puis touchez
   **Ajouter** (ou la touche Entrée). Elle s'ajoute en pastille verte. La croix de la pastille la retire.
4. Dans **Je veux apprendre**, faites de même (pastilles bleues). Dix capacités au plus par liste : à la onzième, un message l'explique.
5. Choisissez jusqu'à **trois domaines** (« Voir tous les domaines » déplie la liste). Au quatrième, un message l'explique.
6. Touchez **Enregistrer** : le message « Profil enregistré. » apparaît, avec le lien **Voir mon profil**.
7. Photo (si le bouton « Ajouter une photo » est présent) : prenez ou choisissez une photo. Après **Enregistrer**, elle apparaît en rond.
   Une photo prise en hauteur doit rester droite. La position GPS de la photo n'est jamais conservée (vérifié par les tests).
   Si le bouton « Ajouter une photo » n'apparaît pas, le serveur n'a pas l'extension d'images : dites-le-moi.

**Le Monde d'une personne.**

8. **Voir mon profil** : votre nom, votre ville, « Sur GAMAD depuis … », votre présentation, vos capacités, vos domaines et le bouton
   **Modifier mon profil**. Il n'y a ni « Suivre », ni « Inviter », ni remerciements : ils viendront avec les lots suivants.
9. Touchez une capacité : le **Carrefour Personnes** s'ouvre, déjà filtré sur elle.

**Le Carrefour Personnes.**

10. Compte 2 : remplissez de la même façon un profil qui **apporte** ce que le compte 1 veut apprendre, et **veut apprendre** ce que le
    compte 1 apporte, **dans la même ville**. Écrivez une capacité en variant les majuscules et les accents (« COUTURE », « couture ») :
    le site propose la capacité existante au lieu d'en créer une autre.
11. **Découvrir** (téléphone) ou **Personnes** (ordinateur) ouvre le Carrefour. Depuis le compte 1, cherchez une capacité du compte 2 :
    « **peut l'apporter** » le trouve. Passez en « **veut l'apprendre** » avec une capacité que le compte 2 veut apprendre : il apparaît.
    Faites l'inverse depuis le compte 2. Ajoutez le filtre **ville** puis **domaine**.
12. Cherchez par nom, avec ou sans majuscules et accents. Cherchez quelque chose qui n'existe pas : l'écran vide propose de compléter votre profil.

**Qui voit mon profil.**

13. Compte 2 : **Qui voit mon profil ? → Les personnes inscrites seulement**, puis Enregistrer.
14. Ouvrez une **fenêtre de navigation privée** (sans compte) sur **https://reseau.dgafrique.com/personnes** : le compte 2 n'apparaît pas, et son
    adresse directe répond « page introuvable ». Connecté, vous le voyez. Remettez « Tout le monde » : un visiteur sans compte le voit,
    mais votre e-mail n'apparaît nulle part.

**Domaines et territoires (super administrateur).**

15. Compte 1 : **Espace → Administration → Paramètres → Domaines**. Vous voyez les 17 domaines. Ajoutez un domaine, renommez-le, désactivez-le :
    il disparaît de « Modifier mon profil » et du filtre du Carrefour. Réactivez-le. **Territoires** fonctionne de même pour les pays et les villes.
    Attention : **rien ne se supprime**, seulement se désactiver ; n'ajoutez donc que ce que vous voulez garder, ou désactivez votre essai ensuite.
16. Le compte 2 ne voit pas la section Administration, et l'adresse `/espace/parametres/domaines` lui répond « Accès refusé ».

La liste des villes ivoiriennes (31 chefs-lieux de région, 2 districts autonomes, 13 communes d'Abidjan) a été reprise de sources publiques :
**contrôlez-la** et corrigez dans Territoires tout nom qui serait faux.

## 5. Essayer le Lot 2, première étape (2a) : publier, répondre, accepter

Même équipement que le §4 : le **compte 1** (le vôtre) et le **compte 2** (votre seconde adresse). Cette section décrit la première étape ; la conversation, le remerciement et les notifications sont au §6.

**Publier.**

1. Compte 1 : touchez le rond jaune **Agir** au centre de la barre du bas (sur ordinateur : le bouton jaune « Agir » en haut). Vous voyez quatre
   choix : **J'ai besoin**, **Je partage un savoir**, **Je veux apprendre**, **Je propose mon aide**.
2. Touchez **J'ai besoin**. Écrivez un besoin réel et précis (500 caractères au plus ; le compteur le montre). Ajoutez si vous voulez une
   capacité recherchée (trois au plus), un domaine, un lieu (repris de votre profil) et choisissez **Public** ou **Inscrits seulement**.
3. Touchez **Publier mon besoin** : vous arrivez sur **Mon besoin**, avec le message « Publié. » et l'état **Ouvert**.
4. Écrivez dans le texte une adresse web (par exemple `https://exemple.com`) : elle s'affiche en texte simple, **jamais cliquable**.

**Le Fil.**

5. **Fil** : votre publication est en haut. Les filtres **Tout**, **Besoins**, **Savoirs**, **Apprendre** et **Aides** trient ; **Voir plus de publications** charge la suite.
   L'ordre est toujours du plus récent au plus ancien. Il n'y a ni « Commenter », ni « Partager », ni « j'aime ».
6. Sur ordinateur, à gauche : votre profil tel que les autres le voient.

**Répondre.**

7. Compte 2 : dans le **Fil**, la publication du compte 1 porte le bouton **Je peux aider**. Touchez-le : une feuille s'ouvre, avec un champ
   « Un mot… (facultatif) », 280 caractères au plus. Touchez **Envoyer ma proposition**.
8. La carte affiche **Proposition envoyée** et **Retirer ma proposition**. Retirez-la, puis renvoyez-la : c'est permis tant qu'elle n'est pas acceptée.
   Sur votre propre publication, il n'y a **pas** de bouton de réponse.

**Décider.**

9. Compte 1 : **Espace → Mes publications → votre besoin** (ou **Voir les réponses** dans le Fil). Sous **Propositions reçues**, vous voyez le nom, la
   ville, les capacités et le mot de la personne, **jamais son e-mail ni son téléphone**. Touchez **Accepter** : la proposition devient
   **Acceptée** et le besoin passe à **En cours**. Vous pouvez en accepter plusieurs ; **Décliner** écarte une proposition.
10. Compte 2 : le Fil affiche **Acceptée** (ou **Non retenue** si elle a été déclinée).
11. Compte 1 : touchez **Marquer comme résolu** (une confirmation vous est demandée) ou **Fermer sans suite**. Le besoin n'accepte plus de réponse : le
    compte 2 voit « Ce besoin est résolu. » ou « Ce besoin est fermé. ».

**Le second parcours : un savoir.**

12. Compte 2 : **Agir → Je partage un savoir** : publiez un savoir. Compte 1 : filtre **Savoirs**, touchez **Je veux apprendre**, envoyez.
    Compte 2 : **Mes publications** → **Mon savoir partagé** → **Accepter** l'apprenant. Compte 1 voit **Acceptée**.

**Qui voit quoi.**

13. Publiez avec **Inscrits seulement** puis, dans une **fenêtre de navigation privée**, ouvrez l'adresse de la publication : « page introuvable ».
    Une publication **Publique** s'y lit, avec **Se connecter pour répondre** à la place du bouton.

**Limites normales.** Texte de publication : 500 caractères ; mot d'un geste : 280 ; trois capacités. Pour freiner le spam, une personne peut
publier 10 fois par heure (30 par jour) et répondre 30 fois par heure (100 par jour) ; au-delà, un message clair le dit.

## 6. Essayer le Lot 2, deuxième étape (2b) : parler, remercier, être prévenu

Même équipement : le **compte 1** (le vôtre, qui publie) et le **compte 2** (votre seconde adresse, qui répond). Les deux adresses doivent être vérifiées.

**La conversation.**

1. Compte 1 : publiez un besoin. Compte 2 : dans le Fil, touchez **Je peux aider** et envoyez. Compte 1 : ouvrez le besoin (**Espace → Mes publications**) et touchez **Accepter**.
2. Sous la proposition acceptée apparaît **Écrire**. Touchez-le : la conversation s'ouvre, avec le rappel « Votre e-mail et votre téléphone restent privés ». Écrivez un message (1 000 caractères au plus) et touchez la flèche d'envoi.
3. Compte 2 : le Fil affiche **Acceptée** et **Ouvrir la conversation**. Ouvrez-la : le message est là. Répondez.
4. Laissez la conversation ouverte sur le compte 1 : la réponse du compte 2 s'affiche **toute seule, en 10 secondes au plus**, sans recharger.
5. Écrivez une adresse web ou du HTML dans un message : il s'affiche en texte simple, jamais cliquable.

**Les notifications.**

6. Une **cloche** est en haut de l'écran. Un chiffre jaune indique les notifications non lues (réponse reçue, proposition acceptée, nouveau message, remerciement). Touchez la cloche : la liste s'ouvre, la plus récente en premier.
7. Touchez une notification : elle est marquée lue et vous arrivez directement sur la publication, la conversation ou votre profil. **Tout marquer lu** les marque toutes.
8. Trois messages d'affilée ne créent **qu'une** notification tant que le premier n'est pas lu. Il n'y a jamais d'e-mail ni de SMS, et jamais l'e-mail ni le téléphone de quelqu'un.

**À faire maintenant** (en haut d'Espace).

9. Compte 1, après qu'une réponse est arrivée : « 1 réponse à traiter » avec le bouton **Voir**. Après un message non lu : « 1 message non lu » avec **Lire**. Chaque ligne **disparaît** quand l'action est faite. Quand tout est traité : « Rien ne vous attend pour l'instant. »

**Remercier.**

10. Compte 1, sur **Mon besoin** : touchez **Marquer comme résolu**. Un écran demande **Qui vous a aidé ?** (les personnes acceptées, ou « Quelqu'un d'autre ou personne en particulier »). Choisissez le compte 2, écrivez un remerciement (280 caractères au plus ; un aperçu s'affiche), puis **Marquer résolu et remercier**.
11. Ouvrez le profil du compte 2 (dans une fenêtre privée aussi) : la section **Remerciements reçus** montre le texte, qui remercie et pour quel besoin. Il n'y a **aucun compteur ni note**.
12. Compte 2 : sur son propre profil, **Masquer de mon profil** retire le remerciement pour les autres ; **Afficher à nouveau** le rétablit.
13. Si vous marquez « résolu sans remerciement », Espace propose pendant 7 jours « Remercier … ? » ; vous pouvez aussi remercier depuis le besoin.

**Supprimer un compte.** Si le compte 2 est supprimé (**Espace → Supprimer mon compte**), ses messages sont effacés ; le compte 1 relit la conversation avec « Personne supprimée » et « Message supprimé », ses propres messages restant lisibles, et ne peut plus écrire.

## 7. Essayer le Lot 2, troisième étape (2c) : signaler et modérer

Il vous faut trois comptes : le **compte 1** (le vôtre, super administrateur, qui modère), le **compte 2** (qui publie) et le **compte 3** (qui signale). Les trois adresses doivent être vérifiées.

**Signaler.**

1. Compte 2 : publiez quelque chose (par exemple un savoir). Compte 3 : dans le **Fil**, sous la publication, touchez **Signaler** (le lien n'existe pas sur vos propres publications).
2. L'écran **Signaler cette publication** propose six raisons (contenu offensant, arnaque, fausse information, publicité ou spam, atteinte à la vie privée, autre) et un champ « Précisez si vous le souhaitez… » (280 caractères). Choisissez une raison et touchez **Envoyer le signalement** : « Merci. Un modérateur examinera votre signalement. »
3. Signalez la même publication une seconde fois : « Vous avez déjà signalé cette publication. » Compte 2 : rien ne dit qui a signalé, ni dans la publication, ni dans les notifications.

**La file de modération.**

4. Compte 1 : **Espace**. En haut, « À faire maintenant » indique « 1 publication signalée » avec **Examiner** ; plus bas, la section **Modération** mène à la file.
5. La file affiche, sous **À examiner**, le texte entier de la publication, son auteur, le nombre de signalements et leurs raisons. **Le nom des personnes qui ont signalé n'apparaît pas.**
6. Touchez **Classer sans suite** : le signalement est clos, la publication reste visible. Signalez-la de nouveau avec le compte 3, puis revenez dans la file.
7. Écrivez un **motif** (obligatoire : l'auteur le verra) et touchez **Masquer**. La publication disparaît du Fil pour tout le monde. Essayez de masquer sans motif : un message vous le demande.

**Côté auteur.**

8. Compte 2 : une notification « … a masqué votre publication. Vous pouvez vous expliquer. » arrive. **Espace → À faire maintenant** affiche « Une publication a été masquée — La modération attend votre explication » et **Expliquer**.
9. Ouvrez la publication : le bandeau « Cette publication est masquée » donne le motif et un champ **Votre explication** (500 caractères). Envoyez-la ; vous pouvez la modifier tant que la publication est masquée. Avec le compte 3 ou dans une fenêtre privée, l'adresse de la publication répond « page introuvable ».

**Rétablir.**

10. Compte 1 : **Modération → Masquées** : la publication, son motif et **l'explication de l'auteur**. Touchez **Rétablir** (une raison est facultative). La publication revient dans le Fil et le compte 2 en est informé.
11. Vous ne pouvez pas masquer ni rétablir une publication dont **vous êtes l'auteur** : un autre modérateur ou le super administrateur doit le faire.

**Qui voit quoi.** Une personne ordinaire qui ouvre `/moderation` obtient « accès refusé ». Chaque masquage, rétablissement et classement sans suite est inscrit au **journal d'administration**, qui ne peut être ni modifié ni effacé.

**Limites normales.** Raison et précision de 280 caractères au plus ; motif et explication de 500 ; pour freiner l'abus du signalement lui-même, 10 signalements par heure et 30 par jour et par personne.

## 8. Essayer le Lot 3, première étape (3a) : créer et trouver une ZUMRA

Il vous faut le **compte 1** (le vôtre) et le **compte 2** (votre seconde adresse). À cette étape, on crée et on trouve des ZUMRA ; **rejoindre, inviter et publier au nom d'une ZUMRA** viennent aux étapes 3b et 3c.

**Créer.**

1. Touchez **ZUMRA** dans la barre du bas (sur ordinateur : **ZUMRA** en haut). Le **Carrefour ZUMRA** s'ouvre ; sans ZUMRA, il vous propose d'en créer une.
2. Touchez **Créer une ZUMRA**. Écrivez un **nom**, choisissez **un seul domaine**, une ville si vous voulez, **où la ZUMRA se retrouve** (en ligne, présentiel, hybride) et sa **mission** (500 caractères au plus). Touchez **Créer la ZUMRA**.
3. Laissez un champ vide et validez : **toutes les erreurs s'affichent ensemble**, chacune sous son champ.
4. Vous arrivez sur le **Monde de la ZUMRA** avec « ZUMRA créée. Vous en êtes le fondateur. ». Essayez de créer une ZUMRA du **même nom** (en changeant les majuscules ou les accents) : le nom est refusé.

**Le Monde d'une ZUMRA.**

5. L'en-tête montre le nom, « domaine · ville · format », l'état **Active**, la mission, le nombre de membres et le mois de création. Sous les onglets, **Membres** : vous y êtes, avec le rôle **Fondateur**.
6. **Modifier la ZUMRA** (visible du fondateur seulement à cette étape) change la mission, la ville et le format. Le **nom et le domaine ne changent pas** : l'écran le dit.
7. Compte 2 : ouvrez la même ZUMRA depuis le **Carrefour**. Il voit l'en-tête et la liste des membres, mais **pas** « Modifier la ZUMRA ». Dans une **fenêtre de navigation privée**, l'en-tête se lit sans compte, mais les membres demandent de se connecter.

**Trouver.**

8. Compte 2 : au **Carrefour ZUMRA**, tapez un mot du nom ou de la mission (les accents et les majuscules sont sans importance), filtrez par **domaine** et par **ville**. L'ordre est **alphabétique** : il n'y a aucun classement. **Mes ZUMRA** montre les vôtres avec votre rôle.
9. Rien n'y est inventé : seul le **nombre de membres** est affiché (pas de projets, pas de « savoirs partagés », pas de « Reconnue par GAMAD », pas de bouton « Je veux rejoindre »).

**Limite normale.** Pour freiner l'abus, une personne peut créer 3 ZUMRA par jour. **Supprimer son compte** supprime la ZUMRA dont on est le seul membre (le transfert de la fondation vient en 3b).

## 9. Ce qu'il faut regarder à chaque écran

- [ ] Le texte se lit sans zoomer, rien ne dépasse de l'écran, aucun défilement de côté.
- [ ] Les boutons sont faciles à toucher du pouce.
- [ ] En tournant le téléphone ou sur ordinateur (fenêtre large), la barre du haut affiche **Fil** et votre initiale qui mène à Espace.
- [ ] Les messages d'erreur sont clairs et en français.
- [ ] Tout bouton ou lien visible mène à une vraie page.

## 10. Les mises à jour se font toutes seules

Chaque fois qu'un changement est fusionné dans `main`, le site se met à jour tout seul en **5 à 10 minutes** : rechargez la page après ce délai.
Si une mise à jour échoue, la version actuelle reste en ligne et l'échec est noté dans un journal côté serveur ; si une mise à jour
casse le site, le serveur revient seul à la version précédente. En cas de doute, dites-le-moi : la personne qui administre le serveur
sait revenir en arrière (voir `deploy/LISEZMOI.md`, réservé à l'administration).

Les données sont sauvegardées chaque nuit (7 jours conservés).

## 11. Ce qui n'existe pas encore (c'est normal)

adhésion et invitation à une ZUMRA (étape 3b), publier, discuter et reconnaître une ZUMRA (étape 3c), projets, la consultation du journal d'administration, « Commenter », « Partager », « Suivre », « Inviter », recherche générale, paiement : ces modules sont masqués tant que leur lot
n'est pas construit.

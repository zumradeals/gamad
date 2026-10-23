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

## 9. Essayer le Lot 3, deuxième étape (3b) : entrer dans une ZUMRA

Il vous faut trois comptes, tous vérifiés : le **compte 1** (le vôtre, fondateur d'une ZUMRA créée au §8), le **compte 2** (votre seconde adresse) et un **compte 3** (une troisième adresse, ou un ami).

**Demander à rejoindre.**

1. Compte 2 : ouvrez la ZUMRA du compte 1 (Carrefour ZUMRA → **Entrer**, ou **Je veux rejoindre** sur la carte). Touchez **Je veux rejoindre**, écrivez un mot si vous voulez (280 caractères) et **Envoyer ma demande**. L'écran affiche **Demande envoyée** et **Retirer ma demande**.
2. Compte 1 : une notification arrive (cloche) et **Espace → À faire maintenant** affiche « 1 demande d'adhésion à traiter ». Ouvrez la ZUMRA : sous **Membres**, **Demandes d'adhésion** montre le nom, les capacités et le mot de la personne, jamais son e-mail. Touchez **Accepter** : la personne devient membre et en est informée. **Décliner** écarte la demande sans prévenir la personne, qui ne peut alors plus redemander.
3. Compte 2 : retirez une demande (**Retirer ma demande**) puis refaites-la : c'est permis.

**Inviter par capacité.**

4. Compte 1, sur la ZUMRA, **Membres → Inviter des personnes selon leurs capacités**. Écrivez une capacité (par exemple « informatique ») : les personnes qui la proposent s'affichent par ordre alphabétique, **sans classement**. Touchez **Inviter** sous une personne, écrivez un mot facultatif, **Envoyer l'invitation**. Sur le profil d'une personne, **Inviter dans ma ZUMRA** mène au même écran.
5. Compte 3 : une notification et « À faire maintenant » annoncent l'invitation. Sur la ZUMRA, un encadré propose **Accepter** ou **Refuser**. L'invitation vaut **14 jours**, après quoi elle ne peut plus être acceptée.
6. Compte 1 : sous **Membres**, **Invitations en attente** liste les invitations ; **Annuler** en retire une.

**Gérer les membres.**

7. Compte 1 (fondateur) : sous un membre, **Nommer responsable** (il en est informé et peut alors modifier la ZUMRA, traiter les demandes et inviter) ou **Retirer le rôle de responsable**, et **Retirer de la ZUMRA** (une confirmation est demandée). Un **responsable** peut retirer un simple membre, pas un autre responsable ni le fondateur.
8. Un membre ou un responsable touche **Quitter la ZUMRA** (il peut ensuite redemander à la rejoindre). **Le fondateur ne peut pas quitter sa ZUMRA.**

**Supprimer son compte de fondateur.** Compte 1 : **Espace → Supprimer mon compte**. Si la ZUMRA a d'autres membres, **la fondation passe au responsable le plus ancien** (à défaut, au membre le plus ancien), qui en est informé ; l'acte est inscrit au journal d'administration. Si le fondateur est seul, la ZUMRA est supprimée avec lui.

**Limites normales.** Une personne peut envoyer 10 demandes par jour ; un responsable peut envoyer 30 invitations par jour.

## 10. Essayer le Lot 3, troisième étape (3c) : vivre dans une ZUMRA et être reconnue

Il vous faut le **compte 1** (fondateur de la ZUMRA), le **compte 2** (membre de cette ZUMRA, voir §9), un **compte 3** (simple visiteur connecté, hors de la ZUMRA) et votre compte de **super administrateur**.

**Publier au nom de la ZUMRA.**

1. Compte 1 : ouvrez la ZUMRA, onglet **Fil** : **Publier au nom de la ZUMRA** propose quatre types, dont **Appel à rejoindre**. Publiez un besoin, un savoir et un appel (niveau **ZUMRA seulement** pour l'un d'eux). L'écran annonce « Vous publiez au nom de… ». Le compte 2 (simple membre) ne voit pas ces boutons.
2. Le **Fil** global affiche le nom de la ZUMRA sur ces publications et propose le filtre **ZUMRA**. Compte 3 : la publication « ZUMRA seulement » n'apparaît pas et son adresse répond « page introuvable ».
3. Compte 3 : sur l'appel, **Je veux rejoindre** mène à la demande d'adhésion de la ZUMRA (pas à un geste).

**Besoins de la ZUMRA.** Compte 3 : répondez au besoin. Comptes 1 et tout **responsable** reçoivent la notification et voient la réponse sous le besoin ; l'un d'eux **Accepte** : la conversation privée s'ouvre entre le compte 3 et **celui qui a accepté**. Celui qui marque le besoin résolu écrit le remerciement. Le compte 2 (simple membre) ne voit pas les réponses.

**Formation et Discussion.** L'onglet **Formation** (premier onglet) montre les savoirs partagés, ce que les membres savent faire et ce que des personnes veulent apprendre. Compte 3 : l'onglet **Discussion** n'existe pas. Comptes 1 et 2 : écrivez un message (1 000 caractères au plus) ; il apparaît dans l'ordre. **Supprimer** retire un message (chacun le sien ; le fondateur et les responsables ceux des autres).

**Reconnaissance.** Super administrateur : ouvrez la ZUMRA, carte **Reconnaissance par GAMAD** → **Reconnaître cette ZUMRA**. Le badge « Reconnue par GAMAD » s'affiche pour tout le monde (même sans compte), dans le Carrefour ZUMRA (filtre **Reconnues par GAMAD**) et dans le Monde. Le compte 1 est prévenu. **Retirer la reconnaissance** exige un motif. Ces actes sont inscrits au journal d'administration.

**Ce qui doit marcher de bout en bout (fin du Lot 3).** Une vraie ZUMRA invite trois personnes selon leurs capacités, publie dans son mini-fil, puis est reconnue ; un visiteur sans compte comprend la ZUMRA depuis le Monde public ; la Discussion n'est lisible que par les membres.

## 11. Essayer le Lot 4, première étape (4a) : créer et trouver un projet

Il vous faut le **compte 1** (fondateur d'une ZUMRA), le **compte 2** (simple membre de cette ZUMRA) et un **visiteur sans compte** (fenêtre privée).

1. Compte 1 : ouvrez votre ZUMRA, onglet **Projets** : « Aucun projet pour l'instant ». Touchez **Créer un projet**, écrivez un nom et un but (500 caractères au plus), **Créer le projet**. La page du projet s'ouvre : état **Idée**, but, histoire « Projet créé comme une idée ».
2. Sur la page du projet, **Changer l'état du projet** : **Démarrer le projet** (confirmation Oui/Non) puis **Marquer terminé**. Chaque changement s'ajoute à l'histoire, avec la date et votre nom. Une idée ne passe pas directement à « Terminé ».
3. L'en-tête de la ZUMRA affiche le **nombre réel de projets**. Le compte 2 voit l'onglet Projets et les pages, mais ni **Créer un projet** ni **Modifier le projet**.
4. Visiteur sans compte : ouvrez l'adresse du projet : tout se lit (nom, ZUMRA, état, but, histoire). **Carrefour ZUMRA → Voir les projets** (ou **Projets** dans la barre d'ordinateur) ouvre le **Carrefour Projets** : recherche, filtres **état**, **domaine** et **ville**, par ordre alphabétique.

**Ce qui n'existe pas encore :** étapes, avancées et réalisations (4b) ; équipe, besoins et soutiens (4c).

## 12. Essayer le Lot 4, deuxième étape (4b) : faire avancer un projet

Même comptes qu'au §11 : le **compte 1** (fondateur), le **compte 2** (simple membre), un **visiteur sans compte**. Ouvrez un projet de votre ZUMRA (onglet Projets).

1. Compte 1, onglet **Avancement** : « Aucune étape pour l'instant ». Sous **Nouvelle étape**, écrivez « Commander le tissu » puis **Ajouter l'étape**, et ajoutez-en deux autres. Un nom d'une seule lettre est refusé, avec un message sous le champ.
2. Changez l'état d'une étape avec la liste **À faire / En cours / Franchie** : le texte « 1 étape franchie sur 3 » et la barre suivent exactement vos étapes. **Monter** et **Descendre** changent l'ordre ; **Retirer** demande une confirmation (Oui/Non). Un projet a 20 étapes au plus.
3. Onglet **Fil** : **Publier une avancée** (ou **Publier une réalisation**). L'écran dit « Vous publiez au nom du projet… » et propose Public, Inscrits seulement, ZUMRA seulement. Après **Publier l'avancée**, la publication s'ouvre ; elle porte un lien **Projet : …**.
4. Le **Fil** global (onglet du bas) montre l'avancée, avec le filtre **Projets** qui n'affiche que les avancées et réalisations de projets. Elle apparaît aussi dans le Fil de la ZUMRA.
5. Compte 2 (simple membre) : il lit les étapes et le Fil du projet, y compris les publications « ZUMRA seulement », mais ne voit aucune commande d'étape ni **Publier une avancée**. Une avancée n'a **aucun bouton de réponse** pour l'instant. Visiteur sans compte : il lit les étapes et les publications publiques, pas celles « ZUMRA seulement » ni « Inscrits seulement ».

**Ce qui n'existe pas encore :** équipe, besoins et soutiens des projets (4c).

## 13. Essayer le Lot 4, troisième étape (4c) : équipe, besoins et soutiens

Il vous faut le **compte 1** (fondateur), le **compte 2** (membre de la ZUMRA), le **compte 3** (hors de la ZUMRA) et un **visiteur sans compte**. Ouvrez un projet de votre ZUMRA.

**Équipe.**

1. Compte 1, onglet **Équipe** : sous **Ajouter des membres de la ZUMRA**, touchez **Ajouter** à côté du compte 2 : il entre dans l'équipe et reçoit une notification. **Retirer de l'équipe** demande une confirmation et prévient la personne.
2. Compte 2 (retiré, ou pas encore dans l'équipe) : **Participer à ce projet**, écrivez un mot (280 caractères au plus) et **Je veux participer**. L'écran affiche « Demande envoyée » et **Retirer ma demande**. Compte 1 : la cloche et **Espace → À faire maintenant** annoncent « 1 demande de participation à traiter ». Sous **Demandes de participation**, **Accepter** fait entrer la personne (elle est prévenue) ; **Décliner** ne la prévient pas, et elle ne peut plus redemander.
3. Compte 3 : l'encadré dit de rejoindre d'abord la ZUMRA. Visiteur sans compte : il voit **le nombre** de personnes de l'équipe, pas leurs noms. Si le compte 2 quitte la ZUMRA, il sort de l'équipe.

**Besoins du projet.** Compte 1, onglet **Besoins** : **Publier un besoin** (même écran que pour une avancée, au nom du projet). Compte 3 : **Je peux aider** sur ce besoin ; le compte 1 reçoit la réponse et peut l'accepter, comme pour un besoin de ZUMRA. Le besoin apparaît aussi dans le **Fil** global (filtre **Projets**) avec le lien « Projet : … ».

**Soutenir une avancée.** Compte 3, sur une avancée (onglet **Fil** du projet ou Fil global) : **Je soutiens**. Le texte devient « Vous soutenez cette avancée » avec **Retirer mon soutien**, et chacun voit « 1 personne soutient » (jamais un nom). Les comptes 1 et 2 gèrent le projet : ils ne voient pas **Je soutiens** sur leurs avancées. Une réalisation n'a pas de bouton de soutien.

**Ce qui doit marcher de bout en bout (fin du Lot 4).** Un visiteur sans compte comprend où en est un projet réel en moins d'une minute : état, but, étapes franchies, avancées publiques, nombre de personnes de l'équipe et de soutiens.

## 14. Essayer le Lot 5, première étape (5a) : accueil public, charte et mentions légales

Il vous faut votre compte de **super administrateur** et une fenêtre privée (visiteur sans compte).

1. **Accueil public.** Fenêtre privée, ouvrez l'adresse du site : accroche, **Créer un compte** / **Se connecter**, « Comment cela marche » (quatre cartes), « Ce que GAMAD ne fait pas ». Une section « Ce qui se passe en ce moment » montre le nombre réel de personnes, de ZUMRA et de projets, et les plus récents ; sur un site vide, elle n'apparaît pas. Aucun lien « Charte » ni « Mentions légales » n'est visible : rien n'est adopté.
2. **Lire les projets de textes.** Super administrateur : **Espace → Administration → Paramètres → Charte et mentions légales**. **Lire la charte** ouvre le projet, marqué « Projet de charte, non adopté ». Dans la fenêtre privée, `/charte` répond « page introuvable ».
3. **Mentions légales.** Renseignez l'éditeur, le responsable de la publication, l'adresse e-mail de contact et l'hébergeur, **Enregistrer les champs**, puis **Lire les mentions légales**. Le bouton **Adopter les mentions légales** ne marche qu'une fois les quatre champs remplis.
4. **Adopter.** **Adopter la charte** (confirmation Oui/Non) : la charte devient publique, avec sa date. Dans la fenêtre privée, les liens **Charte de conduite** (et **Mentions légales** une fois adoptées) apparaissent en pied de page, et la page s'ouvre.
5. **Inscription.** Dans la fenêtre privée, **Créer un compte** demande maintenant de cocher « J'ai lu et j'accepte la charte de conduite » (lien vers la charte) : sans la case, l'inscription est refusée avec un message. **Retirer l'adoption** remet tout comme avant (page introuvable, case absente).
6. Chaque adoption, retrait et changement de champ est inscrit au journal d'administration (lisible dès la PR 5b).

**Ce qui n'existe pas encore :** rien de plus dans cette étape (la copie hors serveur et le pilote sont en 5c). Les personnes déjà inscrites ne sont pas invitées à accepter la charte (voir D-150).

## 15. Essayer le Lot 5, deuxième étape (5b) : journal, avis et suspension

Il vous faut trois comptes : le vôtre (super administrateur), un **modérateur** (désigné par vous) et une **personne ordinaire**.

1. **Journal.** Super administrateur : **Espace → Modération → Journal d'administration**. Les actes passés (adoption de la charte, masquages, etc.) s'y lisent en une phrase, du plus récent au plus ancien, avec le motif. Le filtre **Type d'acte** limite la liste. Compte modérateur : le même écran n'affiche que les actes de modération (pas les réglages, ni les listes).
2. **Envoyer un avis.** Modérateur : **Modération → Personnes**, cherchez la personne par son nom, écrivez le motif, **Envoyer l'avis**. Elle reçoit une notification « … vous a envoyé un avis de la modération » et « À faire maintenant » affiche **Expliquer**.
3. **S'expliquer.** Personne ordinaire : ouvrez la notification, lisez le motif et le délai (7 jours), écrivez votre explication, **Envoyer mon explication**. Modérateur et super administrateur reçoivent « … a répondu à l'avis de la modération ».
4. **Suspendre.** Avant la réponse et avant la fin du délai, le bouton **Suspendre la personne** n'apparaît pas (seul **Classer l'avis sans suite**). Après la réponse, saisissez un motif et **Suspendre la personne**. La personne suspendue qui ouvre le site voit « Votre compte est suspendu » avec le motif, et ne peut que se déconnecter. Les autres ne voient plus son profil ni ses publications.
5. **Rétablir.** **Modération → Personnes → Personnes suspendues → Rétablir le compte** (raison facultative). La personne est prévenue et retrouve tout.
6. **Explication d'une publication masquée.** Quand l'auteur d'une publication masquée s'explique, la modération reçoit « … a écrit son explication sur la publication masquée » ; la notification mène à l'onglet **Masquées**.
7. **Limites à vérifier.** Un modérateur ne peut envoyer d'avis ni à vous, ni à un autre modérateur, ni à lui-même ; vous seul décidez pour un modérateur. Chaque acte apparaît au journal.

**Ce qui n'existe pas encore :** suspension immédiate sans avis (voir D-152) ; recours dans l'application pour une personne suspendue (le contact des mentions légales est affiché une fois celles-ci adoptées).

## 16. Essayer le Lot 5, troisième étape (5c) : copie hors serveur et connexion faible

Il vous faut votre compte de super administrateur, les **accès de votre stockage objet compatible S3** (adresse https, nom du bucket, clé d'accès, clé secrète) et l'accès au serveur (une ligne de commande, pour l'essai de restauration seulement).

1. **Saisir les accès.** **Espace → Administration → Paramètres → Copie hors serveur → Ouvrir**. L'état dit « Non configurée : aucune sauvegarde n'existe hors du serveur ». Renseignez l'adresse, le bucket, les deux clés, puis **Enregistrer et tester la connexion**. Un petit fichier est écrit, relu et effacé sur votre stockage : si cela échoue, un message dit pourquoi et **rien n'est enregistré**.
2. **Noter la phrase de secours.** Elle s'affiche **une seule fois** dans un encadré. Notez-la hors du serveur (gestionnaire de mots de passe, papier en lieu sûr), puis **J'ai noté la phrase**. Sans elle, les copies sont illisibles pour tout le monde. Plus tard, **Réafficher la phrase de secours** redemande votre mot de passe et s'inscrit au journal.
3. **Première copie.** Sur le serveur : `sudo gamad-reseau-copie-hors-serveur copier`. Elle répond « Copie hors serveur réussie et vérifiée ». Rechargez la page des Paramètres : « Dernière copie réussie » affiche l'heure. Sans rien faire, la copie se lance seule chaque nuit vers 4 h 30.
4. **Chez le fournisseur.** Ouvrez votre stockage : vous n'y voyez que des noms illisibles et des fichiers illisibles. C'est voulu.
5. **L'essai de restauration (obligatoire avant la fin du Lot 5).** Sur le serveur : `sudo gamad-reseau-copie-hors-serveur essai-restauration`. Il rapatrie la dernière copie, la restaure dans une base temporaire, compte les tables, les migrations et les personnes, supprime la base temporaire et répond « ESSAI DE RESTAURATION RÉUSSI ». La page des Paramètres l'affiche ensuite. **La base de production n'est jamais touchée.** Si le message n'apparaît pas, le Lot 5 n'est pas terminé : notez ce que le serveur répond.
6. **Si la copie tombe en panne.** Si aucune copie n'a réussi depuis plus de 36 heures, un cadre rouge l'indique dans les Paramètres. `sudo gamad-reseau-copie-hors-serveur etat` donne le détail.
7. **Changer d'accès plus tard.** Dans le même écran, les clés laissées vides sont conservées. La phrase de secours ne se change pas.
8. **Connexion faible.** Sur un téléphone en 3G ou en réseau lent, ouvrez l'accueil, le Fil, une ZUMRA : la première ouverture est la plus longue (script de l'application, environ 180 ko transférés) ; les suivantes sont presque immédiates. Mesures de référence ci-dessous.

**Mesures de référence (téléphone simulé 390 px, connexion simulée par le navigateur, serveur de développement ; ce ne sont pas des mesures sur un vrai réseau mobile) :**

| Connexion simulée | Avant les correctifs : première ouverture / suivantes | Après (compression et cache) : première ouverture / suivantes |
| --- | --- | --- |
| Lente : 400 kb/s, 400 ms | 10,8 s / 3,5 s (environ 500 ko) | 4,5 s / 0,5 à 0,6 s (environ 182 ko, puis 3 à 10 ko) |

Sur une connexion 3G à 1,6 Mb/s et 150 ms, la première ouverture avant correctifs prenait 2,8 s. Toutes les pages mesurées (accueil, connexion, inscription, ZUMRA, projets, Fil, personnes, Espace, notifications) ont le même poids de base : script de l'application (83 ko compressés), feuille de style (9 ko), cinq polices (60 ko), page (3 à 7 ko).

**Ce qui n'existe pas encore :** alerte par e-mail si la copie échoue (l'alerte est dans les Paramètres) ; changement de la phrase de secours ; copie des journaux.

## 17. Préparer et dérouler le pilote fermé

Le pilote réunit **30 à 50 personnes et 3 ZUMRA pendant 4 semaines** (D-158). Ce guide est une proposition de travail : adaptez-la. L'ouverture publique reste votre décision.

**A. Avant d'inviter quelqu'un (cochez chaque ligne)**
- [ ] Le site répond en https sur votre domaine (accueil visible dans une fenêtre privée).
- [ ] Les e-mails partent : **Paramètres → Courriel → Envoyer un e-mail de test** reçu, et un compte de test reçoit bien son e-mail de vérification.
- [ ] La **charte de conduite** et les **mentions légales** sont relues, complétées et **adoptées** (Paramètres → Charte et mentions légales). L'inscription demande alors de cocher la charte.
- [ ] La **copie hors serveur** est configurée, la première copie est réussie et l'**essai de restauration a réussi** (section 16).
- [ ] Au moins **deux modérateurs** sont désignés, en plus de vous, et chacun a ouvert **Modération** une fois.
- [ ] Vous avez essayé « Signaler » vous-même sur une publication de test (section 7).
- [ ] Vous avez parcouru les sections 2 à 15 sur votre téléphone, sans écran vide ou bouton mort.

**B. Les 4 semaines**
- *Semaine 1 : poser le décor.* Vous choisissez 3 fondateurs de ZUMRA et leur donnez l'adresse. Chaque fondateur crée sa ZUMRA, invite les premiers membres et publie une première offre ou un premier besoin. Objectif : environ 10 personnes, 3 ZUMRA qui existent vraiment.
- *Semaine 2 : faire circuler.* Invitez le reste des 30 à 50 personnes (adresse donnée de la main à la main : il n'existe pas de lien d'invitation, voir D-158). Chacun complète son profil, répond à une publication (geste), écrit au moins un message. Objectif : des conversations réelles, des « À faire maintenant » traités.
- *Semaine 3 : agir ensemble.* Chaque ZUMRA lance un projet, avance ses étapes, publie une avancée ; les besoins reçoivent des remerciements. **Scénarios de modération à jouer pendant cette semaine** (condition D-078) : (1) une personne signale une publication ; le modérateur la masque avec un motif ; l'auteur s'explique ; le modérateur la rétablit. (2) Un signalement classé sans suite. (3) Un **avis** à un compte de test, sa réponse, puis le classement ; **ne suspendez pas** une vraie personne pour essayer, utilisez un compte de test et rétablissez-le. (4) Vous lisez le **journal** : chaque acte y est.
- *Semaine 4 : bilan.* Posez-vous les questions de la section C et décidez : continuer, corriger, ou ouvrir.

**C. Chaque jour pendant le pilote (5 minutes)**
1. **Espace → À faire maintenant** : ce qui vous attend.
2. **Modération** : signalements à examiner, avis en cours.
3. **Paramètres → Copie hors serveur** : « Dernière copie réussie » date de moins de 36 heures.
4. Une réponse aux personnes qui vous écrivent : la modération est la première promesse de GAMAD.

**D. Il faut arrêter ou suspendre le pilote si**
- des données sont perdues ou un compte mélangé avec un autre ;
- l'e-mail ou le téléphone d'une personne est visible par quelqu'un d'autre ;
- la copie hors serveur est en échec depuis plus de 36 heures et ne se répare pas ;
- un cas de harcèlement n'est pas traité sous 48 heures.

**E. Pour décider l'ouverture publique (votre décision, à inscrire dans DECISIONS.md)**
- [ ] Les quatre scénarios de modération de la semaine 3 ont été joués, par au moins deux modérateurs.
- [ ] Aucune cause d'arrêt (section D) n'est survenue, ou elle est corrigée et expliquée.
- [ ] Une copie hors serveur a été restaurée avec succès **pendant** le pilote (refaites l'essai de restauration en semaine 4).
- [ ] Les personnes du pilote ont pu utiliser le site sur leur téléphone, y compris en réseau lent.
- [ ] La charte et les mentions légales sont adoptées et visibles en pied de page.

**Ce que le pilote ne fait pas :** pas de paiement (Lot 6), pas de mécanisme d'invitation ou de fermeture des inscriptions (à décider si le pilote doit être fermé techniquement), pas de statistiques d'usage au-delà des chiffres réels déjà visibles (personnes, ZUMRA, projets).

## 18. Essayer le Lot 5.1 : Carrefour Besoins et Découvrir

Il vous faut un téléphone, un second compte, et au moins un besoin publié (Agir → « J'ai besoin ») par une personne, un autre par une ZUMRA, un autre au nom d'un projet.

1. **Découvrir (téléphone).** Dans la barre du bas, touchez **Découvrir** : une fenêtre « Que cherchez-vous ? » monte du bas, avec trois choix : **Personnes**, **Besoins**, **Projets**. Touchez la croix ou en dehors de la fenêtre pour la fermer. Chaque choix mène à son Carrefour.
2. **Sans fenêtre.** Si votre navigateur n'exécute pas le JavaScript, **Découvrir** ouvre la page `/decouvrir` qui montre les mêmes trois choix.
3. **Ordinateur.** La barre du haut affiche **Fil, Personnes, Besoins, Projets, ZUMRA**.
4. **Carrefour Besoins.** Ouvrez **Besoins**. Chaque carte dit qui exprime le besoin (la personne, la ZUMRA ou le projet) et son état. Par défaut, seuls les besoins **ouverts** s'affichent ; le premier menu propose « En cours », « Résolu » et « Tous les états ».
5. **Chercher.** Tapez un mot du besoin ou une capacité demandée (accents et majuscules sans importance). Filtrez par domaine, ville, ou par émetteur (Personnes, ZUMRA, Projets seulement). **Effacer la recherche** remet tout.
6. **Répondre.** Touchez **Voir le besoin** : vous arrivez sur la publication, où se trouve le bouton d'action (« Je peux aider ») et « Signaler ». Le Carrefour ne répond pas lui-même.
7. **Sans compte.** Dans une fenêtre privée, `/besoins` s'ouvre ; seuls les besoins publics s'y voient. Un besoin masqué par la modération n'apparaît jamais, ni pour son auteur ni pour la modération.

**Ce qui n'existe pas encore :** les organisations comme émetteurs de besoins (« une prochaine fois », D-160) ; un sélecteur à trois onglets en haut des Carrefours.

## 19. Ce qu'il faut regarder à chaque écran

- [ ] Le texte se lit sans zoomer, rien ne dépasse de l'écran, aucun défilement de côté.
- [ ] Les boutons sont faciles à toucher du pouce.
- [ ] En tournant le téléphone ou sur ordinateur (fenêtre large), la barre du haut affiche **Fil** et votre initiale qui mène à Espace.
- [ ] Les messages d'erreur sont clairs et en français.
- [ ] Tout bouton ou lien visible mène à une vraie page.

## 20. Les mises à jour se font toutes seules

Chaque fois qu'un changement est fusionné dans `main`, le site se met à jour tout seul en **5 à 10 minutes** : rechargez la page après ce délai.
Si une mise à jour échoue, la version actuelle reste en ligne et l'échec est noté dans un journal côté serveur ; si une mise à jour
casse le site, le serveur revient seul à la version précédente. En cas de doute, dites-le-moi : la personne qui administre le serveur
sait revenir en arrière (voir `deploy/LISEZMOI.md`, réservé à l'administration).

Les données sont sauvegardées chaque nuit (7 jours conservés).

## 21. Ce qui n'existe pas encore (c'est normal)

« Commenter », « Partager », « Suivre », « Inviter », recherche générale, paiement : ces modules sont masqués tant que leur lot
n'est pas construit.

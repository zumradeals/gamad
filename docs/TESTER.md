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

## 4. Ce qu'il faut regarder à chaque écran

- [ ] Le texte se lit sans zoomer, rien ne dépasse de l'écran, aucun défilement de côté.
- [ ] Les boutons sont faciles à toucher du pouce.
- [ ] En tournant le téléphone ou sur ordinateur (fenêtre large), la barre du haut affiche **Fil** et votre initiale qui mène à Espace.
- [ ] Les messages d'erreur sont clairs et en français.
- [ ] Tout bouton ou lien visible mène à une vraie page.

## 5. Les mises à jour se font toutes seules

Chaque fois qu'un changement est fusionné dans `main`, le site se met à jour tout seul en **5 à 10 minutes** : rechargez la page après ce délai.
Si une mise à jour échoue, la version actuelle reste en ligne et l'échec est noté dans un journal côté serveur ; si une mise à jour
casse le site, le serveur revient seul à la version précédente. En cas de doute, dites-le-moi : la personne qui administre le serveur
sait revenir en arrière (voir `deploy/LISEZMOI.md`, réservé à l'administration).

Les données sont sauvegardées chaque nuit (7 jours conservés).

## 6. Ce qui n'existe pas encore (c'est normal)

Profil, ZUMRA, publications, gestes, recherche, notifications, paiement, bouton « Agir » : ces modules sont masqués tant que leur lot
n'est pas construit.

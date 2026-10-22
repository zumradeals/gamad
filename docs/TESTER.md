# TESTER — essayer le lot en cours sur un téléphone

Ce document explique comment le Président essaie le lot 0 sur son téléphone, puis ce qu'il doit vérifier.
Seul le Président marque un lot « Terminé » (`docs/LOTS.md`).

## 1. Mettre l'application à disposition du téléphone

Aucun hébergement de test n'est encore décidé. En attendant, l'application se lance sur un ordinateur,
et le téléphone s'y connecte par le même réseau Wi-Fi.

1. Installer l'application sur l'ordinateur selon `README.md`.
2. Trouver l'adresse de l'ordinateur sur le réseau (exemple : `192.168.1.20`).
3. Dans le fichier `.env`, remplacer la ligne `APP_URL=…` par `APP_URL=http://192.168.1.20:8000`
   (les liens des e-mails utilisent cette adresse).
4. Lancer : `php artisan serve --host=0.0.0.0 --port=8000`
5. Sur le téléphone, ouvrir `http://192.168.1.20:8000`.

## 2. Récupérer le lien de vérification de l'e-mail

En local, les e-mails ne partent pas : ils sont écrits dans `storage/logs/laravel.log`. Pour afficher le dernier
lien de vérification :

```sh
grep -o 'http[^ "<>]*email/verify[^ "<>]*' storage/logs/laravel.log | tail -1 | sed 's/&amp;/\&/g'
```

Copier ce lien et l'ouvrir sur le téléphone. Pour le lien « mot de passe oublié », remplacer `email/verify` par
`reset-password`.

## 3. Parcours à vérifier (critères de fin du lot 0)

Sur le téléphone :

- [ ] L'accueil affiche le titre, l'accroche, les boutons « Créer un compte » et « Se connecter », et la devise en petit.
- [ ] Je crée un compte (nom affiché, e-mail, mot de passe). L'écran m'invite à vérifier mon e-mail.
- [ ] J'ouvre le lien de vérification : j'arrive sur le Fil, qui affiche « Le Fil se remplira bientôt. »
- [ ] La barre du bas n'affiche que **Fil** et **Espace**.
- [ ] Dans Espace, je vois mon nom et mon e-mail, puis je me déconnecte.
- [ ] Je me reconnecte. Un mauvais mot de passe affiche un message clair ; après 5 essais ratés, la connexion est bloquée un moment.
- [ ] « Mot de passe oublié » m'envoie un lien qui me permet de choisir un nouveau mot de passe.
- [ ] Dans Espace, « Supprimer mon compte » demande mon mot de passe, puis supprime définitivement le compte.

Sur l'ordinateur (fenêtre large) :

- [ ] La barre du haut affiche **Fil** et l'avatar (initiale) qui mène à Espace. Rien d'autre.

## 4. Super administrateur

Après avoir créé votre compte et vérifié votre e-mail :

```sh
php artisan gamad:super-admin votre-adresse@exemple.org
```

Le message confirme la promotion. Vérification dans le journal :

```sh
php artisan tinker --execute="dump(App\Models\AdminAction::latest()->first()->toArray());"
```

## 5. Ce qui n'existe pas encore (c'est normal)

Profil, ZUMRA, publications, gestes, recherche, notifications, paiement, bouton « Agir » : ces modules sont
masqués tant que leur lot n'est pas construit.

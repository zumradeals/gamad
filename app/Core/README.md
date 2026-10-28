# App\Core — la face profonde de GAMAD

Le Core partage le dépôt, l'application et la base PostgreSQL du réseau social. Il ne connaît pas les domaines du Social
(`App\Services`, `App\Livewire`, `App\Models` hors `User`) : `tests/Feature/Core/ArchitectureTest.php` le vérifie. Le Social consomme le Core.

## Identity

- **`AccountCloser::close($user)`** est la seule porte de suppression d'un compte (le modèle `User` refuse `delete()` autrement).
  Dans une transaction : contrôles préalables de tous les participants, préparations, suppression, suites, trace `identity_events`,
  liste des fichiers à effacer. Après validation : effacement des fichiers. Une exception annule tout.
- **Participants** (`App\Services\AccountClosure\*`, ordre dans `AppServiceProvider`) : un domaine ajoute ce qu'il doit faire à la
  fermeture en implémentant `AccountClosureParticipant`. Un fichier à effacer se désigne avec `ClosureContext::purgeFile()`, jamais en l'effaçant.
- **`Credentials`** et **`Sessions`** : changement de mot de passe, réinitialisation, sessions lisibles et révocables (pilote de session `database`).
- **`identity_events`** : trace technique en ajout seul (trigger PostgreSQL). Jamais d'IP, de navigateur ni de secret.

## Files

- **`FilePurger`** : `enqueue()` dans la transaction, `drain()` après validation. Une ligne `file_purges` subsiste tant que le fichier n'est pas parti.
- **Reprise garantie par l'application** : `ResumeFilePurges` (middleware web, après l'envoi de la réponse) reprend un lot de fichiers en
  attente au plus une fois toutes les 5 minutes, sans planificateur ni nouvelle fermeture de compte. La commande
  `gamad:purger-fichiers-en-attente` (planifiée toutes les 15 minutes dans `routes/console.php`) fait le même travail quand `schedule:run`
  tourne ; rien n'impose de l'installer. `FilePurger::pending()` donne les fichiers à reprendre et ceux abandonnés après 10 échecs.
- L'effacement attend la validation de la transaction **la plus externe** (`DB::afterCommit`) : une transaction englobante annulée
  ne touche ni le compte, ni les données, ni les fichiers.

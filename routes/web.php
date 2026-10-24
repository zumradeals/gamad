<?php

use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\GeniusPayWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PublicationBookmarkController;
use App\Http\Controllers\PublicationMediaController;
use App\Http\Controllers\SuspendedController;
use App\Http\Controllers\TextesController;
use App\Http\Controllers\WelcomeController;
use App\Livewire\AvisModeration;
use App\Livewire\CarrefourBesoins;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\CarrefourProjets;
use App\Livewire\CarrefourZumras;
use App\Livewire\CompanyCreate;
use App\Livewire\CompanyDirectory;
use App\Livewire\CompanyModeration;
use App\Livewire\CompanyPage;
use App\Livewire\ContributionsAdministration;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\FileModeration;
use App\Livewire\FormulaireProjet;
use App\Livewire\FormulaireZumra;
use App\Livewire\InviterPersonnes;
use App\Livewire\JournalAdministration;
use App\Livewire\MesAttestations;
use App\Livewire\MesEnregistrements;
use App\Livewire\ModerationCommentaires;
use App\Livewire\ModerationConfiance;
use App\Livewire\ModerationPersonnes;
use App\Livewire\ModifierProfil;
use App\Livewire\MondeProjet;
use App\Livewire\MondeZumra;
use App\Livewire\PageContribution;
use App\Livewire\PageConversation;
use App\Livewire\PageNotifications;
use App\Livewire\PagePortefeuille;
use App\Livewire\PagePublication;
use App\Livewire\PaiementsAdministration;
use App\Livewire\Parametres;
use App\Livewire\ParametresCopie;
use App\Livewire\ParametresDomaines;
use App\Livewire\ParametresPaiement;
use App\Livewire\ParametresPortefeuille;
use App\Livewire\ParametresTerritoires;
use App\Livewire\ParametresTextes;
use App\Livewire\PublierPublication;
use App\Livewire\RapportContributions;
use App\Livewire\SignalerProfil;
use App\Livewire\SignalerPublication;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// La charte et les mentions légales n'existent pour le public qu'une fois adoptées par le Président (D-146).
Route::get('/charte', [TextesController::class, 'charte'])->name('charte');
Route::get('/mentions-legales', [TextesController::class, 'mentions'])->name('mentions');

// Le Carrefour Personnes et le Monde d'une personne sont ouverts aux visiteurs sans compte ; chaque profil applique
// sa propre visibilité (D-067).
Route::view('/decouvrir', 'pages.decouvrir')->name('decouvrir');
Route::get('/besoins', CarrefourBesoins::class)->name('besoins.index');
Route::get('/personnes', CarrefourPersonnes::class)->name('personnes.index');
Route::get('/personnes/{profile:public_id}', [PersonController::class, 'show'])->name('personnes.show');
Route::get('/personnes/{profile:public_id}/photo', [PersonController::class, 'photo'])->name('personnes.photo');

// Les entreprises non vérifiées n'apparaissent ni dans l'annuaire ni dans les profils publics.
Route::get('/entreprises', CompanyDirectory::class)->name('entreprises.index');
Route::get('/entreprises/{company}', CompanyPage::class)->where('company', '.+-[a-z0-9]{8}')->name('entreprises.show');

// Le Carrefour ZUMRA et l'en-tête d'une ZUMRA sont publics ; les membres demandent un compte vérifié (D-112).
Route::get('/zumra', CarrefourZumras::class)->name('zumras.index');
Route::get('/rapport-contributions', RapportContributions::class)->middleware('wallets')->name('rapport-contributions');
Route::get('/zumra/{zumra}', MondeZumra::class)->whereUuid('zumra')->name('zumras.show');

// Un projet est public (D-005) : le Carrefour et la page d'un projet se lisent sans compte.
Route::get('/projets', CarrefourProjets::class)->name('projets.index');
Route::get('/projets/{project}', MondeProjet::class)->whereUuid('project')->name('projets.show');

// Une publication publique se lit sans compte ; répondre demande un compte (D-085). Une publication interdite répond 404.
Route::get('/publications/{publication}/fichier', PublicationMediaController::class)->whereUuid('publication')->name('publications.media');
Route::get('/publications/{publication}', PagePublication::class)->whereUuid('publication')->name('publications.show');

Route::get('/compte-suspendu', SuspendedController::class)->middleware('auth')->name('suspendu');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/entreprises/creer', CompanyCreate::class)->name('entreprises.create');
    Route::get('/moderation/entreprises', CompanyModeration::class)->middleware('can:super-admin')->name('moderation.entreprises');
    Route::get('/moderation/entreprises/{company}/documents/{document}', CompanyDocumentController::class)
        ->whereUuid('document')->middleware('can:super-admin')->name('entreprises.document');
    Route::get('/espace/attestations', MesAttestations::class)->name('espace.attestations');
    Route::get('/espace/enregistrements', MesEnregistrements::class)->name('espace.enregistrements');
    Route::post('/publications/{publication}/enregistrer', PublicationBookmarkController::class)->whereUuid('publication')->name('publications.bookmark');
    Route::get('/moderation/confiance', ModerationConfiance::class)->middleware('can:super-admin')->name('moderation.confiance');
    Route::get('/personnes/{profile:public_id}/signaler', SignalerProfil::class)->name('personnes.signaler');
    Route::get('/zumra/creer', FormulaireZumra::class)->name('zumras.create');
    Route::get('/zumra/{zumra}/inviter', InviterPersonnes::class)->whereUuid('zumra')->name('zumras.invite');
    Route::get('/zumra/{zumra}/publier/{slug}', PublierPublication::class)->whereUuid('zumra')->name('zumras.publier');
    Route::get('/zumra/{zumra}/modifier', FormulaireZumra::class)->whereUuid('zumra')->name('zumras.edit');
    Route::get('/zumra/{zumra}/projets/creer', FormulaireProjet::class)->whereUuid('zumra')->name('projets.create');
    Route::get('/projets/{project}/modifier', FormulaireProjet::class)->whereUuid('project')->name('projets.edit');
    Route::get('/projets/{project}/publier/{slug}', PublierPublication::class)->whereUuid('project')->name('projets.publier');
    Route::get('/fil', Fil::class)->name('fil');
    Route::view('/agir', 'pages.agir')->name('agir');
    Route::get('/agir/{slug}', PublierPublication::class)->name('agir.publier');
    Route::get('/publications/{publication}/signaler', SignalerPublication::class)->whereUuid('publication')->name('publications.signaler');
    Route::get('/moderation', FileModeration::class)->middleware('can:moderate')->name('moderation');
    Route::get('/moderation/personnes', ModerationPersonnes::class)->middleware('can:moderate')->name('moderation.personnes');
    Route::get('/moderation/commentaires', ModerationCommentaires::class)->middleware('can:moderate')->name('moderation.commentaires');
    Route::get('/moderation/journal', JournalAdministration::class)->middleware('can:moderate')->name('moderation.journal');
    Route::get('/espace/portefeuille', PagePortefeuille::class)->middleware('wallets')->name('portefeuille');
    Route::get('/zumra/{zumra}/portefeuille', PagePortefeuille::class)->whereUuid('zumra')->middleware('wallets')->name('zumras.portefeuille');
    Route::get('/espace/contribution', PageContribution::class)->middleware('wallets')->name('contribution');
    Route::get('/zumra/{zumra}/contribution', PageContribution::class)->whereUuid('zumra')->middleware('wallets')->name('zumras.contribution');
    Route::get('/espace/avis', AvisModeration::class)->name('avis');
    Route::get('/conversations/{conversation}', PageConversation::class)->whereUuid('conversation')->name('conversations.show');
    Route::get('/notifications', PageNotifications::class)->name('notifications');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
    Route::get('/espace', Espace::class)->name('espace');
    Route::get('/espace/profil', ModifierProfil::class)->name('espace.profil');
    Route::get('/espace/parametres', Parametres::class)->middleware('can:super-admin')->name('espace.parametres');
    Route::get('/espace/parametres/textes', ParametresTextes::class)->middleware('can:super-admin')->name('espace.parametres.textes');
    Route::get('/espace/parametres/paiement', ParametresPaiement::class)->middleware('can:super-admin')->name('espace.parametres.paiement');
    Route::get('/espace/contributions', ContributionsAdministration::class)->middleware('can:super-admin')->name('espace.contributions');
    Route::get('/espace/paiements', PaiementsAdministration::class)->middleware('can:super-admin')->name('espace.paiements');
    Route::get('/espace/parametres/portefeuille', ParametresPortefeuille::class)->middleware('can:super-admin')->name('espace.parametres.portefeuille');
    Route::get('/espace/parametres/copie', ParametresCopie::class)->middleware('can:super-admin')->name('espace.parametres.copie');
    Route::get('/espace/parametres/domaines', ParametresDomaines::class)->middleware('can:super-admin')->name('espace.parametres.domaines');
    Route::get('/espace/parametres/territoires', ParametresTerritoires::class)->middleware('can:super-admin')->name('espace.parametres.territoires');
});

// Rappel serveur de GeniusPay : public (sans compte), protégé par la signature du corps et limité en débit (D-169).
Route::post('/webhooks/geniuspay', [GeniusPayWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('webhooks.geniuspay');

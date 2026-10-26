<?php

use App\Http\Controllers\AdClickController;
use App\Http\Controllers\AdImageController;
use App\Http\Controllers\AideController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CompanyBrandingController;
use App\Http\Controllers\CompanyContactController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyDocumentController;
use App\Http\Controllers\CompanyExperienceController;
use App\Http\Controllers\CompanyExperienceReviewController;
use App\Http\Controllers\CompanyFollowController;
use App\Http\Controllers\CompanyPostController;
use App\Http\Controllers\CompanyReportController;
use App\Http\Controllers\CompanyResubmitController;
use App\Http\Controllers\CompanyVisualController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GeniusPayWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PublicationBookmarkController;
use App\Http\Controllers\PublicationMediaController;
use App\Http\Controllers\SuspendedController;
use App\Http\Controllers\TextesController;
use App\Http\Controllers\WelcomeController;
use App\Livewire\AvisModeration;
use App\Livewire\CarrefourBesoins;
use App\Livewire\CarrefourContributions;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\CarrefourProjets;
use App\Livewire\CarrefourZumras;
use App\Livewire\CompanyCreate;
use App\Livewire\CompanyDirectory;
use App\Livewire\CompanyModeration;
use App\Livewire\ContributionsAdministration;
use App\Livewire\CreerCampagne;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\FileModeration;
use App\Livewire\FormulaireProjet;
use App\Livewire\FormulaireZumra;
use App\Livewire\InviterPersonnes;
use App\Livewire\JournalAdministration;
use App\Livewire\MesAttestations;
use App\Livewire\MesCampagnes;
use App\Livewire\MesContributions;
use App\Livewire\MesEnregistrements;
use App\Livewire\MesProjets;
use App\Livewire\MessagesContact;
use App\Livewire\MesZumras;
use App\Livewire\ModerationAnnonces;
use App\Livewire\ModerationCommentaires;
use App\Livewire\ModerationConfiance;
use App\Livewire\ModerationPersonnes;
use App\Livewire\ModifierProfil;
use App\Livewire\MondeZumra;
use App\Livewire\OpportunityCatalog;
use App\Livewire\OpportunityForm;
use App\Livewire\OpportunityManager;
use App\Livewire\OpportunityPeople;
use App\Livewire\OpportunityResponses;
use App\Livewire\PageContribution;
use App\Livewire\PageConversation;
use App\Livewire\PageNotifications;
use App\Livewire\PagePortefeuille;
use App\Livewire\PageProposition;
use App\Livewire\PagePublication;
use App\Livewire\PageServicesGamad;
use App\Livewire\PaiementsAdministration;
use App\Livewire\Parametres;
use App\Livewire\ParametresContributions;
use App\Livewire\ParametresCopie;
use App\Livewire\ParametresDomaines;
use App\Livewire\ParametresPaiement;
use App\Livewire\ParametresPortefeuille;
use App\Livewire\ParametresPublicite;
use App\Livewire\ParametresTerritoires;
use App\Livewire\ParametresTextes;
use App\Livewire\ParametresZumra;
use App\Livewire\ProposerContribution;
use App\Livewire\PublierBesoin;
use App\Livewire\PublierPublication;
use App\Livewire\RapportContributions;
use App\Livewire\ResultatsCampagnes;
use App\Livewire\SignalerProfil;
use App\Livewire\SignalerPublication;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// La charte et les mentions légales n'existent pour le public qu'une fois adoptées par le Président (D-146).
Route::get('/aide', AideController::class)->name('aide');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
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
Route::get('/opportunites', OpportunityCatalog::class)->name('opportunites.index');
Route::get('/opportunites/{opportunity}', [OpportunityController::class, 'show'])->whereUuid('opportunity')->name('opportunites.show');
Route::get('/entreprises', CompanyDirectory::class)->name('entreprises.index');
Route::get('/entreprises/{company}', [CompanyController::class, 'show'])->where('company', '.+-[a-z0-9]{8}')->name('entreprises.show');
Route::get('/entreprises/{company}/visuel/{kind}', CompanyVisualController::class)
    ->where('company', '.+-[a-z0-9]{8}')->where('kind', 'logo|banner|service-[0-9]')->name('entreprises.visual');

// Le Carrefour ZUMRA et l'en-tête d'une ZUMRA sont publics ; les membres demandent un compte vérifié (D-112).
Route::get('/zumra', CarrefourZumras::class)->name('zumras.index');
Route::get('/rapport-contributions', RapportContributions::class)->middleware('wallets')->name('rapport-contributions');
Route::get('/zumra/{zumra}', MondeZumra::class)->whereUuid('zumra')->name('zumras.show');

// Un projet est public (D-005) : le Carrefour et la page d'un projet se lisent sans compte.
Route::get('/projets', CarrefourProjets::class)->name('projets.index');
// Un projet est privé (P1, D-206) : son porteur et ses collaborateurs ouvrent le dossier ; tout autre n'y trouve, si le porteur l'a
// publiée, que la présentation publique limitée. Sinon, la page n'existe pas.
Route::get('/projets/{project}', function (Project $project) {
    $viewer = request()->user();
    $workspace = Gate::forUser($viewer)->allows('view', $project);
    $showcase = Gate::forUser($viewer)->allows('viewShowcase', $project);
    abort_unless($workspace || $showcase, 404);

    return view('pages.projet', ['project' => $project, 'showcase' => $showcase && (! $workspace || request('vue') === 'publique')]);
})->whereUuid('project')->name('projets.show');

// Une publication publique se lit sans compte ; répondre demande un compte (D-085). Une publication interdite répond 404.
Route::get('/publications/{publication}/fichier', PublicationMediaController::class)->whereUuid('publication')->name('publications.media');
Route::get('/publications/{publication}', PagePublication::class)->whereUuid('publication')->name('publications.show');

Route::get('/compte-suspendu', SuspendedController::class)->middleware('auth')->name('suspendu');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/entreprises/creer', CompanyCreate::class)->name('entreprises.create');
    Route::get('/entreprises/{company}/contacter', [CompanyContactController::class, 'create'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.contact');
    Route::post('/entreprises/{company}/contacter', [CompanyContactController::class, 'store'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.contact.store');
    Route::get('/entreprises/{company}/corriger', [CompanyResubmitController::class, 'edit'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.resubmit');
    Route::post('/entreprises/{company}/corriger', [CompanyResubmitController::class, 'submit'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.resubmit.store');
    Route::get('/entreprises/{company}/signaler', [CompanyReportController::class, 'create'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.reports.create');
    Route::post('/entreprises/{company}/signaler', [CompanyReportController::class, 'store'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.reports.store');
    Route::get('/entreprises/{company}/identite', [CompanyBrandingController::class, 'edit'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.branding.edit');
    Route::post('/entreprises/{company}/identite', [CompanyBrandingController::class, 'update'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.branding.update');
    Route::get('/entreprises/{company}/publier', [CompanyPostController::class, 'create'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.posts.create');
    Route::post('/entreprises/{company}/publier', [CompanyPostController::class, 'store'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.posts.store');
    // Module Opportunités : seules les entreprises publient ; les personnes consultent, sauvegardent et répondent.
    Route::get('/entreprises/{company}/opportunites', OpportunityManager::class)->where('company', '.+-[a-z0-9]{8}')->name('entreprises.opportunities');
    Route::get('/entreprises/{company}/opportunites/nouvelle', OpportunityForm::class)->where('company', '.+-[a-z0-9]{8}')->name('opportunites.create');
    Route::get('/opportunites/{opportunity}/modifier', OpportunityForm::class)->whereUuid('opportunity')->name('opportunites.edit');
    Route::get('/opportunites/{opportunity}/reponses', OpportunityResponses::class)->whereUuid('opportunity')->name('opportunites.responses');
    Route::get('/opportunites/{opportunity}/personnes', OpportunityPeople::class)->whereUuid('opportunity')->name('opportunites.people');
    Route::post('/opportunites/{opportunity}/sauvegarder', [OpportunityController::class, 'save'])->whereUuid('opportunity')->name('opportunites.save');
    Route::get('/opportunites/{opportunity}/repondre', [OpportunityController::class, 'respond'])->whereUuid('opportunity')->name('opportunites.respond');
    Route::post('/opportunites/{opportunity}/repondre', [OpportunityController::class, 'storeResponse'])->whereUuid('opportunity')->middleware('throttle:20,1')->name('opportunites.respond.store');
    Route::post('/opportunites/reponses/{response}/retirer', [OpportunityController::class, 'withdraw'])->whereUuid('response')->name('opportunites.withdraw');
    Route::get('/opportunites/reponses/{response}/document', [OpportunityController::class, 'attachment'])->whereUuid('response')->name('opportunites.attachment');
    Route::get('/espace/opportunites', [OpportunityController::class, 'mine'])->name('espace.opportunites');
    Route::get('/espace/experiences-professionnelles', [CompanyExperienceController::class, 'index'])->name('espace.company-experiences');
    Route::post('/espace/experiences-professionnelles', [CompanyExperienceController::class, 'store'])->name('espace.company-experiences.store');
    Route::post('/espace/experiences-professionnelles/{experience}/retirer', [CompanyExperienceController::class, 'withdraw'])
        ->whereUuid('experience')->name('espace.company-experiences.withdraw');
    Route::post('/espace/experiences-professionnelles/{experience}/visibilite', [CompanyExperienceController::class, 'visibility'])
        ->whereUuid('experience')->name('espace.company-experiences.visibility');
    Route::get('/entreprises/{company}/confirmations', [CompanyExperienceReviewController::class, 'index'])
        ->where('company', '.+-[a-z0-9]{8}')->name('entreprises.experiences.review');
    Route::post('/entreprises/{company}/confirmations/{experience}', [CompanyExperienceReviewController::class, 'decide'])
        ->where('company', '.+-[a-z0-9]{8}')->whereUuid('experience')->name('entreprises.experiences.decision');
    Route::post('/entreprises/{company}/suivre', CompanyFollowController::class)->where('company', '.+-[a-z0-9]{8}')->name('entreprises.follow');
    Route::get('/moderation/entreprises', CompanyModeration::class)->middleware('can:super-admin')->name('moderation.entreprises');
    Route::get('/moderation/entreprises/{company}/documents/{document}', CompanyDocumentController::class)
        ->whereUuid('document')->middleware('can:super-admin')->name('entreprises.document');
    Route::get('/espace/attestations', MesAttestations::class)->name('espace.attestations');
    Route::get('/espace/enregistrements', MesEnregistrements::class)->name('espace.enregistrements');
    Route::post('/publications/{publication}/enregistrer', PublicationBookmarkController::class)->whereUuid('publication')->name('publications.bookmark');
    Route::get('/moderation/confiance', ModerationConfiance::class)->middleware('can:super-admin')->name('moderation.confiance');
    Route::get('/personnes/{profile:public_id}/signaler', SignalerProfil::class)->name('personnes.signaler');
    Route::get('/espace/zumras', MesZumras::class)->name('espace.zumras');
    Route::get('/zumra/creer', FormulaireZumra::class)->name('zumras.create');
    Route::get('/zumra/{zumra}/inviter', InviterPersonnes::class)->whereUuid('zumra')->name('zumras.invite');
    Route::get('/zumra/{zumra}/publier/{slug}', PublierPublication::class)->whereUuid('zumra')->name('zumras.publier');
    Route::get('/zumra/{zumra}/modifier', FormulaireZumra::class)->whereUuid('zumra')->name('zumras.edit');
    Route::get('/espace/projets', MesProjets::class)->name('espace.projets');
    Route::get('/contributions', CarrefourContributions::class)->name('contributions.index');
    Route::get('/contributions/besoin/nouveau', PublierBesoin::class)->name('contributions.need');
    Route::get('/contributions/proposer', ProposerContribution::class)->name('contributions.propose');
    Route::get('/contributions/{proposal}', PageProposition::class)->whereUuid('proposal')->name('contributions.show');
    Route::get('/espace/mes-contributions', MesContributions::class)->name('espace.mes-contributions');
    Route::get('/espace/services', PageServicesGamad::class)->name('espace.services');
    Route::get('/espace/publicite', MesCampagnes::class)->name('publicite.campagnes');
    Route::get('/espace/publicite/nouvelle', CreerCampagne::class)->name('publicite.creer');
    Route::get('/espace/publicite/{campaign}/modifier', CreerCampagne::class)->whereUuid('campaign')->name('publicite.modifier');
    Route::get('/publicite/{campaign}/aller', AdClickController::class)->whereUuid('campaign')->name('publicite.cta');
    Route::get('/espace/publicite/resultats', ResultatsCampagnes::class)->name('publicite.resultats');
    Route::get('/publicite/{campaign}/image', AdImageController::class)->whereUuid('campaign')->name('publicite.image');
    Route::get('/projets/creer', FormulaireProjet::class)->name('projets.new');
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
    Route::get('/moderation/annonces', ModerationAnnonces::class)->middleware('can:moderate')->name('moderation.annonces');
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
    Route::get('/espace/parametres/identite', [BrandingController::class, 'edit'])->middleware('can:super-admin')->name('espace.parametres.identite');
    Route::post('/espace/parametres/identite', [BrandingController::class, 'save'])->middleware('can:super-admin')->name('espace.parametres.identite.save');
    Route::get('/espace/parametres', Parametres::class)->middleware('can:super-admin')->name('espace.parametres');
    Route::get('/espace/parametres/textes', ParametresTextes::class)->middleware('can:super-admin')->name('espace.parametres.textes');
    Route::get('/espace/parametres/zumra', ParametresZumra::class)->middleware('can:super-admin')->name('espace.parametres.zumra');
    Route::get('/espace/parametres/publicite', ParametresPublicite::class)->middleware('can:super-admin')->name('espace.parametres.publicite');
    Route::get('/espace/parametres/contributions', ParametresContributions::class)->middleware('can:super-admin')->name('espace.parametres.contributions');
    Route::get('/espace/parametres/paiement', ParametresPaiement::class)->middleware('can:super-admin')->name('espace.parametres.paiement');
    Route::get('/espace/messages', MessagesContact::class)->middleware('can:super-admin')->name('espace.messages');
    Route::get('/espace/contributions', ContributionsAdministration::class)->middleware('can:super-admin')->name('espace.contributions');
    Route::get('/espace/paiements', PaiementsAdministration::class)->middleware('can:super-admin')->name('espace.paiements');
    Route::get('/espace/parametres/portefeuille', ParametresPortefeuille::class)->middleware('can:super-admin')->name('espace.parametres.portefeuille');
    Route::get('/espace/parametres/copie', ParametresCopie::class)->middleware('can:super-admin')->name('espace.parametres.copie');
    Route::get('/espace/parametres/domaines', ParametresDomaines::class)->middleware('can:super-admin')->name('espace.parametres.domaines');
    Route::get('/espace/parametres/territoires', ParametresTerritoires::class)->middleware('can:super-admin')->name('espace.parametres.territoires');
});

// Rappel serveur de GeniusPay : public (sans compte), protégé par la signature du corps et limité en débit (D-169).
Route::post('/webhooks/geniuspay', [GeniusPayWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('webhooks.geniuspay');

Route::get('/identite/{kind}.png', [BrandingController::class, 'image'])->where('kind', 'logo|icon')->name('branding.image');

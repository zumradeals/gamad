<?php

use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Livewire\MondeZumra;
use App\Livewire\PageConversation;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Domain;
use App\Models\Gesture;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Services\Conversations;
use App\Services\Gestures;
use App\Services\JoinRequests;
use App\Services\ProfileEditor;
use App\Services\Projects;
use App\Services\Publications;
use App\Services\Zumras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutVite();
        // Les limites de débit contre le spam (D-086) ne gênent pas les tests ordinaires ; les tests qui les vérifient les abaissent.
        config([
            'gamad.limits.publications_per_hour' => 10000, 'gamad.limits.publications_per_day' => 10000,
            'gamad.limits.gestures_per_hour' => 10000, 'gamad.limits.gestures_per_day' => 10000,
            'gamad.limits.zumras_per_day' => 10000, 'gamad.limits.projects_per_day' => 10000, 'gamad.limits.participation_requests_per_day' => 10000, 'gamad.limits.supports_per_hour' => 10000, 'gamad.limits.zumra_posts_per_minute' => 10000, 'gamad.limits.zumra_posts_per_day' => 10000, 'gamad.limits.join_requests_per_day' => 10000, 'gamad.limits.invitations_per_day' => 10000, 'gamad.limits.reports_per_hour' => 10000, 'gamad.limits.reports_per_day' => 10000,
            'gamad.limits.messages_per_minute' => 10000, 'gamad.limits.messages_per_day' => 10000,
        ]);
    })
    ->in('Feature');

/**
 * Charge les fonctions de install.sh sans lancer l'installation, puis exécute un scénario.
 *
 * @return array{code: int, sortie: string}
 */
function scenarioInstall(string $scenario): array
{
    $script = base_path('deploy/install.sh');
    $resultat = Process::env(['GAMAD_SOURCE_ONLY' => '1'])
        ->run(['bash', '-c', "source '$script'\ntitre 'Étape d essai'\n$scenario"]);

    return ['code' => $resultat->exitCode(), 'sortie' => $resultat->output().$resultat->errorOutput()];
}

/**
 * Crée une personne vérifiée et enregistre son profil par le chemin réel (App\Services\ProfileEditor), comme l'écran
 * « Modifier mon profil ».
 *
 * @param  array<int, string>  $offered
 * @param  array<int, string>  $wanted
 * @param  array<int, string>  $domains  noms de domaines
 */
function person(string $name, array $offered = [], array $wanted = [], ?string $city = null, string $visibility = 'public', array $domains = [], ?string $bio = null, array $attributes = []): User
{
    $user = User::factory()->create(['name' => $name] + $attributes);

    $cityModel = $city === null ? null : City::query()->where('name', $city)->firstOrFail();

    (new ProfileEditor)->save($user, [
        'name' => $name,
        'bio' => $bio,
        'country_id' => $cityModel?->country_id,
        'city_id' => $cityModel?->id,
        'visibility' => $visibility,
        'offered' => $offered,
        'wanted' => $wanted,
        'domain_ids' => Domain::query()->whereIn('name', $domains)->pluck('id')->all(),
    ]);

    return $user->fresh();
}

/** Publie par le chemin réel (App\Services\Publications), comme l'écran « Publier ». */
function publication(User $author, PublicationType $type = PublicationType::Need, array $data = []): Publication
{
    return app(Publications::class)->publish($author, $type, $data + ['body' => 'Un texte de publication.']);
}

/** Envoie un geste par le chemin réel (App\Services\Gestures). */
function geste(User $helper, Publication $publication, ?string $mot = null): Gesture
{
    return app(Gestures::class)->propose($helper, $publication, $mot);
}

/** Un geste accepté par l'auteur, par le chemin réel : la conversation privée est ouverte. */
function conversationDe(User $auteur, User $aidant, ?Publication $publication = null): Conversation
{
    $publication ??= publication($auteur);
    $geste = geste($aidant, $publication);
    app(Gestures::class)->accept($auteur, $geste);

    return $geste->fresh()->conversation;
}

function ecrire(User $qui, Conversation $conversation, string $texte): void
{
    app(Conversations::class)->send($qui, $conversation, $texte);
}

function pageConversation(User $qui, Conversation $conversation)
{
    return Livewire::actingAs($qui)->test(PageConversation::class, ['conversation' => $conversation]);
}

/** Crée une ZUMRA par le chemin réel (App\Services\Zumras), comme l'écran « Créer une ZUMRA ». */
function zumra(User $fondateur, string $nom = 'Couture solidaire', array $data = []): Zumra
{
    return app(Zumras::class)->create($fondateur, $data + [
        'name' => $nom,
        'domain_id' => Domain::query()->where('name', 'Économie et Finances')->value('id'),
        'format' => 'onsite',
        'mission' => 'Former des couturières et couturiers, et produire des uniformes scolaires à prix juste.',
    ]);
}

/** Ajoute une personne à une ZUMRA avec un rôle, directement en base (l'entrée par demande ou invitation vient en 3b). */
function ajouteMembre(Zumra $zumra, User $personne, ZumraRole $role = ZumraRole::Member): ZumraMembership
{
    $membre = new ZumraMembership(['role' => $role]);
    $membre->zumra()->associate($zumra);
    $membre->user()->associate($personne);
    $membre->save();

    return $membre;
}

/** Une demande d'adhésion acceptée par le fondateur : la personne devient membre. */
function admis(Zumra $zumra, User $personne): ZumraMembership
{
    $demande = app(JoinRequests::class)->propose($personne, $zumra);
    app(JoinRequests::class)->accept($zumra->memberships()->where('role', 'founder')->first()->user, $demande);

    return $zumra->memberships()->where('user_id', $personne->id)->first();
}

function monde(User $qui, $zumra, string $onglet = 'membres')
{
    return Livewire::actingAs($qui)->test(MondeZumra::class, ['zumra' => $zumra])->set('tab', $onglet);
}

/** Crée un projet par le chemin réel (App\Services\Projects), comme l'écran « Créer un projet ». */
function projet(User $auteur, Zumra $zumra, string $nom = 'Uniformes de la rentrée', array $data = []): Project
{
    return app(Projects::class)->create($auteur, $zumra, $data + ['name' => $nom, 'goal' => 'Coudre 200 uniformes pour trois écoles du quartier.']);
}

/** La ligne d'appartenance d'une personne à une ZUMRA (son rôle). */
function ligne(Zumra $zumra, User $personne): ZumraMembership
{
    return $zumra->memberships()->where('user_id', $personne->id)->firstOrFail();
}

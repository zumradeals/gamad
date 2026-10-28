<?php

use App\Core\Identity\Closure\AccountClosureRefused;
use App\Enums\LedgerKind;
use App\Enums\ZumraRole;
use App\Exceptions\ImmutableRecordException;
use App\Exceptions\WalletException;
use App\Livewire\Espace;
use App\Livewire\PagePortefeuille;
use App\Livewire\Parametres;
use App\Livewire\ParametresPortefeuille;
use App\Models\AdminAction;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->awa = person('Awa Traoré');
    $this->bob = person('Bob Kouassi');
});

// ─── Registre : solde, signes, plafonds ──────────────────────────────────────

it('part d’un solde nul et enchaîne les soldes de mouvement en mouvement', function () {
    $w = portefeuilles()->forPerson($this->awa);
    expect(portefeuilles()->balance($w))->toBe(0);

    portefeuilles()->post($w, LedgerKind::Recharge, 5000, 'pay-1');
    $e = portefeuilles()->post($w, LedgerKind::Contribution, 1500);

    expect($e->amount)->toBe(-1500)->and($e->balance_after)->toBe(3500)->and($e->sequence)->toBe(2)
        ->and(portefeuilles()->balance($w))->toBe(3500)
        ->and($w->entries()->pluck('sequence')->all())->toBe([2, 1]);
});

it('refuse un solde négatif et ne laisse aucune trace du refus', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 1000);

    expect(fn () => portefeuilles()->post($w, LedgerKind::Contribution, 1001))->toThrow(WalletException::class, 'insuffisant');
    expect(LedgerEntry::query()->count())->toBe(1)->and(portefeuilles()->balance($w))->toBe(1000);
});

it('refuse un montant nul ou négatif', function (int $montant) {
    $w = portefeuilles()->forPerson($this->awa);

    expect(fn () => portefeuilles()->post($w, LedgerKind::Recharge, $montant))->toThrow(WalletException::class);
})->with([0, -5]);

it('applique le plafond de solde d’une personne et celui d’une ZUMRA, réglables', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 500000);
    expect(fn () => portefeuilles()->post($w, LedgerKind::Recharge, 1))->toThrow(WalletException::class, '500 000 ZAHAB');

    $z = zumra($this->awa);
    $wz = portefeuilles()->forZumra($z);
    portefeuilles()->post($wz, LedgerKind::Recharge, 600000); // au-dessus du plafond d'une personne, sous celui d'une ZUMRA
    app(Settings::class)->update(['wallet_max_balance_zumra' => 600000], User::factory()->superAdmin()->create());
    expect(fn () => portefeuilles()->post($wz, LedgerKind::Recharge, 1))->toThrow(WalletException::class, '600 000 ZAHAB');
});

it('ne plafonne pas une dépense', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 500000);
    app(Settings::class)->update(['wallet_max_balance_person' => 1000], User::factory()->superAdmin()->create());

    expect(portefeuilles()->post($w, LedgerKind::Contribution, 10)->balance_after)->toBe(499990);
});

it('crée un seul portefeuille par titulaire', function () {
    portefeuilles()->forPerson($this->awa);
    portefeuilles()->forPerson($this->awa);
    $z = zumra($this->awa);
    portefeuilles()->forZumra($z);
    portefeuilles()->forZumra($z);

    expect(Wallet::query()->count())->toBe(2);
});

it('refuse tout mouvement sur un portefeuille fermé', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->closeFor(Wallet::PERSON, $this->awa->id);

    expect(fn () => portefeuilles()->post($w->fresh(), LedgerKind::Recharge, 100))->toThrow(WalletException::class, 'fermé');
});

// ─── Ajout seul ──────────────────────────────────────────────────────────────

it('refuse de modifier ou d’effacer un mouvement, côté modèle', function () {
    $w = portefeuilles()->forPerson($this->awa);
    $e = portefeuilles()->post($w, LedgerKind::Recharge, 100);

    expect(fn () => $e->update(['amount' => 999]))->toThrow(ImmutableRecordException::class);
    expect(fn () => $e->delete())->toThrow(ImmutableRecordException::class);
});

it('refuse de modifier ou d’effacer un mouvement, côté base de données', function () {
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 100);

    expect(fn () => DB::transaction(fn () => DB::table('ledger_entries')->update(['amount' => 999])))->toThrow(QueryException::class, 'ajout seul');
    expect(fn () => DB::transaction(fn () => DB::table('ledger_entries')->delete()))->toThrow(QueryException::class, 'ajout seul');
    expect(LedgerEntry::query()->sole()->amount)->toBe(100);
});

it('fait respecter par la base le signe, le solde et l’unicité de la séquence', function () {
    $w = portefeuilles()->forPerson($this->awa);
    $ligne = fn (array $a) => DB::transaction(fn () => DB::table('ledger_entries')->insert($a + ['id' => (string) Str::uuid(), 'wallet_id' => $w->id, 'sequence' => 1, 'kind' => 'recharge', 'amount' => 10, 'balance_after' => 10, 'created_at' => now()]));

    expect(fn () => $ligne(['amount' => -10, 'balance_after' => 0]))->toThrow(QueryException::class); // une recharge ne débite pas
    expect(fn () => $ligne(['kind' => 'contribution', 'amount' => 10]))->toThrow(QueryException::class); // une contribution ne crédite pas
    expect(fn () => $ligne(['amount' => 0]))->toThrow(QueryException::class);
    expect(fn () => $ligne(['balance_after' => -1]))->toThrow(QueryException::class);
    expect(fn () => $ligne(['kind' => 'cadeau']))->toThrow(QueryException::class);

    $ligne([]);
    expect(fn () => $ligne([]))->toThrow(QueryException::class); // même séquence deux fois
});

// ─── Autorisation ────────────────────────────────────────────────────────────

it('réserve un portefeuille de personne à sa personne', function () {
    ouvrirPortefeuilles();
    $w = portefeuilles()->forPerson($this->awa);

    expect($this->awa->can('view', $w))->toBeTrue()
        ->and($this->bob->can('view', $w))->toBeFalse();
    expect(Gate::forUser($this->bob)->inspect('view', $w)->status())->toBe(404);
});

it('ne montre un portefeuille ni au Président ni à la modération', function () {
    ouvrirPortefeuilles();
    $w = portefeuilles()->forPerson($this->awa);
    $z = zumra($this->awa);
    $wz = portefeuilles()->forZumra($z);

    foreach ([User::factory()->superAdmin()->create(), User::factory()->moderator()->create()] as $u) {
        expect($u->can('view', $w))->toBeFalse()->and($u->can('view', $wz))->toBeFalse();
    }
});

it('réserve le portefeuille d’une ZUMRA à son fondateur et à ses responsables', function () {
    $z = zumra($this->awa);
    $resp = person('Chantal Resp');
    $membre = person('Membre Simple');
    ajouteMembre($z, $resp, ZumraRole::Manager);
    ajouteMembre($z, $membre);
    $wz = portefeuilles()->forZumra($z);

    expect($this->awa->can('view', $wz))->toBeTrue()
        ->and($resp->can('view', $wz))->toBeTrue()
        ->and($membre->can('view', $wz))->toBeFalse()
        ->and($this->bob->can('view', $wz))->toBeFalse();
});

it('refuse un portefeuille à un visiteur ou à une personne sans e-mail vérifié', function () {
    $w = portefeuilles()->forPerson($this->awa);
    $this->awa->forceFill(['email_verified_at' => null])->save();

    expect(Gate::forUser(null)->inspect('view', $w)->status())->toBe(404)
        ->and($this->awa->fresh()->can('view', $w))->toBeFalse();
});

// ─── Écrans ──────────────────────────────────────────────────────────────────

it('cache entièrement les pages et les liens tant que les portefeuilles sont fermés', function () {
    $z = zumra($this->awa);

    $this->actingAs($this->awa)->get(route('portefeuille'))->assertNotFound();
    $this->actingAs($this->awa)->get(route('zumras.portefeuille', $z))->assertNotFound();
    Livewire::actingAs($this->awa)->test(Espace::class)->assertDontSee('Mon portefeuille');
    monde($this->awa, $z)->assertDontSee('Portefeuille de la ZUMRA');
});

it('renvoie les visiteurs vers la connexion', function () {
    ouvrirPortefeuilles();

    $this->get(route('portefeuille'))->assertRedirect(route('login'));
});

it('affiche le solde et les mouvements de la personne, sans aucune action', function () {
    ouvrirPortefeuilles();
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 12500);
    portefeuilles()->post($w, LedgerKind::Contribution, 2500);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)
        ->assertSee('10 000 ZAHAB')->assertSee('1 ZAHAB = 1 FCFA')
        ->assertSee('Recharge')->assertSee('Contribution')->assertSee('+12 500')->assertSee('−2 500')
        ->assertDontSee('Recharger')->assertDontSee('Retirer');
});

it('montre un état vide à une personne sans mouvement', function () {
    ouvrirPortefeuilles();

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->assertSee('0 ZAHAB')->assertSee('Aucun mouvement pour l’instant.');
});

it('ne montre à chaque personne que son propre portefeuille', function () {
    ouvrirPortefeuilles();
    portefeuilles()->post(portefeuilles()->forPerson($this->awa), LedgerKind::Recharge, 7777);

    Livewire::actingAs($this->bob)->test(PagePortefeuille::class)->assertDontSee('7 777')->assertSee('0 ZAHAB');
});

it('pagine les mouvements', function () {
    ouvrirPortefeuilles();
    $w = portefeuilles()->forPerson($this->awa);
    foreach (range(1, 25) as $i) {
        portefeuilles()->post($w, LedgerKind::Recharge, 10);
    }

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class)->assertSee('250 ZAHAB')->assertSee('Solde 250')->assertDontSee('Solde 10 ')->assertSee('Suivant');
});

it('affiche le portefeuille d’une ZUMRA à ses responsables et le refuse aux autres', function () {
    ouvrirPortefeuilles();
    $z = zumra($this->awa);
    $membre = person('Membre Simple');
    ajouteMembre($z, $membre);
    portefeuilles()->post(portefeuilles()->forZumra($z), LedgerKind::Recharge, 40000);

    Livewire::actingAs($this->awa)->test(PagePortefeuille::class, ['zumra' => $z])->assertSee('40 000 ZAHAB')->assertSee('Portefeuille de la ZUMRA');
    $this->actingAs($membre)->get(route('zumras.portefeuille', $z))->assertNotFound();
    $this->actingAs($this->bob)->get(route('zumras.portefeuille', $z))->assertNotFound();
    $this->actingAs($this->bob)->get(route('zumras.portefeuille', $z))->assertNotFound();
    expect(Wallet::query()->where('owner_type', 'zumra')->count())->toBe(1);
});

it('ne crée pas de portefeuille pour une personne étrangère à la ZUMRA', function () {
    ouvrirPortefeuilles();
    $z = zumra($this->awa);

    $this->actingAs($this->bob)->get(route('zumras.portefeuille', $z))->assertNotFound();
    expect(Wallet::query()->count())->toBe(0);
});

it('montre les liens quand les portefeuilles sont ouverts : Mon espace pour tous, Monde ZUMRA pour les responsables', function () {
    ouvrirPortefeuilles();
    $z = zumra($this->awa);
    $membre = person('Membre Simple');
    ajouteMembre($z, $membre);

    Livewire::actingAs($this->awa)->test(Espace::class)->call('show', 'activites')->assertSee('Ouvrir mon portefeuille');
    monde($this->awa, $z)->assertSee('Portefeuille de la ZUMRA');
    monde($membre, $z)->assertDontSee('Portefeuille de la ZUMRA');
});

// ─── Réglages du Président ───────────────────────────────────────────────────

it('réserve la page des réglages au Président', function () {
    $this->actingAs($this->awa)->get(route('espace.parametres.portefeuille'))->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get(route('espace.parametres.portefeuille'))->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->get(route('espace.parametres.portefeuille'))->assertOk();
});

it('ouvre les portefeuilles et règle les plafonds, au journal', function () {
    $admin = User::factory()->superAdmin()->create();
    expect(portefeuilles()->enabled())->toBeFalse();

    Livewire::actingAs($admin)->test(ParametresPortefeuille::class)
        ->set('enabled', '1')->set('maxPerson', '100000')->set('maxZumra', '2000000')
        ->call('save')->assertHasNoErrors()->assertSee('enregistrés');

    expect(portefeuilles()->enabled())->toBeTrue();
    expect(AdminAction::query()->where('action', 'setting.changed')->pluck('subject_id')->all())
        ->toContain('wallet_enabled', 'wallet_max_balance_person', 'wallet_max_balance_zumra');
    expect(app(Settings::class)->get('wallet_max_balance_person'))->toBe(100000);
});

it('refuse des réglages invalides', function (string $champ, string $valeur) {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(ParametresPortefeuille::class)
        ->set($champ, $valeur)->call('save')->assertHasErrors([$champ]);
})->with([['enabled', '2'], ['maxPerson', '0'], ['maxPerson', 'beaucoup'], ['maxZumra', '-4']]);

it('refuse l’enregistrement des réglages à un autre que le Président', function () {
    Livewire::actingAs($this->awa)->test(ParametresPortefeuille::class)->assertForbidden();
});

it('propose le lien vers les réglages dans Paramètres', function () {
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(Parametres::class)->assertSee('Portefeuilles');
});

// ─── Suppression de compte ───────────────────────────────────────────────────

it('refuse la suppression d’un compte dont le portefeuille n’est pas vide', function () {
    $this->awa->forceFill(['password' => 'motdepasse1'])->save();
    portefeuilles()->post(portefeuilles()->forPerson($this->awa), LedgerKind::Recharge, 300);

    Livewire::actingAs($this->awa)->test(Espace::class)->call('show', 'compte')->set('password', 'motdepasse1')->call('deleteAccount')
        ->assertHasErrors(['password'])->assertSee('reversement');

    expect(User::find($this->awa->id))->not->toBeNull();
});

it('supprime le compte d’une personne au portefeuille vide et ferme ce portefeuille, registre conservé', function () {
    $this->awa->forceFill(['password' => 'motdepasse1'])->save();
    $w = portefeuilles()->forPerson($this->awa);
    portefeuilles()->post($w, LedgerKind::Recharge, 300);
    portefeuilles()->post($w, LedgerKind::Contribution, 300);

    Livewire::actingAs($this->awa)->test(Espace::class)->set('password', 'motdepasse1')->call('deleteAccount')->assertRedirect('/');

    expect(User::find($this->awa->id))->toBeNull()
        ->and($w->fresh()->isClosed())->toBeTrue()
        ->and(LedgerEntry::query()->where('wallet_id', $w->id)->count())->toBe(2);
});

it('refuse la suppression de la dernière personne d’une ZUMRA au portefeuille non vide', function () {
    $this->awa->forceFill(['password' => 'motdepasse1'])->save();
    $z = zumra($this->awa);
    portefeuilles()->post(portefeuilles()->forZumra($z), LedgerKind::Recharge, 900);

    Livewire::actingAs($this->awa)->test(Espace::class)->call('show', 'compte')->set('password', 'motdepasse1')->call('deleteAccount')
        ->assertHasErrors(['password'])->assertSee('dernière personne');

    expect(User::find($this->awa->id))->not->toBeNull()->and($z->fresh())->not->toBeNull();
});

it('laisse partir le fondateur d’une ZUMRA qui a d’autres membres, même si son portefeuille est plein', function () {
    $this->awa->forceFill(['password' => 'motdepasse1'])->save();
    $z = zumra($this->awa);
    ajouteMembre($z, $this->bob);
    portefeuilles()->post(portefeuilles()->forZumra($z), LedgerKind::Recharge, 900);

    Livewire::actingAs($this->awa)->test(Espace::class)->set('password', 'motdepasse1')->call('deleteAccount')->assertRedirect('/');

    expect(User::find($this->awa->id))->toBeNull()
        ->and(Wallet::query()->where('owner_type', 'zumra')->sole()->isClosed())->toBeFalse();
});

it('ferme le portefeuille vide d’une ZUMRA qui disparaît avec son dernier membre', function () {
    $this->awa->forceFill(['password' => 'motdepasse1'])->save();
    $z = zumra($this->awa);
    $wz = portefeuilles()->forZumra($z);

    Livewire::actingAs($this->awa)->test(Espace::class)->set('password', 'motdepasse1')->call('deleteAccount')->assertRedirect('/');

    expect($wz->fresh()->isClosed())->toBeTrue();
});

it('garde le filet de sécurité : la fermeture refuse un compte au portefeuille non vide, et le modèle refuse toute suppression directe', function () {
    portefeuilles()->post(portefeuilles()->forPerson($this->awa), LedgerKind::Recharge, 50);

    expect(fn () => fermerCompte($this->awa))->toThrow(AccountClosureRefused::class);
    expect(fn () => $this->awa->delete())->toThrow(LogicException::class);
    expect(User::find($this->awa->id))->not->toBeNull();
});

<?php

use App\Http\Controllers\PersonController;
use App\Livewire\CarrefourPersonnes;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\ModifierProfil;
use App\Livewire\PagePublication;
use App\Livewire\Parametres;
use App\Livewire\ParametresDomaines;
use App\Livewire\ParametresTerritoires;
use App\Livewire\PublierPublication;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('fil') : view('pages.welcome'))->name('home');

// Le Carrefour Personnes et le Monde d'une personne sont ouverts aux visiteurs sans compte ; chaque profil applique
// sa propre visibilité (D-067).
Route::get('/personnes', CarrefourPersonnes::class)->name('personnes.index');
Route::get('/personnes/{profile:public_id}', [PersonController::class, 'show'])->name('personnes.show');
Route::get('/personnes/{profile:public_id}/photo', [PersonController::class, 'photo'])->name('personnes.photo');

// Une publication publique se lit sans compte ; répondre demande un compte (D-085). Une publication interdite répond 404.
Route::get('/publications/{publication}', PagePublication::class)->whereUuid('publication')->name('publications.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/fil', Fil::class)->name('fil');
    Route::view('/agir', 'pages.agir')->name('agir');
    Route::get('/agir/{slug}', PublierPublication::class)->name('agir.publier');
    Route::get('/espace', Espace::class)->name('espace');
    Route::get('/espace/profil', ModifierProfil::class)->name('espace.profil');
    Route::get('/espace/parametres', Parametres::class)->middleware('can:super-admin')->name('espace.parametres');
    Route::get('/espace/parametres/domaines', ParametresDomaines::class)->middleware('can:super-admin')->name('espace.parametres.domaines');
    Route::get('/espace/parametres/territoires', ParametresTerritoires::class)->middleware('can:super-admin')->name('espace.parametres.territoires');
});

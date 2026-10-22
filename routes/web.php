<?php

use App\Livewire\Espace;
use App\Livewire\Parametres;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('fil') : view('pages.welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('/fil', 'pages.fil')->name('fil');
    Route::get('/espace', Espace::class)->name('espace');
    Route::get('/espace/parametres', Parametres::class)->middleware('can:super-admin')->name('espace.parametres');
});

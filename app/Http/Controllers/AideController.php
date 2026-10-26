<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/** La page Aide publique (D-208) : des réponses courtes aux questions que posent les parcours réellement construits. */
class AideController extends Controller
{
    public function __invoke(): View
    {
        return view('aide.show');
    }
}

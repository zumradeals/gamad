<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\ContactMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Le formulaire de contact public (D-208). Sans compte requis. Défenses : un champ piège que les personnes ne voient pas
 * (un robot qui le remplit reçoit la même confirmation, sans que rien soit enregistré) et des limites par adresse IP et par
 * adresse e-mail.
 */
class ContactController extends Controller
{
    public function show(Request $request)
    {
        return view('contact.show', ['user' => $request->user(), 'sent' => (bool) session('contact_sent')]);
    }

    public function store(Request $request, ContactMessages $messages): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:254'],
            'subject' => ['required', Rule::in(array_keys(ContactMessage::SUBJECTS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'name.required' => 'Indiquez votre nom.',
            'email.required' => 'Indiquez une adresse e-mail pour que nous puissions vous répondre.',
            'email.email' => 'Cette adresse e-mail ne semble pas valide.',
            'subject.required' => 'Choisissez un sujet.',
            'subject.in' => 'Choisissez un sujet dans la liste.',
            'message.required' => 'Écrivez votre message.',
            'message.min' => 'Votre message est trop court : écrivez au moins 10 caractères.',
            'message.max' => 'Votre message est trop long : 2 000 caractères au maximum.',
        ]);

        // Champ piège : un robot le remplit, une personne ne le voit pas. Même réponse, rien d'enregistré.
        if ($request->filled('website')) {
            return redirect()->route('contact')->with('contact_sent', true);
        }

        $this->throttle($request->ip().'|ip', (int) config('gamad.limits.contact_per_hour'), 3600);
        $this->throttle(mb_strtolower($data['email']).'|email', (int) config('gamad.limits.contact_per_hour'), 3600);
        $this->throttle($request->ip().'|jour', (int) config('gamad.limits.contact_per_day'), 86400);

        $messages->submit($data, $request->user());

        return redirect()->route('contact')->with('contact_sent', true);
    }

    private function throttle(string $key, int $max, int $seconds): void
    {
        $key = 'contact:'.$key;

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages(['message' => 'Vous avez envoyé plusieurs messages à la suite. Réessayez un peu plus tard.']);
        }

        RateLimiter::hit($key, $seconds);
    }
}

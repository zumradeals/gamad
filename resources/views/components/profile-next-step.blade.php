@props(['user'])
@php
    $profile = $user->ensureProfile();
    $tip = null;
    $destination = route('espace.profil');
    if (! $profile->offeredSkills()->exists()) {
        $tip = 'Ajoutez une capacité à votre profil pour montrer ce que vous savez faire.';
    } elseif (! $user->publications()->where('type', \App\Enums\PublicationType::PersonalWork)->whereNull('zumra_id')->whereNull('hidden_at')->exists()) {
        $tip = 'Montrez votre savoir-faire avec une réalisation concrète.';
        $destination = route('agir.publier', 'ma-realisation');
    } elseif (blank($profile->introduction)) {
        $tip = 'Présentez votre expérience en quelques mots pour aider les autres à vous connaître.';
    }
@endphp
@if ($tip)
    <aside class="rounded-button bg-ground p-3" aria-label="Suggestion pour mon profil">
        <p class="text-[13px] text-muted">{{ $tip }}</p>
        <a href="{{ $destination }}" class="inline-flex min-h-11 items-center text-[13px] font-bold">Enrichir mon profil</a>
    </aside>
@endif

@php
    $fieldClass = fn (string $name) => 'min-h-12 w-full rounded-button border bg-soft-blue px-3 text-[15px] outline-none focus:outline focus:outline-[3px] focus:outline-link '.($errors->has($name) ? 'border-danger' : 'border-chip-line');
    $error = fn (string $name) => $errors->has($name) ? $name.'-error' : null;
    $contenu = view('contact.form', ['user' => $user, 'sent' => $sent, 'fieldClass' => $fieldClass])->render();
@endphp
@auth
    <x-layouts.app title="Contact" narrow>{!! $contenu !!}</x-layouts.app>
@else
    <x-layouts.landing title="Contact">
        <div class="landing-container py-8 md:py-12">{!! $contenu !!}</div>
    </x-layouts.landing>
@endauth

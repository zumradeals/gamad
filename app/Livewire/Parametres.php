<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\AdminJournal;
use App\Services\Settings;
use App\Settings\Registry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Page « Paramètres » : réglages métier modifiables par le super administrateur seul (D-049).
 * Le mot de passe SMTP n'est jamais chargé dans la page : un champ vide le laisse inchangé.
 */
#[Layout('components.layouts.app')]
#[Title('Paramètres')]
class Parametres extends Component
{
    /** Propriété de la page => clé du réglage. */
    private const FIELDS = [
        'mailHost' => 'mail_host',
        'mailPort' => 'mail_port',
        'mailEncryption' => 'mail_encryption',
        'mailUsername' => 'mail_username',
        'mailFromAddress' => 'mail_from_address',
        'mailFromName' => 'mail_from_name',
        'appName' => 'app_name',
        'appUrl' => 'app_url',
        'limitRegister' => 'limit_register_per_hour',
        'limitLogin' => 'limit_login_per_minute',
    ];

    public string $mailHost = '';

    public string $mailPort = '';

    public string $mailEncryption = 'starttls';

    public string $mailUsername = '';

    /** Nouveau mot de passe saisi ; jamais rempli avec la valeur enregistrée. */
    public string $mailPassword = '';

    public bool $clearMailPassword = false;

    public bool $hasMailPassword = false;

    public string $mailFromAddress = '';

    public string $mailFromName = '';

    public string $appName = '';

    public string $appUrl = '';

    public string $limitRegister = '';

    public string $limitLogin = '';

    public ?string $saved = null;

    public ?string $testMessage = null;

    public bool $testFailed = false;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');

        $this->load($settings);
    }

    public function save(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved', 'testMessage', 'testFailed');

        $this->validate($this->rules(), [], $this->validationAttributes());

        $changes = [];
        foreach (self::FIELDS as $property => $key) {
            $changes[$key] = $this->{$property};
        }

        // Champ vide = mot de passe inchangé ; seule la case « retirer » l'efface.
        if ($this->mailPassword !== '') {
            $changes['mail_password'] = $this->mailPassword;
        } elseif ($this->clearMailPassword) {
            $changes['mail_password'] = null;
        }

        $modified = $settings->update($changes, Auth::user());
        $settings->applyToConfig();

        $this->load($settings);

        $this->saved = $modified === []
            ? 'Aucune modification.'
            : (count($modified) === 1 ? 'Réglages enregistrés (1 modification).' : 'Réglages enregistrés ('.count($modified).' modifications).');
    }

    public function sendTestEmail(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved', 'testMessage', 'testFailed');

        /** @var User $user */
        $user = Auth::user();

        if (blank($settings->get('mail_host'))) {
            $this->testFailed = true;
            $this->testMessage = 'Renseignez et enregistrez d’abord le serveur SMTP : le test utilise les réglages enregistrés.';

            return;
        }

        $name = $settings->get('app_name');

        try {
            Mail::raw(
                "Ce message confirme que l'envoi de courriels de $name fonctionne.\n\nEnvoyé le ".now()->timezone('Africa/Abidjan')->format('d/m/Y à H:i').' (heure d’Abidjan).',
                fn ($message) => $message->to($user->email)->subject("Courriel de test — $name"),
            );
        } catch (Throwable $e) {
            AdminJournal::record($user, 'settings.test_email_failed', payload: ['erreur' => class_basename($e)]);
            $this->testFailed = true;
            $this->testMessage = 'L’envoi a échoué : '.$this->withoutSecrets($e->getMessage(), $settings);

            return;
        }

        AdminJournal::record($user, 'settings.test_email_sent');
        $this->testMessage = "E-mail de test envoyé à {$user->email}. Vérifiez votre boîte de réception, et les courriers indésirables.";
    }

    public function render()
    {
        return view('livewire.parametres', [
            'encryptions' => Registry::find('mail_encryption')->options,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = [];
        foreach (self::FIELDS as $property => $key) {
            $rules[$property] = Registry::find($key)->rules;
        }

        $rules['mailPassword'] = ['nullable', 'string', 'max:255'];

        // Dès qu'un serveur SMTP est renseigné, le reste du courriel doit l'être aussi.
        foreach (['mailPort', 'mailEncryption', 'mailFromAddress'] as $property) {
            $rules[$property][] = 'required_with:mailHost';
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = [];
        foreach (self::FIELDS as $property => $key) {
            $attributes[$property] = Str::lower(Registry::find($key)->label);
        }

        return $attributes + ['mailPassword' => 'mot de passe'];
    }

    private function load(Settings $settings): void
    {
        foreach (self::FIELDS as $property => $key) {
            $this->{$property} = (string) ($settings->get($key) ?? '');
        }

        $this->hasMailPassword = $settings->hasSecret('mail_password');
        $this->mailPassword = '';
        $this->clearMailPassword = false;
    }

    /** Un message d'erreur du serveur SMTP ne doit jamais pouvoir contenir le mot de passe enregistré. */
    private function withoutSecrets(string $message, Settings $settings): string
    {
        $secret = $settings->secret('mail_password');

        if (filled($secret)) {
            $message = str_replace($secret, '••••••', $message);
        }

        return Str::limit(trim(preg_replace('/\s+/', ' ', $message)), 300);
    }
}

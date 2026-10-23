<?php

namespace App\Livewire;

use App\Services\BackupStorage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Copie hors serveur (Lot 5c, D-155) : le Président saisit ici les accès au stockage compatible S3 où la sauvegarde nocturne est
 * copiée, chiffrée. Les accès sont essayés pour de bon avant d'être enregistrés ; les clés ne sont jamais réaffichées. La phrase de
 * secours, seule clé des copies, est montrée une fois à la création et ne se réaffiche que sur mot de passe (avec trace au journal).
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Copie hors serveur')]
class ParametresCopie extends Component
{
    public string $endpoint = '';

    public string $bucket = '';

    public string $region = '';

    /** Saisies de clés : jamais remplies avec les valeurs enregistrées. */
    public string $accessKey = '';

    public string $secretKey = '';

    /** Phrase existante à reprendre (nouveau serveur) ; vide = en générer une. */
    public string $passphrase = '';

    public string $password = '';

    public bool $askPassword = false;

    public ?string $saved = null;

    /** Phrase à noter, montrée une seule fois. Non persistante : elle disparaît au prochain affichage de la page. */
    public ?string $shownPassphrase = null;

    public function mount(BackupStorage $storage): void
    {
        Gate::authorize('super-admin');

        $this->load($storage);
    }

    public function save(BackupStorage $storage): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved', 'shownPassphrase');
        $this->resetErrorBag();

        try {
            $generated = $storage->save(Auth::user(), [
                'endpoint' => $this->endpoint, 'bucket' => $this->bucket, 'region' => $this->region,
                'access_key' => $this->accessKey, 'secret_key' => $this->secretKey, 'passphrase' => $this->passphrase,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->reset('accessKey', 'secretKey', 'passphrase');
        $this->shownPassphrase = $generated;
        $this->saved = 'Accès enregistrés : l’essai d’écriture et de relecture a réussi.';
        $this->load($storage);
    }

    public function askToReveal(): void
    {
        Gate::authorize('super-admin');
        $this->reset('password', 'shownPassphrase');
        $this->resetErrorBag();
        $this->askPassword = true;
    }

    public function reveal(BackupStorage $storage): void
    {
        Gate::authorize('super-admin');
        $this->resetErrorBag();

        try {
            $this->shownPassphrase = $storage->revealPassphrase(Auth::user(), $this->password);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->password = '';

            return;
        }

        $this->reset('password', 'askPassword');
    }

    public function noted(): void
    {
        $this->reset('shownPassphrase');
    }

    public function render(BackupStorage $storage)
    {
        Gate::authorize('super-admin');

        $status = $storage->status();

        return view('livewire.parametres-copie', [
            'configured' => $storage->configured(),
            'hasPassphrase' => $storage->hasPassphrase(),
            'status' => $status,
            'stale' => $storage->configured() && ($status['derniere_reussite'] === null || $status['derniere_reussite']->lt(now()->subHours(BackupStorage::STALE_AFTER_HOURS))),
        ]);
    }

    private function load(BackupStorage $storage): void
    {
        $visible = $storage->visible();
        $this->endpoint = (string) $visible['endpoint'];
        $this->bucket = (string) $visible['bucket'];
        $this->region = (string) $visible['region'];
    }
}

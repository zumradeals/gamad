<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\User;

/**
 * Les messages du formulaire de contact (D-208). Enregistrés tels quels, sans pièce jointe ; conservés 12 mois par défaut
 * (réglable dans config/gamad.php), puis effacés. Chaque lecture et chaque changement d'état par le super administrateur
 * est inscrit au journal d'administration.
 */
class ContactMessages
{
    /** @param  array{name: string, email: string, subject: string, message: string}  $data */
    public function submit(array $data, ?User $user = null): ContactMessage
    {
        $this->purgeExpired();

        return ContactMessage::query()->create([
            'user_id' => $user?->getKey(),
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'subject' => $data['subject'],
            'body' => $data['message'],
        ]);
    }

    /** Ouvre un message : il passe de « Nouveau » à « Lu » et la lecture est au journal. */
    public function open(User $admin, ContactMessage $message): void
    {
        AdminJournal::record($admin, 'contact.read', $message, payload: ['sujet' => $message->subjectLabel()]);

        if ($message->state === ContactMessage::NEW) {
            $message->forceFill(['state' => ContactMessage::READ, 'read_at' => now()])->save();
        }
    }

    public function markHandled(User $admin, ContactMessage $message): void
    {
        $message->forceFill(['state' => ContactMessage::HANDLED, 'handled_at' => now(), 'read_at' => $message->read_at ?? now()])->save();
        AdminJournal::record($admin, 'contact.handled', $message, payload: ['sujet' => $message->subjectLabel()]);
    }

    public function delete(User $admin, ContactMessage $message): void
    {
        AdminJournal::record($admin, 'contact.deleted', $message, payload: ['sujet' => $message->subjectLabel()]);
        $message->delete();
    }

    public function unreadCount(): int
    {
        return ContactMessage::query()->where('state', ContactMessage::NEW)->count();
    }

    /** Efface les messages plus anciens que la durée de conservation. Retourne leur nombre. */
    public function purgeExpired(): int
    {
        $months = max(1, (int) config('gamad.contact.retention_months'));

        return ContactMessage::query()->where('created_at', '<', now()->subMonths($months))->delete();
    }
}

<?php

namespace App\Services;

use App\Models\Publication;
use App\Models\PublicationBookmark;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** D-193 : accès aux enregistrements limité au propriétaire, relecture des droits de la publication. */
class PublicationBookmarks
{
    public function set(User $user, Publication $publication, bool $save): void
    {
        abort_unless($user->isActive(), 403);
        if (! $save) {
            // Permettre de retirer un élément même s'il vient de devenir inaccessible.
            PublicationBookmark::query()->where('user_id', $user->id)->where('publication_id', $publication->id)->delete();

            return;
        }

        Gate::forUser($user)->authorize('view', $publication);
        abort_if($publication->isHidden() || $publication->zumra_id !== null || $publication->project_id !== null, 404);

        DB::table('publication_bookmarks')->insertOrIgnore([
            'user_id' => $user->id, 'publication_id' => $publication->id, 'created_at' => now(),
        ]);
    }

    /** Identifiants pour une liste de publications, sans requête par carte. */
    public function savedIds(User $user, iterable $publicationIds): array
    {
        return PublicationBookmark::query()->where('user_id', $user->id)
            ->whereIn('publication_id', $publicationIds)->pluck('publication_id')->all();
    }
}

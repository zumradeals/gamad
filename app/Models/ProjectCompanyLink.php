<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lien entre un projet et une entreprise GAMAD (P1) : « porté par » ou « accompagné par ». Proposé par le porteur, confirmé par
 * l'entreprise ; la ZUMRA d'origine est conservée. Ce n'est ni une constitution juridique, ni une contribution financière.
 */
class ProjectCompanyLink extends Model
{
    use HasUuids;

    public const KINDS = ['carried' => 'Porté par', 'supported' => 'Accompagné par'];

    public const REQUESTED = 'requested';

    public const CONFIRMED = 'confirmed';

    public const DECLINED = 'declined';

    public const ENDED = 'ended';

    protected $guarded = ['id', 'project_id', 'company_id', 'requested_by', 'decided_by', 'origin_zumra_id', 'state', 'decided_at', 'ended_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function originZumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class, 'origin_zumra_id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? '';
    }
}

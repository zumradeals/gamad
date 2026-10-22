<?php

namespace App\Models\Concerns;

use App\Support\Normalizer;

/**
 * Tient à jour la colonne `normalized` (clé unique sans casse ni accents) à partir de `name`, à chaque enregistrement.
 * Le libellé affiché est nettoyé de la même façon.
 */
trait HasNormalizedName
{
    protected static function bootHasNormalizedName(): void
    {
        static::saving(function ($model) {
            $model->name = Normalizer::display($model->name);
            $model->normalized = Normalizer::key($model->normalizedSource());
        });
    }

    /** Texte dont la clé est tirée ; les modèles à libellé composé (ville) le redéfinissent. */
    public function normalizedSource(): string
    {
        return $this->name;
    }
}

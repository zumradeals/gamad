<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Support\Normalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Recherche de lieux pour le sélecteur (D-217) : quinze lieux actifs au plus, par nom sans accents ni casse. Avec un pays et sans
 * saisie, un pays à liste courte (60 lieux au plus) est rendu en entier pour que la liste reste parcourable, comme avant.
 * Données publiques (ce sont les lieux que chacun peut choisir) : aucune donnée de personne.
 */
class PlaceSearchController extends Controller
{
    public const LIMIT = 15;

    public const SHORT_LIST = 60;

    public function __invoke(Request $request): JsonResponse
    {
        $key = Normalizer::key(Str::limit((string) $request->query('q', ''), 80, ''));
        $country = (string) $request->query('pays', '');
        $country = Str::isUuid($country) ? $country : '';

        $query = City::query()->active()->with('country:id,name')
            ->when($country !== '', fn ($q) => $q->where('country_id', $country));

        if ($key === '') {
            if ($country === '' || (clone $query)->count() > self::SHORT_LIST) {
                return response()->json([]);
            }
            $cities = $query->orderBy('normalized')->limit(self::SHORT_LIST)->get();
        } else {
            if (mb_strlen($key) < 2) {
                return response()->json([]);
            }
            $like = Normalizer::likeContains($key);
            $cities = $query->where('normalized', 'like', $like)
                ->orderByRaw('case when normalized like ? then 0 else 1 end', [addcslashes($key, '\\%_').'%'])
                ->orderBy('normalized')->limit(self::LIMIT)->get();
        }

        return response()->json($cities->map(fn (City $city) => ['id' => $city->id, 'label' => $city->fullLabel()])->values());
    }
}

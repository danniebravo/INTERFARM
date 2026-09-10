<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Models\PlatformSetting;
use App\Support\EscapesLikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class LotController extends Controller
{
    use EscapesLikeSearch;

    public function index(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $search = trim((string) $request->get('search'));
        $status = trim((string) $request->get('status'));
        $sort = $request->get('sort', 'latest');

        if (! in_array($sort, ['latest', 'name_asc', 'animals_desc', 'area_desc'], true)) {
            $sort = 'latest';
        }

        $lotsQuery = $farm->lots()
            ->withCount('animals')
            ->when($search !== '', function ($query) use ($search) {
                $this->whereLikeAny($query, ['name', 'code', 'type', 'description'], $search);
            })
            ->when(in_array($status, ['activo', 'inactivo'], true), function ($query) use ($status) {
                $query->where('status', $status);
            });

        match ($sort) {
            'name_asc' => $lotsQuery->orderBy('name'),
            'animals_desc' => $lotsQuery->orderByDesc('animals_count'),
            'area_desc' => $lotsQuery->orderByRaw('COALESCE(area_manual, area_calculated, 0) desc'),
            default => $lotsQuery->latest(),
        };

        $lots = $lotsQuery
            ->get();

        return view('lots.index', compact('lots', 'farm', 'search', 'status', 'sort'));
    }

    public function create()
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        return $this->lotFormResponse('lots.create', compact('farm'));
    }

    public function store(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $request->merge([
            'code' => $request->filled('code') ? trim((string) $request->input('code')) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('lots', 'code')->where(fn ($query) => $query->where('farm_id', $farm->id)),
            ],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'area_manual' => ['nullable', 'numeric', 'min:0'],
            'area_calculated' => ['nullable', 'numeric', 'min:0'],
            'center_lat' => ['nullable', 'numeric'],
            'center_lng' => ['nullable', 'numeric'],
            'polygon_json' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], $this->lotValidationMessages());

        $polygon = $this->decodePolygon($data['polygon_json'] ?? null);

        Lot::create([
            'farm_id' => $farm->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? 'activo',
            'area_manual' => $data['area_manual'] ?? null,
            'area_calculated' => $data['area_calculated'] ?? null,
            'center_lat' => $data['center_lat'] ?? null,
            'center_lng' => $data['center_lng'] ?? null,
            'polygon' => $polygon,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('lots.index')
            ->with('success', 'Lote creado correctamente.');
    }

    public function show(Lot $lot)
    {
        $this->authorizeLot($lot);

        $lot->load(['animals']);

        $ownerFarms = auth()->user()->farms()->orderBy('farms.name')->get();
        $ownerFarmIds = $ownerFarms->pluck('id');

        $candidateAnimals = \App\Models\Animal::whereIn('farm_id', $ownerFarmIds)
            ->where(function ($q) use ($lot) {
                $q->where('farm_id', '!=', $lot->farm_id)
                    ->orWhereNull('lot_id')
                    ->orWhere('lot_id', '!=', $lot->id);
            })
            ->where(function ($q) {
                $q->whereNull('status')->orWhereNotIn('status', ['vendido', 'fallecido', 'sold', 'deceased', 'dead']);
            })
            ->with(['lot:id,name', 'farm:id,name'])
            ->orderBy('farm_id')
            ->orderBy('name')
            ->get();

        $farmLots = \App\Models\Lot::where('farm_id', $lot->farm_id)->orderBy('name')->get(['id', 'name']);

        $lotHistory = \Schema::hasTable('animal_lot_history')
            ? \DB::table('animal_lot_history')
                ->leftJoin('animals', 'animals.id', '=', 'animal_lot_history.animal_id')
                ->where('animal_lot_history.lot_id', $lot->id)
                ->orderByDesc('animal_lot_history.entered_at')
                ->limit(300)
                ->get(['animal_lot_history.id', 'animal_lot_history.animal_id', 'animal_lot_history.entered_at', 'animal_lot_history.exited_at', 'animals.name as animal_name', 'animals.ear_tag as animal_ear_tag'])
            : collect();

        return view('lots.show', compact('lot', 'candidateAnimals', 'farmLots', 'lotHistory', 'ownerFarms'));
    }

    public function updateHistory(Request $request, Lot $lot)
    {
        $this->authorizeLot($lot);

        $data = $request->validate([
            'history_id' => ['required', 'integer'],
            'entered_at' => ['required', 'date'],
            'exited_at' => ['nullable', 'date', 'after_or_equal:entered_at'],
        ]);

        $row = \DB::table('animal_lot_history')
            ->where('id', $data['history_id'])
            ->where('lot_id', $lot->id)
            ->first();

        if (! $row) {
            return redirect()->route('lots.show', $lot)->with('error', 'No se encontró ese registro de historial.');
        }

        $entered = \Carbon\Carbon::parse($data['entered_at'])->startOfDay();
        $exited = ! empty($data['exited_at']) ? \Carbon\Carbon::parse($data['exited_at'])->endOfDay() : null;

        \DB::table('animal_lot_history')->where('id', $row->id)->update([
            'entered_at' => $entered,
            'exited_at' => $exited,
            'updated_at' => now(),
        ]);

        if (is_null($exited) && is_null($row->exited_at)) {
            \DB::table('animals')
                ->where('id', $row->animal_id)
                ->where('lot_id', $lot->id)
                ->update(['lot_assigned_at' => $entered]);
        }

        return redirect()->route('lots.show', $lot)->with('success', 'Fechas del historial actualizadas.');
    }

    public function assignAnimals(Request $request, Lot $lot)
    {
        $this->authorizeLot($lot);

        $data = $request->validate([
            'animal_ids' => ['required', 'array', 'min:1'],
            'animal_ids.*' => ['integer'],
            'target_lot_id' => ['nullable', 'integer'],
        ], [
            'animal_ids.required' => 'Selecciona al menos un animal.',
            'animal_ids.min' => 'Selecciona al menos un animal.',
        ]);

        $ids = collect($data['animal_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        $targetLotId = ! empty($data['target_lot_id']) ? (int) $data['target_lot_id'] : $lot->id;
        $targetLot = \App\Models\Lot::where('farm_id', $lot->farm_id)->where('id', $targetLotId)->first();

        if (! $targetLot) {
            return back()->withErrors(['target_lot_id' => 'El lote destino no es valido.']);
        }

        $ownerFarmIds = auth()->user()->farms()->pluck('farms.id');

        $animals = \App\Models\Animal::whereIn('farm_id', $ownerFarmIds)
            ->whereIn('id', $ids)
            ->get();

        if ($animals->isEmpty()) {
            return back()->withErrors(['animal_ids' => 'No encontramos animales válidos.']);
        }

        $added = 0;
        $moved = 0;
        $fromOtherFarm = 0;

        foreach ($animals as $animal) {
            if ((int) $animal->lot_id === (int) $targetLot->id && (int) $animal->farm_id === (int) $targetLot->farm_id) {
                continue;
            }
            $wasInLot = ! empty($animal->lot_id);
            $payload = [
                'lot_id' => $targetLot->id,
                'lot_assigned_at' => now(),
            ];
            if ((int) $animal->farm_id !== (int) $targetLot->farm_id) {
                $payload['farm_id'] = $targetLot->farm_id;
                $fromOtherFarm++;
            }
            $animal->update($payload);
            $wasInLot ? $moved++ : $added++;
        }

        $parts = [];
        if ($added > 0) { $parts[] = $added . ' agregado(s)'; }
        if ($moved > 0) { $parts[] = $moved . ' trasladado(s)'; }
        if ($fromOtherFarm > 0) { $parts[] = $fromOtherFarm . ' de otra finca'; }
        $msg = $parts ? ('Animales actualizados: ' . implode(' · ', $parts) . '.') : 'No hubo cambios.';

        return redirect()->route('lots.show', $targetLot)->with('success', $msg);
    }


    public function edit(Lot $lot)
    {
        $this->authorizeLot($lot);

        return $this->lotFormResponse('lots.edit', compact('lot'));
    }

    public function update(Request $request, Lot $lot)
    {
        $this->authorizeLot($lot);

        if (! $lot->code) {
            $request->merge([
                'code' => $request->filled('code') ? trim((string) $request->input('code')) : null,
            ]);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'area_manual' => ['nullable', 'numeric', 'min:0'],
            'area_calculated' => ['nullable', 'numeric', 'min:0'],
            'center_lat' => ['nullable', 'numeric'],
            'center_lng' => ['nullable', 'numeric'],
            'polygon_json' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $lot->code) {
            $rules['code'] = [
                'nullable',
                'string',
                'max:100',
                Rule::unique('lots', 'code')->where(fn ($query) => $query->where('farm_id', $lot->farm_id)),
            ];
        }

        $data = $request->validate($rules, $this->lotValidationMessages());

        $polygon = $this->decodePolygon($data['polygon_json'] ?? null);

        $lot->update([
            'name' => $data['name'],
            'code' => $lot->code ?: ($data['code'] ?? null),
            'type' => $data['type'] ?? null,
            'status' => $data['status'] ?? 'activo',
            'area_manual' => $data['area_manual'] ?? null,
            'area_calculated' => $data['area_calculated'] ?? null,
            'center_lat' => $data['center_lat'] ?? null,
            'center_lng' => $data['center_lng'] ?? null,
            'polygon' => $polygon,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('lots.show', $lot)
            ->with('success', 'Lote actualizado correctamente.');
    }

    public function destroy(Lot $lot)
    {
        $this->authorizeLot($lot);

        // Los animales del lote no se eliminan; quedan sin lote asignado.
        $lot->animals()->update(['lot_id' => null]);

        $lot->delete();

        return redirect()
            ->route('lots.index')
            ->with('success', 'Lote eliminado correctamente.');
    }

    public function searchLocation(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return response()->json(['results' => []], 403);
        }

        $query = trim((string) $request->get('q'));

        if ($query === '' || mb_strlen($query) < 3) {
            return response()->json(['results' => []]);
        }

        $queries = $this->locationSearchQueries($query, trim((string) $farm->location));

        $results = $this->searchGoogleLocations($queries);

        if ($results->isEmpty()) {
            $results = $this->searchPhotonLocations($queries);
        }

        if ($results->isEmpty()) {
            $results = $this->searchNominatimLocations($queries);
        }

        return response()->json(['results' => $results]);
    }

    protected function searchGoogleLocations(array $queries)
    {
        $apiKey = PlatformSetting::googleMapsApiKey();

        if (! $apiKey) {
            return collect();
        }

        foreach ($queries as $searchQuery) {
            $placesNew = $this->searchGooglePlacesNew($searchQuery, $apiKey);

            if ($placesNew->isNotEmpty()) {
                return $placesNew;
            }

            $places = $this->searchGooglePlaces($searchQuery, $apiKey);

            if ($places->isNotEmpty()) {
                return $places;
            }

            $geocoded = $this->searchGoogleGeocode($searchQuery, $apiKey);

            if ($geocoded->isNotEmpty()) {
                return $geocoded;
            }
        }

        return collect();
    }

    protected function searchGooglePlacesNew(string $query, string $apiKey)
    {
        try {
            $response = Http::timeout(8)
                ->retry(1, 200)
                ->withHeaders([
                    'X-Goog-Api-Key' => $apiKey,
                    'X-Goog-FieldMask' => 'places.displayName,places.formattedAddress,places.location,places.viewport',
                ])
                ->acceptJson()
                ->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery' => $query,
                    'regionCode' => 'CO',
                    'languageCode' => 'es',
                    'maxResultCount' => 5,
                ]);
        } catch (\Throwable) {
            return collect();
        }

        if (! $response->ok()) {
            return collect();
        }

        return collect($response->json('places', []))
            ->filter(fn ($place) => is_array($place) && isset($place['location']['latitude'], $place['location']['longitude']))
            ->map(function (array $place) use ($query) {
                $name = trim(($place['displayName']['text'] ?? 'Ubicación buscada').' - '.($place['formattedAddress'] ?? ''), ' -');

                return [
                    'lat' => (float) $place['location']['latitude'],
                    'lng' => (float) $place['location']['longitude'],
                    'name' => $name,
                    'boundingbox' => $this->googlePlacesNewViewportToBoundingBox($place['viewport'] ?? null),
                    'match_score' => $this->scoreLocationMatch($query, $name, $place['formattedAddress'] ?? ''),
                ];
            })
            ->sortByDesc('match_score')
            ->map(function (array $result) {
                unset($result['match_score']);

                return $result;
            })
            ->values();
    }

    protected function googlePlacesNewViewportToBoundingBox(?array $viewport): ?array
    {
        if (! isset(
            $viewport['low']['latitude'],
            $viewport['low']['longitude'],
            $viewport['high']['latitude'],
            $viewport['high']['longitude']
        )) {
            return null;
        }

        return [
            (float) $viewport['low']['latitude'],
            (float) $viewport['high']['latitude'],
            (float) $viewport['low']['longitude'],
            (float) $viewport['high']['longitude'],
        ];
    }

    protected function locationSearchQueries(string $query, string $farmLocation = ''): array
    {
        $baseQueries = array_values(array_unique(array_filter([
            $query,
            $this->normalizeColombianAddressQuery($query),
        ])));

        $queries = [];

        foreach ($baseQueries as $baseQuery) {
            $queries[] = $baseQuery;

            if ($farmLocation !== '' && ! $this->queryHasKnownColombianLocality($baseQuery)) {
                $queries[] = "{$baseQuery}, {$farmLocation}, Colombia";
            }

            $queries[] = "{$baseQuery}, Colombia";
        }

        if (! $this->queryHasKnownColombianLocality($query)) {
            foreach (['Medellín', 'Bogotá', 'Cali', 'Barranquilla', 'Cartagena', 'Bucaramanga', 'Pereira', 'Manizales'] as $city) {
                foreach ($baseQueries as $baseQuery) {
                    $queries[] = "{$baseQuery}, {$city}, Colombia";
                }
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }

    protected function normalizeColombianAddressQuery(string $query): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $query) ?? $query);
        $normalized = preg_replace('/#\s*([A-Za-z0-9]+)\s+([A-Za-z0-9]+)\b/', '#$1-$2', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b([A-Za-z]+)\s*(\d+[A-Za-z]*)\s*#?\s*([A-Za-z0-9]+)-?([A-Za-z0-9]+)\b/', '$1 $2 #$3-$4', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function queryHasKnownColombianLocality(string $query): bool
    {
        $normalized = $this->normalizeLocationText($query);

        foreach (['medellin', 'bogota', 'cali', 'barranquilla', 'cartagena', 'bucaramanga', 'pereira', 'manizales', 'antioquia', 'cundinamarca', 'valle', 'santander'] as $locality) {
            if (str_contains($normalized, $locality)) {
                return true;
            }
        }

        return false;
    }

    protected function searchGooglePlaces(string $query, string $apiKey)
    {
        try {
            $response = Http::timeout(8)
                ->retry(1, 200)
                ->acceptJson()
                ->get('https://maps.googleapis.com/maps/api/place/textsearch/json', [
                    'query' => $query,
                    'region' => 'co',
                    'key' => $apiKey,
                ]);
        } catch (\Throwable) {
            return collect();
        }

        if (! $response->ok() || $response->json('status') !== 'OK') {
            return collect();
        }

        return collect($response->json('results', []))
            ->filter(fn ($result) => is_array($result) && isset($result['geometry']['location']['lat'], $result['geometry']['location']['lng']))
            ->map(function (array $result) {
                return [
                    'lat' => (float) $result['geometry']['location']['lat'],
                    'lng' => (float) $result['geometry']['location']['lng'],
                    'name' => trim(($result['name'] ?? 'Ubicación buscada').' - '.($result['formatted_address'] ?? ''), ' -'),
                    'boundingbox' => $this->googleViewportToBoundingBox($result['geometry']['viewport'] ?? null),
                ];
            })
            ->values();
    }

    protected function searchGoogleGeocode(string $query, string $apiKey)
    {
        try {
            $response = Http::timeout(8)
                ->retry(1, 200)
                ->acceptJson()
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'address' => $query,
                    'region' => 'co',
                    'components' => 'country:CO',
                    'key' => $apiKey,
                ]);
        } catch (\Throwable) {
            return collect();
        }

        if (! $response->ok() || $response->json('status') !== 'OK') {
            return collect();
        }

        return collect($response->json('results', []))
            ->filter(fn ($result) => is_array($result) && isset($result['geometry']['location']['lat'], $result['geometry']['location']['lng']))
            ->map(function (array $result) {
                return [
                    'lat' => (float) $result['geometry']['location']['lat'],
                    'lng' => (float) $result['geometry']['location']['lng'],
                    'name' => $result['formatted_address'] ?? 'Ubicación buscada',
                    'boundingbox' => $this->googleViewportToBoundingBox($result['geometry']['viewport'] ?? null),
                ];
            })
            ->values();
    }

    protected function googleViewportToBoundingBox(?array $viewport): ?array
    {
        if (! isset(
            $viewport['southwest']['lat'],
            $viewport['southwest']['lng'],
            $viewport['northeast']['lat'],
            $viewport['northeast']['lng']
        )) {
            return null;
        }

        return [
            (float) $viewport['southwest']['lat'],
            (float) $viewport['northeast']['lat'],
            (float) $viewport['southwest']['lng'],
            (float) $viewport['northeast']['lng'],
        ];
    }

    protected function searchPhotonLocations(array $queries)
    {
        $collected = collect();

        foreach ($queries as $searchQuery) {
            try {
                $response = Http::timeout(8)
                    ->retry(1, 200)
                    ->acceptJson()
                    ->get('https://photon.komoot.io/api/', [
                        'q' => $searchQuery,
                        'limit' => 5,
                    ]);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->ok()) {
                continue;
            }

            $results = collect($response->json('features', []))
                ->filter(function ($feature) {
                    return is_array($feature)
                        && isset($feature['geometry']['coordinates'][0], $feature['geometry']['coordinates'][1])
                        && (($feature['properties']['countrycode'] ?? null) === 'CO');
                })
                ->map(function (array $feature) use ($searchQuery) {
                    $properties = $feature['properties'] ?? [];
                    $coordinates = $feature['geometry']['coordinates'];
                    $nameParts = array_filter([
                        $properties['name'] ?? null,
                        $properties['street'] ?? null,
                        $properties['city'] ?? null,
                        $properties['state'] ?? null,
                        $properties['country'] ?? null,
                    ]);
                    $name = implode(', ', array_unique($nameParts)) ?: 'Ubicación buscada';
                    $locality = implode(' ', array_filter([
                        $properties['city'] ?? null,
                        $properties['county'] ?? null,
                        $properties['state'] ?? null,
                        $properties['country'] ?? null,
                    ]));

                    return [
                        'lat' => (float) $coordinates[1],
                        'lng' => (float) $coordinates[0],
                        'name' => $name,
                        'boundingbox' => $this->photonExtentToBoundingBox($properties['extent'] ?? null),
                        'match_score' => $this->scoreLocationMatch($searchQuery, $name, $locality),
                    ];
                })
                ->values();

            if ($results->isNotEmpty()) {
                $collected = $collected->merge($results);
            }
        }

        return $collected
            ->unique(fn ($result) => round((float) $result['lat'], 7).','.round((float) $result['lng'], 7).':'.$result['name'])
            ->sortByDesc('match_score')
            ->map(function (array $result) {
                unset($result['match_score']);

                return $result;
            })
            ->values();
    }

    protected function scoreLocationMatch(string $query, string $name, string $locality = ''): int
    {
        $tokens = collect(preg_split('/\s+/', $this->normalizeLocationText($query)) ?: [])
            ->filter(fn ($token) => mb_strlen($token) > 2)
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $normalizedName = $this->normalizeLocationText($name);
        $normalizedLocality = $this->normalizeLocationText($locality);
        $normalizedQuery = $this->normalizeLocationText($query);

        return $tokens->reduce(function (int $score, string $token) use ($normalizedName, $normalizedLocality) {
            $isAddressToken = (bool) preg_match('/\d/', $token);

            if (str_contains($normalizedName, $token)) {
                $score += $isAddressToken ? 10 : 1;
            }

            if (str_contains($normalizedLocality, $token)) {
                $score += $isAddressToken ? 2 : 4;
            }

            return $score;
        }, str_contains($normalizedName, $normalizedQuery) ? 20 : 0);
    }

    protected function normalizeLocationText(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = $normalized === false ? $value : $normalized;
        $normalized = mb_strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    protected function photonExtentToBoundingBox(?array $extent): ?array
    {
        if (! is_array($extent) || count($extent) !== 4) {
            return null;
        }

        return [
            (float) $extent[3],
            (float) $extent[1],
            (float) $extent[0],
            (float) $extent[2],
        ];
    }

    protected function searchNominatimLocations(array $queries)
    {
        foreach ($queries as $searchQuery) {
            try {
                $response = Http::timeout(8)
                    ->retry(1, 200)
                    ->withUserAgent('InterFarm location search (https://app.somosinterfarm.com)')
                    ->acceptJson()
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'format' => 'jsonv2',
                        'q' => $searchQuery,
                        'countrycodes' => 'co',
                        'limit' => 3,
                        'addressdetails' => 1,
                    ]);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->ok()) {
                continue;
            }

            $results = collect($response->json())
                ->filter(fn ($result) => is_array($result) && isset($result['lat'], $result['lon']))
                ->map(function (array $result) {
                    return [
                        'lat' => (float) $result['lat'],
                        'lng' => (float) $result['lon'],
                        'name' => $result['display_name'] ?? 'Ubicación buscada',
                        'boundingbox' => $result['boundingbox'] ?? null,
                    ];
                })
                ->values();

            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        return collect();
    }

    protected function authorizeLot(Lot $lot): void
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $lot->farm_id !== (int) $farm->id) {
            abort(403);
        }
    }

    protected function lotValidationMessages(): array
    {
        return [
            'code.required' => 'Debes asignar un código único al lote.',
            'code.unique' => 'Ya existe un lote con este código en esta finca.',
            'code.max' => 'El código del lote no puede tener más de 100 caracteres.',
        ];
    }

    protected function lotFormResponse(string $view, array $data)
    {
        return response(view($view, $data))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function decodePolygon(?string $polygonJson): ?array
    {
        if (! $polygonJson) {
            return null;
        }

        $decoded = json_decode($polygonJson, true);

        if (! is_array($decoded)) {
            return null;
        }

        $points = collect($decoded)
            ->map(function ($point) {
                if (! is_array($point)) {
                    return null;
                }

                $lat = isset($point['lat']) ? (float) $point['lat'] : null;
                $lng = isset($point['lng']) ? (float) $point['lng'] : null;

                if ($lat === null || $lng === null) {
                    return null;
                }

                return [
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return count($points) >= 3 ? $points : null;
    }
}
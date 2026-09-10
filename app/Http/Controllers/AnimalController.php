<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalPhoto;
use App\Models\Event;
use App\Models\FarmSetting;
use App\Models\Lot;
use App\Models\MeatProduction;
use App\Models\MilkProduction;
use App\Support\EscapesLikeSearch;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class AnimalController extends Controller
{
    use EscapesLikeSearch;

    protected const MAX_ANIMAL_WEIGHT_KG = 2000;
    protected const MAX_CALVING_COUNT = 25;

    protected const ANIMAL_PHOTO_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'bmp',
        'tif',
        'tiff',
        'avif',
        'heic',
        'heif',
    ];

    public function index(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $status = $request->get('status', 'activos');
        $search = trim((string) $request->get('search'));
        $sex = trim((string) $request->get('sex'));
        $purpose = trim((string) $request->get('purpose'));
        $reproduction = trim((string) $request->get('reproduction'));
        $productionStatus = trim((string) $request->get('production_status'));
        $lotId = $request->filled('lot_id') && ctype_digit((string) $request->get('lot_id'))
            ? (int) $request->get('lot_id')
            : null;
        $sort = $request->get('sort', 'latest');
        $sortOptions = $this->animalSortOptions();

        if (! in_array($status, ['activos', 'vendidos', 'fallecidos', 'todos'], true)) {
            $status = 'activos';
        }

        if (! in_array($purpose, ['leche', 'carne', 'doble_proposito', 'crianza'], true)) {
            $purpose = '';
        }

        if (! in_array($reproduction, ['pregnant', 'not_pregnant'], true)) {
            $reproduction = '';
        }

        if (! in_array($productionStatus, ['producing', 'not_producing'], true)) {
            $productionStatus = '';
        }

        if (! array_key_exists($sort, $sortOptions)) {
            $sort = 'latest';
        }

        $baseQuery = Animal::where('farm_id', $farm->id);

        $lots = Lot::where('farm_id', $farm->id)
            ->orderBy('name')
            ->get();

        $transferFarms = $this->farmsOwner()
            ->farms()
            ->with(['lots' => fn ($query) => $query->where('status', 'activo')->orderBy('name')])
            ->where('farms.id', '!=', $farm->id)
            ->orderBy('name')
            ->get();

        if ($lotId !== null && ! $lots->contains('id', $lotId)) {
            $lotId = null;
        }

        $activeAnimalsCount = (clone $baseQuery)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', [
                        Animal::STATUS_SOLD,
                        Animal::STATUS_DECEASED,
                        'sold',
                        'deceased',
                        'dead',
                    ]);
            })
            ->count();

        $soldAnimalsCount = (clone $baseQuery)
            ->where('status', Animal::STATUS_SOLD)
            ->count();

        $deceasedAnimalsCount = (clone $baseQuery)
            ->whereIn('status', [
                Animal::STATUS_DECEASED,
                'deceased',
                'dead',
            ])
            ->count();

        $today = now()->toDateString();

        $animalsQuery = Animal::where('farm_id', $farm->id)
            ->with(['photos', 'lot'])
            ->withCount([
                'milkProductions',
                'meatProductions',
                'milkProductions as today_milk_productions_count' => fn ($query) => $query->whereDate('production_date', $today),
                'meatProductions as today_meat_productions_count' => fn ($query) => $query->whereDate('production_date', $today),
            ])
            ->when($search !== '', function ($query) use ($search) {
                if (preg_match('/^\d+$/', $search)) {
                    // Busqueda por numero de arete: coincidencia exacta (ignora ceros a la izquierda)
                    $query->where(function ($q) use ($search) {
                        $q->where('ear_tag', $search)
                            ->orWhereRaw('CAST(ear_tag AS UNSIGNED) = ?', [(int) $search]);
                    });
                } else {
                    $this->whereLikeAny($query, ['name', 'internal_code', 'ear_tag', 'breed'], $search);
                }
            })
            ->when(in_array($sex, ['hembra', 'macho'], true), function ($query) use ($sex) {
                $query->where('sex', $sex);
            })
            ->when($purpose !== '', function ($query) use ($purpose) {
                $query->where('purpose', $purpose);
            })
            ->when($reproduction === 'pregnant', function ($query) {
                $query->where('sex', 'hembra')
                    ->where('is_pregnant', 'si');
            })
            ->when($reproduction === 'not_pregnant', function ($query) {
                $query->where('sex', 'hembra')
                    ->where(function ($query) {
                        $query->whereNull('is_pregnant')
                            ->orWhere('is_pregnant', '!=', 'si');
                    });
            })
            ->when($productionStatus === 'producing', function ($query) {
                $query->where('sex', 'hembra')
                    ->where(function ($query) {
                        $query->whereHas('milkProductions')
                            ->orWhere(function ($query) {
                                $query->whereIn('purpose', ['leche', 'doble_proposito'])
                                    ->where(function ($query) {
                                        $query->where('has_calved_before', 'si')
                                            ->orWhere('calving_count', '>', 0);
                                    });
                            });
                    });
            })
            ->when($productionStatus === 'not_producing', function ($query) {
                $query->where('sex', 'hembra')
                    ->whereDoesntHave('milkProductions')
                    ->where(function ($query) {
                        $query->whereNotIn('purpose', ['leche', 'doble_proposito'])
                            ->orWhereNull('purpose')
                            ->orWhere(function ($query) {
                                $query->where(function ($query) {
                                    $query->whereNull('has_calved_before')
                                        ->orWhere('has_calved_before', '!=', 'si');
                                })
                                    ->where(function ($query) {
                                        $query->whereNull('calving_count')
                                            ->orWhere('calving_count', '<=', 0);
                                    });
                            });
                    });
            })
            ->when($lotId !== null, function ($query) use ($lotId) {
                $query->where('lot_id', $lotId);
            });

        if ($status === 'vendidos') {
            $animalsQuery->where('status', Animal::STATUS_SOLD);
        } elseif ($status === 'fallecidos') {
            $animalsQuery->whereIn('status', [
                Animal::STATUS_DECEASED,
                'deceased',
                'dead',
            ]);
        } elseif ($status === 'todos') {
            // Sin filtro de estado: incluye activos, vendidos y fallecidos.
            $status = 'todos';
        } else {
            $animalsQuery->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', [
                        Animal::STATUS_SOLD,
                        Animal::STATUS_DECEASED,
                        'sold',
                        'deceased',
                        'dead',
                    ]);
            });

            $status = 'activos';
        }

        $this->applyAnimalSorting($animalsQuery, $sort);

        $animals = $animalsQuery
            ->paginate(25)
            ->withQueryString();

        return view('animals.index', compact(
            'animals',
            'status',
            'search',
            'sex',
            'purpose',
            'reproduction',
            'productionStatus',
            'lotId',
            'sort',
            'sortOptions',
            'lots',
            'transferFarms',
            'activeAnimalsCount',
            'soldAnimalsCount',
            'deceasedAnimalsCount'
        ));
    }

    protected function animalSortOptions(): array
    {
        return [
            'latest' => 'Más recientes',
            'oldest' => 'Más antiguos',
            'name_asc' => 'Nombre A-Z',
            'name_desc' => 'Nombre Z-A',
            'ear_tag_asc' => 'Arete ascendente',
            'ear_tag_desc' => 'Arete descendente',
            'code_asc' => 'Código ascendente',
            'code_desc' => 'Código descendente',
            'birth_date_asc' => 'Nacimiento antiguo',
            'birth_date_desc' => 'Nacimiento reciente',
            'weight_asc' => 'Peso menor a mayor',
            'weight_desc' => 'Peso mayor a menor',
            'sex_asc' => 'Sexo A-Z',
            'sex_desc' => 'Sexo Z-A',
            'purpose_asc' => 'Propósito A-Z',
            'purpose_desc' => 'Propósito Z-A',
            'category_asc' => 'Categoría A-Z',
            'category_desc' => 'Categoría Z-A',
            'breed_asc' => 'Raza A-Z',
            'breed_desc' => 'Raza Z-A',
            'lot_asc' => 'Lote A-Z',
            'lot_desc' => 'Lote Z-A',
            'status_asc' => 'Estado A-Z',
            'status_desc' => 'Estado Z-A',
            'last_weight_date_desc' => 'Pesaje reciente',
            'last_weight_date_asc' => 'Pesaje antiguo',
        ];
    }

    protected function applyAnimalSorting($query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('animals.created_at')->orderBy('animals.id'),
            'name_asc' => $this->orderByNullableText($query, 'name'),
            'name_desc' => $this->orderByNullableText($query, 'name', 'desc'),
            'ear_tag_asc' => $this->orderByNullableNaturalText($query, 'ear_tag'),
            'ear_tag_desc' => $this->orderByNullableNaturalText($query, 'ear_tag', 'desc'),
            'code_asc' => $this->orderByNullableNaturalText($query, 'internal_code'),
            'code_desc' => $this->orderByNullableNaturalText($query, 'internal_code', 'desc'),
            'birth_date_asc' => $this->orderByNullableColumn($query, 'birth_date'),
            'birth_date_desc' => $this->orderByNullableColumn($query, 'birth_date', 'desc'),
            'weight_asc' => $this->orderByNullableColumn($query, 'weight_current'),
            'weight_desc' => $this->orderByNullableColumn($query, 'weight_current', 'desc'),
            'sex_asc' => $this->orderByNullableText($query, 'sex'),
            'sex_desc' => $this->orderByNullableText($query, 'sex', 'desc'),
            'purpose_asc' => $this->orderByNullableText($query, 'purpose'),
            'purpose_desc' => $this->orderByNullableText($query, 'purpose', 'desc'),
            'category_asc' => $this->orderByNullableText($query, 'category'),
            'category_desc' => $this->orderByNullableText($query, 'category', 'desc'),
            'breed_asc' => $this->orderByNullableText($query, 'breed'),
            'breed_desc' => $this->orderByNullableText($query, 'breed', 'desc'),
            'lot_asc' => $this->orderByLotName($query),
            'lot_desc' => $this->orderByLotName($query, 'desc'),
            'status_asc' => $this->orderByNullableText($query, 'status'),
            'status_desc' => $this->orderByNullableText($query, 'status', 'desc'),
            'last_weight_date_asc' => $this->orderByNullableColumn($query, 'last_weight_date'),
            'last_weight_date_desc' => $this->orderByNullableColumn($query, 'last_weight_date', 'desc'),
            default => $query->latest('animals.created_at')->orderByDesc('animals.id'),
        };
    }

    protected function orderByLotName($query, string $direction = 'asc'): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query
            ->orderByRaw('CASE WHEN animals.lot_id IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy(
                Lot::select('name')
                    ->whereColumn('lots.id', 'animals.lot_id')
                    ->limit(1),
                $direction
            )
            ->orderBy('animals.name')
            ->orderBy('animals.id');
    }

    protected function orderByNullableColumn($query, string $column, string $direction = 'asc'): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query
            ->orderByRaw("CASE WHEN animals.{$column} IS NULL THEN 1 ELSE 0 END ASC")
            ->orderBy("animals.{$column}", $direction)
            ->orderBy('animals.id');
    }

    protected function orderByNullableText($query, string $column, string $direction = 'asc'): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query
            ->orderByRaw("CASE WHEN animals.{$column} IS NULL OR animals.{$column} = '' THEN 1 ELSE 0 END ASC")
            ->orderBy("animals.{$column}", $direction)
            ->orderBy('animals.id');
    }

    protected function orderByNullableNaturalText($query, string $column, string $direction = 'asc'): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query
            ->orderByRaw("CASE WHEN animals.{$column} IS NULL OR animals.{$column} = '' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CASE WHEN animals.{$column} REGEXP '^[0-9]+$' THEN 0 ELSE 1 END ASC")
            ->orderByRaw("CASE WHEN animals.{$column} REGEXP '^[0-9]+$' THEN CAST(animals.{$column} AS UNSIGNED) ELSE NULL END {$direction}")
            ->orderBy("animals.{$column}", $direction)
            ->orderBy('animals.id');
    }

    public function create()
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $animals = Animal::where('farm_id', $farm->id)
            ->orderBy('name')
            ->get();

        $lots = Lot::where('farm_id', $farm->id)
            ->orderBy('name')
            ->get();

        $eligibleDams = $this->getEligibleDams($farm->id);
        $eligibleSires = $this->getEligibleSires($farm->id);

        return view('animals.create', compact('animals', 'lots', 'eligibleDams', 'eligibleSires'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $farm = $user->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $rules = $this->rules(true, $farm->id);
        $rules['internal_code'] = array_values(array_unique([
            'required',
            ...$rules['internal_code'],
        ]));

        $data = $request->validate($rules, $this->animalValidationMessages());

        $this->validateParents($farm->id, $data);
        $this->validateLot($farm->id, $data['lot_id'] ?? null);

        $photos = $request->file('photos', []);
        $animal = null;

        DB::transaction(function () use ($farm, $data, $photos, &$animal) {
            $animalData = $this->buildAnimalPayload($farm->id, $data);

            $animal = Animal::create($animalData);

            $createdPhotos = [];

            foreach ($photos as $index => $photo) {
                $path = $this->storeAnimalPhoto($photo);

                $createdPhotos[] = AnimalPhoto::create([
                    'animal_id' => $animal->id,
                    'path' => $path,
                    'is_main' => $index === 0,
                ]);
            }

            $mainPhoto = collect($createdPhotos)->firstWhere('is_main', true)
                ?? collect($createdPhotos)->first();

            $this->syncAnimalMainPhotoField($animal, $mainPhoto?->path);
        });

        if (! $user->has_completed_onboarding) {
            $user->update([
                'has_completed_onboarding' => true,
            ]);

            return redirect()
                ->route('dashboard')
                ->with('success', 'Animal registrado correctamente. Ya terminaste la configuración inicial.');
        }

        return redirect()
            ->route('animals.show', $animal)
            ->with('success', 'Animal registrado correctamente. Si es una hembra reproductiva, puedes registrar su preñez desde esta ficha.');
    }

    public function show(Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $animal->load(['dam', 'sire', 'pregnancySire', 'photos', 'lot']);

        $offspring = Animal::query()
            ->whereIn('farm_id', auth()->user()->farms()->pluck('farms.id'))
            ->where(function ($q) use ($animal) {
                $q->where('dam_id', $animal->id)->orWhere('sire_id', $animal->id);
            })
            ->orderBy('name')
            ->get();

        $productionRecords = $this->getProductionRecords($animal);
        $healthRecords = $this->getHealthRecords($animal);
        $eligibleSires = $this->getEligibleSires($animal->farm_id, $animal->id);

        $eligibleOffspring = Animal::whereIn('farm_id', $this->farmsOwner()->farms()->pluck('farms.id'))
            ->where('id', '!=', $animal->id)
            ->whereNotIn('id', $offspring->pluck('id'))
            ->with('farm:id,name')
            ->orderBy('name')
            ->get();

        return view('animals.show', compact('animal', 'productionRecords', 'healthRecords', 'eligibleSires', 'offspring', 'eligibleOffspring'));
    }

    public function attachOffspring(Request $request, Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $data = $request->validate([
            'child_id' => ['required', 'integer'],
        ]);

        $ownerFarmIds = $this->farmsOwner()->farms()->pluck('farms.id');
        $child = Animal::whereIn('farm_id', $ownerFarmIds)->find($data['child_id']);

        if (! $child || $child->id === $animal->id) {
            return back()->with('error', 'La cría seleccionada no es válida.');
        }

        if ($animal->dam_id === $child->id || $animal->sire_id === $child->id) {
            return back()->with('error', 'Ese animal es un progenitor de este animal; no puede ser también su cría.');
        }

        if ($animal->sex === 'hembra') {
            $child->dam_id = $animal->id;
        } else {
            $child->sire_id = $animal->id;
        }
        $child->save();

        return back()->with('success', 'Cría vinculada: ' . ($child->name ?: 'Animal '.$child->id) . '.');
    }

    public function edit(Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $farmId = $animal->farm_id;

        $animals = Animal::where('farm_id', $farmId)
            ->where('id', '!=', $animal->id)
            ->orderBy('name')
            ->get();

        $lots = Lot::where('farm_id', $farmId)
            ->orderBy('name')
            ->get();

        $eligibleDams = $this->getEligibleDams($farmId, $animal->id);
        $eligibleSires = $this->getEligibleSires($farmId, $animal->id);
        $transferFarms = $this->farmsOwner()
            ->farms()
            ->with(['lots' => fn ($query) => $query->where('status', 'activo')->orderBy('name')])
            ->where('farms.id', '!=', $farmId)
            ->orderBy('name')
            ->get();

        $animal->load(['photos', 'lot']);

        return view('animals.edit', compact('animal', 'animals', 'lots', 'eligibleDams', 'eligibleSires', 'transferFarms'));
    }

    public function update(Request $request, Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $farmId = $animal->farm_id;

        $rules = $this->rules(false, $farmId, $animal->id);
        $rules['photos'] = ['nullable', 'array', 'max:6'];
        $rules['photos.*'] = $this->animalPhotoRules();
        $rules['main_photo_id'] = ['nullable', 'string', 'max:50'];
        $rules['photo_management_present'] = ['nullable', 'boolean'];
        $rules['keep_existing_photos'] = ['nullable', 'array'];
        $rules['keep_existing_photos.*'] = ['integer'];

        $data = $request->validate($rules, $this->animalValidationMessages());

        $this->validateParents($farmId, $data, $animal->id);
        $this->validateLot($farmId, $data['lot_id'] ?? null);

        $newPhotos = $request->file('photos', []);
        $existingPhotos = $animal->photos()->orderByDesc('is_main')->orderBy('id')->get();

        $keepExistingPhotoIds = collect(
            $request->boolean('photo_management_present')
                ? $request->input('keep_existing_photos', [])
                : $existingPhotos->pluck('id')->all()
        )
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $existingPhotos->pluck('id')->contains($id))
            ->values()
            ->all();

        $mainPhotoId = $request->input('main_photo_id');

        DB::transaction(function () use ($farmId, $animal, $data, $existingPhotos, $keepExistingPhotoIds, $newPhotos, $mainPhotoId) {
            $animalData = $this->buildAnimalPayload($farmId, $data, $animal);
            $animal->update($animalData);

            $photosToDelete = $existingPhotos->filter(function ($photo) use ($keepExistingPhotoIds) {
                return ! in_array($photo->id, $keepExistingPhotoIds, true);
            });

            foreach ($photosToDelete as $photo) {
                if ($photo->path && Storage::disk('public')->exists($photo->path)) {
                    Storage::disk('public')->delete($photo->path);
                }

                $photo->delete();
            }

            $createdPhotos = collect();

            foreach ($newPhotos as $photo) {
                $path = $this->storeAnimalPhoto($photo);

                $createdPhotos->push(
                    AnimalPhoto::create([
                        'animal_id' => $animal->id,
                        'path' => $path,
                        'is_main' => false,
                    ])
                );
            }

            $finalPhotos = $animal->photos()->orderBy('id')->get()->values();

            $resolvedMainPhoto = null;

            if ($mainPhotoId) {
                if (str_starts_with($mainPhotoId, 'new_')) {
                    $newIndex = (int) str_replace('new_', '', $mainPhotoId);
                    $resolvedMainPhoto = $createdPhotos->values()->get($newIndex);
                } else {
                    $resolvedMainPhoto = $finalPhotos->firstWhere('id', (int) $mainPhotoId);
                }
            }

            if (! $resolvedMainPhoto) {
                $resolvedMainPhoto = $finalPhotos->first();
            }

            foreach ($finalPhotos as $photo) {
                $photo->update([
                    'is_main' => $resolvedMainPhoto && $photo->id === $resolvedMainPhoto->id,
                ]);
            }

            $this->syncAnimalMainPhotoField($animal, $resolvedMainPhoto?->path);
        });

        return redirect()
            ->route('animals.show', $animal)
            ->with('success', 'Animal actualizado correctamente');
    }

    public function destroy(Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        DB::transaction(function () use ($animal) {
            foreach ($animal->photos as $photo) {
                if ($photo->path && Storage::disk('public')->exists($photo->path)) {
                    Storage::disk('public')->delete($photo->path);
                }
            }

            $animal->photos()->delete();
            $animal->delete();
        });

        return redirect()
            ->route('animals.index')
            ->with('success', 'Animal eliminado correctamente.');
    }

    public function bulkTransfer(Request $request)
    {
        $currentFarm = $request->user()->currentFarm();

        if (! $currentFarm) {
            return redirect()->route('farms.create');
        }

        $data = $request->validate([
            'animal_ids' => ['required', 'array', 'min:1'],
            'animal_ids.*' => ['integer'],
            'target_farm_id' => ['required', 'integer'],
            'target_lot_id' => ['nullable', 'integer'],
        ], [
            'animal_ids.required' => 'Selecciona al menos un animal para trasladar.',
            'animal_ids.min' => 'Selecciona al menos un animal para trasladar.',
            'target_farm_id.required' => 'Selecciona la finca destino.',
        ]);

        $farmsOwner = $this->farmsOwner();
        $targetFarm = $farmsOwner
            ->farms()
            ->where('farms.id', $data['target_farm_id'])
            ->first();

        if (! $targetFarm || (int) $targetFarm->id === (int) $currentFarm->id) {
            throw ValidationException::withMessages([
                'target_farm_id' => 'Selecciona otra finca del cliente como destino.',
            ]);
        }

        $targetLot = ! empty($data['target_lot_id'])
            ? Lot::where('farm_id', $targetFarm->id)
                ->where('id', $data['target_lot_id'])
                ->first()
            : null;

        if (! empty($data['target_lot_id']) && ! $targetLot) {
            throw ValidationException::withMessages([
                'target_lot_id' => 'El lote destino no pertenece a la finca seleccionada.',
            ]);
        }

        $animalIds = collect($data['animal_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $animals = Animal::where('farm_id', $currentFarm->id)
            ->whereIn('id', $animalIds)
            ->get();

        if ($animals->isEmpty() || $animals->count() !== $animalIds->count()) {
            throw ValidationException::withMessages([
                'animal_ids' => 'No encontramos animales válidos en la finca actual.',
            ]);
        }

        $this->moveAnimalsToFarm($animals, $currentFarm, $targetFarm, $targetLot);

        return redirect()
            ->route('animals.index')
            ->with('success', $animals->count() . ' animal(es) trasladado(s) a ' . $targetFarm->name . ($targetLot ? ' / ' . $targetLot->name : ' sin lote asignado') . '.');
    }

    public function transfer(Request $request, Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $data = $request->validate([
            'target_farm_id' => ['required', 'integer'],
            'target_lot_id' => ['nullable', 'integer'],
        ], [
            'target_farm_id.required' => 'Selecciona la finca destino.',
        ]);

        $sourceFarm = $animal->farm;
        $targetFarm = $this->farmsOwner()
            ->farms()
            ->where('farms.id', $data['target_farm_id'])
            ->first();

        if (! $sourceFarm || ! $targetFarm || (int) $targetFarm->id === (int) $sourceFarm->id) {
            throw ValidationException::withMessages([
                'target_farm_id' => 'Selecciona otra finca del cliente como destino.',
            ]);
        }

        $targetLot = ! empty($data['target_lot_id'])
            ? Lot::where('farm_id', $targetFarm->id)
                ->where('id', $data['target_lot_id'])
                ->first()
            : null;

        if (! empty($data['target_lot_id']) && ! $targetLot) {
            throw ValidationException::withMessages([
                'target_lot_id' => 'El lote destino no pertenece a la finca seleccionada.',
            ]);
        }

        $this->moveAnimalsToFarm(collect([$animal]), $sourceFarm, $targetFarm, $targetLot);

        return redirect()
            ->route('animals.edit', $animal)
            ->with('success', 'Animal trasladado a ' . $targetFarm->name . ($targetLot ? ' / ' . $targetLot->name : ' sin lote asignado') . '.');
    }

    public function production(Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $productionRecords = collect($this->getProductionRecords($animal))
            ->sortByDesc('date')
            ->values();

        return view('animals.production', compact('animal', 'productionRecords'));
    }

    public function health(Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $healthRecords = collect($this->getHealthRecords($animal))
            ->sortByDesc('date')
            ->values();

        return view('animals.health', compact('animal', 'healthRecords'));
    }

    public function storeProduction(Request $request, Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        if (! $animal->canRegisterProduction()) {
            return back()
                ->withErrors(['weight' => $animal->productionBlockedReason()])
                ->withInput();
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'period' => ['nullable', 'in:mañana,tarde,mañana_tarde'],
            'liters' => ['nullable', 'numeric', 'min:0'],
            'liters_morning' => ['nullable', 'numeric', 'min:0'],
            'liters_afternoon' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:' . self::MAX_ANIMAL_WEIGHT_KG],
            'feeding_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ], [
            'period.in' => 'El período debe ser mañana, tarde o mañana y tarde.',
            'weight.max' => 'El peso no puede ser mayor a 2.000 kg.',
        ]);

        $milkEntries = $this->productionMilkEntries($data, $animal);

        if ($animal->canRegisterMilkProduction()
            && (($data['liters'] ?? '') !== '' || ($data['liters_morning'] ?? '') !== '' || ($data['liters_afternoon'] ?? '') !== '')
            && empty($data['period'])) {
            return back()
                ->withErrors(['period' => 'Debes seleccionar el período de producción.'])
                ->withInput();
        }

        if (($data['period'] ?? null) === 'mañana_tarde' && $animal->canRegisterMilkProduction()) {
            if (($data['liters_morning'] ?? '') === '' || ($data['liters_afternoon'] ?? '') === '') {
                return back()
                    ->withErrors(['liters' => 'Debes registrar los litros de la mañana y de la tarde.'])
                    ->withInput();
            }
        }

        if ($animal->isMale() && $milkEntries !== []) {
            return back()
                ->withErrors(['liters' => 'No puedes registrar litros de leche en un macho.'])
                ->withInput();
        }

        if ($milkEntries !== [] && ! $animal->canRegisterMilkProduction()) {
            return back()
                ->withErrors(['liters' => $animal->milkProductionBlockedReason()])
                ->withInput();
        }

        $weight = isset($data['weight']) && $data['weight'] !== ''
            ? (float) $data['weight']
            : null;

        if ($milkEntries === [] && $weight === null) {
            return back()
                ->withErrors(['weight' => 'Debes registrar al menos litros o peso en producción.'])
                ->withInput();
        }

        $duplicatePeriods = $this->duplicateMilkPeriods($animal->farm_id, $animal->id, $data['date'], $milkEntries);

        if ($duplicatePeriods !== []) {
            return back()
                ->withErrors([
                    'period' => $this->duplicateMilkPeriodMessage($duplicatePeriods, $data['period'] ?? null),
                ])
                ->withInput();
        }

        if ($milkEntries !== []) {
            foreach ($milkEntries as $entry) {
                MilkProduction::create([
                    'farm_id' => $animal->farm_id,
                    'animal_id' => $animal->id,
                    'production_date' => $data['date'],
                    'period' => $entry['period'],
                    'liters' => $entry['liters'],
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        }

        if ($weight !== null && Schema::hasColumn('animals', 'weight_current')) {
            $animal->update([
                'weight_current' => $weight,
            ]);
        }

        $legacyRecords = $this->getLegacyProductionRecords($animal);

        if ($milkEntries === []) {
            $legacyRecords[] = [
                'date' => $data['date'],
                'period' => $data['period'] ?? null,
                'liters' => null,
                'weight' => $weight,
                'feeding_type' => $data['feeding_type'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_at' => now()->toDateTimeString(),
            ];
        } else {
            foreach ($milkEntries as $entry) {
                $legacyRecords[] = [
                    'date' => $data['date'],
                    'period' => $entry['period'],
                    'liters' => $entry['liters'],
                    'weight' => $weight,
                    'feeding_type' => $data['feeding_type'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_at' => now()->toDateTimeString(),
                ];
            }
        }

        $this->saveMetaSection($animal, 'productions', $legacyRecords);

        $returnTo = (string) $request->input('_return_to', '');

        if ($returnTo !== '' && Str::startsWith($returnTo, url('/'))) {
            return redirect()
                ->to($returnTo)
                ->with('success', 'Registro de producción guardado correctamente.');
        }

        return redirect()
            ->route('animals.show', $animal)
            ->with('success', 'Registro de producción guardado correctamente.');
    }

    public function storeHealth(Request $request, Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'treatment_type' => ['nullable', 'string', 'max:255'],
            'disease' => ['nullable', 'string', 'max:255'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'medication' => ['nullable', 'string', 'max:255'],
            'days' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        if (
            empty($data['treatment_type']) &&
            empty($data['disease']) &&
            empty($data['diagnosis']) &&
            empty($data['medication']) &&
            empty($data['notes'])
        ) {
            return back()
                ->withErrors(['treatment_type' => 'Debes completar al menos un dato del registro de salud.'])
                ->withInput();
        }

        $records = $this->getHealthRecords($animal);

        $records[] = [
            'date' => $data['date'],
            'treatment_type' => $data['treatment_type'] ?? null,
            'disease' => $data['disease'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'medication' => $data['medication'] ?? null,
            'days' => isset($data['days']) ? (int) $data['days'] : null,
            'notes' => $data['notes'] ?? null,
            'created_at' => now()->toDateTimeString(),
        ];

        $this->saveMetaSection($animal, 'health_records', $records);

        return redirect()
            ->route('animals.show', $animal)
            ->with('success', 'Registro de salud guardado correctamente.');
    }

    public function updateReproductiveStatus(Request $request, Animal $animal)
    {
        if (! $this->userCanAccessFarm($animal->farm_id)) {
            abort(403);
        }

        $data = $request->validate([
            'is_pregnant' => ['required', 'in:si,no'],
            'pregnancy_date' => ['nullable', 'date', 'required_if:is_pregnant,si'],
            'pregnancy_sire_id' => ['nullable', 'integer', 'exists:animals,id'],
            'pregnancy_sire_name_manual' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', 'in:monta_natural,inseminacion,embrion'],
            'last_calving_date' => ['nullable', 'date', 'before_or_equal:today'],
            'calving_count' => ['nullable', 'integer', 'min:0', 'max:' . self::MAX_CALVING_COUNT],
            'status' => ['nullable', 'in:activo,vendido,fallecido'],
            'status_date' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string'],
        ], [
            'pregnancy_date.required_if' => 'Debes indicar la fecha de preñez o servicio.',
            'last_calving_date.before_or_equal' => 'La fecha del parto no puede ser posterior a la fecha actual.',
            'calving_count.max' => 'La cantidad de partos no puede ser mayor a 25.',
        ]);

        if (! $animal->isFemale() && ($data['is_pregnant'] ?? null) === 'si') {
            return back()
                ->withErrors(['is_pregnant' => 'Solo las hembras pueden marcarse como preñadas.'])
                ->withInput();
        }

        if (! empty($data['pregnancy_sire_id'])) {
            $sire = Animal::query()
                ->where('farm_id', $animal->farm_id)
                ->where('id', $data['pregnancy_sire_id'])
                ->where('sex', 'macho')
                ->first();

            if (! $sire || ! $this->canBeSire($sire)) {
                return back()
                    ->withErrors(['pregnancy_sire_id' => 'El toro seleccionado no pertenece a esta finca o aún no tiene edad reproductiva.'])
                    ->withInput();
            }
        }

        $wasPregnant = $animal->isFemale() && $animal->is_pregnant === 'si';
        $submittedCalvingDate = $data['last_calving_date'] ?? null;
        $currentCalvingDate = $animal->last_calving_date?->format('Y-m-d');
        $registeredDelivery = $wasPregnant
            && ($data['is_pregnant'] ?? null) === 'no'
            && ! empty($submittedCalvingDate)
            && $submittedCalvingDate !== $currentCalvingDate;

        $calvingCount = $data['calving_count'] ?? $animal->calving_count;

        if ($registeredDelivery) {
            $calvingCount = min(((int) ($animal->calving_count ?? 0)) + 1, self::MAX_CALVING_COUNT);
        }

        $payload = [
            'is_pregnant' => $animal->isFemale() ? $data['is_pregnant'] : 'no',
            'pregnancy_date' => $animal->isFemale() && $data['is_pregnant'] === 'si' ? ($data['pregnancy_date'] ?? null) : null,
            'pregnancy_sire_id' => $animal->isFemale() && $data['is_pregnant'] === 'si' ? ($data['pregnancy_sire_id'] ?? null) : null,
            'pregnancy_sire_name_manual' => $animal->isFemale() && $data['is_pregnant'] === 'si' ? ($data['pregnancy_sire_name_manual'] ?? null) : null,
            'service_type' => $animal->isFemale() && $data['is_pregnant'] === 'si' ? ($data['service_type'] ?? null) : null,
            'has_calved_before' => $registeredDelivery || ((int) ($calvingCount ?? 0)) > 0 ? 'si' : ($animal->has_calved_before ?? null),
            'last_calving_date' => $registeredDelivery ? $data['last_calving_date'] : ($data['last_calving_date'] ?? $animal->last_calving_date),
            'calving_count' => $calvingCount,
            'status' => $data['status'] ?? ($animal->status ?? Animal::STATUS_ACTIVE),
            'status_date' => $data['status_date'] ?? null,
            'status_notes' => $data['status_notes'] ?? null,
        ];

        $animal->update($this->filterExistingAnimalColumns($payload));

        return redirect()
            ->route('animals.show', $animal)
            ->with('success', 'Seguimiento reproductivo actualizado correctamente.');
    }

    protected function storeAnimalPhoto(UploadedFile $photo): string
    {
        try {
            $manager = new ImageManager(new GdDriver());

            $image = $manager->read($photo->getRealPath());
            $image->scaleDown(width: 1800, height: 1800);

            $filename = 'animal-' . Str::uuid();

            try {
                $encoded = $image->encode(new WebpEncoder(quality: 75));
                $path = 'animals/' . $filename . '.webp';
            } catch (Throwable $e) {
                $encoded = $image->encode(new JpegEncoder(quality: 78));
                $path = 'animals/' . $filename . '.jpg';
            }

            if (! Storage::disk('public')->put($path, (string) $encoded)) {
                throw new \RuntimeException('The optimized animal photo could not be written.');
            }

            return $path;
        } catch (Throwable $e) {
            return $this->storeAnimalPhotoOriginal($photo);
        }
    }

    protected function productionMilkEntries(array $data, Animal $animal): array
    {
        if (! $animal->canRegisterMilkProduction()) {
            return [];
        }

        if (($data['period'] ?? null) === 'mañana_tarde') {
            return collect([
                ['period' => 'mañana', 'liters' => $data['liters_morning'] ?? null],
                ['period' => 'tarde', 'liters' => $data['liters_afternoon'] ?? null],
            ])
                ->filter(fn ($entry) => $entry['liters'] !== null && $entry['liters'] !== '')
                ->map(fn ($entry) => [
                    'period' => $entry['period'],
                    'liters' => (float) $entry['liters'],
                ])
                ->values()
                ->all();
        }

        if (isset($data['liters']) && $data['liters'] !== '') {
            return [[
                'period' => $data['period'] ?? null,
                'liters' => (float) $data['liters'],
            ]];
        }

        return [];
    }

    protected function duplicateMilkPeriods(int $farmId, int $animalId, string $date, array $milkEntries): array
    {
        $periods = collect($milkEntries)->pluck('period')->filter()->unique()->values();

        if ($periods->isEmpty()) {
            return [];
        }

        return MilkProduction::query()
            ->where('farm_id', $farmId)
            ->where('animal_id', $animalId)
            ->whereDate('production_date', $date)
            ->whereIn('period', $periods->all())
            ->pluck('period')
            ->unique()
            ->values()
            ->all();
    }

    protected function duplicateMilkPeriodMessage(array $duplicatePeriods, ?string $selectedPeriod = null): string
    {
        $periodLabel = $selectedPeriod === 'mañana_tarde' && count($duplicatePeriods) > 1
            ? 'la mañana y la tarde'
            : collect($duplicatePeriods)->map(fn ($period) => 'la ' . $period)->join(' y ');

        return 'Este animal ya tiene producción registrada para ' . $periodLabel . ' en esa fecha.';
    }

    protected function storeAnimalPhotoOriginal(UploadedFile $photo): string
    {
        $extension = strtolower($photo->extension() ?: $photo->getClientOriginalExtension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        if (! in_array($extension, self::ANIMAL_PHOTO_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'photos' => 'Una de las imágenes no tiene un formato permitido.',
            ]);
        }

        $path = Storage::disk('public')->putFileAs(
            'animals',
            $photo,
            'animal-' . Str::uuid() . '.' . $extension
        );

        if (! $path) {
            throw ValidationException::withMessages([
                'photos' => 'No se pudo guardar una de las imágenes. Revisa permisos de storage/app/public.',
            ]);
        }

        return $path;
    }

    protected function validateParents(int $farmId, array $data, ?int $ignoreAnimalId = null): void
    {
        if (! empty($data['dam_id']) && ! empty($data['sire_id']) && (int) $data['dam_id'] === (int) $data['sire_id']) {
            throw ValidationException::withMessages([
                'sire_id' => 'La madre y el padre no pueden ser el mismo animal.',
            ]);
        }

        if (! empty($data['dam_id'])) {
            $damQuery = Animal::whereIn('farm_id', $this->farmsOwner()->farms()->pluck('farms.id'))
                ->where('id', $data['dam_id'])
                ->where('sex', 'hembra');

            if ($ignoreAnimalId) {
                $damQuery->where('id', '!=', $ignoreAnimalId);
            }

            $dam = $damQuery->first();

            if (! $dam) {
                throw ValidationException::withMessages([
                    'dam_id' => 'La madre seleccionada no pertenece a esta finca o no es válida.',
                ]);
            }

            if (! $this->canBeDam($dam)) {
                throw ValidationException::withMessages([
                    'dam_id' => 'La madre seleccionada aún no tiene edad suficiente para registrarse como madre.',
                ]);
            }
        }

        if (! empty($data['sire_id'])) {
            $sireQuery = Animal::whereIn('farm_id', $this->farmsOwner()->farms()->pluck('farms.id'))
                ->where('id', $data['sire_id'])
                ->where('sex', 'macho');

            if ($ignoreAnimalId) {
                $sireQuery->where('id', '!=', $ignoreAnimalId);
            }

            $sire = $sireQuery->first();

            if (! $sire) {
                throw ValidationException::withMessages([
                    'sire_id' => 'El padre seleccionado no pertenece a esta finca o no es válido.',
                ]);
            }

            if (! $this->canBeSire($sire)) {
                throw ValidationException::withMessages([
                    'sire_id' => 'El padre seleccionado aún no tiene edad reproductiva suficiente.',
                ]);
            }
        }
    }

    protected function validateLot(int $farmId, ?int $lotId): void
    {
        if (! $lotId) {
            return;
        }

        $lot = Lot::where('farm_id', $farmId)
            ->where('id', $lotId)
            ->first();

        if (! $lot) {
            throw ValidationException::withMessages([
                'lot_id' => 'El lote seleccionado no pertenece a esta finca.',
            ]);
        }
    }

    protected function buildAnimalPayload(int $farmId, array $data, ?Animal $animal = null): array
    {
        $plainNotes = $data['notes'] ?? null;

        $notes = $animal
            ? $this->buildUpdatedNotesPreservingMeta($plainNotes, $animal)
            : $this->buildUpdatedNotesPreservingMeta($plainNotes, null);

        $payload = [
            'farm_id' => $farmId,
            'lot_id' => $data['lot_id'] ?? null,
            'internal_code' => $data['internal_code'] ?? null,
            'ear_tag' => $data['ear_tag'] ?? null,
            'name' => $data['name'] ?? null,
            'breed' => $data['breed'] ?? null,
            'sex' => $data['sex'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'dam_id' => $data['dam_id'] ?? null,
            'sire_id' => $data['sire_id'] ?? null,
            'dam_name_manual' => $data['dam_name_manual'] ?? null,
            'sire_name_manual' => $data['sire_name_manual'] ?? null,
            'weight_current' => $data['weight_current'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $notes,
            'has_calved_before' => $data['has_calved_before'] ?? null,
            'is_pregnant' => $data['is_pregnant'] ?? null,
            'pregnancy_date' => $data['pregnancy_date'] ?? null,
            'pregnancy_sire_id' => $data['pregnancy_sire_id'] ?? ($animal->pregnancy_sire_id ?? null),
            'pregnancy_sire_name_manual' => $data['pregnancy_sire_name_manual'] ?? ($animal->pregnancy_sire_name_manual ?? null),
            'service_type' => $data['service_type'] ?? ($animal->service_type ?? null),
            'last_calving_date' => $data['last_calving_date'] ?? null,
            'calving_count' => $data['calving_count'] ?? null,
            'status' => $data['status'] ?? ($animal->status ?? Animal::STATUS_ACTIVE),
            'status_date' => $data['status_date'] ?? ($animal->status_date ?? null),
            'status_notes' => $data['status_notes'] ?? ($animal->status_notes ?? null),
        ];

        return $this->filterExistingAnimalColumns($payload);
    }

    protected function filterExistingAnimalColumns(array $payload): array
    {
        $table = 'animals';

        return collect($payload)
            ->filter(function ($value, $key) use ($table) {
                return Schema::hasColumn($table, $key);
            })
            ->toArray();
    }

    protected function syncAnimalMainPhotoField(Animal $animal, ?string $path): void
    {
        if (Schema::hasColumn('animals', 'photo')) {
            $animal->update([
                'photo' => $path,
            ]);
        }
    }

    protected function buildUpdatedNotesPreservingMeta(?string $plainNotes, ?Animal $animal = null): string
    {
        $meta = $animal ? $this->getNotesMeta($animal->notes) : [];
        $plainNotes = trim((string) $plainNotes);

        return $this->buildNotesPayload($plainNotes, $meta);
    }

    protected function getProductionRecords(Animal $animal): array
    {
        $records = collect();

        if (Schema::hasTable('milk_productions')) {
            $records = $records->merge(MilkProduction::query()
                ->where('farm_id', $animal->farm_id)
                ->where('animal_id', $animal->id)
                ->orderByDesc('production_date')
                ->orderByDesc('id')
                ->get()
                ->map(function ($record) {
                    return [
                        'source' => 'milk',
                        'id' => $record->id,
                        'type' => 'Leche',
                        'date' => optional($record->production_date)->format('Y-m-d'),
                        'period' => $record->period,
                        'liters' => $record->liters !== null ? (float) $record->liters : null,
                        'weight' => null,
                        'weight_gain' => null,
                        'feeding_type' => null,
                        'notes' => $record->notes,
                        'created_at' => optional($record->created_at)?->toDateTimeString(),
                    ];
                }));
        }

        if (Schema::hasTable('meat_productions')) {
            $records = $records->merge(MeatProduction::query()
                ->where('farm_id', $animal->farm_id)
                ->where('animal_id', $animal->id)
                ->orderByDesc('production_date')
                ->orderByDesc('id')
                ->get()
                ->map(function ($record) {
                    return [
                        'source' => 'meat',
                        'id' => $record->id,
                        'type' => 'Carne',
                        'date' => optional($record->production_date)->format('Y-m-d'),
                        'period' => null,
                        'liters' => null,
                        'weight' => $record->weight_kg !== null ? (float) $record->weight_kg : null,
                        'weight_gain' => $record->weight_gain_kg !== null ? (float) $record->weight_gain_kg : null,
                        'feeding_type' => null,
                        'notes' => $record->notes,
                        'created_at' => optional($record->created_at)?->toDateTimeString(),
                    ];
                }));
        }

        $tableKeys = $records
            ->mapWithKeys(fn ($record) => [$this->productionRecordGroupKey($record) => true]);

        $legacyRecords = collect($this->getLegacyProductionRecords($animal))
            ->reject(fn ($record) => isset($tableKeys[$this->productionRecordGroupKey($record)]));

        $records = $records->merge($legacyRecords);

        return $records
            ->unique(function ($record) {
                return implode('|', [
                    $record['type'] ?? 'Producción',
                    $record['date'] ?? '',
                    $record['period'] ?? '',
                    $record['liters'] ?? '',
                    $record['weight'] ?? '',
                ]);
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    protected function getLegacyProductionRecords(Animal $animal): array
    {
        $meta = $this->getNotesMeta($animal->notes);

        return collect($meta['productions'] ?? [])
            ->map(function ($record) {
                $record['source'] = $record['source'] ?? 'legacy';
                $record['id'] = $record['id'] ?? null;
                $record['type'] = $record['type']
                    ?? (!empty($record['liters']) ? 'Leche' : (!empty($record['weight']) ? 'Carne' : 'Producción'));
                $record['weight_gain'] = $record['weight_gain'] ?? null;

                return $record;
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    protected function productionRecordGroupKey(array $record): string
    {
        return implode('|', [
            $record['type'] ?? 'Producción',
            $record['date'] ?? '',
            $record['period'] ?? '',
        ]);
    }

    protected function getHealthRecords(Animal $animal): array
    {
        $meta = $this->getNotesMeta($animal->notes);

        return collect($meta['health_records'] ?? [])
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    protected function saveMetaSection(Animal $animal, string $section, array $records): void
    {
        $animal->refresh();

        $meta = $this->getNotesMeta($animal->notes);
        $meta[$section] = array_values($records);

        $baseNotes = $this->getPlainNotes($animal->notes);

        $animal->update([
            'notes' => $this->buildNotesPayload($baseNotes, $meta),
        ]);
    }

    protected function appendTransferHistory(Animal $animal, array $record): void
    {
        $meta = $this->getNotesMeta($animal->notes);
        $records = $meta['transfers'] ?? [];
        $records[] = $record;
        $meta['transfers'] = array_values($records);

        $animal->update([
            'notes' => $this->buildNotesPayload($this->getPlainNotes($animal->notes), $meta),
        ]);
    }

    protected function moveAnimalsToFarm($animals, $sourceFarm, $targetFarm, ?Lot $targetLot): void
    {
        DB::transaction(function () use ($animals, $sourceFarm, $targetFarm, $targetLot) {
            $movedAnimalIds = $animals->pluck('id')->all();
            $timestamp = now()->toDateTimeString();

            foreach ($animals as $animal) {
                $animal->loadMissing('lot');

                $this->appendTransferHistory($animal, [
                    'date' => $timestamp,
                    'from_farm_id' => $sourceFarm->id,
                    'from_farm_name' => $sourceFarm->name,
                    'from_lot_id' => $animal->lot_id,
                    'from_lot_name' => $animal->lot?->name,
                    'to_farm_id' => $targetFarm->id,
                    'to_farm_name' => $targetFarm->name,
                    'to_lot_id' => $targetLot?->id,
                    'to_lot_name' => $targetLot?->name,
                ]);

                $animal->update([
                    'farm_id' => $targetFarm->id,
                    'lot_id' => $targetLot?->id,
                    'location' => $targetLot?->name,
                ]);
            }

            if (Schema::hasTable('milk_productions')) {
                MilkProduction::whereIn('animal_id', $movedAnimalIds)->update(['farm_id' => $targetFarm->id]);
            }

            if (Schema::hasTable('meat_productions')) {
                MeatProduction::whereIn('animal_id', $movedAnimalIds)->update(['farm_id' => $targetFarm->id]);
            }

            if (Schema::hasTable('events')) {
                Event::whereIn('animal_id', $movedAnimalIds)->update([
                    'farm_id' => $targetFarm->id,
                    'lot_name' => $targetLot?->name,
                ]);
            }
        });
    }

    protected function getNotesMeta(?string $notes): array
    {
        if (! $notes) {
            return [];
        }

        if (preg_match('/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s', $notes, $matches)) {
            $json = trim($matches[1]);
            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function getPlainNotes(?string $notes): string
    {
        if (! $notes) {
            return '';
        }

        return trim(preg_replace('/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s', '', $notes));
    }

    protected function buildNotesPayload(string $plainNotes, array $meta): string
    {
        $payload = trim($plainNotes);

        if (! empty($meta)) {
            if ($payload !== '') {
                $payload .= "\n\n";
            }

            $payload .= '<!--INTERFARM_META_START-->' . json_encode($meta, JSON_UNESCAPED_UNICODE) . '<!--INTERFARM_META_END-->';
        }

        return $payload;
    }

    protected function getEligibleDams(int $farmId, ?int $excludeAnimalId = null)
    {
        $query = Animal::whereIn('farm_id', $this->farmsOwner()->farms()->pluck('farms.id'))
            ->where('sex', 'hembra')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhereNotIn('status', [Animal::STATUS_SOLD, Animal::STATUS_DECEASED, 'sold', 'deceased', 'dead', 'vendida', 'muerto', 'muerta']);
            })
            ->whereNotNull('birth_date')
            ->with('farm:id,name')
            ->orderBy('name');

        if ($excludeAnimalId) {
            $query->where('id', '!=', $excludeAnimalId);
        }

        return $query->get()->filter(function (Animal $animal) {
            return $this->canBeDam($animal);
        })->values();
    }

    protected function getEligibleSires(int $farmId, ?int $excludeAnimalId = null)
    {
        $query = Animal::whereIn('farm_id', $this->farmsOwner()->farms()->pluck('farms.id'))
            ->where('sex', 'macho')
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhereNotIn('status', [Animal::STATUS_SOLD, Animal::STATUS_DECEASED, 'sold', 'deceased', 'dead', 'vendida', 'muerto', 'muerta']);
            })
            ->whereNotNull('birth_date')
            ->with('farm:id,name')
            ->orderBy('name');

        if ($excludeAnimalId) {
            $query->where('id', '!=', $excludeAnimalId);
        }

        return $query->get()->filter(function (Animal $animal) {
            return $this->canBeSire($animal);
        })->values();
    }

    protected function canBeDam(Animal $animal): bool
    {
        return $this->ageInMonths($animal) >= 22;
    }

    protected function canBeSire(Animal $animal): bool
    {
        return $this->ageInMonths($animal) >= 18;
    }

    protected function ageInMonths(Animal $animal): int
    {
        if (! $animal->birth_date) {
            return 0;
        }

        return (int) floor($animal->birth_date->diffInMonths(now()));
    }

    protected function rules(bool $withPhotos = true, ?int $farmId = null, ?int $ignoreAnimalId = null): array
    {
        $rules = [
            'lot_id' => ['nullable', 'integer', 'exists:lots,id'],
            'internal_code' => $this->uniqueAnimalIdentifierRules('internal_code', $farmId, $ignoreAnimalId),
            'ear_tag' => $this->uniqueAnimalIdentifierRules('ear_tag', $farmId, $ignoreAnimalId),
            'name' => ['nullable', 'string', 'max:255'],

            'breed' => ['nullable', 'string', 'max:255'],
            'sex' => ['required', 'in:macho,hembra'],
            'purpose' => ['nullable', 'in:carne,leche,doble_proposito,crianza'],

            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],

            'dam_id' => ['nullable', 'integer', 'exists:animals,id'],
            'sire_id' => ['nullable', 'integer', 'exists:animals,id'],
            'dam_name_manual' => ['nullable', 'string', 'max:255'],
            'sire_name_manual' => ['nullable', 'string', 'max:255'],

            'weight_current' => ['nullable', 'numeric', 'min:0', 'max:' . self::MAX_ANIMAL_WEIGHT_KG],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],

            'has_calved_before' => ['nullable', 'in:si,no'],
            'is_pregnant' => ['nullable', 'in:si,no'],
            'pregnancy_date' => ['nullable', 'date'],
            'pregnancy_sire_id' => ['nullable', 'integer', 'exists:animals,id'],
            'pregnancy_sire_name_manual' => ['nullable', 'string', 'max:255'],
            'service_type' => ['nullable', 'in:monta_natural,inseminacion,embrion'],
            'last_calving_date' => ['nullable', 'date', 'before_or_equal:today'],
            'calving_count' => ['nullable', 'integer', 'min:0', 'max:' . self::MAX_CALVING_COUNT],

            'status' => ['nullable', 'in:activo,vendido,fallecido'],
            'status_date' => ['nullable', 'date'],
            'status_notes' => ['nullable', 'string'],
        ];

        if ($withPhotos) {
            $rules['photos'] = ['nullable', 'array', 'max:6'];
            $rules['photos.*'] = $this->animalPhotoRules();
        }

        return $rules;
    }

    protected function uniqueAnimalIdentifierRules(string $column, ?int $farmId, ?int $ignoreAnimalId = null): array
    {
        $rules = ['nullable', 'string', 'max:50'];

        if (! $farmId) {
            return $rules;
        }

        $uniqueRule = Rule::unique('animals', $column)
            ->where(fn ($query) => $query->where('farm_id', $farmId));

        if ($ignoreAnimalId) {
            $uniqueRule->ignore($ignoreAnimalId);
        }

        $rules[] = $uniqueRule;

        return $rules;
    }

    protected function animalValidationMessages(): array
    {
        return [
            'internal_code.unique' => 'Ya existe un animal con este código interno en esta finca.',
            'internal_code.required' => 'Debes escribir el código interno del animal.',
            'ear_tag.unique' => 'Ya existe un animal con este arete en esta finca.',
            'weight.max' => 'El peso no puede ser mayor a 2.000 kg.',
            'weight_current.max' => 'El peso actual no puede ser mayor a 2.000 kg.',
            'calving_count.max' => 'La cantidad de partos no puede ser mayor a 25.',
            'last_calving_date.before_or_equal' => 'La fecha del último parto no puede ser posterior a la fecha actual.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser posterior a la fecha actual.',
        ];
    }

    protected function animalPhotoRules(): array
    {
        return [
            'file',
            'max:12288',
            'extensions:' . implode(',', self::ANIMAL_PHOTO_EXTENSIONS),
        ];
    }

    protected function userCanAccessFarm(int $farmId): bool
    {
        return $this->farmsOwner()
            ->farms()
            ->where('farms.id', $farmId)
            ->exists();
    }

    protected function farmsOwner()
    {
        $user = auth()->user();

        if ($user?->canAccessAdminPanel() && session('admin_view_client_id')) {
            return \App\Models\User::find(session('admin_view_client_id')) ?: $user;
        }

        return $user;
    }

    protected function setting(int $farmId, string $key, $default = null)
    {
        return optional(
            FarmSetting::where('farm_id', $farmId)
                ->where('key', $key)
                ->first()
        )->value ?? $default;
    }
}
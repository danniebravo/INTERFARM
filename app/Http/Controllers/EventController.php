<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function index()
    {
        $farm = $this->getCurrentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $animals = Animal::where('farm_id', $farm->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Animal $animal) => $animal->isActive())
            ->values();

        $manualCalendarEvents = Event::where('farm_id', $farm->id)
            ->where(function ($query) {
                $query->whereNull('animal_id')
                    ->orWhereHas('animal', fn ($animalQuery) => $this->whereAnimalIsActive($animalQuery));
            })
            ->with('animal')
            ->orderByRaw('COALESCE(start_datetime, event_date) asc')
            ->get()
            ->map(function (Event $event) {
                $event->sort_date = $this->resolveEventSortDate($event);
                $event->is_automatic = false;
                $event->type_label = $this->resolveTypeLabel($event->type);
                return $event;
            });

        $automaticCalendarEvents = $this->getAutomaticAnimalEvents($farm->id);

        [$futureEvents, $pastEvents] = $manualCalendarEvents
            ->concat($automaticCalendarEvents)
            ->partition(function ($event) {
                return isset($event->sort_date)
                    && $event->sort_date->greaterThanOrEqualTo(now()->startOfDay());
            });

        $upcomingEvents = $futureEvents
            ->sortBy(function ($event) {
                return $event->sort_date?->timestamp ?? PHP_INT_MAX;
            })
            ->concat($pastEvents->sortByDesc(function ($event) {
                return $event->sort_date?->timestamp ?? 0;
            }))
            ->values();

        return view('events.index', compact('farm', 'animals', 'upcomingEvents'));
    }

    public function feed(Request $request)
    {
        $farm = $this->getCurrentFarm();

        if (! $farm) {
            return response()->json([]);
        }

        $start = $request->get('start');
        $end = $request->get('end');

        $filterStart = $request->get('filter_start');
        $filterEnd = $request->get('filter_end');

        $rangeStart = $filterStart
            ? Carbon::parse($filterStart)->startOfDay()
            : ($start ? Carbon::parse($start) : null);

        $rangeEnd = $filterEnd
            ? Carbon::parse($filterEnd)->endOfDay()
            : ($end ? Carbon::parse($end) : null);

        $manualEvents = Event::where('farm_id', $farm->id)
            ->where(function ($query) {
                $query->whereNull('animal_id')
                    ->orWhereHas('animal', fn ($animalQuery) => $this->whereAnimalIsActive($animalQuery));
            })
            ->when($rangeStart && $rangeEnd, function ($query) use ($rangeStart, $rangeEnd) {
                $query->where(function ($subQuery) use ($rangeStart, $rangeEnd) {
                    $subQuery->whereBetween('event_date', [
                        $rangeStart->toDateString(),
                        $rangeEnd->toDateString(),
                    ])->orWhereBetween('start_datetime', [
                        $rangeStart->toDateTimeString(),
                        $rangeEnd->toDateTimeString(),
                    ])->orWhere(function ($rangeQuery) use ($rangeStart, $rangeEnd) {
                        $rangeQuery->whereNotNull('start_datetime')
                            ->whereNotNull('end_datetime')
                            ->where('start_datetime', '<=', $rangeEnd->toDateTimeString())
                            ->where('end_datetime', '>=', $rangeStart->toDateTimeString());
                    });
                });
            })
            ->with('animal')
            ->orderByRaw('COALESCE(start_datetime, event_date) asc')
            ->get()
            ->map(fn (Event $event) => $this->mapManualEventForCalendar($event));

        $automaticEvents = $this->getAutomaticAnimalEvents($farm->id)
            ->filter(function ($event) use ($rangeStart, $rangeEnd) {
                if (! $rangeStart || ! $rangeEnd || ! isset($event->sort_date)) {
                    return true;
                }

                return $event->sort_date->between(
                    $rangeStart->copy()->startOfDay(),
                    $rangeEnd->copy()->endOfDay()
                );
            })
            ->map(fn ($event) => $this->mapAutomaticEventForCalendar($event));

        return response()->json(
            $manualEvents->concat($automaticEvents)->values()
        );
    }

    public function create()
    {
        return redirect()->route('events.index');
    }

    public function store(Request $request)
    {
        $farm = $this->getCurrentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:general,parto,vacuna,tratamiento,inseminacion,celo,revision'],
            'all_day' => ['nullable', 'in:0,1'],
            'event_date' => ['nullable', 'date'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'status' => ['required', 'in:pending,completed,cancelled'],
            'priority' => ['required', 'in:low,medium,high'],
            'lot_name' => ['nullable', 'string', 'max:255'],
            'animal_id' => ['nullable', 'integer', 'exists:animals,id'],
            'color' => ['nullable', 'string', 'max:20'],
        ], [
            'title.required' => 'Debes escribir el título del evento.',
            'type.required' => 'Debes seleccionar el tipo de evento.',
            'status.required' => 'Debes seleccionar el estado.',
            'priority.required' => 'Debes seleccionar la prioridad.',
            'end_datetime.after_or_equal' => 'La fecha final no puede ser menor a la fecha inicial.',
        ]);

        $this->validateFarmAnimal($farm->id, $data['animal_id'] ?? null);

        $allDay = ($data['all_day'] ?? '1') === '1';

        if ($allDay && empty($data['event_date'])) {
            return back()
                ->withErrors(['event_date' => 'Debes seleccionar la fecha del evento.'])
                ->withInput();
        }

        if (! $allDay && empty($data['start_datetime'])) {
            return back()
                ->withErrors(['start_datetime' => 'Debes seleccionar la fecha y hora de inicio.'])
                ->withInput();
        }

        Event::create([
            'farm_id' => $farm->id,
            'animal_id' => $data['animal_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'event_date' => $allDay ? ($data['event_date'] ?? null) : null,
            'start_datetime' => $allDay ? null : ($data['start_datetime'] ?? null),
            'end_datetime' => $allDay ? null : ($data['end_datetime'] ?? null),
            'all_day' => $allDay,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'lot_name' => $data['lot_name'] ?? null,
            'color' => $data['color'] ?? null,
            'meta' => null,
        ]);

        return redirect()
            ->route('events.index')
            ->with('success', 'Evento creado correctamente.');
    }

    public function show(Event $event)
    {
        $this->authorizeEvent($event);

        $event->load('animal');

        return response()->json([
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'type_label' => $this->resolveTypeLabel($event->type),
            'event_date' => optional($event->event_date)?->format('Y-m-d'),
            'start_datetime' => optional($event->start_datetime)?->format('Y-m-d H:i:s'),
            'end_datetime' => optional($event->end_datetime)?->format('Y-m-d H:i:s'),
            'all_day' => (bool) $event->all_day,
            'status' => $event->status,
            'priority' => $event->priority,
            'lot_name' => $event->lot_name,
            'color' => $event->color,
            'animal' => $event->animal ? [
                'id' => $event->animal->id,
                'name' => $event->animal->name,
                'ear_tag' => $event->animal->ear_tag,
            ] : null,
            'meta' => $event->meta,
        ]);
    }

    public function edit(Event $event)
    {
        return redirect()->route('events.index');
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:general,parto,vacuna,tratamiento,inseminacion,celo,revision'],
            'all_day' => ['nullable', 'in:0,1'],
            'event_date' => ['nullable', 'date'],
            'start_datetime' => ['nullable', 'date'],
            'end_datetime' => ['nullable', 'date', 'after_or_equal:start_datetime'],
            'status' => ['required', 'in:pending,completed,cancelled'],
            'priority' => ['required', 'in:low,medium,high'],
            'lot_name' => ['nullable', 'string', 'max:255'],
            'animal_id' => ['nullable', 'integer', 'exists:animals,id'],
            'color' => ['nullable', 'string', 'max:20'],
        ], [
            'title.required' => 'Debes escribir el título del evento.',
            'type.required' => 'Debes seleccionar el tipo de evento.',
            'status.required' => 'Debes seleccionar el estado.',
            'priority.required' => 'Debes seleccionar la prioridad.',
            'end_datetime.after_or_equal' => 'La fecha final no puede ser menor a la fecha inicial.',
        ]);

        $this->validateFarmAnimal($event->farm_id, $data['animal_id'] ?? null);

        $allDay = ($data['all_day'] ?? '1') === '1';

        if ($allDay && empty($data['event_date'])) {
            return back()
                ->withErrors(['event_date' => 'Debes seleccionar la fecha del evento.'])
                ->withInput();
        }

        if (! $allDay && empty($data['start_datetime'])) {
            return back()
                ->withErrors(['start_datetime' => 'Debes seleccionar la fecha y hora de inicio.'])
                ->withInput();
        }

        $event->update([
            'animal_id' => $data['animal_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'event_date' => $allDay ? ($data['event_date'] ?? null) : null,
            'start_datetime' => $allDay ? null : ($data['start_datetime'] ?? null),
            'end_datetime' => $allDay ? null : ($data['end_datetime'] ?? null),
            'all_day' => $allDay,
            'status' => $data['status'],
            'priority' => $data['priority'],
            'lot_name' => $data['lot_name'] ?? null,
            'color' => $data['color'] ?? null,
        ]);

        return redirect()
            ->route('events.index')
            ->with('success', 'Evento actualizado correctamente.');
    }

    public function destroy(Event $event)
    {
        $this->authorizeEvent($event);

        $event->delete();

        return redirect()
            ->route('events.index')
            ->with('success', 'Evento eliminado correctamente.');
    }

    public function updateStatus(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $data = $request->validate([
            'status' => ['required', 'in:pending,completed,cancelled'],
        ], [
            'status.required' => 'Debes seleccionar el estado.',
            'status.in' => 'El estado seleccionado no es válido.',
        ]);

        $event->update([
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('events.index')
            ->with('success', 'Estado del evento actualizado correctamente.');
    }

    protected function getCurrentFarm()
    {
        return auth()->user()?->currentFarm();
    }

    protected function authorizeEvent(Event $event): void
    {
        $farm = $this->getCurrentFarm();

        if (! $farm || (int) $event->farm_id !== (int) $farm->id) {
            abort(403);
        }
    }

    protected function validateFarmAnimal(int $farmId, ?int $animalId): void
    {
        if (! $animalId) {
            return;
        }

        $animal = Animal::where('farm_id', $farmId)
            ->where('id', $animalId)
            ->first();

        if (! $animal || ! $animal->isActive()) {
            throw ValidationException::withMessages([
                'animal_id' => 'El animal seleccionado no pertenece a esta finca o no está activo.',
            ]);
        }
    }

    protected function getAutomaticAnimalEvents(int $farmId): Collection
    {
        $animals = Animal::where('farm_id', $farmId)->get();

        $events = collect();

        foreach ($animals as $animal) {
            if (! $animal->isActive()) {
                continue;
            }

            $events = $events->concat($this->buildAutomaticEventsForAnimal($animal));
        }

        return $events->sortBy(function ($event) {
            return $event->sort_date?->timestamp ?? PHP_INT_MAX;
        })->values();
    }

    protected function buildAutomaticEventsForAnimal(Animal $animal): Collection
    {
        if (! $animal->isActive()) {
            return collect();
        }

        $events = collect();

        $events = $events->concat($this->buildAutomaticHealthEventsForAnimal($animal));

        if ($animal->sex !== 'hembra') {
            return $events;
        }

        $pregnancyDate = $this->parseAnimalDate($animal->pregnancy_date ?? null);
        $lastCalvingDate = $this->parseAnimalDate($animal->last_calving_date ?? null);
        $isPregnant = ($animal->is_pregnant ?? null) === 'si';

        if ($isPregnant && $pregnancyDate) {
            $probableCalvingDate = $pregnancyDate->copy()->addDays(283);
            $prepartumDate = $probableCalvingDate->copy()->subDays(30);
            $dryingDate = $probableCalvingDate->copy()->subDays(60);

            $events->push($this->makeAutomaticEventObject([
                'id' => 'auto-inseminacion-' . $animal->id . '-' . $pregnancyDate->format('Ymd'),
                'title' => 'Servicio / inseminación de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                'description' => 'Evento generado automáticamente desde la fecha de preñez o servicio.',
                'type' => 'inseminacion',
                'type_label' => 'Inseminación',
                'status' => 'pending',
                'priority' => 'medium',
                'event_date' => $pregnancyDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
                'color' => '#d97706',
                'meta' => [
                    'automatic' => true,
                    'source' => 'pregnancy_date',
                ],
            ]));

            $events->push($this->makeAutomaticEventObject([
                'id' => 'auto-parto-' . $animal->id . '-' . $probableCalvingDate->format('Ymd'),
                'title' => 'Parto probable de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                'description' => 'Evento generado automáticamente desde la fecha de preñez.',
                'type' => 'parto',
                'type_label' => 'Próximo parto',
                'status' => 'pending',
                'priority' => 'high',
                'event_date' => $probableCalvingDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
                'color' => '#dc2626',
                'meta' => [
                    'automatic' => true,
                    'source' => 'pregnancy_date',
                ],
            ]));

            $events->push($this->makeAutomaticEventObject([
                'id' => 'auto-preparto-' . $animal->id . '-' . $prepartumDate->format('Ymd'),
                'title' => 'Preparto de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                'description' => 'Preparación estimada 30 días antes del parto probable.',
                'type' => 'revision',
                'type_label' => 'Preparto',
                'status' => 'pending',
                'priority' => 'high',
                'event_date' => $prepartumDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
                'color' => '#f59e0b',
                'meta' => [
                    'automatic' => true,
                    'source' => 'pregnancy_date',
                ],
            ]));

            if (in_array($animal->purpose, ['leche', 'doble_proposito'], true)) {
                $events->push($this->makeAutomaticEventObject([
                    'id' => 'auto-secado-' . $animal->id . '-' . $dryingDate->format('Ymd'),
                    'title' => 'Secado de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                    'description' => 'Secado estimado 60 días antes del parto probable.',
                    'type' => 'revision',
                    'type_label' => 'Secado',
                    'status' => 'pending',
                    'priority' => 'medium',
                    'event_date' => $dryingDate,
                    'animal' => $animal,
                    'animal_id' => $animal->id,
                    'lot_name' => $animal->location ?? null,
                    'color' => '#7c3aed',
                    'meta' => [
                        'automatic' => true,
                        'source' => 'pregnancy_date',
                    ],
                ]));
            }
        }

        if ($lastCalvingDate) {
            $postpartumReviewDate = $lastCalvingDate->copy()->addDays(7);
            $estimatedFertilityDate = $lastCalvingDate->copy()->addDays(45);
            $weaningDate = $lastCalvingDate->copy()->addDays(90);

            $events->push($this->makeAutomaticEventObject([
                'id' => 'auto-revision-posparto-' . $animal->id . '-' . $postpartumReviewDate->format('Ymd'),
                'title' => 'Revisión posparto de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                'description' => 'Control automático 7 días después del último parto.',
                'type' => 'revision',
                'type_label' => 'Revisión posparto',
                'status' => 'pending',
                'priority' => 'medium',
                'event_date' => $postpartumReviewDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
                'color' => '#0891b2',
                'meta' => [
                    'automatic' => true,
                    'source' => 'last_calving_date',
                ],
            ]));

            if (! $isPregnant) {
                $events->push($this->makeAutomaticEventObject([
                    'id' => 'auto-fertilidad-' . $animal->id . '-' . $estimatedFertilityDate->format('Ymd'),
                    'title' => 'Nueva fertilidad estimada de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                    'description' => 'Fecha estimada para volver a fertilidad después del parto.',
                    'type' => 'celo',
                    'type_label' => 'Nueva fertilidad estimada',
                    'status' => 'pending',
                    'priority' => 'medium',
                    'event_date' => $estimatedFertilityDate,
                    'animal' => $animal,
                    'animal_id' => $animal->id,
                    'lot_name' => $animal->location ?? null,
                    'color' => '#db2777',
                    'meta' => [
                        'automatic' => true,
                        'source' => 'last_calving_date',
                    ],
                ]));
            }

            $events->push($this->makeAutomaticEventObject([
                'id' => 'auto-destete-' . $animal->id . '-' . $weaningDate->format('Ymd'),
                'title' => 'Destete estimado de cría de ' . ($animal->name ?: ($animal->ear_tag ?: 'animal')),
                'description' => 'Evento generado automáticamente 90 días después del último parto.',
                'type' => 'revision',
                'type_label' => 'Destete',
                'status' => 'pending',
                'priority' => 'medium',
                'event_date' => $weaningDate,
                'animal' => $animal,
                'animal_id' => $animal->id,
                'lot_name' => $animal->location ?? null,
                'color' => '#16a34a',
                'meta' => [
                    'automatic' => true,
                    'source' => 'last_calving_date',
                ],
            ]));
        }

        return $events;
    }

    protected function whereAnimalIsActive($query)
    {
        return $query->where(function ($statusQuery) {
            $statusQuery->whereNull('status')
                ->orWhereNotIn('status', [
                    Animal::STATUS_SOLD,
                    Animal::STATUS_DECEASED,
                    'sold',
                    'deceased',
                    'dead',
                ]);
        });
    }

    protected function buildAutomaticHealthEventsForAnimal(Animal $animal): Collection
    {
        return collect($animal->healthRecords())
            ->flatMap(function (array $record, int $index) use ($animal) {
                $eventDate = $this->parseAnimalDate($record['date'] ?? null);

                if (! $eventDate) {
                    return [];
                }

                $treatmentType = trim((string) ($record['treatment_type'] ?? ''));
                $isVaccine = mb_strtolower($treatmentType) === 'vacuna';
                $type = $isVaccine ? 'vacuna' : 'tratamiento';
                $typeLabel = $isVaccine ? 'Vacunación' : 'Tratamiento';
                $titleBase = $treatmentType !== '' ? $treatmentType : $typeLabel;
                $animalName = $animal->name ?: ($animal->ear_tag ?: 'animal');
                $recordKey = substr(md5(json_encode($record)), 0, 10);

                $descriptionParts = collect([
                    $record['disease'] ?? null ? 'Enfermedad: ' . $record['disease'] : null,
                    $record['diagnosis'] ?? null ? 'Diagnóstico: ' . $record['diagnosis'] : null,
                    $record['medication'] ?? null ? 'Medicamento: ' . $record['medication'] : null,
                    $record['days'] ?? null ? 'Duración: ' . (int) $record['days'] . ' día(s)' : null,
                    $record['notes'] ?? null,
                ])->filter()->implode('. ');

                $events = [
                    $this->makeAutomaticEventObject([
                        'id' => 'auto-health-' . $animal->id . '-' . $eventDate->format('Ymd') . '-' . $recordKey,
                        'title' => $titleBase . ' de ' . $animalName,
                        'description' => $descriptionParts ?: 'Registro de salud generado desde la ficha del animal.',
                        'type' => $type,
                        'type_label' => $typeLabel,
                        'status' => 'pending',
                        'priority' => $isVaccine ? 'medium' : 'high',
                        'event_date' => $eventDate,
                        'animal' => $animal,
                        'animal_id' => $animal->id,
                        'lot_name' => $animal->location ?? null,
                        'color' => $isVaccine ? '#2563eb' : '#7c3aed',
                        'meta' => [
                            'automatic' => true,
                            'source' => 'health_records',
                            'health_record_key' => $recordKey,
                        ],
                    ]),
                ];

                $days = isset($record['days']) ? (int) $record['days'] : 0;

                if (! $isVaccine && $days > 1) {
                    $reviewDate = $eventDate->copy()->addDays($days);

                    $events[] = $this->makeAutomaticEventObject([
                        'id' => 'auto-health-review-' . $animal->id . '-' . $reviewDate->format('Ymd') . '-' . $recordKey,
                        'title' => 'Revisión de tratamiento de ' . $animalName,
                        'description' => 'Seguimiento automático al finalizar el tratamiento. ' . ($descriptionParts ?: ''),
                        'type' => 'revision',
                        'type_label' => 'Revisión',
                        'status' => 'pending',
                        'priority' => 'medium',
                        'event_date' => $reviewDate,
                        'animal' => $animal,
                        'animal_id' => $animal->id,
                        'lot_name' => $animal->location ?? null,
                        'color' => '#0891b2',
                        'meta' => [
                            'automatic' => true,
                            'source' => 'health_records',
                            'health_record_key' => $recordKey,
                            'reason' => 'treatment_follow_up',
                        ],
                    ]);
                }

                return $events;
            })
            ->filter()
            ->values();
    }

    protected function makeAutomaticEventObject(array $data): object
    {
        $eventDate = $data['event_date'] instanceof Carbon
            ? $data['event_date']->copy()->startOfDay()
            : Carbon::parse($data['event_date'])->startOfDay();

        return (object) [
            'id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'general',
            'type_label' => $data['type_label'] ?? 'Evento',
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'event_date' => $eventDate,
            'start_datetime' => null,
            'end_datetime' => null,
            'all_day' => true,
            'animal' => $data['animal'] ?? null,
            'animal_id' => $data['animal_id'] ?? null,
            'lot_name' => $data['lot_name'] ?? null,
            'color' => $data['color'] ?? '#166534',
            'meta' => $data['meta'] ?? [],
            'sort_date' => $eventDate,
            'is_automatic' => true,
        ];
    }

    protected function mapManualEventForCalendar(Event $event): array
    {
        $start = $event->all_day
            ? ($event->event_date ? $event->event_date->format('Y-m-d') : optional($event->start_datetime)->format('Y-m-d'))
            : optional($event->start_datetime)?->toIso8601String();

        $end = $event->all_day
            ? null
            : optional($event->end_datetime)?->toIso8601String();

        $typeLabel = $this->resolveTypeLabel($event->type);
        $resolvedColor = $event->color ?: $this->resolveEventColor($event->type, $event->status);

        return [
            'id' => (string) $event->id,
            'title' => $event->title,
            'start' => $start,
            'end' => $end,
            'allDay' => (bool) $event->all_day,
            'backgroundColor' => $resolvedColor,
            'borderColor' => $resolvedColor,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'description' => $event->description,
                'type' => $event->type,
                'type_label' => $typeLabel,
                'status' => $event->status,
                'priority' => $event->priority,
                'lot_name' => $event->lot_name,
                'animal_id' => $event->animal_id,
                'animal_name' => $event->animal?->name ?: ($event->animal?->ear_tag ?: null),
                'event_date' => optional($event->event_date)?->format('Y-m-d'),
                'start_datetime' => optional($event->start_datetime)?->format('Y-m-d\TH:i'),
                'end_datetime' => optional($event->end_datetime)?->format('Y-m-d\TH:i'),
                'automatic' => false,
            ],
        ];
    }

    protected function mapAutomaticEventForCalendar(object $event): array
    {
        return [
            'id' => (string) $event->id,
            'title' => $event->title,
            'start' => $event->event_date?->format('Y-m-d'),
            'end' => null,
            'allDay' => true,
            'backgroundColor' => $event->color ?: $this->resolveEventColor($event->type, $event->status),
            'borderColor' => $event->color ?: $this->resolveEventColor($event->type, $event->status),
            'textColor' => '#ffffff',
            'extendedProps' => [
                'description' => $event->description,
                'type' => $event->type,
                'type_label' => $event->type_label ?? 'Evento',
                'status' => $event->status,
                'priority' => $event->priority,
                'lot_name' => $event->lot_name,
                'animal_id' => $event->animal_id,
                'animal_name' => $event->animal?->name ?: ($event->animal?->ear_tag ?: null),
                'event_date' => $event->event_date?->format('Y-m-d'),
                'start_datetime' => null,
                'end_datetime' => null,
                'automatic' => true,
            ],
        ];
    }

    protected function resolveEventSortDate($event): ?Carbon
    {
        if (! empty($event->start_datetime)) {
            return $this->parseAnimalDate($event->start_datetime);
        }

        if (! empty($event->event_date)) {
            return $this->parseAnimalDate($event->event_date);
        }

        if (! empty($event->sort_date) && $event->sort_date instanceof Carbon) {
            return $event->sort_date->copy();
        }

        return null;
    }

    protected function parseAnimalDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function resolveTypeLabel(string $type): string
    {
        return match ($type) {
            'parto' => 'Próximo parto',
            'vacuna' => 'Vacunación',
            'tratamiento' => 'Tratamiento',
            'inseminacion' => 'Inseminación',
            'celo' => 'Celo',
            'revision' => 'Revisión',
            default => 'Evento',
        };
    }

    protected function resolveEventColor(string $type, string $status): string
    {
        if ($status === 'completed') {
            return '#166534';
        }

        if ($status === 'cancelled') {
            return '#6b7280';
        }

        return match ($type) {
            'parto' => '#dc2626',
            'vacuna' => '#2563eb',
            'tratamiento' => '#7c3aed',
            'inseminacion' => '#d97706',
            'celo' => '#db2777',
            'revision' => '#0891b2',
            default => '#166534',
        };
    }
}

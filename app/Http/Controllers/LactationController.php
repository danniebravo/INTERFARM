<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LactationController extends Controller
{
    protected const GESTATION_DAYS = 283;
    protected const DRY_OFF_DAYS = 60;
    protected const WEANING_DAYS = 90;

    public function index(Request $request)
    {
        $user = auth()->user();
        $farm = $user->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $search = trim((string) $request->get('search'));
        $sort = $request->get('sort', 'lactation_desc');
        $filter = $request->get('filter', 'relevant');

        if (! in_array($filter, ['relevant', 'lactating', 'pregnant', 'alerts', 'all'], true)) {
            $filter = 'relevant';
        }

        if (! in_array($sort, ['lactation_desc', 'lactation_asc', 'name_asc', 'calving_soon'], true)) {
            $sort = 'lactation_desc';
        }

        $today = Carbon::today();

        $females = Animal::query()
            ->where('farm_id', $farm->id)
            ->where('sex', 'hembra')
            ->with('pregnancySire')
            ->orderBy('name')
            ->get()
            ->filter(fn (Animal $a) => ! $a->isSold() && ! $a->isDeceased())
            ->values();

        $daysBetween = function (?Carbon $a, ?Carbon $b): ?int {
            if (! $a || ! $b) {
                return null;
            }

            return (int) round(abs($a->copy()->startOfDay()->diffInDays($b->copy()->startOfDay())));
        };

        $rows = $females->map(function (Animal $animal) use ($today, $daysBetween) {
            $lastCalving = $animal->last_calving_date instanceof Carbon ? $animal->last_calving_date : null;
            $isPregnant = $animal->is_pregnant === 'si';
            $ageMonths = $animal->ageInMonths();
            $inCycle = ($animal->last_calving_date instanceof Carbon) || ($animal->is_pregnant === 'si');
            $serviceDate = $animal->pregnancy_date instanceof Carbon ? $animal->pregnancy_date : null;
            $dryOffDate = $animal->dry_off_date instanceof Carbon ? $animal->dry_off_date : null;
            $isGone = $animal->isSold() || $animal->isDeceased();
            $goneDate = ($isGone && $animal->status_date instanceof Carbon) ? $animal->status_date : null;

            $daysInMilk = $daysBetween($lastCalving, $today);
            $milkDuration = $lastCalving ? $this->humanDuration($lastCalving, $today) : null;

            $expectedCalving = ($isPregnant && $serviceDate)
                ? $serviceDate->copy()->addDays(self::GESTATION_DAYS)
                : null;
            $expectedDryOff = ($isPregnant && $serviceDate)
                ? $serviceDate->copy()->addMonths(7)
                : null;

            $expectedWeaning = $lastCalving ? $lastCalving->copy()->addDays(self::WEANING_DAYS) : null;

            $alerts = [];

            if ($expectedDryOff && $isPregnant && ! $dryOffDate) {
                if ($today->greaterThanOrEqualTo($expectedDryOff)) {
                    $alerts[] = ['label' => 'Confirmar secado', 'tone' => 'amber'];
                } else {
                    $d = $daysBetween($today, $expectedDryOff);
                    if ($d !== null && $d <= 10) {
                        $alerts[] = ['label' => 'Secar en ' . $d . ' d', 'tone' => 'amber'];
                    }
                }
            }

            if ($expectedCalving) {
                if ($today->greaterThan($expectedCalving)) {
                    $d = $daysBetween($expectedCalving, $today);
                    if ($d !== null && $d <= 30) {
                        $alerts[] = ['label' => 'Registrar parto', 'tone' => 'red'];
                    }
                } else {
                    $d = $daysBetween($today, $expectedCalving);
                    if ($d !== null && $d <= 15) {
                        $alerts[] = ['label' => 'Parto en ' . $d . ' d', 'tone' => 'red'];
                    }
                }
            }

            if ($expectedWeaning && $today->lessThanOrEqualTo($expectedWeaning)) {
                $d = $daysBetween($today, $expectedWeaning);
                if ($d !== null && $d <= 10) {
                    $alerts[] = ['label' => 'Destetar en ' . $d . ' d', 'tone' => 'amber'];
                }
            }

            $inDryOff = $expectedDryOff && $isPregnant && $today->greaterThanOrEqualTo($expectedDryOff);

            $lactationState = null;
            if ($lastCalving) {
                if ($dryOffDate) {
                    $lactationState = 'Seca';
                } elseif ($inDryOff) {
                    $lactationState = 'En secado';
                } else {
                    $lactationState = 'En producción';
                }
            }

            $milkEnd = $dryOffDate
                ?: ($goneDate
                    ?: (($expectedDryOff && $today->greaterThanOrEqualTo($expectedDryOff)) ? $expectedDryOff : $today));
            $milkDuration = $lastCalving ? $this->humanDuration($lastCalving, $milkEnd) : null;
            $daysInMilk = $daysBetween($lastCalving, $milkEnd);
            $milkStopped = (bool) ($lastCalving && $milkEnd->lessThan($today->copy()->startOfDay()));

            $countdownCalving = ($expectedCalving && $today->lessThanOrEqualTo($expectedCalving))
                ? $this->humanDuration($today, $expectedCalving)
                : null;

            $states = [];
            if ($isPregnant) {
                $states[] = 'Preñada';
            }
            if ($lactationState) {
                $states[] = $lactationState;
            }
            if (empty($states)) {
                $states[] = 'Vacía';
            }

            if ($isGone) {
                $states = [$animal->statusLabel()];
                $alerts = [];
                $lactationState = null;
                $countdownCalving = null;
                $milkStopped = (bool) $lastCalving;
            }

            $sire = $animal->pregnancySire?->name
                ?: ($animal->pregnancy_sire_name_manual ?: null);

            $serviceTypeLabel = $this->serviceLabel($animal->service_type);
            $serviceFull = $serviceTypeLabel
                ? ($serviceTypeLabel . ($sire ? ' — ' . $sire : ''))
                : ($sire ?: null);

            return [
                'id' => $animal->id,
                'name' => $animal->name ?: ('Animal ' . $animal->id),
                'tag' => $animal->ear_tag ?: $animal->internal_code,
                'last_calving' => $lastCalving,
                'days_in_milk' => $daysInMilk,
                'milk_duration' => $milkDuration,
                'calving_count' => (int) ($animal->calving_count ?? 0),
                'is_pregnant' => $isPregnant,
                'expected_calving' => $expectedCalving,
                'expected_dry_off' => $expectedDryOff,
                'expected_weaning' => $expectedWeaning,
                'weaning_display' => $isPregnant ? 'Pendiente parto' : ($lastCalving ? $expectedWeaning->format('d/m/Y') : '—'),
                'lactation_state' => $lactationState,
                'dry_off_date' => $dryOffDate,
                'milk_stopped' => $milkStopped,
                'countdown_calving' => $countdownCalving,
                'alerts' => $alerts,
                'states' => $states,
                'is_gone' => $isGone,
                'status_label' => $animal->statusLabel(),
                'sire' => $sire,
                'service' => $serviceFull,
                'service_date' => $serviceDate,
                'pregnant_duration' => ($isPregnant && $serviceDate) ? $this->humanDuration($serviceDate, $today) : null,
                'relevant' => ($lastCalving !== null) || $isPregnant,
                'in_cycle' => $inCycle,
            ];
        });

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function (array $r) use ($needle) {
                return str_contains(mb_strtolower($r['name']), $needle)
                    || str_contains(mb_strtolower((string) $r['tag']), $needle);
            });
        }

        $rows = $rows->filter(function (array $r) use ($filter) {
            return match ($filter) {
                'lactating' => $r['last_calving'] !== null,
                'pregnant' => $r['is_pregnant'],
                'alerts' => count($r['alerts']) > 0,
                'all' => $r['in_cycle'],
                default => $r['relevant'],
            };
        });

        $rows = match ($sort) {
            'lactation_asc' => $rows->sortBy(fn (array $r) => $r['days_in_milk'] ?? PHP_INT_MAX),
            'name_asc' => $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'calving_soon' => $rows->sortBy(fn (array $r) => optional($r['expected_calving'])->timestamp ?? PHP_INT_MAX),
            default => $rows->sortByDesc(fn (array $r) => $r['days_in_milk'] ?? -1),
        };

        $rows = $rows->values();

        $lactating = $females->filter(fn (Animal $a) => $a->last_calving_date !== null && $a->isActive());
        $pregnant = $females->filter(fn (Animal $a) => $a->is_pregnant === 'si' && $a->isActive());

        $avgDaysInMilk = null;
        if ($lactating->isNotEmpty()) {
            $sum = $lactating->sum(fn (Animal $a) => $daysBetween($a->last_calving_date, $today) ?? 0);
            $avgDaysInMilk = (int) round($sum / $lactating->count());
        }

        $alertsCount = $rows->filter(fn (array $r) => count($r['alerts']) > 0)->count();

        $youngStock = Animal::query()
            ->where('farm_id', $farm->id)
            ->with('lot')
            ->orderBy('name')
            ->get()
            ->filter(fn (Animal $a) => ! $a->isSold() && ! $a->isDeceased())
            ->reject(fn (Animal $a) => $a->isFemale() && (($a->last_calving_date instanceof Carbon) || $a->is_pregnant === 'si'))
            ->map(fn (Animal $a) => [
                'id' => $a->id,
                'name' => $a->name ?: ('Animal ' . $a->id),
                'tag' => $a->ear_tag ?: $a->internal_code,
                'sex' => $a->isFemale() ? 'Hembra' : 'Macho',
                'stage' => $a->developmentStage() ?: 'Sin edad registrada',
                'age' => $a->ageHuman(),
                'lot' => $a->lot?->name,
                'weight' => $a->weight_current,
            ])
            ->groupBy('stage');

        return view('lactation.index', [
            'farm' => $farm,
            'rows' => $rows,
            'search' => $search,
            'sort' => $sort,
            'filter' => $filter,
            'summary' => [
                'lactating' => $lactating->count(),
                'pregnant' => $pregnant->count(),
                'avg_label' => $avgDaysInMilk !== null ? $this->humanDays($avgDaysInMilk) : '-',
                'alerts' => $alertsCount,
            ],
            'youngStock' => $youngStock,
            'gestationDays' => self::GESTATION_DAYS,
            'dryOffDays' => self::DRY_OFF_DAYS,
        ]);
    }

    protected function humanDuration(Carbon $from, Carbon $to): string
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $months = (int) $from->diffInMonths($to);
        $afterMonths = $from->copy()->addMonths($months);
        $days = (int) round(abs($afterMonths->diffInDays($to)));

        $parts = [];
        if ($months > 0) {
            $parts[] = $months . ' ' . ($months === 1 ? 'mes' : 'meses');
        }
        $parts[] = $days . ' ' . ($days === 1 ? 'día' : 'días');

        return implode(' y ', $parts);
    }

    protected function humanDays(int $days): string
    {
        $ref = Carbon::today();

        return $this->humanDuration($ref->copy()->subDays($days), $ref);
    }

    protected function serviceLabel(?string $type): ?string
    {
        return match ($type) {
            'monta_natural' => 'Monta natural (toro)',
            'inseminacion' => 'Inseminación (pajilla)',
            'embrion' => 'Transferencia de embrión',
            default => null,
        };
    }

    public function confirmDryOff(Request $request, Animal $animal)
    {
        $farm = auth()->user()->currentFarm();
        if (! $farm || $animal->farm_id !== $farm->id) {
            abort(403);
        }

        $date = $request->filled('dry_off_date') ? Carbon::parse($request->input('dry_off_date')) : Carbon::today();
        if ($date->greaterThan(Carbon::today())) {
            $date = Carbon::today();
        }

        $animal->update(['dry_off_date' => $date->toDateString()]);

        return back()->with('success', 'Secado confirmado.');
    }

    public function registerCalving(Request $request, Animal $animal)
    {
        $farm = auth()->user()->currentFarm();
        if (! $farm || $animal->farm_id !== $farm->id) {
            abort(403);
        }

        $date = $request->filled('calving_date') ? Carbon::parse($request->input('calving_date')) : Carbon::today();
        if ($date->greaterThan(Carbon::today())) {
            $date = Carbon::today();
        }

        $sireId = $animal->pregnancy_sire_id;
        $sireNameManual = $animal->pregnancy_sire_name_manual;

        $animal->update([
            'last_calving_date' => $date->toDateString(),
            'has_calved_before' => 'si',
            'calving_count' => (int) ($animal->calving_count ?? 0) + 1,
            'is_pregnant' => 'no',
            'pregnancy_date' => null,
            'pregnancy_sire_id' => null,
            'pregnancy_sire_name_manual' => null,
            'service_type' => null,
            'dry_off_date' => null,
        ]);

        $calfNote = '';

        if ($request->boolean('register_calf')) {
            $validated = $request->validate([
                'calf_sex' => ['required', 'in:macho,hembra'],
                'calf_name' => ['nullable', 'string', 'max:255'],
                'calf_ear_tag' => ['nullable', 'string', 'max:50'],
            ]);

            $tag = $validated['calf_ear_tag'] ?? null;
            if ($tag && Animal::where('farm_id', $animal->farm_id)->where('ear_tag', $tag)->exists()) {
                $tag = null;
            }

            $calf = new Animal();
            $calf->farm_id = $animal->farm_id;
            $calf->lot_id = $animal->lot_id;
            $calf->name = $validated['calf_name'] ?? null;
            $calf->ear_tag = $tag;
            $calf->sex = $validated['calf_sex'];
            $calf->birth_date = $date->toDateString();
            $calf->dam_id = $animal->id;

            if ($sireId) {
                $calf->sire_id = $sireId;
            } elseif ($sireNameManual && \Illuminate\Support\Facades\Schema::hasColumn('animals', 'sire_name_manual')) {
                $calf->sire_name_manual = $sireNameManual;
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('animals', 'breed')) {
                $calf->breed = $animal->breed;
            }

            $calf->save();

            $calfNote = $calf->name
                ? ' Se registró la cría "' . $calf->name . '".'
                : ' Se registró la cría.';
        }

        return back()->with('success', 'Parto registrado. Comienza una nueva lactancia.' . $calfNote);
    }
}
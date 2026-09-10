<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\DailyMilkProduction;
use App\Models\FinancialTransaction;
use App\Models\MeatProduction;
use App\Models\MilkProduction;
use App\Models\MilkUsage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $farm = $user->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $range = $request->get('range', 'fortnight_current');
        $animalId = $request->get('animal_id');
        $productionType = $this->normalizeProductionType($request->get('production_type', 'all'));
        $sort = $request->get('sort', 'date_desc');

        if (! in_array($sort, ['date_desc', 'date_asc', 'animal_asc', 'type_asc'], true)) {
            $sort = 'date_desc';
        }

        [$startDate, $endDate, $rangeLabel] = $this->resolveDateRange($request, $range);

        $animals = Animal::where('farm_id', $farm->id)
            ->orderByRaw("status = 'fallecido'")
            ->orderBy('name')
            ->get();

        $milkAnimals = $animals
            ->filter(fn (Animal $animal) => $animal->canRegisterMilkProduction())
            ->values();

        $productionAnimals = $animals
            ->filter(fn (Animal $animal) => $animal->canRegisterProduction())
            ->values();

        $milkQuery = MilkProduction::query()
            ->with('animal')
            ->where('farm_id', $farm->id)
            ->whereBetween('production_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->orderByDesc('production_date')
            ->orderByDesc('id');

        $meatQuery = MeatProduction::query()
            ->with('animal')
            ->where('farm_id', $farm->id)
            ->whereBetween('production_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->orderByDesc('production_date')
            ->orderByDesc('id');

        if ($animalId) {
            $milkQuery->where('animal_id', $animalId);
            $meatQuery->where('animal_id', $animalId);
        }

        $milkProductions = $milkQuery->get();
        $meatProductions = $meatQuery->get();
        $dailyMilkProductions = ! $animalId && Schema::hasTable('daily_milk_productions')
            ? DailyMilkProduction::query()
                ->where('farm_id', $farm->id)
                ->whereBetween('production_date', [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ])
                ->orderByDesc('production_date')
                ->orderByDesc('id')
                ->get()
            : collect();
        $milkUsages = MilkUsage::query()
            ->where('farm_id', $farm->id)
            ->whereBetween('usage_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->orderByDesc('usage_date')
            ->orderByDesc('id')
            ->get();

        $milkSaleRows = $this->milkSaleRows($farm->id, $startDate, $endDate);

        $milkToday = $this->officialMilkProducedForRange($farm->id, now()->startOfDay(), now()->endOfDay(), $animalId);
        $milkWeek = $this->officialMilkProducedForRange($farm->id, now()->startOfWeek(), now()->endOfWeek(), $animalId);
        $milkMonth = $this->officialMilkProducedForRange($farm->id, now()->startOfMonth(), now()->endOfMonth(), $animalId);

        $fortnightToday = now();
        $isFirstFortnight = (int) $fortnightToday->format('d') <= 15;
        $fortnightStart = $isFirstFortnight ? $fortnightToday->copy()->startOfMonth() : $fortnightToday->copy()->day(16)->startOfDay();
        $fortnightEnd = $isFirstFortnight ? $fortnightToday->copy()->day(15)->endOfDay() : $fortnightToday->copy()->endOfMonth();
        $milkFortnight = $this->officialMilkProducedForRange($farm->id, $fortnightStart, $fortnightEnd, $animalId);

        $milkSalesSummary = $this->buildMilkSalesSummary($milkProductions, $dailyMilkProductions, $milkUsages, $milkSaleRows);
        $totalMilkLiters = (float) $milkSalesSummary['produced_liters'];
        $totalCalfMilkLiters = (float) $milkSalesSummary['calf_liters'];
        $totalConsumedMilkLiters = (float) $milkSalesSummary['consumed_liters'];
        $totalMilkReadyForSaleLiters = (float) $milkSalesSummary['ready_for_sale_liters'];
        $totalSoldMilkLiters = (float) $milkSalesSummary['sold_liters'];
        $totalSaleableMilkLiters = (float) $milkSalesSummary['saleable_liters'];

        // Precio aproximado por litro segun la ultima quincena cerrada (para estimar el valor de la leche disponible).
        $milkPricePerLiter = 0.0;
        if (Schema::hasTable('financial_transactions') && Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
            $refToday = Carbon::today();
            if ($refToday->day <= 15) {
                $prevQuincenaStart = $refToday->copy()->subMonthNoOverflow()->day(16);
                $prevQuincenaEnd = $refToday->copy()->subMonthNoOverflow()->endOfMonth();
            } else {
                $prevQuincenaStart = $refToday->copy()->day(1);
                $prevQuincenaEnd = $refToday->copy()->day(15);
            }

            $milkPriceBase = FinancialTransaction::where('farm_id', $farm->id)
                ->where('type', FinancialTransaction::TYPE_INCOME)
                ->where('milk_liters_sold', '>', 0);

            // La venta se ubica por el PERIODO de la leche vendida (milk_sale_start/end_date),
            // no por la fecha de registro, para caer en la quincena correcta.
            $hasSaleRange = Schema::hasColumn('financial_transactions', 'milk_sale_start_date')
                && Schema::hasColumn('financial_transactions', 'milk_sale_end_date');

            $prevQuincenaSales = (clone $milkPriceBase)
                ->where(function ($q) use ($prevQuincenaStart, $prevQuincenaEnd, $hasSaleRange) {
                    if ($hasSaleRange) {
                        $q->whereRaw('COALESCE(milk_sale_start_date, transaction_date) <= ?', [$prevQuincenaEnd->toDateString()])
                            ->whereRaw('COALESCE(milk_sale_end_date, transaction_date) >= ?', [$prevQuincenaStart->toDateString()]);
                    } else {
                        $q->whereBetween('transaction_date', [$prevQuincenaStart->toDateString(), $prevQuincenaEnd->toDateString()]);
                    }
                })
                ->get(['amount', 'milk_liters_sold']);

            $prevLiters = (float) $prevQuincenaSales->sum('milk_liters_sold');
            $prevAmount = (float) $prevQuincenaSales->sum('amount');

            if ($prevLiters > 0) {
                $milkPricePerLiter = $prevAmount / $prevLiters;
            } else {
                // Respaldo: la venta de leche mas reciente (por periodo de la leche o fecha de registro).
                $lastMilkSale = (clone $milkPriceBase)
                    ->orderByRaw('COALESCE(milk_sale_end_date, transaction_date) DESC')
                    ->orderByDesc('id')
                    ->first(['amount', 'milk_liters_sold']);

                if ($lastMilkSale && (float) $lastMilkSale->milk_liters_sold > 0) {
                    $milkPricePerLiter = (float) $lastMilkSale->amount / (float) $lastMilkSale->milk_liters_sold;
                }
            }
        }

        $milkPricePerLiter = round($milkPricePerLiter);
        $estimatedSaleableMilkValue = (int) round($totalSaleableMilkLiters * $milkPricePerLiter);

        $totalMeatWeight = (float) $meatProductions->sum(function ($item) {
            return $item->weight_gain_kg ?: $item->weight_kg;
        });

        $recentMilkProductions = $milkProductions->take(10)->values();
        $recentMeatProductions = $meatProductions->take(10)->values();
        $productionRecords = $this->buildProductionRecords($milkProductions, $meatProductions, $dailyMilkProductions, $productionType, $sort);
        $dailyMilkSummaries = $productionType === 'meat'
            ? collect()
            : $this->buildDailyMilkSummaries($milkProductions);
        $productionTableRows = $this->buildProductionTableRows($productionRecords, $dailyMilkSummaries, $sort);

        $milkChartData = $milkSalesSummary['daily'];

        $meatChartData = $meatProductions
            ->groupBy(fn ($row) => Carbon::parse($row->production_date)->format('Y-m-d'))
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->translatedFormat('d M'),
                    'weight' => round((float) $items->sum(function ($item) {
                        return $item->weight_gain_kg ?: $item->weight_kg;
                    }), 2),
                ];
            })
            ->sortBy('date')
            ->values();

        return view('production.index', [
            'farm' => $farm,
            'animals' => $animals,
            'milkAnimals' => $milkAnimals,
            'productionAnimals' => $productionAnimals,

            'selectedAnimalId' => $animalId,
            'selectedRange' => $range,
            'selectedProductionType' => $productionType,
            'selectedSort' => $sort,
            'rangeLabel' => $rangeLabel,
            'startDate' => $startDate,
            'endDate' => $endDate,

            'milkToday' => round($milkToday, 2),
            'milkWeek' => round($milkWeek, 2),
            'milkMonth' => round($milkMonth, 2),
            'milkFortnight' => round($milkFortnight, 2),

            'totalMilkLiters' => round($totalMilkLiters, 2),
            'totalCalfMilkLiters' => round($totalCalfMilkLiters, 2),
            'totalConsumedMilkLiters' => round($totalConsumedMilkLiters, 2),
            'totalMilkReadyForSaleLiters' => round($totalMilkReadyForSaleLiters, 2),
            'totalSoldMilkLiters' => round($totalSoldMilkLiters, 2),
            'totalSaleableMilkLiters' => round($totalSaleableMilkLiters, 2),
            'milkPricePerLiter' => $milkPricePerLiter,
            'estimatedSaleableMilkValue' => $estimatedSaleableMilkValue,
            'totalMeatWeight' => round($totalMeatWeight, 2),

            'recentMilkProductions' => $recentMilkProductions,
            'recentDailyMilkProductions' => $dailyMilkProductions->take(8)->values(),
            'recentMilkUsages' => $milkUsages->take(8)->values(),
            'milkSales' => $milkSaleRows->sortByDesc('transaction_date')->values(),
            'recentMeatProductions' => $recentMeatProductions,
            'productionRecords' => $productionRecords,
            'dailyMilkSummaries' => $dailyMilkSummaries,
            'productionTableRows' => $productionTableRows,

            'milkChartData' => $milkChartData,
            'meatChartData' => $meatChartData,
        ]);
    }

    public function storeMilk(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $data = $request->validate([
            'animal_id' => ['required', 'exists:animals,id'],
            'production_date' => ['required', 'date'],
            'period' => ['required', 'in:mañana,tarde,mañana_tarde'],
            'liters' => ['nullable', 'numeric', 'min:0.01'],
            'liters_morning' => ['nullable', 'numeric', 'min:0.01'],
            'liters_afternoon' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ], [
            'period.required' => 'Debes seleccionar el período de producción.',
            'period.in' => 'El período debe ser mañana, tarde o mañana y tarde.',
        ]);

        $animal = Animal::where('farm_id', $farm->id)
            ->where('id', $data['animal_id'])
            ->first();

        if (! $animal || ! $animal->isFemale()) {
            return back()->withErrors([
                'animal_id' => 'Debes seleccionar una hembra válida.',
            ])->withInput();
        }

        if (! $animal->canRegisterMilkProduction()) {
            return back()->withErrors([
                'animal_id' => $animal->milkProductionBlockedReason(),
            ])->withInput();
        }

        $milkEntries = $this->milkEntries($data);

        if (($data['period'] ?? null) === 'mañana_tarde'
            && (($data['liters_morning'] ?? '') === '' || ($data['liters_afternoon'] ?? '') === '')) {
            return back()
                ->withErrors(['liters' => 'Debes registrar los litros de la mañana y de la tarde.'])
                ->withInput();
        }

        if ($milkEntries === []) {
            return back()
                ->withErrors(['liters' => 'Debes registrar litros.'])
                ->withInput();
        }

        $duplicatePeriods = $this->duplicateMilkPeriods($farm->id, $animal->id, $data['production_date'], $milkEntries);

        if ($duplicatePeriods !== []) {
            return back()
                ->withErrors([
                    'period' => $this->duplicateMilkPeriodMessage($duplicatePeriods, $data['period'] ?? null),
                ])
                ->withInput();
        }

        foreach ($milkEntries as $entry) {
            MilkProduction::create([
                'farm_id' => $farm->id,
                'animal_id' => $animal->id,
                'production_date' => $data['production_date'],
                'period' => $entry['period'],
                'liters' => $entry['liters'],
                'notes' => $data['notes'] ?? null,
            ]);
        }

        $hasDailyTotal = Schema::hasTable('daily_milk_productions')
            && DailyMilkProduction::where('farm_id', $farm->id)
                ->whereDate('production_date', $data['production_date'])
                ->exists();

        if ($hasDailyTotal) {
            return back()->with('success', 'Leche guardada como detalle por animal. Ese día ya tiene total diario, así que el análisis usará el total de la finca.');
        }

        return back()->with('success', 'Producción de leche registrada.');
    }

    public function storeDailyMilk(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        if (! Schema::hasTable('daily_milk_productions')) {
            return back()
                ->withErrors(['liters' => 'La tabla de producción diaria aún no está lista.'])
                ->withInput();
        }

        $data = $request->validate([
            'production_date' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $existingDailyMilkProduction = DailyMilkProduction::query()
            ->where('farm_id', $farm->id)
            ->whereDate('production_date', $data['production_date'])
            ->exists();

        if ($existingDailyMilkProduction) {
            return back()
                ->withErrors(['production_date' => 'Ya se agregó producción de día completo para esa fecha. Puedes editarla desde el historial.'])
                ->withInput();
        }

        DailyMilkProduction::create([
            'farm_id' => $farm->id,
            'production_date' => $data['production_date'],
            'liters' => $data['liters'],
            'notes' => $data['notes'] ?? null,
        ]);

        $hasAnimalDetails = MilkProduction::where('farm_id', $farm->id)
            ->whereDate('production_date', $data['production_date'])
            ->exists();

        if ($hasAnimalDetails) {
            return back()->with('success', 'Total diario guardado. Los registros por animal de ese día quedan como detalle.');
        }

        return back()->with('success', 'Total diario de leche guardado.');
    }

    protected function milkEntries(array $data): array
    {
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
                'period' => $data['period'],
                'liters' => (float) $data['liters'],
            ]];
        }

        return [];
    }

    protected function duplicateMilkPeriods(int $farmId, int $animalId, string $date, array $milkEntries, ?int $ignoreId = null): array
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
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
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

    public function storeMeat(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $data = $request->validate([
            'animal_id' => ['required', 'exists:animals,id'],
            'production_date' => ['required', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'weight_gain_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $animal = Animal::where('farm_id', $farm->id)
            ->where('id', $data['animal_id'])
            ->first();

        if (! $animal) {
            return back()->withErrors([
                'animal_id' => 'Animal inválido.',
            ])->withInput();
        }

        if (! $animal->canRegisterProduction()) {
            return back()->withErrors([
                'animal_id' => $animal->productionBlockedReason(),
            ])->withInput();
        }

        MeatProduction::create([
            'farm_id' => $farm->id,
            'animal_id' => $animal->id,
            'production_date' => $data['production_date'],
            'weight_kg' => $data['weight_kg'] ?? null,
            'weight_gain_kg' => $data['weight_gain_kg'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Producción de carne registrada.');
    }

    public function storeMilkUsage(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $data = $request->validate([
            'usage_date' => ['required', 'date'],
            'usage_type' => ['required', 'in:calves,internal,both'],
            'calf_liters' => ['nullable', 'numeric', 'min:0'],
            'consumed_liters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $usageType = $data['usage_type'];
        $calfLiters = in_array($usageType, ['calves', 'both'], true) ? (float) ($data['calf_liters'] ?? 0) : 0;
        $consumedLiters = in_array($usageType, ['internal', 'both'], true) ? (float) ($data['consumed_liters'] ?? 0) : 0;

        if (in_array($usageType, ['calves', 'both'], true) && $calfLiters <= 0) {
            return back()
                ->withErrors(['calf_liters' => 'Registra los litros para terneras.'])
                ->withInput();
        }

        if (in_array($usageType, ['internal', 'both'], true) && $consumedLiters <= 0) {
            return back()
                ->withErrors(['consumed_liters' => 'Registra los litros de consumo interno.'])
                ->withInput();
        }

        $requestedLiters = round($calfLiters + $consumedLiters, 2);
        $availableMilkLiters = $this->availableMilkLitersForUse(
            $farm->id,
            Carbon::parse($data['usage_date'])->endOfDay(),
            $data['usage_date']
        );

        if ($availableMilkLiters <= 0) {
            return back()
                ->withErrors(['usage_date' => 'No hay litros disponibles para descontar en esa fecha. Registra producción de leche antes de guardar este uso.'])
                ->withInput();
        }

        if ($requestedLiters > $availableMilkLiters) {
            return back()
                ->withErrors([
                    'calf_liters' => 'No hay suficientes litros disponibles. Disponible: ' . $this->formatLiters($availableMilkLiters) . ' L.',
                ])
                ->withInput();
        }

        MilkUsage::updateOrCreate(
            [
                'farm_id' => $farm->id,
                'usage_date' => $data['usage_date'],
            ],
            [
                'calf_liters' => $calfLiters,
                'consumed_liters' => $consumedLiters,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Uso interno de leche actualizado.');
    }

    public function updateMilk(Request $request, MilkProduction $milkProduction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $milkProduction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $data = $request->validate([
            'animal_id' => ['required', 'exists:animals,id'],
            'production_date' => ['required', 'date'],
            'period' => ['required', 'in:mañana,tarde'],
            'liters' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ], [
            'period.required' => 'Debes seleccionar el período de producción.',
            'period.in' => 'El período debe ser mañana o tarde.',
        ]);

        $animal = Animal::where('farm_id', $farm->id)
            ->where('id', $data['animal_id'])
            ->first();

        if (! $animal || ! $animal->canRegisterMilkProduction()) {
            return back()->withErrors([
                'animal_id' => $animal?->milkProductionBlockedReason() ?: 'Debes seleccionar una hembra válida para leche.',
            ])->withInput();
        }

        $duplicatePeriods = $this->duplicateMilkPeriods(
            $farm->id,
            $animal->id,
            $data['production_date'],
            [['period' => $data['period']]],
            $milkProduction->id
        );

        if ($duplicatePeriods !== []) {
            return back()
                ->withErrors([
                    'period' => $this->duplicateMilkPeriodMessage($duplicatePeriods, $data['period'] ?? null),
                ])
                ->withInput();
        }

        $milkProduction->update([
            'animal_id' => $animal->id,
            'production_date' => $data['production_date'],
            'period' => $data['period'],
            'liters' => $data['liters'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Producción de leche actualizada correctamente.');
    }

    public function updateMilkDay(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            abort(403);
        }

        $data = $request->validate([
            'animal_id' => ['required', 'exists:animals,id'],
            'production_date' => ['required', 'date'],
            'liters_morning' => ['nullable', 'numeric', 'min:0'],
            'liters_afternoon' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $animal = Animal::where('farm_id', $farm->id)
            ->where('id', $data['animal_id'])
            ->first();

        if (! $animal || ! $animal->canRegisterMilkProduction()) {
            return back()->withErrors([
                'animal_id' => $animal?->milkProductionBlockedReason() ?: 'Debes seleccionar una hembra válida para leche.',
            ])->withInput();
        }

        $morning = $data['liters_morning'] ?? null;
        $afternoon = $data['liters_afternoon'] ?? null;

        $morningValue = ($morning === null || $morning === '') ? 0 : (float) $morning;
        $afternoonValue = ($afternoon === null || $afternoon === '') ? 0 : (float) $afternoon;

        if ($morningValue <= 0 && $afternoonValue <= 0) {
            return back()
                ->withErrors(['liters_morning' => 'Debes dejar al menos un período con litros.'])
                ->withInput();
        }

        foreach ([
            'mañana' => $morning,
            'tarde' => $afternoon,
        ] as $period => $liters) {
            $existing = MilkProduction::query()
                ->where('farm_id', $farm->id)
                ->where('animal_id', $animal->id)
                ->whereDate('production_date', $data['production_date'])
                ->where('period', $period)
                ->first();

            if ($liters === null || $liters === '' || (float) $liters <= 0) {
                $existing?->delete();
                continue;
            }

            MilkProduction::updateOrCreate(
                [
                    'farm_id' => $farm->id,
                    'animal_id' => $animal->id,
                    'production_date' => $data['production_date'],
                    'period' => $period,
                ],
                [
                    'liters' => (float) $liters,
                    'notes' => $data['notes'] ?? $existing?->notes,
                ]
            );
        }

        return back()->with('success', 'Producción diaria del animal actualizada correctamente.');
    }

    public function destroyMilkDay(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            abort(403);
        }

        $data = $request->validate([
            'animal_id' => ['nullable', 'integer'],
            'production_date' => ['required', 'date'],
        ]);

        $animalId = $data['animal_id'] ?? null;

        $query = MilkProduction::query()
            ->where('farm_id', $farm->id)
            ->whereDate('production_date', $data['production_date']);

        if ($animalId) {
            // Registros por animal: valida que el animal sea de esta finca.
            $animal = Animal::where('farm_id', $farm->id)
                ->where('id', $animalId)
                ->first();

            if (! $animal) {
                abort(403);
            }

            $query->where('animal_id', $animal->id);
        } else {
            // Registros de leche SIN animal (animal_id null): permite eliminarlos.
            $query->whereNull('animal_id');
        }

        $query->delete();

        return back()->with('success', 'Producción de leche del día eliminada correctamente.');
    }

    public function updateDailyMilk(Request $request, DailyMilkProduction $dailyMilkProduction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $dailyMilkProduction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $data = $request->validate([
            'production_date' => ['required', 'date'],
            'liters' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $exists = DailyMilkProduction::where('farm_id', $farm->id)
            ->whereDate('production_date', $data['production_date'])
            ->where('id', '!=', $dailyMilkProduction->id)
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['production_date' => 'Ya existe un registro de finca completa para esa fecha.'])
                ->withInput();
        }

        $dailyMilkProduction->update([
            'production_date' => $data['production_date'],
            'liters' => $data['liters'],
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Registro de finca actualizado correctamente.');
    }

    public function updateMeat(Request $request, MeatProduction $meatProduction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $meatProduction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $data = $request->validate([
            'animal_id' => ['required', 'exists:animals,id'],
            'production_date' => ['required', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'weight_gain_kg' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $animal = Animal::where('farm_id', $farm->id)
            ->where('id', $data['animal_id'])
            ->first();

        if (! $animal || ! $animal->canRegisterProduction()) {
            return back()->withErrors([
                'animal_id' => $animal?->productionBlockedReason() ?: 'Animal inválido.',
            ])->withInput();
        }

        if (($data['weight_kg'] ?? '') === '' && ($data['weight_gain_kg'] ?? '') === '') {
            return back()
                ->withErrors(['weight_kg' => 'Debes registrar peso actual o ganancia de peso.'])
                ->withInput();
        }

        $meatProduction->update([
            'animal_id' => $animal->id,
            'production_date' => $data['production_date'],
            'weight_kg' => $data['weight_kg'] ?? null,
            'weight_gain_kg' => $data['weight_gain_kg'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Producción de carne actualizada correctamente.');
    }

    public function destroyMilk(MilkProduction $milkProduction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $milkProduction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $milkProduction->delete();

        return back()->with('success', 'Registro de leche eliminado correctamente.');
    }

    public function bulkDestroyMilk(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            abort(403);
        }

        $data = $request->validate([
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['string'],
        ], [
            'targets.required' => 'Selecciona al menos un registro de leche.',
            'targets.min' => 'Selecciona al menos un registro de leche.',
        ]);

        $deleted = 0;

        foreach ($data['targets'] as $token) {
            $parts = explode(':', (string) $token);
            $type = $parts[0] ?? null;

            if ($type === 'milk' && isset($parts[1]) && ctype_digit($parts[1])) {
                $deleted += MilkProduction::where('farm_id', $farm->id)->where('id', (int) $parts[1])->delete();
            } elseif ($type === 'daily' && isset($parts[1]) && ctype_digit($parts[1])) {
                $deleted += DailyMilkProduction::where('farm_id', $farm->id)->where('id', (int) $parts[1])->delete();
            } elseif ($type === 'day' && isset($parts[1], $parts[2])) {
                $query = MilkProduction::where('farm_id', $farm->id)->whereDate('production_date', $parts[2]);

                if ($parts[1] === '0' || $parts[1] === '') {
                    $query->whereNull('animal_id');
                } else {
                    $query->where('animal_id', (int) $parts[1]);
                }

                $deleted += $query->delete();
            }
        }

        return back()->with('success', $deleted . ' registro(s) de leche eliminado(s).');
    }

    public function destroyDailyMilk(DailyMilkProduction $dailyMilkProduction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $dailyMilkProduction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $dailyMilkProduction->delete();

        return back()->with('success', 'Total diario de leche eliminado correctamente.');
    }

    public function destroyMeat(MeatProduction $meatProduction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $meatProduction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $meatProduction->delete();

        return back()->with('success', 'Registro de carne eliminado correctamente.');
    }

    public function destroyMilkUsage(MilkUsage $milkUsage)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $milkUsage->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $milkUsage->delete();

        return back()->with('success', 'Uso interno de leche eliminado correctamente.');
    }

    protected function resolveDateRange(Request $request, string $range): array
    {
        $today = now();

        switch ($range) {
            case 'today':
                return [
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                    'Hoy'
                ];

            case 'fortnight_current':
                // Quincena EN CURSO acumulada a hoy (1-15 o 16-fin del mes actual, hasta hoy).
                $dayOfMonth = (int) $today->format('d');

                if ($dayOfMonth <= 15) {
                    $fortnightStart = $today->copy()->startOfMonth()->startOfDay();
                } else {
                    $fortnightStart = $today->copy()->day(16)->startOfDay();
                }

                return [
                    $fortnightStart,
                    $today->copy()->endOfDay(),
                    'Quincena en curso'
                ];

            case 'month':
                $prevMonthEnd = $today->copy()->startOfMonth()->subDay();
                return [
                    $prevMonthEnd->copy()->startOfMonth()->startOfDay(),
                    $prevMonthEnd->copy()->endOfDay(),
                    'Último mes'
                ];

            case 'fortnight':
                $dayOfMonth = (int) $today->format('d');

                if ($dayOfMonth >= 16) {
                    // Consulta entre el 16 y fin de mes: 1a quincena cerrada (1 al 15) del mes actual
                    $fortnightStart = $today->copy()->startOfMonth()->startOfDay();
                    $fortnightEnd = $today->copy()->day(15)->endOfDay();
                } else {
                    // Consulta entre el 1 y el 15: 2a quincena (16 a fin) del mes anterior
                    $prevMonthEnd = $today->copy()->startOfMonth()->subDay();
                    $fortnightStart = $prevMonthEnd->copy()->day(16)->startOfDay();
                    $fortnightEnd = $prevMonthEnd->copy()->endOfDay();
                }

                return [
                    $fortnightStart,
                    $fortnightEnd,
                    'Última quincena'
                ];

            case 'custom':
                $start = $request->start_date
                    ? Carbon::parse($request->start_date)->startOfDay()
                    : now()->startOfWeek();

                $end = $request->end_date
                    ? Carbon::parse($request->end_date)->endOfDay()
                    : now()->endOfWeek();

                return [$start, $end, 'Rango personalizado'];

            case 'week':
            default:
                return [
                    $today->copy()->subDays(6)->startOfDay(),
                    $today->copy()->endOfDay(),
                    'Última semana'
                ];
        }
    }

    protected function buildProductionRecords($milkProductions, $meatProductions, $dailyMilkProductions, string $productionType, string $sort)
    {
        $dailyMilkRecords = $productionType === 'meat'
            ? collect()
            : $dailyMilkProductions->map(function (DailyMilkProduction $record) {
                return [
                    'type' => 'milk_total',
                    'type_label' => 'Finca',
                    'date' => $record->production_date,
                    'animal_id' => null,
                    'animal_name' => 'Finca completa',
                    'period' => 'Día completo',
                    'quantity' => $this->formatLiters((float) $record->liters) . ' L',
                    'total_value' => (float) $record->liters,
                    'total_class' => 'text-green-700',
                    'record' => $record,
                ];
            });

        $milkRecords = $productionType === 'meat'
            ? collect()
            : $milkProductions->map(function (MilkProduction $record) {
                return [
                    'type' => 'milk',
                    'type_label' => 'Leche',
                    'date' => $record->production_date,
                    'animal_id' => $record->animal_id,
                    'animal_name' => $record->animal->name ?? ('Animal ' . $record->animal_id),
                    'period' => $record->period ? ucfirst($record->period) : 'Sin período',
                    'quantity' => $this->formatLiters((float) $record->liters) . ' L',
                    'total_value' => (float) $record->liters,
                    'total_class' => 'text-green-700',
                    'record' => $record,
                ];
            });

        $meatRecords = $productionType === 'milk'
            ? collect()
            : $meatProductions->map(function (MeatProduction $record) {
                $weightValue = $record->weight_gain_kg ?? $record->weight_kg ?? 0;

                return [
                    'type' => 'meat',
                    'type_label' => 'Carne',
                    'date' => $record->production_date,
                    'animal_id' => $record->animal_id,
                    'animal_name' => $record->animal->name ?? ('Animal ' . $record->animal_id),
                    'period' => 'No aplica',
                    'quantity' => number_format((float) $weightValue, 2, ',', '.') . ' kg',
                    'total_value' => (float) $weightValue,
                    'total_class' => 'text-red-700',
                    'record' => $record,
                ];
            });

        $records = $dailyMilkRecords->concat($milkRecords)->concat($meatRecords);

        return match ($sort) {
            'date_asc' => $records->sortBy(fn (array $record) => optional($record['date'])->timestamp ?? 0)->values(),
            'animal_asc' => $records->sortBy('animal_name')->values(),
            'type_asc' => $records->sortBy('type_label')->values(),
            default => $records->sortByDesc(fn (array $record) => optional($record['date'])->timestamp ?? 0)->values(),
        };
    }

    protected function officialMilkProducedForRange(int $farmId, Carbon $startDate, Carbon $endDate, $animalId = null): float
    {
        $animalProductions = MilkProduction::query()
            ->where('farm_id', $farmId)
            ->whereBetween('production_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($animalId, fn ($query) => $query->where('animal_id', $animalId))
            ->get();

        if ($animalId || ! Schema::hasTable('daily_milk_productions')) {
            return round((float) $animalProductions->sum('liters'), 2);
        }

        $dailyTotalsByDate = DailyMilkProduction::where('farm_id', $farmId)
            ->whereBetween('production_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->keyBy(fn (DailyMilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        $animalMilkByDate = $animalProductions
            ->groupBy(fn (MilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        return round((float) $dailyTotalsByDate->keys()
            ->merge($animalMilkByDate->keys())
            ->unique()
            ->sum(function (string $date) use ($dailyTotalsByDate, $animalMilkByDate) {
                $dailyTotal = $dailyTotalsByDate->get($date);

                return $dailyTotal
                    ? (float) $dailyTotal->liters
                    : (float) $animalMilkByDate->get($date, collect())->sum('liters');
            }), 2);
    }

    protected function buildMilkSalesSummary($milkProductions, $dailyMilkProductions, $milkUsages, $milkSaleRows): array
    {
        $usagesByDate = $milkUsages->keyBy(fn (MilkUsage $usage) => $usage->usage_date->format('Y-m-d'));
        $dailyTotalsByDate = $dailyMilkProductions->keyBy(fn (DailyMilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));
        $animalMilkByDate = $milkProductions->groupBy(fn (MilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));
        $dates = $dailyTotalsByDate->keys()
            ->merge($animalMilkByDate->keys())
            ->merge($usagesByDate->keys())
            ->merge($this->milkSaleDates($milkSaleRows))
            ->unique()
            ->values();

        $daily = $dates
            ->map(function (string $date) use ($dailyTotalsByDate, $animalMilkByDate, $usagesByDate) {
                $dailyTotal = $dailyTotalsByDate->get($date);
                $items = $animalMilkByDate->get($date, collect());
                $producedLiters = $dailyTotal
                    ? round((float) $dailyTotal->liters, 2)
                    : round((float) $items->sum('liters'), 2);
                $usage = $usagesByDate->get($date);
                $calfLiters = round((float) ($usage?->calf_liters ?? 0), 2);
                $consumedLiters = round((float) ($usage?->consumed_liters ?? 0), 2);
                $usedLiters = min($producedLiters, $calfLiters + $consumedLiters);
                $readyForSaleLiters = round(max(0, $producedLiters - $usedLiters), 2);

                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->translatedFormat('d M'),
                    'produced_liters' => $producedLiters,
                    'calf_liters' => $calfLiters,
                    'consumed_liters' => $consumedLiters,
                    'ready_for_sale_liters' => $readyForSaleLiters,
                    'sold_liters' => 0,
                    'saleable_liters' => $readyForSaleLiters,
                    'liters' => $readyForSaleLiters,
                ];
            })
            ->sortBy('date')
            ->keyBy('date');

        $milkSaleRows->each(function (FinancialTransaction $sale) use (&$daily) {
            $startDate = $sale->milk_sale_start_date ?: $sale->transaction_date;
            $endDate = $sale->milk_sale_end_date ?: $sale->transaction_date;

            if (! $startDate || ! $endDate) {
                return;
            }

            if ($startDate->greaterThan($endDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            $period = collect(range(0, (int) $startDate->diffInDays($endDate)))
                ->map(fn (int $offset) => $startDate->copy()->addDays($offset)->format('Y-m-d'));
            $readyTotal = round((float) $period->sum(fn (string $date) => (float) ($daily->get($date)['saleable_liters'] ?? 0)), 2);
            $saleLiters = round((float) $sale->milk_liters_sold, 2);

            $period->each(function (string $date) use (&$daily, $readyTotal, $saleLiters, $period) {
                if (! $daily->has($date)) {
                    $daily[$date] = [
                        'date' => $date,
                        'label' => Carbon::parse($date)->translatedFormat('d M'),
                        'produced_liters' => 0,
                        'calf_liters' => 0,
                        'consumed_liters' => 0,
                        'ready_for_sale_liters' => 0,
                        'sold_liters' => 0,
                        'saleable_liters' => 0,
                        'liters' => 0,
                    ];
                }

                $row = $daily->get($date);
                $share = $readyTotal > 0
                    ? ($saleLiters * ((float) $row['saleable_liters'] / $readyTotal))
                    : ($saleLiters / max(1, $period->count()));
                $soldForDay = min((float) $row['saleable_liters'], round($share, 2));

                $row['sold_liters'] = round((float) $row['sold_liters'] + $soldForDay, 2);
                $row['saleable_liters'] = round(max(0, (float) $row['saleable_liters'] - $soldForDay), 2);
                $row['liters'] = $row['saleable_liters'];
                $daily[$date] = $row;
            });
        });

        $daily = $daily->sortKeys()->values();

        $producedLiters = round((float) $daily->sum('produced_liters'), 2);
        $calfLiters = round((float) $milkUsages->sum('calf_liters'), 2);
        $consumedLiters = round((float) $milkUsages->sum('consumed_liters'), 2);
        $usedLiters = round($calfLiters + $consumedLiters, 2);
        $readyForSaleLiters = round(max(0, $producedLiters - $usedLiters), 2);
        $soldLiters = round((float) $daily->sum('sold_liters'), 2);
        $saleableLiters = round(max(0, $readyForSaleLiters - $soldLiters), 2);

        return [
            'daily' => $daily,
            'produced_liters' => $producedLiters,
            'calf_liters' => $calfLiters,
            'consumed_liters' => $consumedLiters,
            'ready_for_sale_liters' => $readyForSaleLiters,
            'sold_liters' => $soldLiters,
            'saleable_liters' => $saleableLiters,
        ];
    }

    protected function milkSaleRows(int $farmId, Carbon $startDate, Carbon $endDate)
    {
        if (! Schema::hasTable('financial_transactions')
            || ! Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
            return collect();
        }

        $query = FinancialTransaction::query()
            ->where('farm_id', $farmId)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->where('milk_liters_sold', '>', 0);

        if (Schema::hasColumn('financial_transactions', 'milk_sale_start_date')
            && Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
            $query->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($rangeQuery) use ($startDate, $endDate) {
                    $rangeQuery
                        ->whereNotNull('milk_sale_start_date')
                        ->whereNotNull('milk_sale_end_date')
                        ->whereDate('milk_sale_start_date', '<=', $endDate->toDateString())
                        ->whereDate('milk_sale_end_date', '>=', $startDate->toDateString());
                })->orWhere(function ($legacyQuery) use ($startDate, $endDate) {
                    $legacyQuery
                        ->whereNull('milk_sale_start_date')
                        ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()]);
                });
            });
        } else {
            $query->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()]);
        }

        return $query->get();
    }

    protected function allocatedMilkSalesByDate($milkSaleRows)
    {
        return $milkSaleRows->flatMap(function (FinancialTransaction $sale) {
            $startDate = $sale->milk_sale_start_date ?: $sale->transaction_date;
            $endDate = $sale->milk_sale_end_date ?: $sale->transaction_date;

            if (! $startDate || ! $endDate) {
                return [];
            }

            if ($startDate->greaterThan($endDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            $days = max(1, (int) $startDate->diffInDays($endDate) + 1);
            $dailyLiters = round((float) $sale->milk_liters_sold / $days, 2);

            return collect(range(0, $days - 1))->map(fn (int $offset) => [
                'date' => $startDate->copy()->addDays($offset)->format('Y-m-d'),
                'liters' => $dailyLiters,
            ]);
        })
            ->groupBy('date')
            ->map(fn ($rows) => round((float) $rows->sum('liters'), 2));
    }

    protected function milkSaleDates($milkSaleRows)
    {
        return $milkSaleRows->flatMap(function (FinancialTransaction $sale) {
            $startDate = $sale->milk_sale_start_date ?: $sale->transaction_date;
            $endDate = $sale->milk_sale_end_date ?: $sale->transaction_date;

            if (! $startDate || ! $endDate) {
                return [];
            }

            if ($startDate->greaterThan($endDate)) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            return collect(range(0, (int) $startDate->diffInDays($endDate)))
                ->map(fn (int $offset) => $startDate->copy()->addDays($offset)->format('Y-m-d'));
        });
    }

    protected function availableMilkLitersForUse(int $farmId, Carbon $untilDate, ?string $ignoreUsageDate = null): float
    {
        $produced = $this->officialMilkProducedUntil($farmId, $untilDate);
        $sold = $this->milkSoldUntil($farmId, $untilDate);
        $used = $this->milkUsedUntil($farmId, $untilDate, $ignoreUsageDate);

        return round(max(0, $produced - $sold - $used), 2);
    }

    protected function officialMilkProducedUntil(int $farmId, Carbon $untilDate): float
    {
        if (! Schema::hasTable('milk_productions')) {
            return 0;
        }

        $animalProductions = MilkProduction::query()
            ->where('farm_id', $farmId)
            ->whereDate('production_date', '<=', $untilDate->toDateString())
            ->get();

        if (! Schema::hasTable('daily_milk_productions')) {
            return round((float) $animalProductions->sum('liters'), 2);
        }

        $dailyTotalsByDate = DailyMilkProduction::where('farm_id', $farmId)
            ->whereDate('production_date', '<=', $untilDate->toDateString())
            ->get()
            ->keyBy(fn (DailyMilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        $animalMilkByDate = $animalProductions
            ->groupBy(fn (MilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        return round((float) $dailyTotalsByDate->keys()
            ->merge($animalMilkByDate->keys())
            ->unique()
            ->sum(function (string $date) use ($dailyTotalsByDate, $animalMilkByDate) {
                $dailyTotal = $dailyTotalsByDate->get($date);

                return $dailyTotal
                    ? (float) $dailyTotal->liters
                    : (float) $animalMilkByDate->get($date, collect())->sum('liters');
            }), 2);
    }

    protected function milkUsedUntil(int $farmId, Carbon $untilDate, ?string $ignoreUsageDate = null): float
    {
        if (! Schema::hasTable('milk_usages')) {
            return 0;
        }

        return round((float) MilkUsage::query()
            ->where('farm_id', $farmId)
            ->whereDate('usage_date', '<=', $untilDate->toDateString())
            ->when($ignoreUsageDate, fn ($query) => $query->whereDate('usage_date', '!=', $ignoreUsageDate))
            ->get()
            ->sum(fn (MilkUsage $usage) => (float) $usage->calf_liters + (float) $usage->consumed_liters), 2);
    }

    protected function milkSoldUntil(int $farmId, Carbon $untilDate): float
    {
        if (! Schema::hasTable('financial_transactions')
            || ! Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
            return 0;
        }

        $query = FinancialTransaction::query()
            ->where('farm_id', $farmId)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->where('milk_liters_sold', '>', 0);

        if (Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
            $query->where(function ($query) use ($untilDate) {
                $query->where(function ($rangeQuery) use ($untilDate) {
                    $rangeQuery
                        ->whereNotNull('milk_sale_end_date')
                        ->whereDate('milk_sale_end_date', '<=', $untilDate->toDateString());
                })->orWhere(function ($legacyQuery) use ($untilDate) {
                    $legacyQuery
                        ->whereNull('milk_sale_end_date')
                        ->whereDate('transaction_date', '<=', $untilDate->toDateString());
                });
            });
        } else {
            $query->whereDate('transaction_date', '<=', $untilDate->toDateString());
        }

        return round((float) $query->sum('milk_liters_sold'), 2);
    }

    protected function buildDailyMilkSummaries($milkProductions)
    {
        return $milkProductions
            ->groupBy(function (MilkProduction $record) {
                $date = optional($record->production_date)->format('Y-m-d') ?: 'sin-fecha';

                return $date . '|' . $record->animal_id;
            })
            ->map(function ($items) {
                $first = $items->first();
                $morning = (float) $items
                    ->filter(fn (MilkProduction $record) => mb_strtolower((string) $record->period) === 'mañana')
                    ->sum('liters');
                $afternoon = (float) $items
                    ->filter(fn (MilkProduction $record) => mb_strtolower((string) $record->period) === 'tarde')
                    ->sum('liters');
                $total = (float) $items->sum('liters');

                return [
                    'date' => $first->production_date,
                    'animal_id' => $first->animal_id,
                    'animal_name' => $first->animal->name ?? ('Animal ' . $first->animal_id),
                    'ear_tag' => $first->animal->ear_tag ?? null,
                    'morning_liters' => $morning,
                    'afternoon_liters' => $afternoon,
                    'total_liters' => $total,
                    'records_count' => $items->count(),
                ];
            })
            ->sort(function (array $a, array $b) {
                $dateComparison = (optional($b['date'])->timestamp ?? 0) <=> (optional($a['date'])->timestamp ?? 0);

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return strcasecmp($a['animal_name'], $b['animal_name']);
            })
            ->values();
    }

    protected function buildProductionTableRows($productionRecords, $dailyMilkSummaries, string $sort)
    {
        $milkSummaryRows = $dailyMilkSummaries
            ->map(function (array $summary) {
                return [
                    'type' => 'milk_summary',
                    'type_label' => 'Leche',
                    'date' => $summary['date'],
                    'animal_id' => $summary['animal_id'],
                    'animal_name' => $summary['animal_name'],
                    'period' => 'Día completo',
                    'quantity' => $this->formatLiters((float) $summary['total_liters']) . ' L',
                    'total_value' => (float) $summary['total_liters'],
                    'total_class' => 'text-green-700',
                    'record' => null,
                    'summary' => $summary,
                    'edit_action' => route('production.milk-day.update'),
                ];
            });

        $records = $productionRecords
            ->reject(fn (array $record) => ($record['type'] ?? null) === 'milk')
            ->concat($milkSummaryRows);

        $records = match ($sort) {
            'date_asc' => $records->sortBy(fn (array $record) => optional($record['date'])->timestamp ?? 0),
            'animal_asc' => $records->sortBy('animal_name'),
            'type_asc' => $records->sortBy('type_label'),
            default => $records->sortByDesc(fn (array $record) => optional($record['date'])->timestamp ?? 0),
        };

        return $records
            ->map(fn (array $record) => [
                'row_type' => 'record',
                'data' => $record,
            ])
            ->values();
    }

    protected function normalizeProductionType($productionType): string
    {
        $value = strtolower(trim((string) $productionType));

        return match ($value) {
            'milk', 'leche' => 'milk',
            'meat', 'carne' => 'meat',
            default => 'all',
        };
    }

    protected function formatLiters(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

}
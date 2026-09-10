@extends('layouts.app')

@section('title', 'Resumen general')

@section('content')

@php
    use App\Models\Animal;
    use App\Models\FinancialTransaction;
    use App\Models\MeatProduction;
    use App\Models\MilkProduction;
    use App\Models\SubscriptionInvoice;
    use App\Models\User;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Schema;
    use App\Models\DailyMilkProduction;

    $user = auth()->user();
    $billingUser = $user;

    if ($user?->canAccessAdminPanel() && session('admin_view_client_id')) {
        $billingUser = User::find(session('admin_view_client_id')) ?: $user;
    }

    $shortName = $user?->short_name ?? 'Usuario';
    $hour = now()->timezone('America/Bogota')->hour;

    if ($hour >= 5 && $hour < 12) {
        $greetings = [
            "Buenos días, {$shortName} ☀️",
            "Buen día, {$shortName} ☀️",
            "Hola, {$shortName} 👋",
            "Hola de nuevo, {$shortName} 👋",
            "Qué bueno verte, {$shortName} 😄",
        ];
    } elseif ($hour >= 12 && $hour < 19) {
        $greetings = [
            "Buenas tardes, {$shortName} 🌤️",
            "Hola, {$shortName} 👋",
            "Hola de nuevo, {$shortName} 👋",
            "Qué bueno verte, {$shortName} 😄",
        ];
    } else {
        $greetings = [
            "Buenas noches, {$shortName} 🌙",
            "Hola, {$shortName} 👋",
            "Hola de nuevo, {$shortName} 👋",
            "Qué bueno verte, {$shortName} 😄",
        ];
    }

    $dashboardGreeting = $greetings[array_rand($greetings)];

    $farm = $farm ?? $user?->currentFarm();

    // --- Cálculo de leche AUTÉNTICO: usa milk_productions + daily_milk_productions,
    //     sin filtrar por animal activo ni excluir totales de finca (evita datos "congelados"). ---
    $milkTableReady = $farm && Schema::hasTable('milk_productions');
    $dailyMilkTableReady = $farm && Schema::hasTable('daily_milk_productions');

    $officialMilkForRange = function ($start, $end) use ($farm, $milkTableReady, $dailyMilkTableReady) {
        if (! $milkTableReady) {
            return 0.0;
        }

        $start = Carbon::parse($start)->toDateString();
        $end = Carbon::parse($end)->toDateString();

        $animalRows = MilkProduction::where('farm_id', $farm->id)
            ->whereBetween('production_date', [$start, $end])
            ->get();

        if (! $dailyMilkTableReady) {
            return round((float) $animalRows->sum('liters'), 2);
        }

        $dailyByDate = DailyMilkProduction::where('farm_id', $farm->id)
            ->whereBetween('production_date', [$start, $end])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        $animalByDate = $animalRows->groupBy(fn ($row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        return round((float) $dailyByDate->keys()->merge($animalByDate->keys())->unique()->sum(function ($date) use ($dailyByDate, $animalByDate) {
            $dailyTotal = $dailyByDate->get($date);

            return $dailyTotal
                ? (float) $dailyTotal->liters
                : (float) $animalByDate->get($date, collect())->sum('liters');
        }), 2);
    };

    // Quincena en curso (a hoy) y acumulado del año
    $bogotaToday = now();
    $fortnightDay = (int) $bogotaToday->format('d');
    $fortnightStartDate = $fortnightDay <= 15
        ? $bogotaToday->copy()->startOfMonth()->toDateString()
        : $bogotaToday->copy()->day(16)->toDateString();
    $todayDateStr = $bogotaToday->toDateString();
    $fortnightDays = Carbon::parse($fortnightStartDate)->diffInDays(Carbon::parse($todayDateStr)) + 1;
    $milkFortnightToDate = round($officialMilkForRange($fortnightStartDate, $todayDateStr), 1);
    $milkYearToDate = round($officialMilkForRange($bogotaToday->copy()->startOfYear()->toDateString(), $todayDateStr), 1);

    $animals = $farm
        ? Animal::where('farm_id', $farm->id)->get()
        : collect();

    $animals = $animals
        ->filter(fn ($animal) => $animal->isActive())
        ->values();

    $animalsCount = $animals->count();
    $femaleCount = $animals->filter(fn ($animal) => $animal->isFemale())->count();
    $maleCount = $animals->filter(fn ($animal) => $animal->isMale())->count();

    $calvesCount = $animals->filter(function ($animal) {
        $stage = $animal->developmentStage();
        return in_array($stage, ['Ternera', 'Ternero']);
    })->count();

    $novillosCount = $animals->filter(function ($animal) {
        $stage = $animal->developmentStage();
        return in_array($stage, ['Novilla', 'Novillo']);
    })->count();

    $adultCount = $animals->filter(function ($animal) {
        $stage = $animal->developmentStage();
        return in_array($stage, ['Vaca', 'Toro']);
    })->count();

    $milkProductionRecords = collect();
    $meatProductionRecords = collect();

    if ($farm && Schema::hasTable('milk_productions')) {
        $milkProductionRecords = MilkProduction::with('animal')
            ->where('farm_id', $farm->id)
            ->whereNotNull('animal_id')
            ->orderByDesc('production_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($record) => $record->animal && $record->animal->isActive())
            ->values()
            ->map(function ($record) {
                return [
                    'type' => 'Leche',
                    'date' => optional($record->production_date)->format('Y-m-d'),
                    'period' => $record->period,
                    'liters' => $record->liters !== null ? (float) $record->liters : null,
                    'weight' => null,
                    'animal_name' => $record->animal?->name ?: 'Animal #' . $record->animal_id,
                    'animal_id' => $record->animal_id,
                ];
            });
    }

    if ($farm && Schema::hasTable('meat_productions')) {
        $meatProductionRecords = MeatProduction::with('animal')
            ->where('farm_id', $farm->id)
            ->orderByDesc('production_date')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($record) => $record->animal && $record->animal->isActive())
            ->values()
            ->map(function ($record) {
                return [
                    'type' => 'Carne',
                    'date' => optional($record->production_date)->format('Y-m-d'),
                    'period' => null,
                    'liters' => null,
                    'weight' => $record->weight_kg !== null ? (float) $record->weight_kg : null,
                    'weight_gain' => $record->weight_gain_kg !== null ? (float) $record->weight_gain_kg : null,
                    'animal_name' => $record->animal?->name ?: 'Animal #' . $record->animal_id,
                    'animal_id' => $record->animal_id,
                ];
            });
    }

    $allProductionRecords = $milkProductionRecords
        ->merge($meatProductionRecords)
        ->sortByDesc('date')
        ->values();

    $allHealthRecords = collect();
    $alerts = collect();
    $billingInvoices = collect();
    $billingAlerts = collect();

    foreach ($animals as $animal) {
        $shouldTrackDailyMilkProduction = $animal->isFemale()
            && in_array($animal->purpose, ['leche', 'doble_proposito'], true)
            && $animal->canRegisterMilkProduction();

        $productions = $allProductionRecords
            ->where('animal_id', $animal->id)
            ->when($shouldTrackDailyMilkProduction, fn ($records) => $records->where('type', 'Leche'))
            ->values();

        $healths = collect(method_exists($animal, 'healthRecords') ? $animal->healthRecords() : []);

        $healths = $healths->map(function ($record) use ($animal) {
            $record['animal_name'] = $animal->name ?: 'Animal #' . $animal->id;
            $record['animal_id'] = $animal->id;
            return $record;
        });

        $allHealthRecords = $allHealthRecords->merge($healths);

        $lastProduction = $productions->sortByDesc('date')->first();
        if ($shouldTrackDailyMilkProduction && ! $lastProduction) {
            $alerts->push([
                'type' => 'warning',
                'title' => 'Sin producción de leche registrada',
                'animal' => $animal->name ?: 'Animal #' . $animal->id,
                'text' => 'Esta hembra en producción aún no tiene registros de leche.',
                'date' => null,
                'url' => route('animals.show', $animal),
            ]);
        } elseif ($shouldTrackDailyMilkProduction && $lastProduction && ! empty($lastProduction['date'])) {
            $daysSinceProduction = Carbon::parse($lastProduction['date'])->diffInDays(now());
            if ($daysSinceProduction > 7) {
                $alerts->push([
                    'type' => 'warning',
                    'title' => 'Producción de leche desactualizada',
                    'animal' => $animal->name ?: 'Animal #' . $animal->id,
                    'text' => 'No registra producción de leche desde hace ' . $daysSinceProduction . ' días.',
                    'date' => $lastProduction['date'],
                    'url' => route('animals.show', $animal),
                ]);
            }
        }

        $lastHealth = $healths->sortByDesc('date')->first();
        if ($lastHealth && ! empty($lastHealth['date'])) {
            $daysSinceHealth = Carbon::parse($lastHealth['date'])->diffInDays(now());
            if ($daysSinceHealth > 90) {
                $alerts->push([
                    'type' => 'danger',
                    'title' => 'Control sanitario recomendado',
                    'animal' => $animal->name ?: 'Animal #' . $animal->id,
                    'text' => 'Último registro de salud hace ' . $daysSinceHealth . ' días.',
                    'date' => $lastHealth['date'],
                    'url' => route('animals.show', $animal),
                ]);
            }
        }

        if (
            $animal->isFemale() &&
            $animal->is_pregnant === 'si' &&
            ! empty($animal->pregnancy_date)
        ) {
            $pregnancyDate = Carbon::parse($animal->pregnancy_date);
            $estimatedBirth = $pregnancyDate->copy()->addDays(283);
            $daysToBirth = now()->startOfDay()->diffInDays($estimatedBirth->startOfDay(), false);

            if ($daysToBirth >= 0 && $daysToBirth <= 30) {
                $alerts->push([
                    'type' => 'brand',
                    'title' => 'Preparto próximo',
                    'animal' => $animal->name ?: 'Animal #' . $animal->id,
                    'text' => 'Faltan ' . $daysToBirth . ' días para el parto estimado.',
                    'date' => $estimatedBirth->format('Y-m-d'),
                    'url' => route('animals.show', $animal),
                ]);
            }

            if ($daysToBirth >= 0 && $daysToBirth <= 10) {
                $alerts->push([
                    'type' => 'success',
                    'title' => 'Parto probable',
                    'animal' => $animal->name ?: 'Animal #' . $animal->id,
                    'text' => 'Parto estimado muy próximo.',
                    'date' => $estimatedBirth->format('Y-m-d'),
                    'url' => route('animals.show', $animal),
                ]);
            }
        }
    }

    $billingReady = $billingUser
        && Schema::hasTable('subscription_invoices')
        && Schema::hasColumn('subscription_invoices', 'user_id')
        && Schema::hasColumn('subscription_invoices', 'status')
        && Schema::hasColumn('subscription_invoices', 'due_date')
        && Route::has('client.billing.invoices.show');

    if ($billingReady) {
        $billingInvoices = SubscriptionInvoice::with('plan')
            ->where('user_id', $billingUser->id)
            ->whereIn('status', [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_OVERDUE])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $billingAlerts = $billingInvoices
            ->map(function ($invoice) {
                $isOverdue = $invoice->status === SubscriptionInvoice::STATUS_OVERDUE
                    || ($invoice->due_date && $invoice->due_date->isPast() && ! $invoice->due_date->isToday());

                return [
                    'type' => $isOverdue ? 'danger' : 'warning',
                    'title' => $isOverdue ? 'Factura vencida' : 'Factura pendiente',
                    'subject' => $invoice->invoice_number ?: 'Factura #' . $invoice->id,
                    'text' => 'Tienes un cobro de '
                        . number_format((float) $invoice->amount, 0, ',', '.')
                        . ' ' . ($invoice->currency ?: 'COP')
                        . ' por el plan ' . ($invoice->plan?->name ?: 'actual') . '.',
                    'date' => optional($invoice->due_date)->format('Y-m-d'),
                    'url' => route('client.billing.invoices.show', $invoice),
                ];
            })
            ->values();

        $alerts = $alerts->merge($billingAlerts);
    }

    $allHealthRecords = $allHealthRecords->sortByDesc('date')->values();
    $alerts = $alerts
        ->sortBy(function ($alert) {
            return $alert['date'] ?? '9999-12-31';
        })
        ->values();

    // Serie diaria de la quincena en curso (mini-gráfico "Producción total"), fuente auténtica.
    $fortnightAnimalByDate = $milkTableReady
        ? MilkProduction::where('farm_id', $farm->id)
            ->whereBetween('production_date', [$fortnightStartDate, $todayDateStr])
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->production_date)->format('Y-m-d'))
        : collect();
    $fortnightDailyByDate = $dailyMilkTableReady
        ? DailyMilkProduction::where('farm_id', $farm->id)
            ->whereBetween('production_date', [$fortnightStartDate, $todayDateStr])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->production_date)->format('Y-m-d'))
        : collect();

    $productionByDay = collect();
    $chartCursor = Carbon::parse($fortnightStartDate);
    $chartEnd = Carbon::parse($todayDateStr);
    while ($chartCursor->lte($chartEnd)) {
        $dateStr = $chartCursor->toDateString();
        $dailyTotal = $fortnightDailyByDate->get($dateStr);
        $dayLiters = $dailyTotal
            ? (float) $dailyTotal->liters
            : (float) ($fortnightAnimalByDate->get($dateStr, collect())->sum('liters'));

        $productionByDay->push([
            'date' => $dateStr,
            'label' => $chartCursor->translatedFormat('j'),
            'value' => round($dayLiters, 1),
        ]);

        $chartCursor->addDay();
    }
    $productionByDay = $productionByDay->values();

    $productionValues = $productionByDay->pluck('value')->all();
    $productionLabels = $productionByDay->pluck('label')->all();

    $pointCount = count($productionValues);
    $chartW = 360;
    $chartH = 180;
    $padX = 20;
    $padTop = 24;
    $padBottom = 26;
    $usableW = $chartW - ($padX * 2);
    $usableH = $chartH - $padTop - $padBottom;

    $productionMax = max(1, (float) max($productionValues ?: [0]));

    $points = [];
    foreach ($productionValues as $i => $value) {
        $x = $padX + ($pointCount > 1 ? ($usableW / ($pointCount - 1)) * $i : $usableW / 2);
        $y = $padTop + $usableH - (($value / $productionMax) * $usableH);
        $points[] = ['x' => round($x, 2), 'y' => round($y, 2), 'value' => $value, 'label' => $productionLabels[$i] ?? ''];
    }

    $linePath = '';
    $areaPath = '';

    if (count($points) === 1) {
        $p = $points[0];
        $linePath = "M {$p['x']} {$p['y']}";
        $areaPath = "M {$p['x']} {$p['y']} L {$p['x']} " . ($chartH - 10) . " L {$p['x']} " . ($chartH - 10) . " Z";
    } elseif (count($points) > 1) {
        $linePath = "M {$points[0]['x']} {$points[0]['y']} ";
        for ($i = 0; $i < count($points) - 1; $i++) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $cx = ($p1['x'] + $p2['x']) / 2;
            $linePath .= "C {$cx} {$p1['y']}, {$cx} {$p2['y']}, {$p2['x']} {$p2['y']} ";
        }

        $areaPath = $linePath
            . "L {$points[count($points) - 1]['x']} " . ($chartH - 10) . " "
            . "L {$points[0]['x']} " . ($chartH - 10) . " Z";
    }

    $totalLiters = round($milkProductionRecords->sum(fn ($record) => (float) ($record['liters'] ?? 0)), 1);
    $totalWeight = round($meatProductionRecords->sum(fn ($record) => (float) ($record['weight'] ?? 0)), 1);

    $lastProductionDate = $allProductionRecords->first()['date'] ?? null;
    $lastHealthDate = $allHealthRecords->first()['date'] ?? null;

    $todayDate = now()->toDateString();
    $weekStart = now()->startOfWeek()->toDateString();
    $weekEnd = now()->endOfWeek()->toDateString();
    $monthStart = now()->startOfMonth()->toDateString();
    $monthEnd = now()->endOfMonth()->toDateString();

    $milkToday = round($officialMilkForRange($todayDate, $todayDate), 1);
    $milkWeek = round($officialMilkForRange($weekStart, $weekEnd), 1);
    $milkMonth = round($officialMilkForRange($monthStart, $monthEnd), 1);
    $meatMonthWeight = round($meatProductionRecords->filter(fn ($record) => (($record['date'] ?? null) !== null) && $record['date'] >= $monthStart && $record['date'] <= $monthEnd)->sum(fn ($record) => (float) ($record['weight'] ?? 0)), 1);

    $morningLiters = round($milkProductionRecords
        ->filter(fn ($record) => ($record['period'] ?? null) === 'mañana' && (($record['date'] ?? null) !== null) && $record['date'] >= $monthStart && $record['date'] <= $monthEnd)
        ->sum(fn ($record) => (float) ($record['liters'] ?? 0)), 1);

    $afternoonLiters = round($milkProductionRecords
        ->filter(fn ($record) => ($record['period'] ?? null) === 'tarde' && (($record['date'] ?? null) !== null) && $record['date'] >= $monthStart && $record['date'] <= $monthEnd)
        ->sum(fn ($record) => (float) ($record['liters'] ?? 0)), 1);

    // Resumen del ÚLTIMO DÍA realmente registrado (todas las vacas + total de finca), fuente auténtica.
    $lastAnimalMilkDate = $milkTableReady ? MilkProduction::where('farm_id', $farm->id)->max('production_date') : null;
    $lastDailyMilkDate = $dailyMilkTableReady ? DailyMilkProduction::where('farm_id', $farm->id)->max('production_date') : null;
    $lastMilkDate = collect([$lastAnimalMilkDate, $lastDailyMilkDate])
        ->filter()
        ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
        ->max();

    $lastDayAnimalRows = ($lastMilkDate && $milkTableReady)
        ? MilkProduction::where('farm_id', $farm->id)->whereDate('production_date', $lastMilkDate)->get()
        : collect();
    $lastDayDailyTotal = ($lastMilkDate && $dailyMilkTableReady)
        ? DailyMilkProduction::where('farm_id', $farm->id)->whereDate('production_date', $lastMilkDate)->first()
        : null;

    $lastMilkDayMorning = round((float) $lastDayAnimalRows->filter(fn ($r) => $r->period === 'mañana')->sum('liters'), 1);
    $lastMilkDayAfternoon = round((float) $lastDayAnimalRows->filter(fn ($r) => $r->period === 'tarde')->sum('liters'), 1);
    $lastMilkDayTotal = $lastDayDailyTotal
        ? round((float) $lastDayDailyTotal->liters, 1)
        : round($lastMilkDayMorning + $lastMilkDayAfternoon, 1);
    $lastMilkDayCows = $lastDayAnimalRows->pluck('animal_id')->filter()->unique()->count();

    $pregnantFemales = $animals
        ->filter(fn ($animal) => $animal->isFemale() && $animal->is_pregnant === 'si')
        ->values();

    $reproductiveReadyFemales = $animals
        ->filter(fn ($animal) => $animal->canBeDam() && $animal->is_pregnant !== 'si')
        ->count();

    $upcomingBirths = $pregnantFemales
        ->filter(fn ($animal) => ! empty($animal->pregnancy_date))
        ->map(function ($animal) {
            $estimatedBirth = Carbon::parse($animal->pregnancy_date)->addDays(283);

            return [
                'animal' => $animal->name ?: 'Animal #' . $animal->id,
                'date' => $estimatedBirth->format('Y-m-d'),
                'days' => now()->startOfDay()->diffInDays($estimatedBirth->copy()->startOfDay(), false),
                'url' => route('animals.show', $animal),
            ];
        })
        ->filter(fn ($item) => $item['days'] >= 0)
        ->sortBy('days')
        ->values();

    $nextBirth = $upcomingBirths->first();

    $missingLotCount = $animals->filter(fn ($animal) => empty($animal->lot_id))->count();
    $missingBirthDateCount = $animals->filter(fn ($animal) => empty($animal->birth_date))->count();
    $missingWeightCount = $animals->filter(fn ($animal) => empty($animal->weight_current))->count();
    $dataPendingCount = $missingLotCount + $missingBirthDateCount + $missingWeightCount;

    $financeIncomeMonth = 0;
    $financeExpenseMonth = 0;
    $financeBalanceMonth = 0;
    $financePeriodLabel = 'Este mes';
    $financeMonthHasMovements = false;
    $financeReady = $farm && Schema::hasTable('financial_transactions');

    if ($financeReady) {
        $financeMonthHasMovements = FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->exists();

        $financeIncomeMonth = (float) FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->whereIn('type', [FinancialTransaction::TYPE_INCOME, 'ingreso'])
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $financeExpenseMonth = (float) FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->whereIn('type', [FinancialTransaction::TYPE_EXPENSE, 'gasto'])
            ->whereBetween('transaction_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $financeBalanceMonth = $financeIncomeMonth - $financeExpenseMonth;
    }

    $productionHealthPercent = $animalsCount > 0
        ? min(100, round(($allProductionRecords->count() / max($animalsCount, 1)) * 100))
        : 0;

    $healthPercent = $animalsCount > 0
        ? min(100, round(($allHealthRecords->count() / max($animalsCount, 1)) * 100))
        : 0;

    $recordsDayPercent = min(100, round((($allProductionRecords->count() + $allHealthRecords->count()) / max($animalsCount * 2, 1)) * 100));
    $billingPendingCount = $billingInvoices->count();
    $billingPendingAmount = $billingInvoices->sum(fn ($invoice) => (float) $invoice->amount);
    $nextBillingInvoice = $billingInvoices->first();
@endphp

<style>
    .dashboard-card {
        position: relative;
        overflow: hidden;
    }

    .dashboard-card::after {
        content: '';
        position: absolute;
        inset: 0;
        pointer-events: none;
        border-radius: inherit;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.68);
    }

    .dashboard-alert-card {
        border-radius: 20px;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.62);
        transition: .18s ease;
    }

    .dashboard-alert-card:hover {
        transform: translateY(-1px);
        background: rgba(255,255,255,.78);
    }

    .dashboard-alert-brand {
        border-color: rgba(22,101,52,.12);
        background: rgba(22,101,52,.06);
    }

    .dashboard-alert-success {
        border-color: rgba(34,197,94,.18);
        background: rgba(34,197,94,.08);
    }

    .dashboard-alert-warning {
        border-color: rgba(245,158,11,.18);
        background: rgba(245,158,11,.08);
    }

    .dashboard-alert-danger {
        border-color: rgba(239,68,68,.18);
        background: rgba(239,68,68,.08);
    }

    .dashboard-billing-alert {
        border: 1px solid rgba(22,101,52,.16);
        background: linear-gradient(135deg, rgba(22,101,52,.10), rgba(34,197,94,.06));
        border-radius: 24px;
        box-shadow: 0 18px 38px rgba(22,101,52,.08);
    }

    .dashboard-wave-labels {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
        margin-top: 10px;
    }

    .dashboard-wave-labels span {
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: #6b7280;
        text-transform: capitalize;
    }

    .dashboard-mini-stat {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.60);
        border-radius: 20px;
        padding: 16px;
    }

    .glass-soft {
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.58);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.56);
    }

    .dashboard-kpi-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.66);
        border-radius: 24px;
        padding: 18px;
        box-shadow: 0 12px 28px rgba(15,23,42,.05);
    }

    .dashboard-kpi-label {
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
    }

    .dashboard-kpi-value {
        margin-top: 10px;
        font-size: 30px;
        line-height: 1;
        font-weight: 900;
        color: #111827;
    }

    .dashboard-kpi-note {
        margin-top: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #6b7280;
    }

    .dark .dashboard-page {
        color: #f8fafc;
    }

    .dark .dashboard-page .glass,
    .dark .dashboard-page .dashboard-kpi-card,
    .dark .dashboard-page .dashboard-mini-stat,
    .dark .dashboard-page .glass-soft,
    .dark .dashboard-page .dashboard-alert-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .dashboard-page .glass-soft,
    .dark .dashboard-page .dashboard-mini-stat {
        background: rgba(30,41,59,.74);
    }

    .dark .dashboard-page .dashboard-kpi-value,
    .dark .dashboard-page .text-gray-900,
    .dark .dashboard-page .font-semibold,
    .dark .dashboard-page .font-bold,
    .dark .dashboard-page .font-extrabold {
        color: #f8fafc !important;
    }

    .dark .dashboard-page .dashboard-kpi-label,
    .dark .dashboard-page .dashboard-kpi-note,
    .dark .dashboard-page .text-gray-500,
    .dark .dashboard-page .text-gray-600,
    .dark .dashboard-page .text-gray-700 {
        color: #cbd5e1 !important;
    }

    .dark .dashboard-page .text-brand {
        color: #86efac !important;
    }

    .dark .dashboard-page .text-red-500,
    .dark .dashboard-page .text-red-600 {
        color: #fca5a5 !important;
    }

    .dark .dashboard-page .bg-white\/60,
    .dark .dashboard-page .bg-white\/70,
    .dark .dashboard-page .bg-white\/80 {
        background-color: rgba(30,41,59,.78) !important;
    }

    .dark .dashboard-page .border-black\/10 {
        border-color: rgba(148,163,184,.24) !important;
    }

    .dark .dashboard-page .dashboard-alert-brand {
        background: rgba(34,197,94,.14);
        border-color: rgba(134,239,172,.28);
    }

    .dark .dashboard-page .dashboard-alert-success {
        background: rgba(22,163,74,.16);
        border-color: rgba(74,222,128,.30);
    }

    .dark .dashboard-page .dashboard-alert-warning {
        background: rgba(245,158,11,.16);
        border-color: rgba(251,191,36,.32);
    }

    .dark .dashboard-page .dashboard-alert-danger {
        background: rgba(239,68,68,.16);
        border-color: rgba(248,113,113,.32);
    }

    .dark .dashboard-page .dashboard-billing-alert {
        background: linear-gradient(135deg, rgba(20,83,45,.42), rgba(15,23,42,.90));
        border-color: rgba(134,239,172,.28);
        box-shadow: 0 18px 42px rgba(0,0,0,.36);
    }

    .dark .dashboard-page a.glass-soft:hover,
    .dark .dashboard-page .dashboard-alert-card:hover {
        background: rgba(51,65,85,.82);
    }
</style>

<div class="dashboard-page space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">
                {{ $dashboardGreeting }}
            </h1>

            <p class="text-sm text-gray-500 mt-2">
                @if($farm)
                    Resumen actual de {{ $farm->name }}.
                @else
                    Aún no tienes una finca activa.
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('animals.index') }}"
               class="rounded-xl border border-black/10 bg-white/60 hover:bg-white/80 transition px-4 py-2 text-sm font-semibold">
                Ver animales
            </a>

            <a href="{{ route('animals.create') }}"
               class="rounded-xl bg-brand hover:bg-brand-dark transition px-4 py-2 text-sm font-semibold text-white shadow-glow">
                + Nuevo animal
            </a>
        </div>
    </div>

    @if($billingPendingCount > 0)
        <div class="dashboard-billing-alert p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wide text-brand">
                        Facturación
                    </div>
                    <h2 class="mt-1 text-xl font-extrabold text-gray-900">
                        Tienes {{ $billingPendingCount }} factura(s) por atender
                    </h2>
                    <p class="mt-1 text-sm font-semibold text-gray-600">
                        Total pendiente: {{ number_format($billingPendingAmount, 0, ',', '.') }} {{ $nextBillingInvoice?->currency ?: 'COP' }}
                        @if($nextBillingInvoice?->due_date)
                            · Próximo vencimiento: {{ $nextBillingInvoice->due_date->format('d/m/Y') }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if(Route::has('client.billing.invoices'))
                        <a href="{{ route('client.billing.invoices') }}"
                           class="rounded-xl border border-black/10 bg-white/70 hover:bg-white/90 transition px-4 py-2 text-sm font-extrabold">
                            Ver facturas
                        </a>
                    @endif

                    @if($nextBillingInvoice)
                        <a href="{{ route('client.billing.invoices.show', $nextBillingInvoice) }}"
                           class="rounded-xl bg-brand hover:bg-brand-dark transition px-4 py-2 text-sm font-extrabold text-white shadow-glow">
                            Revisar cobro
                        </a>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Animales activos</div>
            <div class="dashboard-kpi-value">{{ $animalsCount }}</div>
            <div class="dashboard-kpi-note">{{ $femaleCount }} hembras · {{ $maleCount }} machos</div>
        </div>

        <div class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Leche hoy</div>
            <div class="dashboard-kpi-value">{{ $milkToday > 0 ? $milkToday . ' L' : '0 L' }}</div>
            <div class="dashboard-kpi-note">{{ $milkFortnightToDate }} L en la quincena</div>
        </div>

        <div class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Balance</div>
            <div class="dashboard-kpi-value {{ $financeBalanceMonth < 0 ? 'text-red-600' : 'text-brand' }}">
                {{ $financeReady ? '$' . number_format($financeBalanceMonth, 0, ',', '.') : '—' }}
            </div>
            <div class="dashboard-kpi-note">
                {{ $financeReady ? ($financeMonthHasMovements ? $financePeriodLabel . ' · Ingresos $' . number_format($financeIncomeMonth, 0, ',', '.') : 'Sin movimientos este mes') : 'Pendiente por activar' }}
            </div>
        </div>

        <div class="dashboard-kpi-card">
            <div class="dashboard-kpi-label">Alertas</div>
            <div class="dashboard-kpi-value">{{ $alerts->count() }}</div>
            <div class="dashboard-kpi-note">{{ $dataPendingCount }} datos por completar</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <div class="xl:col-span-4 glass dashboard-card rounded-[28px] p-5 overflow-hidden">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="text-sm text-gray-500 font-medium">Leche · quincena en curso</div>
                    <div class="text-3xl font-extrabold mt-1">{{ $milkFortnightToDate > 0 ? $milkFortnightToDate.' L' : 'Sin registros' }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Acumulado de {{ $fortnightDays }} {{ $fortnightDays === 1 ? 'día' : 'días' }}{{ $lastMilkDate ? ' · último registro '.Carbon::parse($lastMilkDate)->format('d/m/Y') : '' }}
                    </div>
                    @if($totalWeight > 0)
                        <div class="text-xs font-semibold text-gray-600 mt-2">
                            Carne registrada: {{ $totalWeight }} kg
                        </div>
                    @endif
                </div>

                <div class="rounded-full border border-green-200 bg-green-50 text-green-700 px-3 py-1 text-xs font-semibold">
                    Leche / carne
                </div>
            </div>

            <svg viewBox="0 0 360 180" class="w-full h-[180px]">
                <defs>
                    <linearGradient id="prodFillReal" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgba(34,197,94,0.22)" />
                        <stop offset="100%" stop-color="rgba(34,197,94,0.02)" />
                    </linearGradient>

                    <linearGradient id="prodLineReal" x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stop-color="#22c55e" />
                        <stop offset="100%" stop-color="#a3e635" />
                    </linearGradient>

                    <filter id="prodGlowReal">
                        <feGaussianBlur stdDeviation="4" result="coloredBlur"/>
                        <feMerge>
                            <feMergeNode in="coloredBlur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>

                @if(count($points) > 1)
                    <path d="{{ $areaPath }}" fill="url(#prodFillReal)"></path>

                    <path d="{{ $linePath }}"
                          fill="none"
                          stroke="url(#prodLineReal)"
                          stroke-width="4"
                          stroke-linecap="round"
                          filter="url(#prodGlowReal)"
                          opacity=".92"
                          pathLength="100"
                          stroke-dasharray="100"
                          stroke-dashoffset="100">
                        <animate attributeName="stroke-dashoffset" from="100" to="0" dur="1.8s" fill="freeze" />
                    </path>

                    @foreach($points as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4.5" fill="#ffffff" opacity=".95"></circle>
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3" fill="#22c55e"></circle>
                    @endforeach
                @else
                    <path d="M20 145 C 70 140, 110 130, 150 128 C 190 126, 230 110, 270 100 C 300 93, 320 86, 340 78 L340 170 L20 170 Z"
                          fill="url(#prodFillReal)"></path>

                    <path d="M20 145 C 70 140, 110 130, 150 128 C 190 126, 230 110, 270 100 C 300 93, 320 86, 340 78"
                          fill="none"
                          stroke="url(#prodLineReal)"
                          stroke-width="4"
                          stroke-linecap="round"
                          filter="url(#prodGlowReal)"
                          opacity=".45"
                          pathLength="100"
                          stroke-dasharray="100"
                          stroke-dashoffset="100">
                        <animate attributeName="stroke-dashoffset" from="100" to="0" dur="1.8s" fill="freeze" />
                    </path>
                @endif
            </svg>

            <div class="dashboard-wave-labels" style="grid-template-columns: repeat({{ max(1, count($productionLabels)) }}, minmax(0, 1fr));">
                @foreach($productionLabels as $label)
                    <span>{{ $label }}</span>
                @endforeach
            </div>
        </div>

        <div class="xl:col-span-4 glass dashboard-card rounded-[28px] p-5 overflow-hidden">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <div class="text-sm text-gray-500 font-medium">Animales activos</div>
                    <div class="text-3xl font-extrabold mt-1">{{ $animalsCount }}</div>
                    <div class="text-xs text-gray-500 mt-1">Inventario general actual</div>
                </div>

                <div class="rounded-full border border-black/10 bg-white/60 text-gray-700 px-3 py-1 text-xs font-semibold">
                    Inventario
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="glass-soft rounded-2xl p-4">
                    <div class="text-xs text-gray-500">Hembras</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $femaleCount }}</div>
                </div>

                <div class="glass-soft rounded-2xl p-4">
                    <div class="text-xs text-gray-500">Machos</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $maleCount }}</div>
                </div>

                <div class="glass-soft rounded-2xl p-4">
                    <div class="text-xs text-gray-500">Terneros</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $calvesCount }}</div>
                </div>

                <div class="glass-soft rounded-2xl p-4">
                    <div class="text-xs text-gray-500">Novillos / novillas</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $novillosCount }}</div>
                </div>

                <div class="glass-soft rounded-2xl p-4 col-span-2">
                    <div class="text-xs text-gray-500">Adultos</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $adultCount }}</div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-4 glass dashboard-card rounded-[28px] p-5 overflow-hidden">
            <div class="flex items-start justify-between mb-5">
                <div>
                    <div class="text-sm text-gray-500 font-medium">Estado general</div>
                    <div class="text-3xl font-extrabold mt-1">{{ $animalsCount ? 'Activo' : 'Pendiente' }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $animalsCount ? 'Resumen basado en datos reales.' : 'Disponible cuando existan registros.' }}
                    </div>
                </div>

                <div class="rounded-full border border-lime-200 bg-lime-50 text-lime-700 px-3 py-1 text-xs font-semibold">
                    Monitoreo
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="text-gray-600">Cobertura de registros</span>
                        <span class="font-semibold text-gray-700">{{ $productionHealthPercent }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-black/5 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-green-500 to-lime-400" style="width:{{ $productionHealthPercent }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="text-gray-600">Sanidad</span>
                        <span class="font-semibold text-gray-700">{{ $healthPercent }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-black/5 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-green-500" style="width:{{ $healthPercent }}%"></div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="text-gray-600">Registros al día</span>
                        <span class="font-semibold text-gray-700">{{ $recordsDayPercent }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-black/5 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-lime-400 to-green-600" style="width:{{ $recordsDayPercent }}%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 pt-3">
                    <div class="glass-soft rounded-2xl p-3 text-center">
                        <div class="text-xs text-gray-500">Alertas</div>
                        <div class="text-lg font-extrabold mt-1">{{ $alerts->count() }}</div>
                    </div>
                    <div class="glass-soft rounded-2xl p-3 text-center">
                        <div class="text-xs text-gray-500">Registros</div>
                        <div class="text-lg font-extrabold mt-1">{{ $allProductionRecords->count() }}</div>
                    </div>
                    <div class="glass-soft rounded-2xl p-3 text-center">
                        <div class="text-xs text-gray-500">Salud</div>
                        <div class="text-lg font-extrabold mt-1">{{ $allHealthRecords->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-4 glass rounded-[28px] p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-lg">Reproducción</h3>
                <span class="text-xs px-3 py-1 rounded-full border border-black/10 bg-white/60">Hembras</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="dashboard-mini-stat">
                    <div class="text-xs text-gray-500 font-bold">Preñadas</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $pregnantFemales->count() }}</div>
                </div>

                <div class="dashboard-mini-stat">
                    <div class="text-xs text-gray-500 font-bold">Aptas libres</div>
                    <div class="text-2xl font-extrabold mt-1">{{ $reproductiveReadyFemales }}</div>
                </div>
            </div>

            <div class="mt-4 glass-soft rounded-2xl p-4">
                <div class="text-xs text-gray-500 font-bold">Próximo parto estimado</div>
                @if($nextBirth)
                    <a href="{{ $nextBirth['url'] }}" class="block mt-2">
                        <div class="font-extrabold text-gray-900">{{ $nextBirth['animal'] }}</div>
                        <div class="text-sm text-gray-500 mt-1">
                            {{ Carbon::parse($nextBirth['date'])->format('d/m/Y') }} · faltan {{ $nextBirth['days'] }} días
                        </div>
                    </a>
                @else
                    <div class="mt-2 text-sm font-semibold text-gray-500">Sin partos próximos registrados.</div>
                @endif
            </div>
        </div>

        <div class="xl:col-span-4 glass rounded-[28px] p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-lg">Producción clave</h3>
                <span class="text-xs px-3 py-1 rounded-full border border-black/10 bg-white/60">Mes actual</span>
            </div>

            <div class="space-y-3">
                <div class="dashboard-mini-stat flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Leche del mes</div>
                        <div class="text-sm text-gray-500">Total registrado</div>
                    </div>
                    <div class="text-lg font-extrabold text-brand">{{ $milkMonth }} L</div>
                </div>

                <div class="dashboard-mini-stat flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Mañana / tarde</div>
                        <div class="text-sm text-gray-500">Distribución del mes</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-extrabold text-gray-900">{{ $morningLiters }} L</div>
                        <div class="text-xs font-bold text-gray-500">{{ $afternoonLiters }} L</div>
                    </div>
                </div>

                <div class="dashboard-mini-stat flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Carne registrada</div>
                        <div class="text-sm text-gray-500">Registrado este mes</div>
                    </div>
                    <div class="text-lg font-extrabold text-gray-900">{{ $meatMonthWeight > 0 ? $meatMonthWeight . ' kg' : '—' }}</div>
                </div>
            </div>
        </div>

        <div class="xl:col-span-4 glass rounded-[28px] p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-lg">Finanzas y datos</h3>
                <span class="text-xs px-3 py-1 rounded-full border border-black/10 bg-white/60">Control</span>
            </div>

            <div class="space-y-3">
                <div class="dashboard-mini-stat flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Ingresos / gastos</div>
                        <div class="text-sm text-gray-500">{{ $financeMonthHasMovements ? $financePeriodLabel : 'Sin movimientos este mes' }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-extrabold text-brand">{{ $financeReady ? '$' . number_format($financeIncomeMonth, 0, ',', '.') : '—' }}</div>
                        <div class="text-xs font-bold text-red-500">{{ $financeReady ? '$' . number_format($financeExpenseMonth, 0, ',', '.') : 'Sin módulo' }}</div>
                    </div>
                </div>

                <div class="dashboard-mini-stat">
                    <div class="font-semibold">Datos pendientes</div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div>
                            <div class="text-lg font-extrabold">{{ $missingLotCount }}</div>
                            <div class="text-[11px] font-bold text-gray-500">Sin lote</div>
                        </div>
                        <div>
                            <div class="text-lg font-extrabold">{{ $missingBirthDateCount }}</div>
                            <div class="text-[11px] font-bold text-gray-500">Sin fecha</div>
                        </div>
                        <div>
                            <div class="text-lg font-extrabold">{{ $missingWeightCount }}</div>
                            <div class="text-[11px] font-bold text-gray-500">Sin peso</div>
                        </div>
                    </div>
                </div>

                <a href="{{ route('animals.index') }}" class="dashboard-mini-stat block hover:bg-white/80 transition">
                    <div class="font-semibold text-gray-900">Revisar inventario</div>
                    <div class="text-sm text-gray-500 mt-1">Ver filtros, lotes y animales activos.</div>
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <div class="xl:col-span-5 glass rounded-[28px] p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-lg">Alertas prioritarias</h3>
                <span class="text-xs px-3 py-1 rounded-full border border-black/10 bg-white/60">
                    {{ $alerts->count() }} alerta(s)
                </span>
            </div>

            <div class="space-y-3">
                @forelse($alerts->take(6) as $alert)
                    <a href="{{ $alert['url'] }}"
                       class="dashboard-alert-card dashboard-alert-{{ $alert['type'] }} block p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-bold text-gray-900">{{ $alert['title'] }}</div>
                                <div class="text-sm text-gray-600 mt-1">{{ $alert['subject'] ?? $alert['animal'] ?? 'Alerta' }}</div>
                                <div class="text-sm text-gray-500 mt-2">{{ $alert['text'] }}</div>
                            </div>

                            @if(!empty($alert['date']))
                                <div class="text-xs font-semibold text-gray-500 whitespace-nowrap">
                                    {{ Carbon::parse($alert['date'])->format('d/m/Y') }}
                                </div>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="glass-soft rounded-2xl p-5 text-sm text-gray-500">
                        Aquí aparecerán prepartos, partos probables, controles sanitarios y alertas de producción.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="xl:col-span-3 glass rounded-[28px] p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-lg">Producción del último día</h3>
                <span class="text-xs px-3 py-1 rounded-full border border-black/10 bg-white/60">Leche</span>
            </div>

            <div class="space-y-3">
                @if($lastMilkDate)
                    <a href="{{ route('production.index') }}" class="glass-soft rounded-2xl p-4 block hover:bg-white/80 transition">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">Último día registrado</div>
                            <div class="text-sm font-bold text-gray-700">{{ \Carbon\Carbon::parse($lastMilkDate)->format('d/m/Y') }}</div>
                        </div>
                        <div class="mt-2">
                            <div class="text-3xl font-extrabold text-brand leading-none">{{ $lastMilkDayTotal }} L</div>
                            <div class="text-xs text-gray-500 mt-1">Total de la finca{{ $lastMilkDayCows > 0 ? ' · '.$lastMilkDayCows.' '.($lastMilkDayCows === 1 ? 'vaca' : 'vacas') : '' }}</div>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-white/60 border border-black/5 px-3 py-2">
                                <div class="text-xs text-gray-500">Mañana</div>
                                <div class="text-base font-extrabold text-gray-900">{{ $lastMilkDayMorning }} L</div>
                            </div>
                            <div class="rounded-xl bg-white/60 border border-black/5 px-3 py-2">
                                <div class="text-xs text-gray-500">Tarde</div>
                                <div class="text-base font-extrabold text-gray-900">{{ $lastMilkDayAfternoon }} L</div>
                            </div>
                        </div>
                    </a>
                @else
                    <div class="glass-soft rounded-2xl p-5 text-sm text-gray-500">
                        Aún no hay producción de leche registrada.
                    </div>
                @endif
            </div>
        </div>

        <div class="xl:col-span-4 glass rounded-[28px] p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-lg">Resumen de registros</h3>
                <span class="text-xs px-3 py-1 rounded-full border border-black/10 bg-white/60">Producción</span>
            </div>

            <div class="space-y-3">
                <div class="glass-soft rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Animales</div>
                        <div class="text-sm text-gray-500">Inventario total</div>
                    </div>
                    <div class="text-lg font-extrabold text-gray-900">{{ $animalsCount }}</div>
                </div>

                <div class="glass-soft rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Producción reciente</div>
                        <div class="text-sm text-gray-500">Último día con leche</div>
                    </div>
                    <div class="text-lg font-extrabold text-gray-900">
                        {{ $lastMilkDate ? Carbon::parse($lastMilkDate)->format('d/m') : '—' }}
                    </div>
                </div>

                <div class="glass-soft rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Salud reciente</div>
                        <div class="text-sm text-gray-500">Último registro</div>
                    </div>
                    <div class="text-lg font-extrabold text-gray-900">
                        {{ $lastHealthDate ? Carbon::parse($lastHealthDate)->format('d/m') : '—' }}
                    </div>
                </div>

                <div class="glass-soft rounded-2xl p-4 flex items-center justify-between">
                    <div>
                        <div class="font-semibold">Producción acumulada</div>
                        <div class="text-sm text-gray-500">Leche registrada este año</div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-extrabold text-brand">{{ $milkYearToDate > 0 ? $milkYearToDate.' L' : '—' }}</div>
                        @if($totalWeight > 0)
                            <div class="text-xs font-bold text-gray-500">{{ $totalWeight }} kg</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

@endsection

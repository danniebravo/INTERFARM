<?php

namespace App\Http\Controllers;

use App\Models\FinancialTransaction;
use App\Models\DailyMilkProduction;
use App\Models\MilkProduction;
use App\Models\MilkUsage;
use App\Support\EscapesLikeSearch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FinanceController extends Controller
{
    use EscapesLikeSearch;

    public function index(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        if (! Schema::hasTable('financial_transactions')) {
            return $this->financeIndexResponse([
                'farm' => $farm,
                'transactions' => collect(),
                'incomeTotal' => 0,
                'expenseTotal' => 0,
                'balance' => 0,
                'categories' => collect(),
                'chartData' => collect(),
                'selectedRange' => 'month',
                'selectedType' => 'all',
                'selectedCategory' => '',
                'selectedSort' => 'date_desc',
                'search' => '',
                'rangeLabel' => 'Último mes',
                'startDate' => now()->startOfMonth()->subDay()->startOfMonth(),
                'endDate' => now()->startOfMonth()->subDay(),
                'databaseReady' => false,
                'milkAvailableLiters' => 0,
                'milkAvailabilityByDate' => collect(),
            ]);
        }

        $range = $request->get('range', 'month');
        $type = $request->get('type', 'all');
        $category = trim((string) $request->get('category'));
        $search = trim((string) $request->get('search'));
        $sort = $request->get('sort', 'date_desc');

        $range = match ($range) {
            'annual', 'yearly', 'this_year' => 'year',
            default => $range,
        };

        if (! in_array($range, ['today', 'week', 'fortnight', 'month', 'year', 'custom'], true)) {
            $range = 'month';
        }

        if (! in_array($type, ['all', FinancialTransaction::TYPE_INCOME, FinancialTransaction::TYPE_EXPENSE], true)) {
            $type = 'all';
        }

        if (! in_array($sort, ['date_desc', 'date_asc', 'amount_desc', 'title_asc'], true)) {
            $sort = 'date_desc';
        }

        [$startDate, $endDate, $rangeLabel] = $this->resolveDateRange($request, $range);

        $baseQuery = FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->whereBetween('transaction_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $transactionsQuery = (clone $baseQuery)
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($search !== '', function ($query) use ($search) {
                $this->whereLikeAny($query, ['title', 'category', 'reference', 'description'], $search);
            });

        match ($sort) {
            'date_asc' => $transactionsQuery->orderBy('transaction_date')->orderBy('id'),
            'amount_desc' => $transactionsQuery->orderByDesc('amount'),
            'title_asc' => $transactionsQuery->orderBy('title'),
            default => $transactionsQuery->orderByDesc('transaction_date')->orderByDesc('id'),
        };

        $transactions = $transactionsQuery->get();

        $incomeTotal = (float) (clone $baseQuery)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->sum('amount');

        $expenseTotal = (float) (clone $baseQuery)
            ->where('type', FinancialTransaction::TYPE_EXPENSE)
            ->sum('amount');

        $balance = $incomeTotal - $expenseTotal;

        $categories = FinancialTransaction::query()
            ->where('farm_id', $farm->id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $chartData = $this->buildChartData($baseQuery, $startDate, $endDate);
        $milkAvailableLiters = $this->availableMilkLiters($farm->id, now());
        $milkAvailabilityByDate = $this->milkAvailabilityByDate($farm->id);

        [$farmBreakdown, $consolidatedTotals] = $this->buildOwnerFarmBreakdown($startDate, $endDate);

        return $this->financeIndexResponse([
            'farm' => $farm,
            'ownerFarmCount' => $farmBreakdown->count(),
            'farmBreakdown' => $farmBreakdown,
            'consolidatedTotals' => $consolidatedTotals,
            'transactions' => $transactions,
            'incomeTotal' => round($incomeTotal, 2),
            'expenseTotal' => round($expenseTotal, 2),
            'balance' => round($balance, 2),
            'categories' => $categories,
            'chartData' => $chartData,
            'selectedRange' => $range,
            'selectedType' => $type,
            'selectedCategory' => $category,
            'selectedSort' => $sort,
            'search' => $search,
            'rangeLabel' => $rangeLabel,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'databaseReady' => true,
            'milkAvailableLiters' => round($milkAvailableLiters, 2),
            'milkAvailabilityByDate' => $milkAvailabilityByDate,
        ]);
    }

    public function store(Request $request)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        if (! Schema::hasTable('financial_transactions')) {
            return back()
                ->with('warning', 'La tabla de gastos e ingresos todavía no está creada. Ejecuta las migraciones antes de registrar movimientos.');
        }

        $data = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'productive_movement' => ['nullable', 'in:milk_sale'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'milk_liters_sold' => ['nullable', 'numeric', 'min:0.01'],
            'milk_price_per_liter' => ['nullable', 'numeric', 'min:0.01'],
            'milk_sale_start_date' => ['nullable', 'date'],
            'milk_sale_end_date' => ['nullable', 'date'],
            'transaction_date' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'animal_id' => ['nullable', 'integer'],
        ], [
            'type.required' => 'Debes seleccionar si es ingreso o gasto.',
            'title.required' => 'Debes escribir el nombre del movimiento.',
            'transaction_date.required' => 'Debes seleccionar la fecha.',
        ]);

        $milkLitersSold = isset($data['milk_liters_sold']) && $data['milk_liters_sold'] !== ''
            ? (float) $data['milk_liters_sold']
            : null;

        if (($data['productive_movement'] ?? null) === 'milk_sale'
            && $data['type'] === FinancialTransaction::TYPE_INCOME
            && $milkLitersSold === null) {
            return back()
                ->withErrors(['milk_liters_sold' => 'Registra los litros vendidos de leche.'])
                ->withInput();
        }

        $milkPricePerLiter = isset($data['milk_price_per_liter']) && $data['milk_price_per_liter'] !== ''
            ? (float) $data['milk_price_per_liter']
            : null;

        if ($milkLitersSold !== null && $data['type'] !== FinancialTransaction::TYPE_INCOME) {
            return back()
                ->withErrors(['milk_liters_sold' => 'Los litros vendidos de leche deben registrarse como ingreso.'])
                ->withInput();
        }

        if ($milkLitersSold !== null && $milkPricePerLiter === null) {
            return back()
                ->withErrors(['milk_price_per_liter' => 'Registra el valor actual por litro de leche.'])
                ->withInput();
        }

        $amount = isset($data['amount']) && $data['amount'] !== ''
            ? (float) $data['amount']
            : null;

        [$milkSaleStartDate, $milkSaleEndDate] = $this->resolveMilkSaleRange($data);

        if ($milkLitersSold !== null) {
            if (! $milkSaleStartDate || ! $milkSaleEndDate) {
                return back()
                    ->withErrors(['milk_sale_start_date' => 'Selecciona el rango de fechas de la leche vendida.'])
                    ->withInput();
            }

            $availableMilkLiters = $this->availableMilkLitersForRange($farm->id, $milkSaleStartDate, $milkSaleEndDate);

            if ($availableMilkLiters <= 0) {
                return back()
                    ->withErrors(['milk_liters_sold' => 'No hay litros disponibles para vender en ese rango. Registra producción de leche antes de guardar la venta.'])
                    ->withInput();
            }

            if ($unavailableDate = $this->firstUnavailableMilkDateInRange($farm->id, $milkSaleStartDate, $milkSaleEndDate)) {
                return back()
                    ->withErrors(['milk_sale_start_date' => 'El rango incluye un día sin litros disponibles: ' . $unavailableDate->format('d/m/Y') . '.'])
                    ->withInput();
            }

            if ($milkLitersSold > $availableMilkLiters) {
                return back()
                    ->withErrors([
                        'milk_liters_sold' => 'No hay suficientes litros disponibles. Disponible: ' . $this->formatLiters($availableMilkLiters) . ' L.',
                    ])
                    ->withInput();
            }

            $amount = round($milkLitersSold * $milkPricePerLiter, 2);
        }

        if ($amount === null || $amount <= 0) {
            return back()
                ->withErrors(['amount' => 'Debes ingresar el valor del movimiento.'])
                ->withInput();
        }

        $transactionData = [
            'farm_id' => $farm->id,
            'type' => $data['type'],
            'title' => $milkLitersSold !== null && trim((string) $data['title']) === '' ? 'Venta de leche' : $data['title'],
            'amount' => $amount,
            'milk_liters_sold' => $milkLitersSold,
            'transaction_date' => $data['transaction_date'],
            'category' => $milkLitersSold !== null && empty($data['category']) ? 'Venta de leche' : ($data['category'] ?? null),
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if ($milkLitersSold !== null && Schema::hasColumn('financial_transactions', 'milk_price_per_liter')) {
            $transactionData['milk_price_per_liter'] = $milkPricePerLiter;
        }

        if (Schema::hasColumn('financial_transactions', 'milk_sale_start_date')) {
            $transactionData['milk_sale_start_date'] = $milkLitersSold !== null ? $milkSaleStartDate?->toDateString() : null;
        }

        if (Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
            $transactionData['milk_sale_end_date'] = $milkLitersSold !== null ? $milkSaleEndDate?->toDateString() : null;
        }

        $createdTransaction = FinancialTransaction::create($transactionData);

        $this->maybeMarkAnimalSold($farm, $data, $createdTransaction, (float) $amount);

        return back()
            ->with('success', 'Movimiento financiero registrado correctamente.')
            ->with('finance_cache_refresh', true);
    }

    public function destroy(FinancialTransaction $transaction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $transaction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $transaction->delete();

        return back()
            ->with('success', 'Movimiento eliminado correctamente.')
            ->with('finance_cache_refresh', true);
    }

    public function update(Request $request, FinancialTransaction $transaction)
    {
        $farm = auth()->user()->currentFarm();

        if (! $farm || (int) $transaction->farm_id !== (int) $farm->id) {
            abort(403);
        }

        $data = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'productive_movement' => ['nullable', 'in:milk_sale'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'milk_liters_sold' => ['nullable', 'numeric', 'min:0.01'],
            'milk_price_per_liter' => ['nullable', 'numeric', 'min:0.01'],
            'milk_sale_start_date' => ['nullable', 'date'],
            'milk_sale_end_date' => ['nullable', 'date'],
            'transaction_date' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'animal_id' => ['nullable', 'integer'],
        ], [
            'type.required' => 'Debes seleccionar si es ingreso o gasto.',
            'title.required' => 'Debes escribir el nombre del movimiento.',
            'transaction_date.required' => 'Debes seleccionar la fecha.',
        ]);

        $milkLitersSold = isset($data['milk_liters_sold']) && $data['milk_liters_sold'] !== ''
            ? (float) $data['milk_liters_sold']
            : null;

        if (($data['productive_movement'] ?? null) !== 'milk_sale') {
            $milkLitersSold = null;
        }

        if (($data['productive_movement'] ?? null) === 'milk_sale'
            && $data['type'] === FinancialTransaction::TYPE_INCOME
            && $milkLitersSold === null) {
            return back()
                ->withErrors(['milk_liters_sold' => 'Registra los litros vendidos de leche.'])
                ->withInput();
        }

        $milkPricePerLiter = isset($data['milk_price_per_liter']) && $data['milk_price_per_liter'] !== ''
            ? (float) $data['milk_price_per_liter']
            : null;

        if ($milkLitersSold !== null && $data['type'] !== FinancialTransaction::TYPE_INCOME) {
            return back()
                ->withErrors(['milk_liters_sold' => 'Los litros vendidos de leche deben registrarse como ingreso.'])
                ->withInput();
        }

        if ($milkLitersSold !== null && $milkPricePerLiter === null) {
            return back()
                ->withErrors(['milk_price_per_liter' => 'Registra el valor actual por litro de leche.'])
                ->withInput();
        }

        $amount = isset($data['amount']) && $data['amount'] !== ''
            ? (float) $data['amount']
            : null;

        [$milkSaleStartDate, $milkSaleEndDate] = $this->resolveMilkSaleRange($data);

        if ($milkLitersSold !== null) {
            if (! $milkSaleStartDate || ! $milkSaleEndDate) {
                return back()
                    ->withErrors(['milk_sale_start_date' => 'Selecciona el rango de fechas de la leche vendida.'])
                    ->withInput();
            }

            $availableMilkLiters = $this->availableMilkLitersForRange($farm->id, $milkSaleStartDate, $milkSaleEndDate, $transaction->id);

            if ($availableMilkLiters <= 0) {
                return back()
                    ->withErrors(['milk_liters_sold' => 'No hay litros disponibles para vender en ese rango. Registra producción de leche antes de guardar la venta.'])
                    ->withInput();
            }

            if ($unavailableDate = $this->firstUnavailableMilkDateInRange($farm->id, $milkSaleStartDate, $milkSaleEndDate, $transaction->id)) {
                return back()
                    ->withErrors(['milk_sale_start_date' => 'El rango incluye un día sin litros disponibles: ' . $unavailableDate->format('d/m/Y') . '.'])
                    ->withInput();
            }

            if ($milkLitersSold > $availableMilkLiters) {
                return back()
                    ->withErrors([
                        'milk_liters_sold' => 'No hay suficientes litros disponibles. Disponible: ' . $this->formatLiters($availableMilkLiters) . ' L.',
                    ])
                    ->withInput();
            }

            $amount = round($milkLitersSold * $milkPricePerLiter, 2);
        }

        if ($amount === null || $amount <= 0) {
            return back()
                ->withErrors(['amount' => 'Debes ingresar el valor del movimiento.'])
                ->withInput();
        }

        $transactionData = [
            'type' => $data['type'],
            'title' => $data['title'],
            'amount' => $amount,
            'milk_liters_sold' => $milkLitersSold,
            'transaction_date' => $data['transaction_date'],
            'category' => $milkLitersSold !== null && empty($data['category']) ? 'Venta de leche' : ($data['category'] ?? null),
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if (Schema::hasColumn('financial_transactions', 'milk_price_per_liter')) {
            $transactionData['milk_price_per_liter'] = $milkLitersSold !== null ? $milkPricePerLiter : null;
        }

        if (Schema::hasColumn('financial_transactions', 'milk_sale_start_date')) {
            $transactionData['milk_sale_start_date'] = $milkLitersSold !== null ? $milkSaleStartDate?->toDateString() : null;
        }

        if (Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
            $transactionData['milk_sale_end_date'] = $milkLitersSold !== null ? $milkSaleEndDate?->toDateString() : null;
        }

        $transaction->update($transactionData);

        $this->maybeMarkAnimalSold($farm, $data, $transaction, (float) $amount);

        return back()
            ->with('success', 'Movimiento actualizado correctamente.')
            ->with('finance_cache_refresh', true);
    }

    protected function resolveDateRange(Request $request, string $range): array
    {
        $today = now();

        if ($range === 'custom') {
            $startValue = $request->start_date ?: $request->end_date;
            $endValue = $request->end_date ?: $request->start_date;

            $start = $startValue
                ? Carbon::parse($startValue)->startOfDay()
                : $today->copy()->startOfMonth();

            $end = $endValue
                ? Carbon::parse($endValue)->endOfDay()
                : $today->copy()->endOfMonth();

            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            return [$start, $end, $start->format('d/m/Y') . ' al ' . $end->format('d/m/Y')];
        }

        $dayOfMonth = (int) $today->format('d');
        $isFirstFortnight = $dayOfMonth >= 16;

        if ($isFirstFortnight) {
            // Consulta entre el 16 y fin de mes: mostrar la 1a quincena cerrada (1 al 15) del mes actual
            $fortnightStart = $today->copy()->startOfMonth()->startOfDay();
            $fortnightEnd = $today->copy()->day(15)->endOfDay();
        } else {
            // Consulta entre el 1 y el 15: mostrar la 2a quincena (16 a fin) del mes anterior
            $prevMonthEnd = $today->copy()->startOfMonth()->subDay();
            $fortnightStart = $prevMonthEnd->copy()->day(16)->startOfDay();
            $fortnightEnd = $prevMonthEnd->copy()->endOfDay();
        }

        return match ($range) {
            'today' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
                'Hoy',
            ],
            'week' => [
                $today->copy()->startOfWeek(),
                $today->copy()->endOfWeek(),
                'Esta semana',
            ],
            'fortnight' => [
                $fortnightStart,
                $fortnightEnd,
                ($isFirstFortnight ? 'Primera' : 'Segunda') . ' quincena · ' . $fortnightStart->format('d/m') . ' al ' . $fortnightEnd->format('d/m'),
            ],
            'year' => [
                $today->copy()->startOfYear()->startOfDay(),
                $today->copy()->endOfYear()->endOfDay(),
                'Este año',
            ],
            default => [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfMonth()->endOfDay(),
                'Este mes',
            ],
        };
    }

    protected function buildOwnerFarmBreakdown(Carbon $startDate, Carbon $endDate): array
    {
        $user = auth()->user();
        $viewingClient = ($user->canAccessAdminPanel() && session('admin_view_client_id'))
            ? \App\Models\User::find(session('admin_view_client_id'))
            : null;
        $owner = $viewingClient ?: $user;

        $ownerFarms = $owner
            ? $owner->farms()->orderBy('farms.name')->get(['farms.id', 'farms.name'])
            : collect();

        if ($ownerFarms->isEmpty() || ! Schema::hasTable('financial_transactions')) {
            return [collect(), ['income' => 0.0, 'expense' => 0.0, 'utility' => 0.0]];
        }

        $rows = FinancialTransaction::query()
            ->whereIn('farm_id', $ownerFarms->pluck('id')->all())
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('farm_id, type, SUM(amount) as total')
            ->groupBy('farm_id', 'type')
            ->get();

        $farmBreakdown = $ownerFarms->map(function ($f) use ($rows) {
            $income = (float) $rows->where('farm_id', $f->id)->where('type', FinancialTransaction::TYPE_INCOME)->sum('total');
            $expense = (float) $rows->where('farm_id', $f->id)->where('type', FinancialTransaction::TYPE_EXPENSE)->sum('total');

            return [
                'id' => $f->id,
                'name' => $f->name,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'utility' => round($income - $expense, 2),
            ];
        })->values();

        $consolidated = [
            'income' => round($farmBreakdown->sum('income'), 2),
            'expense' => round($farmBreakdown->sum('expense'), 2),
            'utility' => round($farmBreakdown->sum('utility'), 2),
        ];

        return [$farmBreakdown, $consolidated];
    }

    protected function maybeMarkAnimalSold($farm, array $data, ?FinancialTransaction $transaction, float $amount): void
    {
        $animalId = $data['animal_id'] ?? null;

        if (! $animalId || ($data['type'] ?? null) !== FinancialTransaction::TYPE_INCOME) {
            return;
        }

        $animal = \App\Models\Animal::where('farm_id', $farm->id)->find($animalId);

        if (! $animal || $animal->isSold() || $animal->isDeceased()) {
            return;
        }

        $label = $animal->name ?: ('Animal #' . $animal->id);
        if ($animal->ear_tag) {
            $label .= ' (' . $animal->ear_tag . ')';
        }

        $amountText = number_format($amount, 0, ',', '.');
        $note = 'Vendido el ' . \Carbon\Carbon::parse($data['transaction_date'])->format('d/m/Y')
            . ' - ' . ($data['title'] ?? 'Venta') . ' (' . '$' . $amountText . ')';

        $animal->update([
            'status' => \App\Models\Animal::STATUS_SOLD,
            'status_date' => $data['transaction_date'],
            'status_notes' => trim(($animal->status_notes ? $animal->status_notes . "\n" : '') . $note),
        ]);

        if ($transaction && empty($transaction->reference)) {
            $transaction->update(['reference' => 'Animal: ' . $label]);
        }
    }

    protected function financeIndexResponse(array $data)
    {
        return response()
            ->view('finances.index', $data)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    protected function buildChartData($baseQuery, Carbon $startDate, Carbon $endDate)
    {
        $rows = (clone $baseQuery)
            ->selectRaw('transaction_date, type, SUM(amount) as total')
            ->groupBy('transaction_date', 'type')
            ->orderBy('transaction_date')
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->transaction_date)->format('Y-m-d'));

        return collect(range(0, (int) $startDate->diffInDays($endDate)))
            ->map(function ($offset) use ($startDate, $rows) {
                $date = $startDate->copy()->addDays($offset)->format('Y-m-d');
                $dayRows = $rows->get($date, collect());

                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->translatedFormat('d M'),
                    'income' => round((float) optional($dayRows->firstWhere('type', FinancialTransaction::TYPE_INCOME))->total, 2),
                    'expense' => round((float) optional($dayRows->firstWhere('type', FinancialTransaction::TYPE_EXPENSE))->total, 2),
                ];
            })
            ->values();
    }

    protected function availableMilkLiters(int $farmId, Carbon $untilDate, ?int $ignoreTransactionId = null): float
    {
        $produced = $this->officialMilkProducedUntil($farmId, $untilDate);
        $used = $this->milkUsedUntil($farmId, $untilDate);
        $sold = $this->milkSoldUntil($farmId, $untilDate, $ignoreTransactionId);

        return round(max(0, $produced - $used - $sold), 2);
    }

    protected function availableMilkLitersForRange(int $farmId, Carbon $startDate, Carbon $endDate, ?int $ignoreTransactionId = null): float
    {
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy(), $startDate->copy()];
        }

        $produced = $this->officialMilkProducedForRange($farmId, $startDate, $endDate);
        $used = $this->milkUsedForRange($farmId, $startDate, $endDate);
        $sold = $this->milkSoldForRange($farmId, $startDate, $endDate, $ignoreTransactionId);

        return round(max(0, $produced - $used - $sold), 2);
    }

    protected function resolveMilkSaleRange(array $data): array
    {
        $startValue = $data['milk_sale_start_date'] ?? null;
        $endValue = $data['milk_sale_end_date'] ?? null;

        if (! $startValue && ! $endValue) {
            return [null, null];
        }

        $startValue = $startValue ?: $endValue;
        $endValue = $endValue ?: $startValue;

        $startDate = Carbon::parse($startValue)->startOfDay();
        $endDate = Carbon::parse($endValue)->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    protected function firstUnavailableMilkDateInRange(int $farmId, Carbon $startDate, Carbon $endDate, ?int $ignoreTransactionId = null): ?Carbon
    {
        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy(), $startDate->copy()];
        }

        foreach (range(0, (int) $startDate->diffInDays($endDate)) as $offset) {
            $date = $startDate->copy()->addDays($offset);

            if ($this->availableMilkLitersForRange($farmId, $date, $date, $ignoreTransactionId) <= 0) {
                return $date;
            }
        }

        return null;
    }

    protected function officialMilkProducedForRange(int $farmId, Carbon $startDate, Carbon $endDate): float
    {
        if (! Schema::hasTable('milk_productions')) {
            return 0;
        }

        $animalProductions = MilkProduction::query()
            ->where('farm_id', $farmId)
            ->whereBetween('production_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        if (! Schema::hasTable('daily_milk_productions')) {
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

    protected function milkUsedUntil(int $farmId, Carbon $untilDate): float
    {
        if (! Schema::hasTable('milk_usages')) {
            return 0;
        }

        return round((float) MilkUsage::query()
            ->where('farm_id', $farmId)
            ->whereDate('usage_date', '<=', $untilDate->toDateString())
            ->get()
            ->sum(fn (MilkUsage $usage) => (float) $usage->calf_liters + (float) $usage->consumed_liters), 2);
    }

    protected function milkUsedForRange(int $farmId, Carbon $startDate, Carbon $endDate): float
    {
        if (! Schema::hasTable('milk_usages')) {
            return 0;
        }

        return round((float) MilkUsage::query()
            ->where('farm_id', $farmId)
            ->whereBetween('usage_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->sum(fn (MilkUsage $usage) => (float) $usage->calf_liters + (float) $usage->consumed_liters), 2);
    }

    protected function milkSoldUntil(int $farmId, Carbon $untilDate, ?int $ignoreTransactionId = null): float
    {
        if (! Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
            return 0;
        }

        $query = FinancialTransaction::query()
            ->where('farm_id', $farmId)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->where('milk_liters_sold', '>', 0)
            ->when($ignoreTransactionId, fn ($query) => $query->where('id', '!=', $ignoreTransactionId));

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

    protected function milkSoldForRange(int $farmId, Carbon $startDate, Carbon $endDate, ?int $ignoreTransactionId = null): float
    {
        if (! Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
            return 0;
        }

        $query = FinancialTransaction::query()
            ->where('farm_id', $farmId)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->where('milk_liters_sold', '>', 0)
            ->when($ignoreTransactionId, fn ($query) => $query->where('id', '!=', $ignoreTransactionId));

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

        return round((float) $query->sum('milk_liters_sold'), 2);
    }

    protected function milkAvailabilityByDate(int $farmId)
    {
        if (! Schema::hasTable('milk_productions')) {
            return collect();
        }

        $animalProductions = MilkProduction::query()
            ->where('farm_id', $farmId)
            ->get()
            ->groupBy(fn (MilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'));

        $dailyTotalsByDate = Schema::hasTable('daily_milk_productions')
            ? DailyMilkProduction::where('farm_id', $farmId)
                ->get()
                ->keyBy(fn (DailyMilkProduction $row) => Carbon::parse($row->production_date)->format('Y-m-d'))
            : collect();

        $usagesByDate = Schema::hasTable('milk_usages')
            ? MilkUsage::where('farm_id', $farmId)
                ->get()
                ->keyBy(fn (MilkUsage $usage) => $usage->usage_date->format('Y-m-d'))
            : collect();

        $dates = $dailyTotalsByDate->keys()
            ->merge($animalProductions->keys())
            ->merge($usagesByDate->keys())
            ->unique()
            ->sort()
            ->values();

        $rows = $dates->mapWithKeys(function (string $date) use ($dailyTotalsByDate, $animalProductions, $usagesByDate) {
            $dailyTotal = $dailyTotalsByDate->get($date);
            $produced = $dailyTotal
                ? round((float) $dailyTotal->liters, 2)
                : round((float) $animalProductions->get($date, collect())->sum('liters'), 2);
            $usage = $usagesByDate->get($date);
            $used = round((float) ($usage?->calf_liters ?? 0) + (float) ($usage?->consumed_liters ?? 0), 2);

            return [
                $date => [
                    'date' => $date,
                    'produced' => $produced,
                    'used' => min($produced, $used),
                    'sold' => 0,
                    'available' => round(max(0, $produced - $used), 2),
                ],
            ];
        });

        $this->milkSaleRowsForAvailability($farmId)->each(function (FinancialTransaction $sale) use (&$rows) {
            $startDate = $sale->milk_sale_start_date ?: $sale->transaction_date;
            $endDate = $sale->milk_sale_end_date ?: $sale->transaction_date;

            if (! $startDate || ! $endDate) {
                return;
            }

            $period = collect(range(0, (int) $startDate->diffInDays($endDate)))
                ->map(fn (int $offset) => $startDate->copy()->addDays($offset)->format('Y-m-d'));

            $readyTotal = round((float) $period->sum(fn (string $date) => (float) ($rows->get($date)['available'] ?? 0)), 2);
            $saleLiters = round((float) $sale->milk_liters_sold, 2);

            $period->each(function (string $date) use (&$rows, $readyTotal, $saleLiters, $period) {
                if (! $rows->has($date)) {
                    $rows[$date] = [
                        'date' => $date,
                        'produced' => 0,
                        'used' => 0,
                        'sold' => 0,
                        'available' => 0,
                    ];
                }

                $row = $rows->get($date);
                $share = $readyTotal > 0
                    ? ($saleLiters * ((float) $row['available'] / $readyTotal))
                    : ($saleLiters / max(1, $period->count()));
                $soldForDay = min((float) $row['available'], round($share, 2));

                $row['sold'] = round((float) $row['sold'] + $soldForDay, 2);
                $row['available'] = round(max(0, (float) $row['available'] - $soldForDay), 2);
                $rows[$date] = $row;
            });
        });

        return $rows->sortKeys()->values();
    }

    protected function milkSaleRowsForAvailability(int $farmId)
    {
        if (! Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
            return collect();
        }

        return FinancialTransaction::query()
            ->where('farm_id', $farmId)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->where('milk_liters_sold', '>', 0)
            ->orderBy('transaction_date')
            ->get();
    }

    protected function formatLiters(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

}
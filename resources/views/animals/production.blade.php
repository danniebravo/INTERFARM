@extends('layouts.app')

@section('title', 'Producción · ' . ($animal->name ?: 'Animal'))

@section('content')

@php
    use Carbon\Carbon;

    $animalName = $animal->name ?: 'Animal sin nombre';
    $isFemaleAnimal = $animal->isFemale();
    $canRegisterProduction = $animal->canRegisterProduction();
    $canRegisterMilkProduction = $animal->canRegisterMilkProduction();
    $productionRecords = collect($productionRecords ?? [])->sortByDesc('date')->values();
    $milkProductionRecords = $productionRecords->filter(fn ($record) => ($record['type'] ?? 'Leche') === 'Leche')->values();
    $meatProductionRecords = $productionRecords->filter(fn ($record) => ($record['type'] ?? null) === 'Carne')->values();

    $totalLiters = round($milkProductionRecords->sum(fn ($record) => (float) ($record['liters'] ?? 0)), 1);
    $latestWeight = $meatProductionRecords->whereNotNull('weight')->sortByDesc('date')->first();
    $totalWeightGain = round($meatProductionRecords->sum(fn ($record) => (float) ($record['weight_gain'] ?? 0)), 1);

    $averageLiters = $milkProductionRecords->whereNotNull('liters')->count() > 0
        ? round($milkProductionRecords->whereNotNull('liters')->avg('liters'), 1)
        : null;

    $bestLiters = $milkProductionRecords->whereNotNull('liters')->count() > 0
        ? round($milkProductionRecords->whereNotNull('liters')->max('liters'), 1)
        : null;

    $dailyMilkSummaries = $milkProductionRecords
        ->groupBy(fn ($record) => $record['date'] ?? 'sin-fecha')
        ->map(function ($items, $date) {
            $morning = (float) $items
                ->filter(fn ($record) => mb_strtolower((string) ($record['period'] ?? '')) === 'mañana')
                ->sum(fn ($record) => (float) ($record['liters'] ?? 0));
            $afternoon = (float) $items
                ->filter(fn ($record) => mb_strtolower((string) ($record['period'] ?? '')) === 'tarde')
                ->sum(fn ($record) => (float) ($record['liters'] ?? 0));

            return [
                'date' => $date,
                'morning_liters' => round($morning, 2),
                'afternoon_liters' => round($afternoon, 2),
                'total_liters' => round((float) $items->sum(fn ($record) => (float) ($record['liters'] ?? 0)), 2),
                'records_count' => $items->count(),
            ];
        })
        ->sortByDesc('date')
        ->values();
    $dailyMilkSummaryByDate = $dailyMilkSummaries->keyBy('date');
@endphp

<style>
    .production-summary-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255,255,255,.62);
        border-radius: 22px;
        padding: 18px;
    }

    .production-history-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255,255,255,.66);
        border-radius: 24px;
        padding: 18px;
        transition: .18s ease;
    }

    .production-history-card:hover {
        transform: translateY(-1px);
        background: rgba(255,255,255,.78);
    }

    .production-edit-panel {
        margin-top: 16px;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255,255,255,.58);
        border-radius: 18px;
        padding: 16px;
    }

    .production-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .production-pill.morning {
        background: rgba(250, 204, 21, .14);
        color: #a16207;
    }

    .production-pill.afternoon {
        background: rgba(59, 130, 246, .12);
        color: #1d4ed8;
    }

    .production-pill.neutral {
        background: rgba(17, 24, 39, .07);
        color: #374151;
    }

    .production-stat-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }

    @media (max-width: 768px) {
        .production-stat-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">
                Producción · {{ $animalName }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Historial completo de registros productivos del animal.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('animals.show', $animal) }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver al perfil
            </a>

            <a href="{{ route('animals.edit', $animal) }}"
               class="rounded-xl bg-brand/10 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand/15 transition">
                Editar animal
            </a>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="production-stat-grid">
            <div class="production-summary-card">
                <div class="text-sm text-gray-500">Total de registros</div>
                <div class="text-3xl font-extrabold text-gray-900 mt-2">
                    {{ $productionRecords->count() }}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Producciones guardadas
                </div>
            </div>

            <div class="production-summary-card">
                <div class="text-sm text-gray-500">Litros acumulados</div>
                <div class="text-3xl font-extrabold text-gray-900 mt-2">
                    {{ $totalLiters > 0 ? $totalLiters . ' L' : '—' }}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    Total registrado en leche
                </div>
            </div>

            <div class="production-summary-card">
                <div class="text-sm text-gray-500">Último peso / mejor leche</div>
                <div class="text-3xl font-extrabold text-gray-900 mt-2">
                    @if($latestWeight && !empty($latestWeight['weight']))
                        {{ $latestWeight['weight'] }} kg
                    @elseif($bestLiters !== null)
                        {{ $bestLiters }} L
                    @else
                        —
                    @endif
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    {{ $totalWeightGain > 0 ? 'Ganancia acumulada: '.$totalWeightGain.' kg' : 'Indicador productivo individual' }}
                </div>
            </div>
        </div>
    </div>

    @if($dailyMilkSummaries->count())
        <div class="glass rounded-[28px] p-6">
            <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900">Suma diaria de leche</h2>
                    <p class="text-sm text-gray-500 mt-1">Total por día, igual que en el módulo general de producción.</p>
                </div>

                <span class="production-pill morning">{{ $dailyMilkSummaries->count() }} día(s)</span>
            </div>

            <div class="space-y-3">
                @foreach($dailyMilkSummaries->take(12) as $summary)
                    <div class="production-history-card">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-lg font-extrabold text-gray-900">
                                    {{ $summary['date'] !== 'sin-fecha' ? Carbon::parse($summary['date'])->format('d/m/Y') : 'Sin fecha' }}
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    Mañana {{ number_format($summary['morning_liters'], 2, ',', '.') }} L ·
                                    Tarde {{ number_format($summary['afternoon_liters'], 2, ',', '.') }} L
                                </div>
                            </div>

                            <div class="text-left md:text-right">
                                <div class="text-2xl font-extrabold text-green-700">
                                    {{ number_format($summary['total_liters'], 2, ',', '.') }} L
                                </div>
                                <div class="text-xs font-semibold text-gray-500">{{ $summary['records_count'] }} reg.</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Historial de producción</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Aquí ves fecha, período, litros, peso, alimentación y observaciones.
                </p>
            </div>

            @if($isFemaleAnimal && $averageLiters !== null)
                <div class="inline-flex items-center gap-2 rounded-full border border-black/10 bg-white/70 px-3 py-1.5 text-xs font-semibold text-gray-600">
                    Promedio: {{ $averageLiters }} L
                </div>
            @endif
        </div>

        <div class="space-y-4">
            @forelse($productionRecords as $record)
                @php
                    $period = $record['period'] ?? null;
                    $isMilkRecord = ($record['type'] ?? null) === 'Leche';
                    $recordDailySummary = $isMilkRecord
                        ? $dailyMilkSummaryByDate->get($record['date'] ?? 'sin-fecha')
                        : null;
                    $periodLabel = ($record['type'] ?? null) === 'Carne'
                        ? 'Registro de carne'
                        : ($period ? ucfirst($period) : 'Sin período');
                    $periodClass = match($period) {
                        'mañana' => 'morning',
                        'tarde' => 'afternoon',
                        default => 'neutral',
                    };

                    $recordDate = !empty($record['date'])
                        ? Carbon::parse($record['date'])->format('d/m/Y')
                        : '—';
                @endphp

                <div class="production-history-card">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-3 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="text-lg font-extrabold text-gray-900">
                                    {{ $recordDate }}
                                </div>

                                <span class="production-pill neutral">
                                    {{ $record['type'] ?? 'Producción' }}
                                </span>

                                <span class="production-pill {{ $periodClass }}">
                                    {{ $periodLabel }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-sm">
                                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-3">
                                    <div class="text-gray-500">Litros</div>
                                    <div class="font-bold text-gray-900 mt-1">
                                        {{ isset($record['liters']) && $record['liters'] !== null && $record['liters'] !== '' ? $record['liters'] . ' L' : '—' }}
                                    </div>
                                </div>

                                @if($recordDailySummary)
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 px-4 py-3">
                                        <div class="text-emerald-700">Total del día</div>
                                        <div class="font-extrabold text-emerald-800 mt-1">
                                            {{ number_format($recordDailySummary['total_liters'], 2, ',', '.') }} L
                                        </div>
                                        <div class="mt-1 text-xs font-semibold text-emerald-700/80">
                                            Mañana {{ number_format($recordDailySummary['morning_liters'], 2, ',', '.') }} L ·
                                            Tarde {{ number_format($recordDailySummary['afternoon_liters'], 2, ',', '.') }} L
                                        </div>
                                    </div>
                                @endif

                                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-3">
                                    <div class="text-gray-500">Peso</div>
                                    <div class="font-bold text-gray-900 mt-1">
                                        {{ isset($record['weight']) && $record['weight'] !== null && $record['weight'] !== '' ? $record['weight'] . ' kg' : '—' }}
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-3 md:col-span-2 xl:col-span-2">
                                    <div class="text-gray-500">Ganancia / alimentación</div>
                                    <div class="font-bold text-gray-900 mt-1">
                                        @if(isset($record['weight_gain']) && $record['weight_gain'] !== null && $record['weight_gain'] !== '')
                                            {{ $record['weight_gain'] }} kg
                                        @else
                                            {{ $record['feeding_type'] ?? '—' }}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-black/10 bg-white/45 px-4 py-4">
                                <div class="text-sm font-semibold text-gray-700 mb-2">Observaciones</div>
                                <div class="text-sm text-gray-600">
                                    {{ $record['notes'] ?? 'Sin observaciones.' }}
                                </div>
                            </div>
                        </div>

                        @if(!empty($record['created_at']))
                            <div class="text-xs font-semibold text-gray-400 whitespace-nowrap">
                                Guardado: {{ Carbon::parse($record['created_at'])->format('d/m/Y H:i') }}
                            </div>
                        @endif
                    </div>

                    @if(($record['source'] ?? null) === 'milk' && !empty($record['id']) && $canRegisterMilkProduction)
                        <details class="production-edit-panel">
                            <summary class="cursor-pointer text-sm font-extrabold text-brand">Editar este registro</summary>

                            <form method="POST" action="{{ route('production.milk.update', $record['id']) }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="animal_id" value="{{ $animal->id }}">

                                <div>
                                    <label class="text-xs font-bold text-gray-500">Fecha</label>
                                    <input type="date" name="production_date" value="{{ $record['date'] ?? now()->format('Y-m-d') }}" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-gray-500">Periodo</label>
                                    <select name="period" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">
                                        <option value="mañana" @selected(($record['period'] ?? '') === 'mañana')>Mañana</option>
                                        <option value="tarde" @selected(($record['period'] ?? '') === 'tarde')>Tarde</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-gray-500">Litros</label>
                                    <input type="number" step="0.01" min="0.01" name="liters" value="{{ $record['liters'] ?? '' }}" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-gray-500">Observaciones</label>
                                    <textarea name="notes" rows="2" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">{{ $record['notes'] ?? '' }}</textarea>
                                </div>

                                <div class="md:col-span-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark transition">
                                        Guardar cambios
                                    </button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('production.milk.destroy', $record['id']) }}" class="mt-3" onsubmit="return confirm('¿Eliminar este registro de leche?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                                    Eliminar registro
                                </button>
                            </form>
                        </details>
                    @elseif(($record['source'] ?? null) === 'meat' && !empty($record['id']) && $canRegisterProduction)
                        <details class="production-edit-panel">
                            <summary class="cursor-pointer text-sm font-extrabold text-brand">Editar este registro</summary>

                            <form method="POST" action="{{ route('production.meat.update', $record['id']) }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="animal_id" value="{{ $animal->id }}">

                                <div>
                                    <label class="text-xs font-bold text-gray-500">Fecha</label>
                                    <input type="date" name="production_date" value="{{ $record['date'] ?? now()->format('Y-m-d') }}" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-gray-500">Peso actual (kg)</label>
                                    <input type="number" step="0.01" min="0" name="weight_kg" value="{{ $record['weight'] ?? '' }}" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-gray-500">Ganancia de peso (kg)</label>
                                    <input type="number" step="0.01" min="0" name="weight_gain_kg" value="{{ $record['weight_gain'] ?? '' }}" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">
                                </div>

                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-gray-500">Observaciones</label>
                                    <textarea name="notes" rows="2" class="w-full mt-1 rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm">{{ $record['notes'] ?? '' }}</textarea>
                                </div>

                                <div class="md:col-span-2 flex flex-col gap-2 sm:flex-row sm:items-center">
                                    <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark transition">
                                        Guardar cambios
                                    </button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('production.meat.destroy', $record['id']) }}" class="mt-3" onsubmit="return confirm('¿Eliminar este registro de carne?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                                    Eliminar registro
                                </button>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 px-6 py-12 text-center">
                    <div class="text-lg font-extrabold text-gray-900">Aún no hay registros de producción</div>
                    <p class="text-sm text-gray-500 mt-2">
                        Cuando registres producción desde el perfil del animal, aparecerá aquí el historial completo.
                    </p>
                    <a href="{{ route('animals.show', $animal) }}"
                       class="mt-5 inline-flex rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                        Ir al perfil del animal
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection

@extends('layouts.app')

@section('title', 'Gastos e ingresos')

@section('content')

@php
    $chartJson = collect($chartData ?? [])->toJson();
    $milkAvailabilityJson = collect($milkAvailabilityByDate ?? [])->values()->toJson();
    $typeLabels = [
        'income' => 'Ingreso',
        'expense' => 'Gasto',
    ];
    $typeClasses = [
        'income' => 'income',
        'expense' => 'expense',
    ];
    $formatLiters = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
@endphp

<style>
    .finance-card {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.64);
        box-shadow: 0 12px 30px rgba(0,0,0,.05);
        padding: 22px;
    }

    .finance-card::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        pointer-events: none;
    }

    .finance-kpi-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 16px;
    }

    @media (min-width: 768px) {
        .finance-kpi-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .finance-kpi-label {
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
    }

    .finance-kpi-value {
        margin-top: 10px;
        font-size: 30px;
        line-height: 1;
        font-weight: 900;
        color: #111827;
    }

    .finance-form-input,
    .finance-form-select,
    .finance-form-textarea {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,.10);
        background: linear-gradient(135deg, rgba(255,255,255,.92), rgba(255,255,255,.72));
        padding: 12px 16px;
        outline: none;
        transition: .18s ease;
        color: #111827;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72), 0 8px 18px rgba(15,23,42,.04);
    }

    .finance-form-textarea {
        resize: vertical;
    }

    .finance-form-input:focus,
    .finance-form-select:focus,
    .finance-form-textarea:focus {
        border-color: rgba(22,101,52,.25);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .finance-select-shell {
        position: relative;
    }

    .finance-select-shell::after {
        content: "";
        position: absolute;
        top: 50%;
        right: 16px;
        width: 9px;
        height: 9px;
        border-right: 2px solid rgba(22,101,52,.76);
        border-bottom: 2px solid rgba(22,101,52,.76);
        transform: translateY(-66%) rotate(45deg);
        pointer-events: none;
    }

    .finance-form-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 44px;
        cursor: pointer;
    }

    .finance-milk-range-card {
        border-radius: 20px;
        border: 1px solid rgba(22,101,52,.14);
        background: linear-gradient(135deg, rgba(240,253,244,.92), rgba(255,255,255,.78));
        padding: 14px;
    }

    .finance-milk-range-summary {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 10px;
    }

    @media (min-width: 640px) {
        .finance-milk-range-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .finance-milk-range-metric {
        border-radius: 16px;
        background: rgba(255,255,255,.7);
        border: 1px solid rgba(22,101,52,.08);
        padding: 10px 12px;
    }

    .finance-milk-range-metric span {
        display: block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .finance-milk-range-metric strong {
        display: block;
        margin-top: 4px;
        font-size: 18px;
        line-height: 1;
        font-weight: 900;
        color: #166534;
    }

    .finance-range-calendar {
        border-radius: 18px;
        border: 1px solid rgba(22,101,52,.12);
        background: rgba(255,255,255,.76);
        padding: 12px;
    }

    .finance-range-calendar-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .finance-range-calendar-title {
        font-size: 13px;
        font-weight: 900;
        color: #14532d;
        text-transform: capitalize;
    }

    .finance-range-calendar-nav {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 12px;
        border: 1px solid rgba(22,101,52,.14);
        background: rgba(240,253,244,.9);
        color: #166534;
        font-weight: 900;
        transition: .18s ease;
    }

    .finance-range-calendar-nav:hover {
        background: rgba(220,252,231,.95);
    }

    .finance-range-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 5px;
    }

    .finance-range-calendar-weekday {
        padding: 4px 0;
        text-align: center;
        font-size: 10px;
        font-weight: 900;
        color: #6b7280;
        text-transform: uppercase;
    }

    .finance-range-calendar-day {
        position: relative;
        min-height: 38px;
        border-radius: 12px;
        border: 1px solid rgba(22,101,52,.08);
        background: rgba(255,255,255,.86);
        color: #1f2937;
        font-size: 12px;
        font-weight: 900;
        transition: .16s ease;
        touch-action: manipulation;
        user-select: none;
        -webkit-user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    .finance-range-calendar-day:hover {
        border-color: rgba(22,101,52,.28);
        background: rgba(240,253,244,.95);
    }

    .finance-range-calendar-day.is-muted {
        opacity: .38;
    }

    .finance-range-calendar-day.is-disabled {
        cursor: not-allowed;
        border-color: rgba(156,163,175,.2);
        background: rgba(229,231,235,.72);
        color: #9ca3af;
        text-decoration: line-through;
    }

    .finance-range-calendar-day.is-in-range {
        border-color: rgba(22,101,52,.18);
        background: rgba(187,247,208,.7);
        color: #14532d;
    }

    .finance-range-calendar-day.is-start,
    .finance-range-calendar-day.is-end {
        border-color: rgba(22,101,52,.42);
        background: linear-gradient(135deg, #166534, #15803d);
        color: #fff;
        box-shadow: 0 8px 18px rgba(22,101,52,.2);
    }

    .finance-range-calendar-day.is-today:not(.is-start):not(.is-end) {
        box-shadow: inset 0 0 0 2px rgba(22,101,52,.22);
    }

    .finance-range-calendar-status {
        margin-top: 8px;
        min-height: 18px;
        font-size: 11px;
        font-weight: 800;
        color: #4b5563;
    }

    .finance-range-calendar-readable {
        margin-top: 10px;
        border-radius: 14px;
        border: 1px solid rgba(22,101,52,.12);
        background: rgba(240,253,244,.82);
        padding: 10px 12px;
        font-size: 12px;
        font-weight: 900;
        color: #14532d;
    }

    .finance-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 900;
        transition: .18s ease;
    }

    .finance-btn-primary {
        background: linear-gradient(135deg, #166534, #14532d);
        color: #fff;
        box-shadow: 0 10px 24px rgba(22,101,52,.22);
    }

    .finance-btn-secondary {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.78);
        color: #374151;
    }

    .finance-chart-wrap {
        height: 320px;
        position: relative;
    }

    .finance-chart-canvas {
        width: 100%;
        height: 100%;
    }

    .finance-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 16px;
        color: #4b5563;
        font-size: 12px;
        font-weight: 800;
    }

    .finance-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .finance-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
    }

    .finance-table-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        border-radius: 22px;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.42);
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .finance-table-wrap::-webkit-scrollbar {
        height: 8px;
    }

    .finance-table-wrap::-webkit-scrollbar-thumb {
        background: rgba(22,101,52,.24);
        border-radius: 999px;
    }

    .finance-table {
        min-width: 980px;
        width: 100%;
    }

    .finance-table th {
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .finance-table td,
    .finance-table th {
        padding: 14px 16px;
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }

    .finance-table tbody tr {
        border-top: 1px solid rgba(0,0,0,.05);
    }

    .finance-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 12px;
        font-weight: 900;
    }

    .finance-pill.income {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .finance-pill.expense {
        background: rgba(239,68,68,.10);
        color: #dc2626;
    }

    .finance-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        border-radius: 12px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 900;
        line-height: 1;
        border: 1px solid transparent;
        transition: .18s ease;
        box-shadow: 0 8px 18px rgba(15,23,42,.05);
    }

    .finance-action-btn.edit {
        border-color: rgba(22,101,52,.18);
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .finance-action-btn.edit:hover {
        background: rgba(22,101,52,.16);
    }

    .finance-action-btn.delete {
        border-color: rgba(220,38,38,.18);
        background: rgba(220,38,38,.10);
        color: #dc2626;
    }

    .finance-action-btn.delete:hover {
        background: rgba(220,38,38,.16);
    }

    .finance-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 80;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 18px;
        background: rgba(15,23,42,.48);
        backdrop-filter: blur(8px);
    }

    .finance-modal-backdrop.is-open {
        display: flex;
    }

    .finance-modal-panel {
        width: min(760px, 100%);
        max-height: calc(100vh - 36px);
        overflow-y: auto;
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,.36);
        background: rgba(255,255,255,.96);
        box-shadow: 0 24px 80px rgba(15,23,42,.28);
        padding: 24px;
    }
</style>

<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if(! ($databaseReady ?? true))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            La sección de gastos e ingresos está creada, pero falta ejecutar la migración de base de datos.
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            Revisa los campos del formulario. Hay información pendiente o inválida.
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Gastos e ingresos</h1>
            <p class="text-sm text-gray-500 mt-1">Control básico del dinero que entra y sale de la finca.</p>
        </div>

        <div class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-bold text-gray-700">
            {{ $rangeLabel }}
        </div>
    </div>

    <div class="finance-kpi-grid">
        <div class="finance-card">
            <div class="finance-kpi-label">Ingresos</div>
            <div class="finance-kpi-value text-green-700">${{ number_format($incomeTotal, 0, ',', '.') }}</div>
        </div>

        <div class="finance-card">
            <div class="finance-kpi-label">Gastos</div>
            <div class="finance-kpi-value text-red-600">${{ number_format($expenseTotal, 0, ',', '.') }}</div>
        </div>

        <div class="finance-card">
            <div class="finance-kpi-label">Balance</div>
            <div class="finance-kpi-value {{ $balance >= 0 ? 'text-green-700' : 'text-red-600' }}">
                ${{ number_format($balance, 0, ',', '.') }}
            </div>
        </div>
    </div>

    @isset($farmBreakdown)
    @if(($ownerFarmCount ?? 0) >= 1)
    <div class="finance-card mb-6">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Consolidado del propietario</h2>
            <p class="text-sm text-gray-500 mt-1">Ingresos, gastos y utilidad por finca · {{ $rangeLabel }}.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-black/10">
                        <th class="py-2 pr-4 font-semibold">Finca</th>
                        <th class="py-2 px-4 font-semibold text-right">Ingresos</th>
                        <th class="py-2 px-4 font-semibold text-right">Gastos</th>
                        <th class="py-2 pl-4 font-semibold text-right">Utilidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($farmBreakdown as $row)
                    <tr class="border-b border-black/5 {{ ($row['id'] == ($farm->id ?? null)) ? 'bg-green-50/40' : '' }}">
                        <td class="py-2 pr-4 font-semibold text-gray-900">
                            {{ $row['name'] }}
                            @if($row['id'] == ($farm->id ?? null))<span class="text-[11px] font-bold text-green-700">(finca actual)</span>@endif
                        </td>
                        <td class="py-2 px-4 text-right text-green-700 font-semibold">${{ number_format($row['income'], 0, ',', '.') }}</td>
                        <td class="py-2 px-4 text-right text-red-600 font-semibold">${{ number_format($row['expense'], 0, ',', '.') }}</td>
                        <td class="py-2 pl-4 text-right font-extrabold {{ $row['utility'] >= 0 ? 'text-green-700' : 'text-red-600' }}">${{ number_format($row['utility'], 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-black/20">
                        <td class="py-2 pr-4 font-extrabold text-gray-900">Consolidado</td>
                        <td class="py-2 px-4 text-right font-extrabold text-green-700">${{ number_format($consolidatedTotals['income'], 0, ',', '.') }}</td>
                        <td class="py-2 px-4 text-right font-extrabold text-red-600">${{ number_format($consolidatedTotals['expense'], 0, ',', '.') }}</td>
                        <td class="py-2 pl-4 text-right font-extrabold {{ $consolidatedTotals['utility'] >= 0 ? 'text-green-700' : 'text-red-600' }}">${{ number_format($consolidatedTotals['utility'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-xs text-gray-400 mt-3">Las tarjetas de arriba y la lista de movimientos son de la finca actual ({{ $farm->name ?? '' }}). Esta tabla suma todas tus fincas en el rango seleccionado.</p>
    </div>
    @endif
    @endisset

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="finance-card xl:col-span-2">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Ingresos vs gastos</h2>
                <p class="text-sm text-gray-500 mt-1">Comparación diaria dentro del rango seleccionado.</p>
            </div>

            <div class="finance-chart-wrap">
                <canvas id="financeChart" class="finance-chart-canvas"></canvas>
            </div>

            <div class="finance-legend">
                <div class="finance-legend-item">
                    <span class="finance-legend-dot" style="background:#16a34a;"></span>
                    Ingresos
                </div>

                <div class="finance-legend-item">
                    <span class="finance-legend-dot" style="background:#dc2626;"></span>
                    Gastos
                </div>
            </div>
        </div>

        <div class="finance-card">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Nuevo movimiento</h2>
                <p class="text-sm text-gray-500 mt-1">Registra entradas o salidas de dinero.</p>
            </div>

            <form method="POST" action="{{ route('finances.store') }}" class="space-y-4" data-offline-sync="false">
                @csrf
                <input type="hidden" data-current-milk-available value="{{ (float) ($milkAvailableLiters ?? 0) }}">

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo</label>
                    <div class="finance-select-shell">
                        <select name="type" class="finance-form-select" data-finance-type-select required>
                            <option value="income" {{ old('type') === 'income' ? 'selected' : '' }}>Ingreso</option>
                            <option value="expense" {{ old('type') === 'expense' ? 'selected' : '' }}>Gasto</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="title" value="{{ old('title') }}" class="finance-form-input" placeholder="Ej: Venta de leche" required data-title-input>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Movimiento productivo</label>
                    <div class="finance-select-shell">
                        <select name="productive_movement" class="finance-form-select" data-productive-movement-select>
                            <option value="" @selected(old('productive_movement') === null || old('productive_movement') === '')>General</option>
                            <option value="milk_sale" @selected(old('productive_movement') === 'milk_sale' || old('milk_liters_sold'))>Venta de leche</option>
                        </select>
                    </div>
                </div>

                <div class="hidden space-y-4" data-milk-sale-fields>
                    <div class="finance-milk-range-card space-y-3">
                        <div>
                            <div class="text-sm font-extrabold text-green-900">Periodo de leche vendida</div>
                            <div class="text-xs font-semibold text-green-800/70 mt-1">Selecciona el primer día y luego el último. Los días grises no tienen litros disponibles.</div>
                        </div>

                        <input type="hidden" name="milk_sale_start_date" value="{{ old('milk_sale_start_date') }}" data-milk-sale-start-input>
                        <input type="hidden" name="milk_sale_end_date" value="{{ old('milk_sale_end_date') }}" data-milk-sale-end-input>

                        <div class="finance-range-calendar" data-milk-range-calendar>
                            <div class="finance-range-calendar-head">
                                <button type="button" class="finance-range-calendar-nav" data-calendar-prev aria-label="Mes anterior">‹</button>
                                <div class="finance-range-calendar-title" data-calendar-title></div>
                                <button type="button" class="finance-range-calendar-nav" data-calendar-next aria-label="Mes siguiente">›</button>
                            </div>
                            <div class="finance-range-calendar-grid" data-calendar-grid></div>
                            <div class="finance-range-calendar-status" data-calendar-status>Elige el primer día de la venta.</div>
                            <div class="finance-range-calendar-readable" data-calendar-readable>Sin rango seleccionado.</div>
                        </div>

                        <div class="finance-milk-range-summary">
                            <div class="finance-milk-range-metric">
                                <span>Producido</span>
                                <strong data-milk-range-produced>0,00 L</strong>
                            </div>
                            <div class="finance-milk-range-metric">
                                <span>Uso interno</span>
                                <strong data-milk-range-used>0,00 L</strong>
                            </div>
                            <div class="finance-milk-range-metric">
                                <span>Disponible</span>
                                <strong data-milk-range-available>0,00 L</strong>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Litros vendidos de leche</label>
                        <input type="number" step="0.01" min="0.01" name="milk_liters_sold" value="{{ old('milk_liters_sold') }}" class="finance-form-input bg-gray-50" placeholder="Se calcula con el rango" readonly data-milk-liters-sold-input>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Valor actual por litro</label>
                        <input type="number" step="0.01" min="0.01" name="milk_price_per_liter" value="{{ old('milk_price_per_liter') }}" class="finance-form-input" placeholder="Ej: 1850" data-milk-price-input>
                    </div>

                    <div class="text-xs font-semibold text-gray-500">
                        Disponible general en Producción: {{ $formatLiters($milkAvailableLiters ?? 0) }} L. Esta venta se descuenta según el rango seleccionado.
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Valor</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="finance-form-input" placeholder="0" required data-amount-input>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha de venta</label>
                        <input type="date" name="transaction_date" value="{{ old('transaction_date', now()->format('Y-m-d')) }}" class="finance-form-input" required data-transaction-date-input>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Categoría</label>
                    <input type="text" name="category" value="{{ old('category') }}" class="finance-form-input" placeholder="Ej: Venta de ganado, arriendo, alimentación" list="financeCategoryList" data-category-input>
                    <datalist id="financeCategoryList">
                        <option value="Venta de leche"></option>
                        <option value="Venta de ganado"></option>
                        <option value="Venta de novillas"></option>
                        <option value="Venta de terneros"></option>
                        <option value="Venta de toros"></option>
                        <option value="Venta de carne"></option>
                        <option value="Arriendo de lotes"></option>
                        <option value="Alimentación"></option>
                        <option value="Salud / veterinaria"></option>
                        <option value="Mano de obra"></option>
                        <option value="Insumos"></option>
                        <option value="Transporte"></option>
                        <option value="Otro"></option>
                    </datalist>
                </div>

                @php
                    $ifSaleAnimals = \App\Models\Animal::where('farm_id', $farm->id)
                        ->where(function ($q) {
                            $q->whereNull('status')->orWhereNotIn('status', ['vendido','fallecido','sold','deceased','dead']);
                        })
                        ->orderBy('name')->get(['id','name','ear_tag','internal_code']);
                @endphp
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Vender un animal del inventario <span class="text-gray-400 font-normal">(opcional)</span></label>
                    <select name="animal_id" class="finance-form-select" data-sale-animal-select>
                        <option value="">— No aplica —</option>
                        @foreach($ifSaleAnimals as $ifAnimal)
                            <option value="{{ $ifAnimal->id }}" @selected(old('animal_id') == $ifAnimal->id)>{{ $ifAnimal->name ?: ('Animal #'.$ifAnimal->id) }}@if($ifAnimal->ear_tag) · {{ $ifAnimal->ear_tag }}@endif</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Si eliges un animal y el movimiento es un ingreso, se marcará como <strong>vendido</strong> en el inventario.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Método de pago</label>
                    <input type="text" name="payment_method" value="{{ old('payment_method') }}" class="finance-form-input" placeholder="Efectivo, transferencia, crédito">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Referencia</label>
                    <input type="text" name="reference" value="{{ old('reference') }}" class="finance-form-input" placeholder="Factura, recibo o comprobante">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción</label>
                    <textarea name="description" rows="3" class="finance-form-textarea" placeholder="Detalle opcional...">{{ old('description') }}</textarea>
                </div>

                <button type="submit" class="finance-btn finance-btn-primary w-full">
                    Guardar movimiento
                </button>
            </form>
        </div>
    </div>

    <div class="finance-card">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Filtros</h2>
            <p class="text-sm text-gray-500 mt-1">Organiza los movimientos por fecha, tipo, categoría o valor.</p>
        </div>

        <form method="GET" action="{{ route('finances.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-8 gap-4" data-auto-filter>
            <div class="md:col-span-2 xl:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar</label>
                <input type="text" name="search" value="{{ $search }}" class="finance-form-input" placeholder="Nombre, categoría o referencia">
            </div>

            <div class="md:col-span-2 xl:col-span-2 flex flex-wrap items-end gap-3">
                <button type="submit" class="finance-btn finance-btn-primary">
                    Aplicar filtros
                </button>

                <a href="{{ route('finances.index') }}" class="finance-btn finance-btn-secondary">
                    Limpiar
                </a>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Rango</label>
                <div class="finance-select-shell">
                    <select name="range" id="financeRangeSelect" class="finance-form-select">
                        <option value="today" {{ $selectedRange === 'today' ? 'selected' : '' }}>Hoy</option>
                        <option value="week" {{ $selectedRange === 'week' ? 'selected' : '' }}>Esta semana</option>
                        <option value="fortnight" {{ $selectedRange === 'fortnight' ? 'selected' : '' }}>Última quincena</option>
                        <option value="month" {{ $selectedRange === 'month' ? 'selected' : '' }}>Este mes</option>
                        <option value="year" {{ $selectedRange === 'year' ? 'selected' : '' }}>Este año</option>
                        <option value="custom" {{ $selectedRange === 'custom' ? 'selected' : '' }}>Personalizado</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo</label>
                <div class="finance-select-shell">
                    <select name="type" class="finance-form-select">
                        <option value="all" {{ $selectedType === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="income" {{ $selectedType === 'income' ? 'selected' : '' }}>Ingresos</option>
                        <option value="expense" {{ $selectedType === 'expense' ? 'selected' : '' }}>Gastos</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Categoría</label>
                <div class="finance-select-shell">
                    <select name="category" class="finance-form-select">
                        <option value="">Todas</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ $selectedCategory === $category ? 'selected' : '' }}>
                                {{ $category }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Ordenar</label>
                <div class="finance-select-shell">
                    <select name="sort" class="finance-form-select">
                        <option value="date_desc" {{ $selectedSort === 'date_desc' ? 'selected' : '' }}>Más recientes</option>
                        <option value="date_asc" {{ $selectedSort === 'date_asc' ? 'selected' : '' }}>Más antiguos</option>
                        <option value="amount_desc" {{ $selectedSort === 'amount_desc' ? 'selected' : '' }}>Mayor valor</option>
                        <option value="title_asc" {{ $selectedSort === 'title_asc' ? 'selected' : '' }}>Nombre A-Z</option>
                    </select>
                </div>
            </div>

            <div class="finance-custom-field {{ $selectedRange === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha inicial</label>
                <input type="date" name="start_date" value="{{ request('start_date', optional($startDate)->format('Y-m-d')) }}" class="finance-form-input">
            </div>

            <div class="finance-custom-field {{ $selectedRange === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha final</label>
                <input type="date" name="end_date" value="{{ request('end_date', optional($endDate)->format('Y-m-d')) }}" class="finance-form-input">
            </div>
        </form>
    </div>

    <div id="financeResults" class="finance-card">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Movimientos</h2>
                <p class="text-sm text-gray-500 mt-1">Listado completo del rango filtrado.</p>
            </div>

            <span class="inline-flex rounded-full border border-black/10 bg-white/70 px-3 py-1.5 text-xs font-black text-gray-600">
                {{ $transactions->count() }} registros
            </span>
        </div>

        @if($transactions->count())
            <div class="finance-table-wrap">
                <table class="finance-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Valor</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                            <tr>
                                <td>{{ optional($transaction->transaction_date)->format('d/m/Y') }}</td>
                                <td>
                                    <span class="finance-pill {{ $typeClasses[$transaction->type] ?? '' }}">
                                        {{ $typeLabels[$transaction->type] ?? 'Movimiento' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-bold text-gray-900">{{ $transaction->title }}</div>
                                    @if((float) ($transaction->milk_liters_sold ?? 0) > 0)
                                        <div class="text-xs font-bold text-green-700 mt-1">{{ $formatLiters($transaction->milk_liters_sold) }} L vendidos</div>
                                        @php
                                            $saleStartDate = $transaction->milk_sale_start_date ?: $transaction->transaction_date;
                                            $saleEndDate = $transaction->milk_sale_end_date ?: $transaction->transaction_date;
                                        @endphp
                                        @if($saleStartDate && $saleEndDate)
                                            <div class="text-xs font-bold text-gray-500 mt-1">
                                                {{ $saleStartDate->format('d/m/Y') }} - {{ $saleEndDate->format('d/m/Y') }}
                                            </div>
                                        @endif
                                        @php
                                            $milkUnitPrice = (float) ($transaction->milk_price_per_liter ?? 0);
                                        @endphp
                                        @if($milkUnitPrice <= 0 && (float) $transaction->milk_liters_sold > 0)
                                            @php
                                                $milkUnitPrice = (float) $transaction->amount / (float) $transaction->milk_liters_sold;
                                            @endphp
                                        @endif
                                        @if($milkUnitPrice > 0)
                                            <div class="text-xs font-bold text-gray-500 mt-1">${{ number_format($milkUnitPrice, 0, ',', '.') }} / L</div>
                                        @endif
                                    @endif
                                    @if($transaction->description)
                                        <div class="text-xs text-gray-500 mt-1 max-w-[280px] truncate">{{ $transaction->description }}</div>
                                    @endif
                                </td>
                                <td>{{ $transaction->category ?: '—' }}</td>
                                <td>{{ $transaction->payment_method ?: '—' }}</td>
                                <td>{{ $transaction->reference ?: '—' }}</td>
                                <td class="font-black {{ $transaction->isIncome() ? 'text-green-700' : 'text-red-600' }}">
                                    ${{ number_format((float) $transaction->amount, 0, ',', '.') }}
                                </td>
                                <td>
                                    @php
                                        $storedMilkPrice = (float) ($transaction->milk_price_per_liter ?? 0);
                                        $editMilkLiters = (float) ($transaction->milk_liters_sold ?? 0);
                                        $editMilkPrice = $storedMilkPrice > 0
                                            ? $storedMilkPrice
                                            : ($editMilkLiters > 0 ? (float) $transaction->amount / $editMilkLiters : 0);
                                        $editMilkSaleStart = $transaction->milk_sale_start_date ?: $transaction->transaction_date;
                                        $editMilkSaleEnd = $transaction->milk_sale_end_date ?: $transaction->transaction_date;
                                    @endphp
                                    <div class="flex flex-wrap gap-3">
                                        <button type="button"
                                                class="finance-action-btn edit"
                                                data-open-finance-edit
                                                data-edit-action="{{ route('finances.update', $transaction) }}"
                                                data-edit-type="{{ $transaction->type }}"
                                                data-edit-title="{{ e($transaction->title) }}"
                                                data-edit-amount="{{ $transaction->amount }}"
                                                data-edit-date="{{ optional($transaction->transaction_date)->format('Y-m-d') }}"
                                                data-edit-category="{{ e($transaction->category) }}"
                                                data-edit-method="{{ e($transaction->payment_method) }}"
                                                data-edit-reference="{{ e($transaction->reference) }}"
                                                data-edit-description="{{ e($transaction->description) }}"
                                                data-edit-milk-liters="{{ $editMilkLiters > 0 ? $editMilkLiters : '' }}"
                                                data-edit-milk-price="{{ $editMilkPrice > 0 ? round($editMilkPrice, 2) : '' }}"
                                                data-edit-milk-sale-start="{{ $editMilkSaleStart ? $editMilkSaleStart->format('Y-m-d') : '' }}"
                                                data-edit-milk-sale-end="{{ $editMilkSaleEnd ? $editMilkSaleEnd->format('Y-m-d') : '' }}">
                                            Editar
                                        </button>

                                        <form method="POST" action="{{ route('finances.destroy', $transaction) }}" data-offline-sync="false" onsubmit="return confirm('¿Eliminar este movimiento?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="finance-action-btn delete">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-[22px] border border-dashed border-black/10 bg-white/50 px-6 py-10 text-center">
                <div class="text-lg font-extrabold text-gray-900">Aún no hay movimientos para este filtro</div>
                <p class="text-sm text-gray-500 mt-2">Registra el primer ingreso o gasto para empezar a ver el balance.</p>
            </div>
        @endif
    </div>
</div>

<div id="financeEditModal" class="finance-modal-backdrop">
    <div class="finance-modal-panel">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900">Editar movimiento</h2>
                <p class="text-sm text-gray-500 mt-1">Ajusta ingresos, gastos o ventas de leche registradas.</p>
            </div>

            <button type="button" class="finance-btn finance-btn-secondary" data-close-finance-edit>
                Cerrar
            </button>
        </div>

        <form method="POST" action="#" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-finance-edit-form data-offline-sync="false">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo</label>
                <div class="finance-select-shell">
                    <select name="type" class="finance-form-select" data-edit-field="type" data-edit-finance-type required>
                        <option value="income">Ingreso</option>
                        <option value="expense">Gasto</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Movimiento productivo</label>
                <div class="finance-select-shell">
                    <select name="productive_movement" class="finance-form-select" data-edit-field="productive_movement" data-edit-productive-movement>
                        <option value="">General</option>
                        <option value="milk_sale">Venta de leche</option>
                    </select>
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre</label>
                <input type="text" name="title" class="finance-form-input" data-edit-field="title" required>
            </div>

            <div class="hidden md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4" data-edit-milk-sale-fields>
                <div class="md:col-span-2 finance-milk-range-card space-y-3">
                    <div>
                        <div class="text-sm font-extrabold text-green-900">Periodo de leche vendida</div>
                        <div class="text-xs font-semibold text-green-800/70 mt-1">Selecciona el primer día y luego el último. Los días grises no tienen litros disponibles.</div>
                    </div>

                        <input type="hidden" name="milk_sale_start_date" data-edit-field="milk_sale_start_date" data-edit-milk-sale-start>
                        <input type="hidden" name="milk_sale_end_date" data-edit-field="milk_sale_end_date" data-edit-milk-sale-end>

                        <div class="finance-range-calendar" data-edit-milk-range-calendar>
                            <div class="finance-range-calendar-head">
                                <button type="button" class="finance-range-calendar-nav" data-calendar-prev aria-label="Mes anterior">‹</button>
                                <div class="finance-range-calendar-title" data-calendar-title></div>
                                <button type="button" class="finance-range-calendar-nav" data-calendar-next aria-label="Mes siguiente">›</button>
                            </div>
                            <div class="finance-range-calendar-grid" data-calendar-grid></div>
                            <div class="finance-range-calendar-status" data-calendar-status>Elige el primer día de la venta.</div>
                            <div class="finance-range-calendar-readable" data-calendar-readable>Sin rango seleccionado.</div>
                        </div>

                    <div class="finance-milk-range-summary">
                        <div class="finance-milk-range-metric">
                            <span>Producido</span>
                            <strong data-edit-milk-range-produced>0,00 L</strong>
                        </div>
                        <div class="finance-milk-range-metric">
                            <span>Uso interno</span>
                            <strong data-edit-milk-range-used>0,00 L</strong>
                        </div>
                        <div class="finance-milk-range-metric">
                            <span>Disponible</span>
                            <strong data-edit-milk-range-available>0,00 L</strong>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Litros vendidos de leche</label>
                    <input type="number" step="0.01" min="0.01" name="milk_liters_sold" class="finance-form-input bg-gray-50" readonly data-edit-field="milk_liters_sold" data-edit-milk-liters>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Valor por litro</label>
                    <input type="number" step="0.01" min="0.01" name="milk_price_per_liter" class="finance-form-input" data-edit-field="milk_price_per_liter" data-edit-milk-price>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Valor</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="finance-form-input" data-edit-field="amount" data-edit-amount required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha de venta</label>
                <input type="date" name="transaction_date" class="finance-form-input" data-edit-field="transaction_date" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Categoría</label>
                <input type="text" name="category" class="finance-form-input" data-edit-field="category">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Método de pago</label>
                <input type="text" name="payment_method" class="finance-form-input" data-edit-field="payment_method">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Referencia</label>
                <input type="text" name="reference" class="finance-form-input" data-edit-field="reference">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción</label>
                <textarea name="description" rows="3" class="finance-form-textarea" data-edit-field="description"></textarea>
            </div>

            <div class="md:col-span-2 flex flex-wrap gap-3">
                <button type="submit" class="finance-btn finance-btn-primary">
                    Guardar cambios
                </button>
                <button type="button" class="finance-btn finance-btn-secondary" data-close-finance-edit>
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rangeSelect = document.getElementById('financeRangeSelect');
    const customFields = document.querySelectorAll('.finance-custom-field');
    const financeFilterForm = document.querySelector('form[data-ajax-filter][data-ajax-target="#financeResults"]');

    const toggleCustomFields = () => {
        const isCustom = rangeSelect && rangeSelect.value === 'custom';

        customFields.forEach((field) => {
            field.classList.toggle('hidden', !isCustom);
        });
    };

    if (rangeSelect) {
        rangeSelect.addEventListener('change', toggleCustomFields);
        toggleCustomFields();
    }

    const financeTypeSelect = document.querySelector('[data-finance-type-select]');
    const productiveMovementSelect = document.querySelector('[data-productive-movement-select]');
    const milkSaleFields = document.querySelector('[data-milk-sale-fields]');
    const milkAvailable = Number(document.querySelector('[data-current-milk-available]')?.value || 0);
    const milkAvailabilityByDate = @json(json_decode($milkAvailabilityJson, true) ?: []);
    const amountInput = document.querySelector('[data-amount-input]');
    const milkLitersSoldInput = document.querySelector('[data-milk-liters-sold-input]');
    const milkPriceInput = document.querySelector('[data-milk-price-input]');
    const milkSaleStartInput = document.querySelector('[data-milk-sale-start-input]');
    const milkSaleEndInput = document.querySelector('[data-milk-sale-end-input]');
    const transactionDateInput = document.querySelector('[data-transaction-date-input]');
    const milkRangeProduced = document.querySelector('[data-milk-range-produced]');
    const milkRangeUsed = document.querySelector('[data-milk-range-used]');
    const milkRangeAvailable = document.querySelector('[data-milk-range-available]');
    const titleInput = document.querySelector('[data-title-input]');
    const categoryInput = document.querySelector('[data-category-input]');
    const milkAvailabilityMap = new Map(milkAvailabilityByDate.map((row) => [row.date, row]));
    const numberFormatter = new Intl.NumberFormat('es-CO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });

    const formatLiters = (value) => `${numberFormatter.format(Number(value || 0))} L`;
    const formatNumberInput = (value) => {
        const number = Number(value || 0);

        if (!Number.isFinite(number) || number <= 0) return '';

        return number.toFixed(2).replace(/\.?0+$/, '');
    };
    const readableDateFormatter = new Intl.DateTimeFormat('es-CO', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const parseDate = (value) => {
        if (!value) return null;

        const date = new Date(`${value}T00:00:00`);

        return Number.isNaN(date.getTime()) ? null : date;
    };

    const formatReadableDate = (value) => {
        const date = parseDate(value);

        return date ? readableDateFormatter.format(date) : '';
    };

    const dateRange = (start, end) => {
        if (!start || !end) return [];

        const startDate = new Date(`${start}T00:00:00`);
        const endDate = new Date(`${end}T00:00:00`);

        if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) return [];

        const first = startDate <= endDate ? startDate : endDate;
        const last = startDate <= endDate ? endDate : startDate;
        const dates = [];
        const cursor = new Date(first);

        while (cursor <= last) {
            dates.push(formatDate(cursor));
            cursor.setDate(cursor.getDate() + 1);
        }

        return dates;
    };

    const summarizeMilkRange = (start, end) => {
        return dateRange(start, end).reduce((summary, date) => {
            const row = milkAvailabilityMap.get(date) || {};

            summary.produced += Number(row.produced || 0);
            summary.used += Number(row.used || 0);
            summary.available += Number(row.available || 0);

            return summary;
        }, { produced: 0, used: 0, available: 0 });
    };

    const createMilkRangeCalendar = ({ root, startInput, endInput, onChange, getAllowedExtraDates = () => [] }) => {
        if (!root || !startInput || !endInput) return null;

        const title = root.querySelector('[data-calendar-title]');
        const grid = root.querySelector('[data-calendar-grid]');
        const status = root.querySelector('[data-calendar-status]');
        const readable = root.querySelector('[data-calendar-readable]');
        const prev = root.querySelector('[data-calendar-prev]');
        const next = root.querySelector('[data-calendar-next]');
        const monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        const weekDays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
        const today = formatDate(new Date());
        let monthCursor = parseDate(startInput.value || endInput.value) || new Date();

        monthCursor = new Date(monthCursor.getFullYear(), monthCursor.getMonth(), 1);

        const allowedExtraDates = () => new Set(getAllowedExtraDates().filter(Boolean));
        const isSelectable = (date) => {
            const row = milkAvailabilityMap.get(date);

            return Number(row?.available || 0) > 0 || allowedExtraDates().has(date);
        };
        const rangeIsSelectable = (start, end) => dateRange(start, end).every(isSelectable);
        const selectedDates = () => {
            if (startInput.value && endInput.value) return dateRange(startInput.value, endInput.value);
            if (startInput.value) return [startInput.value];

            return [];
        };
        const setStatus = () => {
            if (!status) return;

            if (startInput.value && endInput.value) {
                status.textContent = 'Rango completo seleccionado.';
                if (readable) {
                    readable.textContent = `Día inicial: ${formatReadableDate(startInput.value)}. Día final: ${formatReadableDate(endInput.value)}. Rango del ${formatReadableDate(startInput.value)} al ${formatReadableDate(endInput.value)}.`;
                }
            } else if (startInput.value) {
                status.textContent = 'Listo para vender este día. Si quieres rango, elige el último día.';
                if (readable) {
                    readable.textContent = `Día inicial: ${formatReadableDate(startInput.value)}. Toca otro día para marcar el día final.`;
                }
            } else {
                status.textContent = 'Elige el primer día de la venta.';
                if (readable) {
                    readable.textContent = 'Sin rango seleccionado.';
                }
            }
        };

        const render = () => {
            if (!grid || !title) return;

            grid.innerHTML = '';
            title.textContent = `${monthNames[monthCursor.getMonth()]} ${monthCursor.getFullYear()}`;

            weekDays.forEach((day) => {
                const label = document.createElement('div');
                label.className = 'finance-range-calendar-weekday';
                label.textContent = day;
                grid.appendChild(label);
            });

            const firstOfMonth = new Date(monthCursor.getFullYear(), monthCursor.getMonth(), 1);
            const startOffset = (firstOfMonth.getDay() + 6) % 7;
            const calendarStart = new Date(firstOfMonth);
            calendarStart.setDate(firstOfMonth.getDate() - startOffset);
            const inRangeDates = new Set(selectedDates());

            for (let index = 0; index < 42; index += 1) {
                const dayDate = new Date(calendarStart);
                dayDate.setDate(calendarStart.getDate() + index);

                const date = formatDate(dayDate);
                const button = document.createElement('button');
                const disabled = !isSelectable(date);

                button.type = 'button';
                button.className = 'finance-range-calendar-day';
                button.textContent = String(dayDate.getDate());
                button.dataset.date = date;

                if (dayDate.getMonth() !== monthCursor.getMonth()) button.classList.add('is-muted');
                if (disabled) button.classList.add('is-disabled');
                if (date === today) button.classList.add('is-today');
                if (inRangeDates.has(date)) button.classList.add('is-in-range');
                if (date === startInput.value) button.classList.add('is-start');
                if (date === endInput.value) button.classList.add('is-end');

                button.disabled = disabled;
                button.title = disabled ? 'Sin litros disponibles para venta' : `${formatLiters(milkAvailabilityMap.get(date)?.available || 0)} disponibles`;

                let handledTouch = false;
                const selectDate = () => {
                    if (disabled) return;

                    if (!startInput.value || endInput.value) {
                        startInput.value = date;
                        endInput.value = '';
                    } else if (startInput.value === date) {
                        startInput.value = '';
                        endInput.value = '';
                    } else {
                        const startDate = parseDate(startInput.value);
                        const clickedDate = parseDate(date);
                        const first = clickedDate < startDate ? date : startInput.value;
                        const last = clickedDate < startDate ? startInput.value : date;

                        if (!rangeIsSelectable(first, last)) {
                            if (status) status.textContent = 'Ese rango cruza días sin litros disponibles. Elige otro tramo.';
                            return;
                        }

                        startInput.value = first;
                        endInput.value = last;
                    }

                    onChange?.();
                    setStatus();
                    render();
                };

                button.addEventListener('touchend', (event) => {
                    handledTouch = true;
                    event.preventDefault();
                    selectDate();
                }, { passive: false });

                button.addEventListener('click', (event) => {
                    event.preventDefault();

                    if (handledTouch) {
                        handledTouch = false;
                        return;
                    }

                    selectDate();
                });

                grid.appendChild(button);
            }

            setStatus();
        };

        prev?.addEventListener('click', () => {
            monthCursor = new Date(monthCursor.getFullYear(), monthCursor.getMonth() - 1, 1);
            render();
        });

        next?.addEventListener('click', () => {
            monthCursor = new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 1);
            render();
        });

        return {
            render,
            setMonthFromSelection() {
                const selected = parseDate(startInput.value || endInput.value);

                if (selected) {
                    monthCursor = new Date(selected.getFullYear(), selected.getMonth(), 1);
                }

                render();
            },
        };
    };

    const calculateMilkSaleAmount = () => {
        if (!amountInput || !milkLitersSoldInput || !milkPriceInput || productiveMovementSelect?.value !== 'milk_sale') return;

        const liters = Number(milkLitersSoldInput.value || 0);
        const price = Number(milkPriceInput.value || 0);
        const amount = liters > 0 && price > 0 ? liters * price : 0;

        amountInput.value = amount > 0 ? amount.toFixed(2) : '';
    };

    const syncMilkRangeSummary = () => {
        if (!milkLitersSoldInput || productiveMovementSelect?.value !== 'milk_sale') return;

        const effectiveEndDate = milkSaleEndInput?.value || milkSaleStartInput?.value;
        const summary = summarizeMilkRange(milkSaleStartInput?.value, effectiveEndDate);
        const available = Math.max(0, summary.available);

        if (milkRangeProduced) milkRangeProduced.textContent = formatLiters(summary.produced);
        if (milkRangeUsed) milkRangeUsed.textContent = formatLiters(summary.used);
        if (milkRangeAvailable) milkRangeAvailable.textContent = formatLiters(available);

        milkLitersSoldInput.value = formatNumberInput(available);
        milkLitersSoldInput.max = formatNumberInput(available);

        if (transactionDateInput && effectiveEndDate) {
            transactionDateInput.value = effectiveEndDate;
        }

        calculateMilkSaleAmount();
    };

    const milkRangeCalendar = createMilkRangeCalendar({
        root: document.querySelector('[data-milk-range-calendar]'),
        startInput: milkSaleStartInput,
        endInput: milkSaleEndInput,
        onChange: syncMilkRangeSummary,
    });

    const syncMilkSaleFields = () => {
        if (!productiveMovementSelect || !milkSaleFields) return;

        const isIncome = !financeTypeSelect || financeTypeSelect.value === 'income';
        const isMilkSale = productiveMovementSelect.value === 'milk_sale' && isIncome;

        milkSaleFields.classList.toggle('hidden', !isMilkSale);
        if (amountInput) {
            amountInput.readOnly = isMilkSale;
            amountInput.classList.toggle('bg-gray-50', isMilkSale);
        }
        milkSaleFields.querySelectorAll('input').forEach((input) => {
            input.disabled = !isMilkSale;
        });

        if (isMilkSale) {
            if (titleInput && titleInput.value.trim() === '') {
                titleInput.value = 'Venta de leche';
            }

            if (categoryInput && categoryInput.value.trim() === '') {
                categoryInput.value = 'Venta de leche';
            }

            syncMilkRangeSummary();
            milkRangeCalendar?.render();
        }
    };

    if (financeTypeSelect) {
        financeTypeSelect.addEventListener('change', syncMilkSaleFields);
    }

    if (productiveMovementSelect) {
        productiveMovementSelect.addEventListener('change', syncMilkSaleFields);
        syncMilkSaleFields();
    }

    if (milkLitersSoldInput) {
        milkLitersSoldInput.addEventListener('input', calculateMilkSaleAmount);
    }

    if (milkPriceInput) {
        milkPriceInput.addEventListener('input', calculateMilkSaleAmount);
    }

    if (milkSaleStartInput) {
        milkSaleStartInput.addEventListener('change', syncMilkRangeSummary);
    }

    if (milkSaleEndInput) {
        milkSaleEndInput.addEventListener('change', syncMilkRangeSummary);
    }

    const financeEditModal = document.getElementById('financeEditModal');
    const financeEditForm = document.querySelector('[data-finance-edit-form]');
    const editTypeSelect = document.querySelector('[data-edit-finance-type]');
    const editProductiveSelect = document.querySelector('[data-edit-productive-movement]');
    const editMilkSaleFields = document.querySelector('[data-edit-milk-sale-fields]');
    const editMilkLitersInput = document.querySelector('[data-edit-milk-liters]');
    const editMilkPriceInput = document.querySelector('[data-edit-milk-price]');
    const editMilkSaleStartInput = document.querySelector('[data-edit-milk-sale-start]');
    const editMilkSaleEndInput = document.querySelector('[data-edit-milk-sale-end]');
    const editAmountInput = document.querySelector('[data-edit-amount]');
    const editMilkRangeProduced = document.querySelector('[data-edit-milk-range-produced]');
    const editMilkRangeUsed = document.querySelector('[data-edit-milk-range-used]');
    const editMilkRangeAvailable = document.querySelector('[data-edit-milk-range-available]');

    const setEditField = (field, value) => {
        const input = financeEditForm?.querySelector(`[data-edit-field="${field}"]`);
        if (input) {
            input.value = value ?? '';
        }
    };

    const calculateEditMilkAmount = () => {
        if (!editAmountInput || !editMilkLitersInput || !editMilkPriceInput || editProductiveSelect?.value !== 'milk_sale') return;

        const liters = Number(editMilkLitersInput.value || 0);
        const price = Number(editMilkPriceInput.value || 0);
        const amount = liters > 0 && price > 0 ? liters * price : 0;

        editAmountInput.value = amount > 0 ? amount.toFixed(2) : '';
    };

    const syncEditMilkRangeSummary = () => {
        if (!editMilkLitersInput || editProductiveSelect?.value !== 'milk_sale') return;

        const effectiveEndDate = editMilkSaleEndInput?.value || editMilkSaleStartInput?.value;
        const isCurrentRange = editMilkSaleStartInput?.value === editMilkLitersInput.dataset.currentStart
            && effectiveEndDate === editMilkLitersInput.dataset.currentEnd;
        const currentLiters = isCurrentRange ? Number(editMilkLitersInput.dataset.currentLiters || 0) : 0;
        const summary = summarizeMilkRange(editMilkSaleStartInput?.value, effectiveEndDate);
        const available = Math.max(0, summary.available + currentLiters);

        if (editMilkRangeProduced) editMilkRangeProduced.textContent = formatLiters(summary.produced);
        if (editMilkRangeUsed) editMilkRangeUsed.textContent = formatLiters(summary.used);
        if (editMilkRangeAvailable) editMilkRangeAvailable.textContent = formatLiters(available);

        editMilkLitersInput.value = formatNumberInput(available);
        editMilkLitersInput.max = formatNumberInput(available);

        calculateEditMilkAmount();
    };

    const editMilkRangeCalendar = createMilkRangeCalendar({
        root: document.querySelector('[data-edit-milk-range-calendar]'),
        startInput: editMilkSaleStartInput,
        endInput: editMilkSaleEndInput,
        onChange: syncEditMilkRangeSummary,
        getAllowedExtraDates: () => dateRange(
            editMilkLitersInput?.dataset.currentStart || '',
            editMilkLitersInput?.dataset.currentEnd || ''
        ),
    });

    const syncEditMilkSaleFields = () => {
        if (!editProductiveSelect || !editMilkSaleFields) return;

        const isIncome = !editTypeSelect || editTypeSelect.value === 'income';
        const isMilkSale = editProductiveSelect.value === 'milk_sale' && isIncome;

        editMilkSaleFields.classList.toggle('hidden', !isMilkSale);
        editMilkSaleFields.querySelectorAll('input').forEach((input) => {
            input.disabled = !isMilkSale;
        });

        if (editAmountInput) {
            editAmountInput.readOnly = isMilkSale;
            editAmountInput.classList.toggle('bg-gray-50', isMilkSale);
        }

        if (isMilkSale) {
            syncEditMilkRangeSummary();
            editMilkRangeCalendar?.render();
        }
    };

    const openFinanceEditModal = (button) => {
        if (!financeEditModal || !financeEditForm) return;

        financeEditForm.action = button.dataset.editAction || '#';
        setEditField('type', button.dataset.editType || 'expense');
        setEditField('productive_movement', button.dataset.editMilkLiters ? 'milk_sale' : '');
        setEditField('title', button.dataset.editTitle || '');
        setEditField('amount', button.dataset.editAmount || '');
        setEditField('transaction_date', button.dataset.editDate || '');
        setEditField('category', button.dataset.editCategory || '');
        setEditField('payment_method', button.dataset.editMethod || '');
        setEditField('reference', button.dataset.editReference || '');
        setEditField('description', button.dataset.editDescription || '');
        setEditField('milk_liters_sold', button.dataset.editMilkLiters || '');
        setEditField('milk_price_per_liter', button.dataset.editMilkPrice || '');
        setEditField('milk_sale_start_date', button.dataset.editMilkSaleStart || button.dataset.editDate || '');
        setEditField('milk_sale_end_date', button.dataset.editMilkSaleEnd || button.dataset.editDate || '');

        if (editMilkLitersInput) {
            editMilkLitersInput.dataset.currentLiters = button.dataset.editMilkLiters || '0';
            editMilkLitersInput.dataset.currentStart = button.dataset.editMilkSaleStart || button.dataset.editDate || '';
            editMilkLitersInput.dataset.currentEnd = button.dataset.editMilkSaleEnd || button.dataset.editDate || '';
        }

        syncEditMilkSaleFields();
        editMilkRangeCalendar?.setMonthFromSelection();
        financeEditModal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    };

    const closeFinanceEditModal = () => {
        if (!financeEditModal) return;
        financeEditModal.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-open-finance-edit]');
        const closeButton = event.target.closest('[data-close-finance-edit]');

        if (openButton) {
            event.preventDefault();
            openFinanceEditModal(openButton);
        }

        if (closeButton) {
            event.preventDefault();
            closeFinanceEditModal();
        }
    });

    if (financeEditModal) {
        financeEditModal.addEventListener('click', (event) => {
            if (event.target === financeEditModal) {
                closeFinanceEditModal();
            }
        });
    }

    if (editTypeSelect) {
        editTypeSelect.addEventListener('change', syncEditMilkSaleFields);
    }

    if (editProductiveSelect) {
        editProductiveSelect.addEventListener('change', syncEditMilkSaleFields);
    }

    if (editMilkLitersInput) {
        editMilkLitersInput.addEventListener('input', calculateEditMilkAmount);
    }

    if (editMilkPriceInput) {
        editMilkPriceInput.addEventListener('input', calculateEditMilkAmount);
    }

    if (editMilkSaleStartInput) {
        editMilkSaleStartInput.addEventListener('change', syncEditMilkRangeSummary);
    }

    if (editMilkSaleEndInput) {
        editMilkSaleEndInput.addEventListener('change', syncEditMilkRangeSummary);
    }

    const buildFinanceFilterUrl = (form) => {
        const url = new URL(form.action, window.location.origin);
        const params = new URLSearchParams(new FormData(form));

        Array.from(params.keys()).forEach((key) => {
            const value = params.get(key);

            if (value === null || value.trim() === '') {
                params.delete(key);
            }
        });

        url.search = params.toString();

        return url;
    };

    const fetchFinanceResults = async () => {
        if (!financeFilterForm) return;

        const targetSelector = financeFilterForm.dataset.ajaxTarget;
        const target = document.querySelector(targetSelector);

        if (!target) return;

        const url = buildFinanceFilterUrl(financeFilterForm);

        target.style.opacity = '.55';
        target.style.pointerEvents = 'none';

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextTarget = doc.querySelector(targetSelector);

            if (!nextTarget) {
                window.location.href = url.toString();
                return;
            }

            target.replaceWith(nextTarget);
            window.history.replaceState({}, '', url.toString());
        } catch (error) {
            console.error('Error filtrando finanzas:', error);
            window.location.href = url.toString();
        } finally {
            const updatedTarget = document.querySelector(targetSelector);

            if (updatedTarget) {
                updatedTarget.style.opacity = '';
                updatedTarget.style.pointerEvents = '';
            }
        }
    };

    if (financeFilterForm) {
        financeFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            fetchFinanceResults();
        });

        financeFilterForm.addEventListener('auto-filter:submit', (event) => {
            event.preventDefault();
            fetchFinanceResults();
        });
    }

    const chartData = @json($chartJson ? json_decode($chartJson, true) : []);
    const canvas = document.getElementById('financeChart');

    if (!canvas) return;

    const drawChart = () => {
        const parent = canvas.parentElement;
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(parent.clientWidth, 320);
        const height = Math.max(parent.clientHeight, 260);

        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const padding = { top: 22, right: 18, bottom: 44, left: 54 };
        const innerWidth = width - padding.left - padding.right;
        const innerHeight = height - padding.top - padding.bottom;

        if (!chartData.length) {
            ctx.fillStyle = '#6b7280';
            ctx.font = '14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sin datos para mostrar', width / 2, height / 2);
            return;
        }

        const maxValue = Math.max(
            ...chartData.map(item => Number(item.income || 0)),
            ...chartData.map(item => Number(item.expense || 0)),
            1
        );

        ctx.strokeStyle = 'rgba(17,24,39,.08)';
        ctx.lineWidth = 1;
        ctx.fillStyle = '#6b7280';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'right';

        for (let i = 0; i <= 4; i++) {
            const value = maxValue - ((maxValue / 4) * i);
            const y = padding.top + (innerHeight / 4) * i;

            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(width - padding.right, y);
            ctx.stroke();
            ctx.fillText('$' + Math.round(value).toLocaleString('es-CO'), padding.left - 10, y + 4);
        }

        const pointsFor = (key) => chartData.map((item, index) => {
            const x = padding.left + (chartData.length === 1 ? innerWidth / 2 : (innerWidth / (chartData.length - 1)) * index);
            const y = padding.top + innerHeight - ((Number(item[key] || 0) / maxValue) * innerHeight);
            return { x, y, label: item.label };
        });

        const drawLine = (points, color) => {
            ctx.strokeStyle = color;
            ctx.lineWidth = 3;
            ctx.beginPath();

            points.forEach((point, index) => {
                if (index === 0) {
                    ctx.moveTo(point.x, point.y);
                } else {
                    const previous = points[index - 1];
                    const cx = (previous.x + point.x) / 2;
                    ctx.bezierCurveTo(cx, previous.y, cx, point.y, point.x, point.y);
                }
            });

            ctx.stroke();

            ctx.fillStyle = color;
            points.forEach((point) => {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 3.5, 0, Math.PI * 2);
                ctx.fill();
            });
        };

        drawLine(pointsFor('income'), '#16a34a');
        drawLine(pointsFor('expense'), '#dc2626');

        ctx.fillStyle = '#6b7280';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';

        const labelStep = Math.max(1, Math.ceil(chartData.length / 6));
        chartData.forEach((item, index) => {
            if (index % labelStep !== 0 && index !== chartData.length - 1) return;

            const x = padding.left + (chartData.length === 1 ? innerWidth / 2 : (innerWidth / (chartData.length - 1)) * index);
            ctx.fillText(item.label, x, height - 16);
        });
    };

    drawChart();
    window.addEventListener('resize', drawChart);
});
</script>

@endsection
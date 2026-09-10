@extends('layouts.app')

@section('title', 'Producción')

@section('content')

@php
    $selectedAnimal = collect($animals ?? [])->firstWhere('id', (int) $selectedAnimalId);
    $formatLiters = fn ($value) => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');

    $milkChartJson = collect($milkChartData ?? [])->map(function ($item) {
        return [
            'label' => $item['label'] ?? '—',
            'value' => (float) ($item['produced_liters'] ?? 0),
        ];
    })->values()->toJson();

    $meatChartJson = collect($meatChartData ?? [])->map(function ($item) {
        return [
            'label' => $item['label'] ?? '—',
            'value' => (float) ($item['weight'] ?? 0),
        ];
    })->values()->toJson();
@endphp

<style>
    .prod-hero {
        position: relative;
        overflow: hidden;
        border-radius: 32px;
        border: 1px solid rgba(0, 0, 0, .06);
        background:
            radial-gradient(900px 300px at 0% 0%, rgba(34,197,94,.14), transparent 44%),
            radial-gradient(700px 260px at 100% 0%, rgba(163,230,53,.10), transparent 38%),
            linear-gradient(135deg, rgba(255,255,255,.78), rgba(255,255,255,.60));
        box-shadow: 0 18px 50px rgba(0,0,0,.06);
        padding: 28px;
    }

    .prod-hero::after,
    .prod-kpi-card::after,
    .prod-filter-card::after,
    .prod-chart-card::after,
    .prod-table-card::after,
    .prod-modal-panel::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        pointer-events: none;
    }

    .prod-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.75);
        color: #374151;
        font-size: 12px;
        font-weight: 800;
    }

    .prod-chip-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 0 4px rgba(34,197,94,.10);
    }

    .prod-kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    @media (min-width: 768px) {
        .prod-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .prod-kpi-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .prod-kpi-card,
    .prod-filter-card,
    .prod-chart-card,
    .prod-table-card {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid rgba(0, 0, 0, .07);
        background: rgba(255,255,255,.62);
        box-shadow: 0 12px 30px rgba(0,0,0,.05);
        padding: 22px;
    }

    .prod-kpi-card {
        border-radius: 24px;
        padding: 18px;
    }

    .prod-kpi-icon {
        width: 52px;
        height: 52px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 14px;
    }

    .prod-kpi-icon.milk { background: rgba(22,101,52,.10); color: #166534; }
    .prod-kpi-icon.money { background: rgba(234,179,8,.14); color: #a16207; }
    .prod-kpi-icon.meat { background: rgba(239,68,68,.10); color: #dc2626; }
    .prod-kpi-icon.filter { background: rgba(59,130,246,.10); color: #2563eb; }

    .prod-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
        font-weight: 700;
        color: #374151;
    }

    .prod-input,
    .prod-select,
    .prod-textarea {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, .10);
        background: rgba(255,255,255,.82);
        padding: 12px 16px;
        outline: none;
        transition: .2s ease;
        color: #111827;
    }

    .prod-input:focus,
    .prod-select:focus,
    .prod-textarea:focus {
        border-color: rgba(22,101,52,.35);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .prod-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image:
            linear-gradient(45deg, transparent 50%, #6b7280 50%),
            linear-gradient(135deg, #6b7280 50%, transparent 50%);
        background-position:
            calc(100% - 18px) calc(50% - 3px),
            calc(100% - 12px) calc(50% - 3px);
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
        padding-right: 42px;
    }

    .prod-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 16px;
        font-size: 14px;
        font-weight: 800;
        transition: all .18s ease;
        padding: 12px 16px;
    }

    .prod-btn:hover {
        transform: translateY(-1px);
    }

    .prod-btn-primary {
        background: #166534;
        color: white;
    }

    .prod-btn-primary:hover {
        background: #14532d;
    }

    .prod-btn-secondary {
        background: rgba(255,255,255,.78);
        color: #374151;
        border: 1px solid rgba(0,0,0,.08);
    }

    .prod-btn-danger {
        background: rgba(220,38,38,.92);
        color: white;
    }

    .prod-mini-kpi {
        border-radius: 20px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.56);
        padding: 16px;
    }

    .prod-chart-shell {
        position: relative;
        overflow: hidden;
        border-radius: 22px;
        border: 1px solid rgba(0,0,0,.06);
        background:
            radial-gradient(800px 220px at 0% 0%, rgba(34,197,94,.08), transparent 45%),
            linear-gradient(180deg, rgba(255,255,255,.80), rgba(255,255,255,.62));
        padding: 16px;
        min-height: 360px;
    }

    .prod-chart-canvas-wrap {
        position: relative;
        width: 100%;
        height: 280px;
    }

    .prod-chart-canvas {
        width: 100%;
        height: 280px;
        display: block;
    }

    .prod-chart-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }

    .prod-chart-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 700;
        color: #4b5563;
    }

    .prod-chart-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 999px;
    }

    .prod-table-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        border-radius: 22px;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.42);
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .prod-table-wrap::-webkit-scrollbar {
        height: 8px;
    }

    .prod-table-wrap::-webkit-scrollbar-thumb {
        background: rgba(22,101,52,.24);
        border-radius: 999px;
    }

    .prod-table-wrap::-webkit-scrollbar-track {
        background: rgba(0,0,0,.04);
    }

    .prod-table table {
        min-width: 100%;
        width: max-content;
    }

    .prod-table th {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .prod-table td,
    .prod-table th {
        padding: 14px 16px;
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }

    .prod-table tbody tr {
        border-top: 1px solid rgba(0,0,0,.05);
        transition: background .18s ease;
    }

    .prod-table tbody tr:hover {
        background: rgba(255,255,255,.64);
    }

    .prod-table tbody tr.prod-summary-row {
        border-top: 1px solid rgba(22,101,52,.16);
        background: rgba(22,101,52,.06);
    }

    .prod-table tbody tr.prod-summary-row:hover {
        background: rgba(22,101,52,.09);
    }

    .prod-badge {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .prod-badge.milk {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .prod-badge.meat {
        background: rgba(239,68,68,.10);
        color: #dc2626;
    }

    .prod-empty {
        border-radius: 22px;
        border: 1px dashed rgba(0,0,0,.10);
        background: rgba(255,255,255,.42);
        padding: 28px;
        text-align: center;
        color: #6b7280;
    }

    .prod-empty.is-compact {
        padding: 14px;
        font-size: 13px;
        font-weight: 600;
    }

    .prod-section-title {
        font-size: 20px;
        font-weight: 900;
        color: #111827;
        line-height: 1.1;
    }

    .prod-section-subtitle {
        font-size: 14px;
        color: #6b7280;
        margin-top: 6px;
    }

    .prod-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, .34);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        opacity: 0;
        transition: opacity .22s ease;
    }

    .prod-modal-backdrop.is-open {
        display: block;
        opacity: 1;
    }

    .prod-modal-frame {
        width: 100vw;
        min-height: 100vh;
        overflow-y: auto;
        padding: 28px 18px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .prod-modal-panel {
        position: relative;
        width: min(760px, 100%);
        max-height: calc(100vh - 56px);
        overflow-y: auto;
        margin: 0 auto;
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,.55);
        background: rgba(255,255,255,.92);
        box-shadow: 0 24px 60px rgba(0,0,0,.16), inset 0 1px 0 rgba(255,255,255,.72);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 22px;
        transform: translateY(20px) scale(.97);
        opacity: 0;
        transition: transform .24s cubic-bezier(.22,.9,.2,1), opacity .24s ease;
    }

    .prod-modal-backdrop.is-open .prod-modal-panel {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .prod-modal-header {
        margin: -22px -22px 20px -22px;
        padding: 20px 22px 16px 22px;
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.90));
        border-bottom: 1px solid rgba(0, 0, 0, .06);
    }

    .prod-delete-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 12px;
        border: 1px solid rgba(220,38,38,.14);
        background: rgba(254,242,242,.88);
        color: #dc2626;
        font-size: 12px;
        font-weight: 800;
        transition: .18s ease;
    }

    .prod-delete-btn:hover {
        background: rgba(254,226,226,1);
        transform: translateY(-1px);
    }

    .prod-edit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 8px 12px;
        border-radius: 12px;
        border: 1px solid rgba(22,101,52,.14);
        background: rgba(240,253,244,.9);
        color: #166534;
        font-size: 12px;
        font-weight: 800;
        transition: .18s ease;
    }

    .prod-edit-btn:hover {
        background: rgba(220,252,231,1);
        transform: translateY(-1px);
    }

    @media (max-width: 1024px) {
        .prod-chart-canvas-wrap,
        .prod-chart-canvas {
            height: 240px;
        }
    }

    @media (max-width: 640px) {
        .prod-modal-frame {
            padding: 12px;
        }

        .prod-modal-panel {
            width: 100%;
            padding: 16px;
            border-radius: 22px;
        }

        .prod-modal-header {
            margin: -16px -16px 18px -16px;
            padding: 18px 16px 14px 16px;
        }
    }
        .prod-toggle-individual { display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .75rem; font-size:.75rem; font-weight:600; color:#166534; background:#dcfce7; border:1px solid #bbf7d0; border-radius:9999px; cursor:pointer; transition:background .15s ease; }
        .prod-toggle-individual:hover { background:#bbf7d0; }
        .prod-table-wrap.hide-individual .prod-individual-row { display:none; }
        .prod-modal-actions {
            position: sticky;
            bottom: 0;
            margin: 16px -22px -22px -22px;
            padding: 14px 22px calc(14px + env(safe-area-inset-bottom));
            background: linear-gradient(180deg, rgba(255,255,255,.55), rgba(255,255,255,.97) 45%);
            border-top: 1px solid rgba(0,0,0,.06);
            border-bottom-left-radius: 28px;
            border-bottom-right-radius: 28px;
            z-index: 3;
        }
        @media (max-width: 640px) {
            .prod-modal-actions { margin: 12px -16px -16px -16px; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); border-bottom-left-radius: 22px; border-bottom-right-radius: 22px; }
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

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            {{ $errors->first() ?: 'Revisa los campos del formulario. Hay información pendiente o inválida.' }}
        </div>
    @endif

    <div class="prod-hero">
        <div class="relative z-[2] flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="prod-chip mb-4">
                    <span class="prod-chip-dot"></span>
                    <span>{{ $farm->name ?? 'Tu finca' }}</span>
                </div>

                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">
                    Producción
                </h1>

                <p class="text-sm text-gray-500 mt-2 max-w-2xl">
                    Controla litros producidos, uso interno, disponibilidad de leche y comportamiento productivo con filtros por período y por animal.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="prod-btn prod-btn-primary" data-open-modal="dailyMilkModal">
                    + Total diario
                </button>

                <button type="button" class="prod-btn prod-btn-secondary" data-open-modal="milkModal">
                    + Leche por animal
                </button>

                <button type="button" class="prod-btn prod-btn-secondary" data-open-modal="milkUsageModal">
                    + Uso interno
                </button>

                <button type="button" class="prod-btn prod-btn-secondary" data-open-modal="meatModal">
                    + Registrar carne
                </button>

            </div>
        </div>
    </div>

    <div class="prod-kpi-grid">
        <div class="prod-kpi-card">
            <div class="prod-kpi-icon milk">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l1.3 3.5H7.7L9 3zm-2 5h10l-1.2 12H8.2L7 8z"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-500">Litros producidos</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900">{{ $formatLiters($totalMilkLiters) }}</div>
            <div class="mt-2 text-xs text-gray-500">{{ $rangeLabel }}</div>
        </div>

        <div class="prod-kpi-card">
            <div class="prod-kpi-icon milk">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M5 12c2.5-4.5 5-7 7-7s4.5 2.5 7 7c-2.5 4.5-5 7-7 7s-4.5-2.5-7-7Z"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m-3-3h6"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-500">Disponibles por vender</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900">{{ $formatLiters($totalSaleableMilkLiters) }}</div>
            @if(($milkPricePerLiter ?? 0) > 0)
                <div class="mt-1 text-lg font-extrabold text-green-700">≈ ${{ number_format($estimatedSaleableMilkValue ?? 0, 0, ',', '.') }}</div>
                <div class="text-[11px] text-gray-400">aprox. a ${{ number_format($milkPricePerLiter, 0, ',', '.') }}/L (quincena pasada)</div>
            @endif
            <div class="mt-2 text-xs text-gray-500">Listos {{ $formatLiters($totalMilkReadyForSaleLiters) }} L · vendidos {{ $formatLiters($totalSoldMilkLiters) }} L</div>
        </div>

        <div class="prod-kpi-card">
            <div class="prod-kpi-icon money">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m-6-5c1 1.8 3.2 2.8 6 2.8 3.3 0 5.5-1.3 5.5-3.4 0-2-1.7-3-5.5-3.7-3.6-.7-5.5-1.6-5.5-3.6S8.7 4.2 12 4.2c2.5 0 4.4.8 5.4 2.2"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-500">Venta registrada</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900">{{ $formatLiters($totalSoldMilkLiters) }} L</div>
            <div class="mt-2 text-xs text-gray-500">Descontados desde Finanzas</div>
        </div>

        <div class="prod-kpi-card">
            <div class="prod-kpi-icon filter">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l1 4H7l1-4Zm-1 7h10l-1 8H8l-1-8Z"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M10 15h4"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-500">Para terneras</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900">{{ $formatLiters($totalCalfMilkLiters) }} L</div>
            <div class="mt-2 text-xs text-gray-500">Descontado de la venta</div>
        </div>

        <div class="prod-kpi-card">
            <div class="prod-kpi-icon money">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M6 8h12l-1 12H7L6 8Zm3-4h6l1 4H8l1-4Z"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M10 12h4"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-500">Consumo interno</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900">{{ $formatLiters($totalConsumedMilkLiters) }} L</div>
            <div class="mt-2 text-xs text-gray-500">Descontado de la venta</div>
        </div>

        <div class="prod-kpi-card">
            <div class="prod-kpi-icon meat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M7 13c0-3.3 2.7-6 6-6 2.8 0 5 2.2 5 5 0 2.2-1.2 4.1-3 5.1l-5.2 2.9A3.5 3.5 0 0 1 5 17c0-2 1.1-3.2 2-4Z"/>
                </svg>
            </div>
            <div class="text-sm font-semibold text-gray-500">Peso / carne</div>
            <div class="mt-2 text-3xl font-extrabold text-gray-900">{{ number_format($totalMeatWeight, 2, ',', '.') }} kg</div>
            <div class="mt-2 text-xs text-gray-500">Peso registrado o ganancia estimada</div>
        </div>

    </div>

    <div class="prod-filter-card">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="prod-section-title">Uso interno de leche</div>
                <div class="prod-section-subtitle">Registros diarios que se descuentan de los litros vendidos.</div>
            </div>

            <button type="button" class="prod-btn prod-btn-secondary" data-open-modal="milkUsageModal">
                Registrar uso
            </button>
        </div>

        @if(($recentMilkUsages ?? collect())->count())
            <div class="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-3">
                @foreach($recentMilkUsages as $usage)
                    <div class="prod-mini-kpi">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs font-bold uppercase text-gray-500">{{ optional($usage->usage_date)->format('d/m/Y') }}</div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @if((float) $usage->calf_liters > 0)
                                        <span class="prod-badge milk">Terneras {{ $formatLiters($usage->calf_liters) }} L</span>
                                    @endif

                                    @if((float) $usage->consumed_liters > 0)
                                        <span class="prod-badge filter">Consumo {{ $formatLiters($usage->consumed_liters) }} L</span>
                                    @endif
                                </div>
                                <div class="mt-2 text-sm font-extrabold text-gray-900">
                                    Total descontado {{ $formatLiters($usage->total_used_liters) }} L
                                </div>
                            </div>

                            <form method="POST" action="{{ route('production.milk-usage.destroy', $usage) }}" onsubmit="return confirm('¿Eliminar este uso interno de leche?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="prod-delete-btn">Eliminar</button>
                            </form>
                        </div>

                        @if($usage->notes)
                            <div class="mt-3 text-xs text-gray-500">{{ $usage->notes }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="prod-empty is-compact mt-5">
                Aún no hay uso interno de leche en este rango.
            </div>
        @endif

    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="prod-filter-card xl:col-span-2">
            <div class="prod-section-title">Filtros de análisis</div>
            <div class="prod-section-subtitle">Consulta producción por rango de tiempo y por animal.</div>

            <form method="GET" action="{{ route('production.index') }}" class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
                <div class="prod-field">
                    <label>Rango</label>
                    <select name="range" id="rangeSelect" class="prod-select">
                        <option value="fortnight_current" {{ $selectedRange === 'fortnight_current' ? 'selected' : '' }}>Quincena en curso (a hoy)</option>
                        <option value="fortnight" {{ $selectedRange === 'fortnight' ? 'selected' : '' }}>Última quincena (cerrada)</option>
                        <option value="month" {{ $selectedRange === 'month' ? 'selected' : '' }}>Último mes</option>
                        <option value="custom" {{ $selectedRange === 'custom' ? 'selected' : '' }}>Personalizado</option>
                    </select>
                </div>

                <div class="prod-field">
                    <label>Tipo</label>
                    <select name="production_type" class="prod-select">
                        <option value="all" {{ ($selectedProductionType ?? 'all') === 'all' ? 'selected' : '' }}>Todos</option>
                        <option value="milk" {{ ($selectedProductionType ?? 'all') === 'milk' ? 'selected' : '' }}>Leche</option>
                        <option value="meat" {{ ($selectedProductionType ?? 'all') === 'meat' ? 'selected' : '' }}>Carne</option>
                    </select>
                </div>

                <div class="prod-field xl:col-span-2">
                    <label>Animal</label>
                    <select name="animal_id" class="prod-select">
                        <option value="">Todos los animales</option>
                        @foreach($animals as $animal)
                            <option value="{{ $animal->id }}" {{ (string) $selectedAnimalId === (string) $animal->id ? 'selected' : '' }}>
                                {{ $animal->name ?: 'Animal '.$animal->id }}{{ $animal->ear_tag ? ' · '.$animal->ear_tag : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="prod-field">
                    <label>Ordenar</label>
                    <select name="sort" class="prod-select">
                        <option value="date_desc" {{ ($selectedSort ?? 'date_desc') === 'date_desc' ? 'selected' : '' }}>Más recientes</option>
                        <option value="date_asc" {{ ($selectedSort ?? 'date_desc') === 'date_asc' ? 'selected' : '' }}>Más antiguos</option>
                        <option value="animal_asc" {{ ($selectedSort ?? 'date_desc') === 'animal_asc' ? 'selected' : '' }}>Animal A-Z</option>
                        <option value="type_asc" {{ ($selectedSort ?? 'date_desc') === 'type_asc' ? 'selected' : '' }}>Tipo</option>
                    </select>
                </div>

                <div class="prod-field custom-range-field {{ $selectedRange === 'custom' ? '' : 'hidden' }}">
                    <label>Fecha inicial</label>
                    <input type="date" name="start_date" value="{{ request('start_date', optional($startDate)->format('Y-m-d')) }}" class="prod-input">
                </div>

                <div class="prod-field custom-range-field {{ $selectedRange === 'custom' ? '' : 'hidden' }}">
                    <label>Fecha final</label>
                    <input type="date" name="end_date" value="{{ request('end_date', optional($endDate)->format('Y-m-d')) }}" class="prod-input">
                </div>

                <div class="md:col-span-2 xl:col-span-6 flex flex-wrap gap-3 pt-1">
                    <button type="submit" class="prod-btn prod-btn-primary">
                        Aplicar filtros
                    </button>

                    <a href="{{ route('production.index') }}" class="prod-btn prod-btn-secondary">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>

        <div class="prod-filter-card">
            <div class="prod-section-title">Resumen rápido</div>
            <div class="prod-section-subtitle">Lectura corta del desempeño reciente.</div>

            <div class="mt-6 grid grid-cols-2 gap-4">
                <div class="prod-mini-kpi">
                    <div class="text-sm font-semibold text-gray-500">Producidos hoy</div>
                    <div class="mt-2 text-2xl font-extrabold text-gray-900">{{ $formatLiters($milkToday) }} L</div>
                </div>

                <div class="prod-mini-kpi">
                    <div class="text-sm font-semibold text-gray-500">Producidos quincena (a hoy)</div>
                    <div class="mt-2 text-2xl font-extrabold text-gray-900">{{ $formatLiters($milkFortnight) }} L</div>
                </div>

                <div class="prod-mini-kpi">
                    <div class="text-sm font-semibold text-gray-500">Producidos mes</div>
                    <div class="mt-2 text-2xl font-extrabold text-gray-900">{{ $formatLiters($milkMonth) }} L</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="prod-chart-card">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
                <div>
                    <div class="prod-section-title">Reporte de leche</div>
                    <div class="prod-section-subtitle">Comportamiento de litros producidos en el rango seleccionado.</div>
                </div>

                <span class="prod-badge milk">Leche</span>
            </div>

            @if(collect($milkChartData ?? [])->isEmpty())
                <div class="prod-empty is-compact">Sin datos de leche para mostrar en este rango.</div>
            @else
            <div class="prod-chart-shell">
                <div class="prod-chart-canvas-wrap">
                    <canvas id="milkChart" class="prod-chart-canvas"></canvas>
                </div>

                <div class="prod-chart-legend">
                    <div class="prod-chart-legend-item">
                        <span class="prod-chart-legend-dot" style="background:#22c55e;"></span>
                        Litros producidos
                    </div>
                    <div class="prod-chart-legend-item">
                        <span class="prod-chart-legend-dot" style="background:#166534;"></span>
                        Línea base
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="prod-chart-card">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
                <div>
                    <div class="prod-section-title">Reporte de carne</div>
                    <div class="prod-section-subtitle">Peso registrado o ganancia productiva por fecha.</div>
                </div>

                <span class="prod-badge meat">Carne</span>
            </div>

            @if(collect($meatChartData ?? [])->isEmpty())
                <div class="prod-empty is-compact">Sin datos de carne para mostrar en este rango.</div>
            @else
            <div class="prod-chart-shell">
                <div class="prod-chart-canvas-wrap">
                    <canvas id="meatChart" class="prod-chart-canvas"></canvas>
                </div>

                <div class="prod-chart-legend">
                    <div class="prod-chart-legend-item">
                        <span class="prod-chart-legend-dot" style="background:#ef4444;"></span>
                        Kg registrados
                    </div>
                    <div class="prod-chart-legend-item">
                        <span class="prod-chart-legend-dot" style="background:#b91c1c;"></span>
                        Línea base
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="prod-table-card">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
            <div>
                <div class="prod-section-title">Historial de producción</div>
                <div class="prod-section-subtitle">Registros productivos del rango seleccionado.</div>
            </div>

            @php
                $prodIndividualCount = ($productionTableRows ?? collect())->filter(fn ($r) => in_array((($r['data'] ?? [])['type'] ?? null), ['milk_summary', 'milk', 'meat'], true))->count();
            @endphp
            <div class="flex items-center gap-2">
                @if($prodIndividualCount)
                    <button type="button" id="toggleIndividualRows" class="prod-toggle-individual" data-count="{{ $prodIndividualCount }}" aria-expanded="false">Ver {{ $prodIndividualCount }} registros individuales</button>
                @endif
                <span class="prod-badge filter">{{ ($productionTableRows ?? collect())->count() }} registros</span>
            </div>
        </div>

        @if(($productionTableRows ?? collect())->count())
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <button type="button" id="bulkDeleteMilkBtn" class="prod-delete-btn" style="padding:8px 14px;font-size:.85rem;">Borrar seleccionados (<span id="bulkMilkCount">0</span>)</button>
                <span class="text-xs text-gray-500">Marca las casillas de los registros de leche para borrar varios a la vez.</span>
            </div>
            <form id="bulkMilkForm" method="POST" action="{{ route('production.milk.bulk-destroy') }}" class="hidden">@csrf</form>

            <div class="prod-table-wrap prod-table overflow-x-auto hide-individual" id="productionHistoryTable">
                <table>
                    <thead>
                        <tr>
                            <th style="width:48px;text-align:center;"><input type="checkbox" id="milkSelectAll" title="Seleccionar todos los de leche" style="width:22px;height:22px;cursor:pointer;accent-color:#16a34a;"></th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Animal</th>
                            <th>Periodo</th>
                            <th>Cantidad</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($productionTableRows ?? collect() as $row)
                            @php
                                $rowType = $row['row_type'] ?? 'record';
                                $rowData = $row['data'] ?? null;
                                $item = $rowType === 'record' ? $rowData : null;
                                $itemType = is_array($item) ? ($item['type'] ?? null) : null;
                                $itemRecord = is_array($item) ? ($item['record'] ?? null) : null;
                                $itemAnimal = $itemType === 'milk_total' ? null : $itemRecord?->animal;
                                $canEditItem = $itemType === 'milk'
                                    ? $itemAnimal?->canRegisterMilkProduction()
                                    : ($itemType === 'meat' ? $itemAnimal?->canRegisterProduction() : false);
                                $itemDate = is_array($item) ? ($item['date'] ?? null) : null;
                                $itemTypeLabel = is_array($item) ? ($item['type_label'] ?? 'Registro') : 'Registro';
                                $itemAnimalName = is_array($item) ? ($item['animal_name'] ?? 'Sin animal') : 'Sin animal';
                                $itemPeriod = is_array($item) ? ($item['period'] ?? 'Sin período') : 'Sin período';
                                $itemQuantity = is_array($item) ? ($item['quantity'] ?? '0') : '0';
                                $itemSummary = is_array($item) ? ($item['summary'] ?? null) : null;
                            @endphp

                            @if(is_array($item))
                                <tr class="{{ $itemType === 'milk_summary' ? 'prod-summary-row' : '' }}{{ in_array($itemType, ['milk_summary', 'milk', 'meat'], true) ? ' prod-individual-row' : '' }}">
                                    <td style="text-align:center;">
                                        @if(in_array($itemType, ['milk_total', 'milk_summary', 'milk'], true))
                                            @php
                                                $bulkTarget = $itemType === 'milk_total'
                                                    ? 'daily:'.$itemRecord->id
                                                    : ($itemType === 'milk'
                                                        ? 'milk:'.$itemRecord->id
                                                        : 'day:'.($item['animal_id'] ?: 0).':'.optional($itemDate)->format('Y-m-d'));
                                            @endphp
                                            <input type="checkbox" class="milk-bulk-check" data-target="{{ $bulkTarget }}" style="width:22px;height:22px;cursor:pointer;accent-color:#16a34a;">
                                        @endif
                                    </td>
                                    <td>{{ optional($itemDate)->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="prod-badge {{ in_array($itemType, ['milk', 'milk_total', 'milk_summary'], true) ? 'milk' : 'meat' }}">
                                            {{ $itemTypeLabel }}
                                        </span>
                                    </td>
                                    <td>{{ $itemAnimalName }}</td>
                                    <td>
                                        {{ $itemPeriod }}
                                        @if(is_array($itemSummary))
                                            <div class="mt-1 text-xs font-semibold text-gray-500">
                                                Mañana {{ $formatLiters((float) ($itemSummary['morning_liters'] ?? 0)) }} L ·
                                                Tarde {{ $formatLiters((float) ($itemSummary['afternoon_liters'] ?? 0)) }} L
                                            </div>
                                        @endif
                                    </td>
                                    <td>{{ $itemQuantity }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            @if($itemType === 'milk_total' && $itemRecord)
                                                <button type="button"
                                                        class="prod-edit-btn"
                                                        data-open-modal="editDailyMilkModal"
                                                        data-edit-action="{{ route('production.milk-daily.update', $itemRecord) }}"
                                                        data-edit-date="{{ optional($itemRecord->production_date)->format('Y-m-d') }}"
                                                        data-edit-liters="{{ $itemRecord->liters }}"
                                                        data-edit-notes="{{ e($itemRecord->notes) }}">
                                                    Editar
                                                </button>

                                                <form method="POST" action="{{ route('production.milk-daily.destroy', $itemRecord) }}" onsubmit="return confirm('¿Eliminar este registro diario de leche?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="prod-delete-btn">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @elseif($itemType === 'milk_summary' && is_array($itemSummary))
                                                <button type="button"
                                                        class="prod-edit-btn"
                                                        data-open-modal="editMilkDayModal"
                                                        data-edit-action="{{ $item['edit_action'] ?? route('production.milk-day.update') }}"
                                                        data-edit-animal="{{ $item['animal_id'] }}"
                                                        data-edit-date="{{ optional($itemDate)->format('Y-m-d') }}"
                                                        data-edit-morning="{{ $itemSummary['morning_liters'] ?? 0 }}"
                                                        data-edit-afternoon="{{ $itemSummary['afternoon_liters'] ?? 0 }}">
                                                    Editar
                                                </button>

                                                <form method="POST" action="{{ route('production.milk-day.destroy') }}" onsubmit="return confirm('¿Eliminar la producción de leche de este animal para este día?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="animal_id" value="{{ $item['animal_id'] }}">
                                                    <input type="hidden" name="production_date" value="{{ optional($itemDate)->format('Y-m-d') }}">
                                                    <button type="submit" class="prod-delete-btn">
                                                        Eliminar
                                                    </button>
                                                </form>
                                            @elseif($itemType === 'milk')
                                                @if($canEditItem)
                                                    <button type="button"
                                                            class="prod-edit-btn"
                                                            data-open-modal="editMilkModal"
                                                            data-edit-action="{{ route('production.milk.update', $itemRecord) }}"
                                                            data-edit-animal="{{ $itemRecord->animal_id }}"
                                                            data-edit-date="{{ optional($itemRecord->production_date)->format('Y-m-d') }}"
                                                            data-edit-period="{{ $itemRecord->period }}"
                                                            data-edit-liters="{{ $itemRecord->liters }}"
                                                            data-edit-notes="{{ e($itemRecord->notes) }}">
                                                        Editar
                                                    </button>
                                                @endif

                                                @if($itemRecord)
                                                    <form method="POST" action="{{ route('production.milk.destroy', $itemRecord) }}" onsubmit="return confirm('¿Eliminar este registro de leche?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="prod-delete-btn">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                @if($canEditItem)
                                                    <button type="button"
                                                            class="prod-edit-btn"
                                                            data-open-modal="editMeatModal"
                                                            data-edit-action="{{ route('production.meat.update', $itemRecord) }}"
                                                            data-edit-animal="{{ $itemRecord->animal_id }}"
                                                            data-edit-date="{{ optional($itemRecord->production_date)->format('Y-m-d') }}"
                                                            data-edit-weight="{{ $itemRecord->weight_kg }}"
                                                            data-edit-gain="{{ $itemRecord->weight_gain_kg }}"
                                                            data-edit-notes="{{ e($itemRecord->notes) }}">
                                                        Editar
                                                    </button>
                                                @endif

                                                @if($itemRecord)
                                                    <form method="POST" action="{{ route('production.meat.destroy', $itemRecord) }}" onsubmit="return confirm('¿Eliminar este registro de carne?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="prod-delete-btn">
                                                            Eliminar
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="prod-empty">
                Aún no hay registros de producción para este filtro.
            </div>
        @endif
    </div>

    <div class="prod-table-card">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
            <div>
                <div class="prod-section-title">Leche vendida</div>
                <div class="prod-section-subtitle">Registros creados en Finanzas dentro del mismo rango seleccionado.</div>
            </div>

            <span class="prod-badge milk">{{ ($milkSales ?? collect())->count() }} registros</span>
        </div>

        @if(($milkSales ?? collect())->count())
            <div class="prod-table-wrap prod-table overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Registro</th>
                            <th>Categoría</th>
                            <th>Litros vendidos</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($milkSales as $sale)
                            @php
                                $saleStartDate = $sale->milk_sale_start_date ?: $sale->transaction_date;
                                $saleEndDate = $sale->milk_sale_end_date ?: $sale->transaction_date;
                            @endphp
                            <tr>
                                <td>
                                    {{ optional($sale->transaction_date)->format('d/m/Y') }}
                                    @if($saleStartDate && $saleEndDate)
                                        <div class="text-xs font-bold text-gray-500 mt-1">
                                            {{ $saleStartDate->format('d/m/Y') }} - {{ $saleEndDate->format('d/m/Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="font-extrabold text-gray-900">{{ $sale->title }}</td>
                                <td>{{ $sale->category ?: 'Venta de leche' }}</td>
                                <td class="font-extrabold text-green-700">{{ $formatLiters($sale->milk_liters_sold) }} L</td>
                                <td>{{ $sale->reference ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="prod-empty">
                No hay ventas de leche registradas en este rango.
            </div>
        @endif
    </div>
</div>

<div id="editMilkDayModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Editar leche del día</h3>
                    <p class="text-sm text-gray-500 mt-1">Actualiza los litros de mañana y tarde para este animal.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editMilkDayModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="{{ route('production.milk-day.update') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-edit-milk-day-form>
                @csrf
                @method('PATCH')

                <input type="hidden" name="animal_id" data-edit-field="animal_id">

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" class="prod-input" data-edit-field="production_date" required>
                </div>

                <div class="prod-field">
                    <label>Litros mañana</label>
                    <input type="number" step="0.01" min="0" name="liters_morning" class="prod-input" data-edit-field="liters_morning">
                </div>

                <div class="prod-field">
                    <label>Litros tarde</label>
                    <input type="number" step="0.01" min="0" name="liters_afternoon" class="prod-input" data-edit-field="liters_afternoon">
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas para ambos períodos</label>
                    <textarea name="notes" rows="2" class="prod-textarea" placeholder="Opcional"></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-primary">Guardar cambios</button>
                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editMilkDayModal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editMilkModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Editar leche</h3>
                    <p class="text-sm text-gray-500 mt-1">Corrige fecha, animal, período, litros o notas.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editMilkModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="#" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-edit-milk-form>
                @csrf
                @method('PATCH')

                <div class="prod-field">
                    <label>Animal</label>
                    <select name="animal_id" class="prod-select" data-edit-field="animal_id" required>
                        @foreach($milkAnimals ?? collect() as $animal)
                            <option value="{{ $animal->id }}">
                                {{ $animal->name ?: 'Animal '.$animal->id }}{{ $animal->ear_tag ? ' · '.$animal->ear_tag : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" class="prod-input" data-edit-field="production_date" required>
                </div>

                <div class="prod-field">
                    <label>Periodo</label>
                    <select name="period" class="prod-select" data-edit-field="period" required>
                        <option value="mañana">Mañana</option>
                        <option value="tarde">Tarde</option>
                    </select>
                </div>

                <div class="prod-field">
                    <label>Litros</label>
                    <input type="number" step="0.01" min="0.01" name="liters" class="prod-input" data-edit-field="liters" required>
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="3" class="prod-textarea" data-edit-field="notes"></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-primary">Guardar cambios</button>
                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editMilkModal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editMeatModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Editar carne</h3>
                    <p class="text-sm text-gray-500 mt-1">Corrige fecha, animal, peso, ganancia o notas.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editMeatModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="#" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-edit-meat-form>
                @csrf
                @method('PATCH')

                <div class="prod-field">
                    <label>Animal</label>
                    <select name="animal_id" class="prod-select" data-edit-field="animal_id" required>
                        @foreach($productionAnimals ?? collect() as $animal)
                            <option value="{{ $animal->id }}">
                                {{ $animal->name ?: 'Animal '.$animal->id }}{{ $animal->ear_tag ? ' · '.$animal->ear_tag : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" class="prod-input" data-edit-field="production_date" required>
                </div>

                <div class="prod-field">
                    <label>Peso actual (kg)</label>
                    <input type="number" step="0.01" min="0" name="weight_kg" class="prod-input" data-edit-field="weight_kg">
                </div>

                <div class="prod-field">
                    <label>Ganancia de peso (kg)</label>
                    <input type="number" step="0.01" min="0" name="weight_gain_kg" class="prod-input" data-edit-field="weight_gain_kg">
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="3" class="prod-textarea" data-edit-field="notes"></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-danger">Guardar cambios</button>
                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editMeatModal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="dailyMilkModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Registrar total diario</h3>
                    <p class="text-sm text-gray-500 mt-1">Usa este registro cuando tengas los litros totales de la finca.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="dailyMilkModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="{{ route('production.milk-daily.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" value="{{ old('production_date', now()->format('Y-m-d')) }}" class="prod-input" required>
                </div>

                <div class="prod-field">
                    <label>Litros totales</label>
                    <input type="number" step="0.01" min="0.01" name="liters" value="{{ old('liters') }}" class="prod-input" placeholder="Ej: 304" required>
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="2" class="prod-textarea" placeholder="Ej: Medición del tanque, pago quincenal o ajuste del día.">{{ old('notes') }}</textarea>
                </div>

                <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800 md:col-span-2">
                    Los registros por animal de ese día quedan como detalle.
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-primary">
                        Guardar total diario
                    </button>

                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="dailyMilkModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="editDailyMilkModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Editar finca completa</h3>
                    <p class="text-sm text-gray-500 mt-1">Corrige fecha, litros totales o notas.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editDailyMilkModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="#" class="grid grid-cols-1 md:grid-cols-2 gap-4" data-edit-daily-milk-form>
                @csrf
                @method('PATCH')

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" class="prod-input" data-edit-field="production_date" required>
                </div>

                <div class="prod-field">
                    <label>Litros totales</label>
                    <input type="number" step="0.01" min="0.01" name="liters" class="prod-input" data-edit-field="liters" required>
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="3" class="prod-textarea" data-edit-field="notes"></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-primary">Guardar cambios</button>
                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="editDailyMilkModal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="milkModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Registrar leche</h3>
                    <p class="text-sm text-gray-500 mt-1">Registra litros por animal para conservar el historial productivo.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="milkModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="{{ route('production.milk.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf

                <div class="prod-field">
                    <label>Animal</label>
                    <select name="animal_id" class="prod-select" required>
                        <option value="">Selecciona un animal</option>
                        @foreach($milkAnimals ?? collect() as $animal)
                            <option value="{{ $animal->id }}">
                                {{ $animal->name ?: 'Animal '.$animal->id }}{{ $animal->ear_tag ? ' · '.$animal->ear_tag : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(($milkAnimals ?? collect())->isEmpty())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 md:col-span-2">
                        No hay hembras con parto registrado para producción de leche.
                    </div>
                @endif

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" value="{{ now()->format('Y-m-d') }}" class="prod-input" required>
                </div>

                <div class="prod-field">
                    <label>Periodo</label>
                    <select name="period" class="prod-select" data-production-period-select required>
                        <option value="">Selecciona</option>
                        <option value="mañana">Mañana</option>
                        <option value="tarde">Tarde</option>
                        <option value="mañana_tarde">Mañana y Tarde</option>
                    </select>
                </div>

                <div class="prod-field" data-single-liters-field>
                    <label>Litros</label>
                    <input type="number" step="0.01" min="0.01" name="liters" class="prod-input" placeholder="Ej: 18.5">
                </div>

                <div class="prod-field hidden" data-dual-liters-field>
                    <label>Litros mañana</label>
                    <input type="number" step="0.01" min="0.01" name="liters_morning" class="prod-input" placeholder="Ej: 18.5">
                </div>

                <div class="prod-field hidden" data-dual-liters-field>
                    <label>Litros tarde</label>
                    <input type="number" step="0.01" min="0.01" name="liters_afternoon" class="prod-input" placeholder="Ej: 16.2">
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="2" class="prod-textarea" placeholder="Observaciones del registro..."></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-primary">
                        Guardar leche
                    </button>

                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="milkModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="milkUsageModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Registrar uso interno</h3>
                    <p class="text-sm text-gray-500 mt-1">Estos litros se descuentan de la leche disponible para venta del día.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="milkUsageModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="{{ route('production.milk-usage.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="usage_date" value="{{ old('usage_date', now()->format('Y-m-d')) }}" class="prod-input" required>
                </div>

                <div class="prod-field">
                    <label>Tipo de uso</label>
                    <select name="usage_type" class="prod-select" data-milk-usage-type-select required>
                        <option value="" @selected(old('usage_type') === null || old('usage_type') === '')>Selecciona un uso</option>
                        <option value="calves" @selected(old('usage_type') === 'calves')>Terneras</option>
                        <option value="internal" @selected(old('usage_type') === 'internal')>Uso interno / finca</option>
                        <option value="both" @selected(old('usage_type') === 'both')>Terneras y uso de la finca</option>
                    </select>
                </div>

                <div class="prod-kpi-card" data-milk-usage-field="calves">
                    <div class="prod-kpi-icon filter">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M8 4h8l1 4H7l1-4Zm-1 7h10l-1 8H8l-1-8Z"/>
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M10 15h4"/>
                        </svg>
                    </div>
                    <div class="prod-field">
                        <label>Litros para terneras</label>
                        <input type="number" step="0.01" min="0" name="calf_liters" value="{{ old('calf_liters') }}" class="prod-input" placeholder="Ej: 12">
                    </div>
                </div>

                <div class="prod-kpi-card" data-milk-usage-field="internal">
                    <div class="prod-kpi-icon money">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M6 8h12l-1 12H7L6 8Zm3-4h6l1 4H8l1-4Z"/>
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M10 12h4"/>
                        </svg>
                    </div>
                    <div class="prod-field">
                        <label>Litros consumidos</label>
                        <input type="number" step="0.01" min="0" name="consumed_liters" value="{{ old('consumed_liters') }}" class="prod-input" placeholder="Ej: 5">
                    </div>
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="3" class="prod-textarea" placeholder="Observaciones del uso interno...">{{ old('notes') }}</textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-primary">
                        Guardar uso interno
                    </button>

                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="milkUsageModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="meatModal" class="prod-modal-backdrop">
    <div class="prod-modal-frame">
        <div class="prod-modal-panel">
            <div class="prod-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Registrar carne</h3>
                    <p class="text-sm text-gray-500 mt-1">Registra peso o ganancia para conservar el historial productivo.</p>
                </div>

                <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="meatModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="{{ route('production.meat.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf

                <div class="prod-field">
                    <label>Animal</label>
                    <select name="animal_id" class="prod-select" required>
                        <option value="">Selecciona un animal</option>
                        @foreach($productionAnimals ?? collect() as $animal)
                            <option value="{{ $animal->id }}">
                                {{ $animal->name ?: 'Animal '.$animal->id }}{{ $animal->ear_tag ? ' · '.$animal->ear_tag : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if(($productionAnimals ?? collect())->isEmpty())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 md:col-span-2">
                        No hay animales disponibles para registrar producción.
                    </div>
                @endif

                <div class="prod-field">
                    <label>Fecha</label>
                    <input type="date" name="production_date" value="{{ now()->format('Y-m-d') }}" class="prod-input" required>
                </div>

                <div class="prod-field">
                    <label>Peso actual (kg)</label>
                    <input type="number" step="0.01" min="0" name="weight_kg" class="prod-input" placeholder="Ej: 420">
                </div>

                <div class="prod-field">
                    <label>Ganancia de peso (kg)</label>
                    <input type="number" step="0.01" min="0" name="weight_gain_kg" class="prod-input" placeholder="Ej: 12.8">
                </div>

                <div class="prod-field md:col-span-2">
                    <label>Notas</label>
                    <textarea name="notes" rows="4" class="prod-textarea" placeholder="Observaciones del registro..."></textarea>
                </div>

                <div class="md:col-span-2 flex flex-wrap gap-3 prod-modal-actions">
                    <button type="submit" class="prod-btn prod-btn-danger">
                        Guardar carne
                    </button>

                    <button type="button" class="prod-btn prod-btn-secondary" data-close-modal="meatModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rangeSelect = document.getElementById('rangeSelect');
    const customFields = document.querySelectorAll('.custom-range-field');

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

    const modalIds = ['dailyMilkModal', 'milkModal', 'milkUsageModal', 'meatModal', 'editDailyMilkModal', 'editMilkDayModal', 'editMilkModal', 'editMeatModal'];

    modalIds.forEach((id) => {
        const modal = document.getElementById(id);
        if (modal) {
            document.body.appendChild(modal);
        }
    });

    const openModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.style.display = 'block';

        requestAnimationFrame(() => {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        });
    };

    const closeModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.remove('is-open');

        setTimeout(() => {
            modal.style.display = 'none';
            if (!document.querySelector('.prod-modal-backdrop.is-open')) {
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            }
        }, 240);
    };

    const syncProductionPeriodFields = (select) => {
        const scope = select.closest('.prod-modal-panel') || select.form || select.closest('form');
        if (!scope) return;

        const isDual = select.value === 'mañana_tarde';
        scope.querySelectorAll('[data-single-liters-field]').forEach((field) => {
            field.classList.toggle('hidden', isDual);
            field.style.display = isDual ? 'none' : '';
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = isDual;
            });
        });
        scope.querySelectorAll('[data-dual-liters-field]').forEach((field) => {
            field.classList.toggle('hidden', !isDual);
            field.style.display = isDual ? '' : 'none';
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !isDual;
            });
        });
    };

    const syncMilkUsageFields = (select) => {
        const scope = select.closest('.prod-modal-panel') || select.form || select.closest('form');
        if (!scope) return;

        const value = select.value || '';
        const showCalves = value === 'calves' || value === 'both';
        const showInternal = value === 'internal' || value === 'both';

        scope.querySelectorAll('[data-milk-usage-field="calves"]').forEach((field) => {
            field.classList.toggle('hidden', !showCalves);
            field.classList.toggle('md:col-span-2', showCalves && !showInternal);
            field.style.display = showCalves ? '' : 'none';
            field.querySelectorAll('input').forEach((input) => {
                input.disabled = !showCalves;
            });
        });

        scope.querySelectorAll('[data-milk-usage-field="internal"]').forEach((field) => {
            field.classList.toggle('hidden', !showInternal);
            field.classList.toggle('md:col-span-2', showInternal && !showCalves);
            field.style.display = showInternal ? '' : 'none';
            field.querySelectorAll('input').forEach((input) => {
                input.disabled = !showInternal;
            });
        });
    };

    document.addEventListener('change', (e) => {
        const select = e.target.closest('[data-production-period-select]');
        const usageSelect = e.target.closest('[data-milk-usage-type-select]');

        if (select) {
            syncProductionPeriodFields(select);
        }

        if (usageSelect) {
            syncMilkUsageFields(usageSelect);
        }
    });

    document.querySelectorAll('[data-production-period-select]').forEach((select) => {
        syncProductionPeriodFields(select);
    });

    document.querySelectorAll('[data-milk-usage-type-select]').forEach((select) => {
        syncMilkUsageFields(select);
    });

    const setFieldValue = (form, field, value) => {
        const input = form?.querySelector(`[data-edit-field="${field}"]`);
        if (input) {
            input.value = value ?? '';
        }
    };

    const fillMilkEditForm = (button) => {
        const form = document.querySelector('[data-edit-milk-form]');
        if (!form) return;

        form.action = button.dataset.editAction || '#';
        setFieldValue(form, 'animal_id', button.dataset.editAnimal);
        setFieldValue(form, 'production_date', button.dataset.editDate);
        setFieldValue(form, 'period', button.dataset.editPeriod);
        setFieldValue(form, 'liters', button.dataset.editLiters);
        setFieldValue(form, 'notes', button.dataset.editNotes);
    };

    const fillMilkDayEditForm = (button) => {
        const form = document.querySelector('[data-edit-milk-day-form]');
        if (!form) return;

        form.action = button.dataset.editAction || '{{ route('production.milk-day.update') }}';
        setFieldValue(form, 'animal_id', button.dataset.editAnimal);
        setFieldValue(form, 'production_date', button.dataset.editDate);
        setFieldValue(form, 'liters_morning', button.dataset.editMorning);
        setFieldValue(form, 'liters_afternoon', button.dataset.editAfternoon);
    };

    const fillDailyMilkEditForm = (button) => {
        const form = document.querySelector('[data-edit-daily-milk-form]');
        if (!form) return;

        form.action = button.dataset.editAction || '#';
        setFieldValue(form, 'production_date', button.dataset.editDate);
        setFieldValue(form, 'liters', button.dataset.editLiters);
        setFieldValue(form, 'notes', button.dataset.editNotes);
    };

    const fillMeatEditForm = (button) => {
        const form = document.querySelector('[data-edit-meat-form]');
        if (!form) return;

        form.action = button.dataset.editAction || '#';
        setFieldValue(form, 'animal_id', button.dataset.editAnimal);
        setFieldValue(form, 'production_date', button.dataset.editDate);
        setFieldValue(form, 'weight_kg', button.dataset.editWeight);
        setFieldValue(form, 'weight_gain_kg', button.dataset.editGain);
        setFieldValue(form, 'notes', button.dataset.editNotes);
    };

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const modalId = button.getAttribute('data-open-modal');

            if (modalId === 'editMilkModal') {
                fillMilkEditForm(button);
            }

            if (modalId === 'editMilkDayModal') {
                fillMilkDayEditForm(button);
            }

            if (modalId === 'editDailyMilkModal') {
                fillDailyMilkEditForm(button);
            }

            if (modalId === 'editMeatModal') {
                fillMeatEditForm(button);
            }

            openModal(modalId);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeModal(button.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('.prod-modal-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop || e.target.classList.contains('prod-modal-frame')) {
                closeModal(backdrop.id);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.prod-modal-backdrop.is-open').forEach((modal) => {
                closeModal(modal.id);
            });
        }
    });

    const milkChartData = @json($milkChartJson ? json_decode($milkChartJson, true) : []);
    const meatChartData = @json($meatChartJson ? json_decode($meatChartJson, true) : []);

    const drawLineChart = (canvasId, data, options = {}) => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        const parent = canvas.parentElement;
        const dpr = window.devicePixelRatio || 1;
        const width = Math.max(parent.clientWidth, 300);
        const height = Math.max(parent.clientHeight, 240);

        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const padding = { top: 20, right: 18, bottom: 42, left: 18 };
        const innerWidth = width - padding.left - padding.right;
        const innerHeight = height - padding.top - padding.bottom;

        if (!data.length) {
            ctx.fillStyle = '#6b7280';
            ctx.font = '14px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Sin datos para mostrar', width / 2, height / 2);
            return;
        }

        const values = data.map(item => Number(item.value || 0));
        const maxValue = Math.max(...values, 1);

        const points = data.map((item, index) => {
            const x = padding.left + (data.length === 1 ? innerWidth / 2 : (innerWidth / (data.length - 1)) * index);
            const y = padding.top + innerHeight - ((Number(item.value || 0) / maxValue) * innerHeight);
            return { x, y, label: item.label, value: Number(item.value || 0) };
        });

        ctx.strokeStyle = 'rgba(17,24,39,.08)';
        ctx.lineWidth = 1;

        for (let i = 0; i < 4; i++) {
            const y = padding.top + (innerHeight / 3) * i;
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(width - padding.right, y);
            ctx.stroke();
        }

        const lineColor = options.lineColor || '#22c55e';
        const fillColor = options.fillColor || 'rgba(34,197,94,.12)';

        const linePath = new Path2D();
        points.forEach((point, index) => {
            if (index === 0) {
                linePath.moveTo(point.x, point.y);
            } else {
                const prev = points[index - 1];
                const cpx = (prev.x + point.x) / 2;
                linePath.bezierCurveTo(cpx, prev.y, cpx, point.y, point.x, point.y);
            }
        });

        const areaPath = new Path2D();
        points.forEach((point, index) => {
            if (index === 0) {
                areaPath.moveTo(point.x, point.y);
            } else {
                const prev = points[index - 1];
                const cpx = (prev.x + point.x) / 2;
                areaPath.bezierCurveTo(cpx, prev.y, cpx, point.y, point.x, point.y);
            }
        });
        areaPath.lineTo(points[points.length - 1].x, height - padding.bottom);
        areaPath.lineTo(points[0].x, height - padding.bottom);
        areaPath.closePath();

        ctx.fillStyle = fillColor;
        ctx.fill(areaPath);

        ctx.strokeStyle = lineColor;
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke(linePath);

        points.forEach((point) => {
            ctx.beginPath();
            ctx.fillStyle = '#ffffff';
            ctx.arc(point.x, point.y, 5, 0, Math.PI * 2);
            ctx.fill();

            ctx.beginPath();
            ctx.fillStyle = lineColor;
            ctx.arc(point.x, point.y, 3, 0, Math.PI * 2);
            ctx.fill();
        });

        ctx.fillStyle = '#6b7280';
        ctx.font = '12px sans-serif';
        ctx.textAlign = 'center';

        points.forEach((point) => {
            ctx.fillText(point.label, point.x, height - 14);
        });
    };

    const renderCharts = () => {
        drawLineChart('milkChart', milkChartData, {
            lineColor: '#22c55e',
            fillColor: 'rgba(34,197,94,.12)'
        });

        drawLineChart('meatChart', meatChartData, {
            lineColor: '#ef4444',
            fillColor: 'rgba(239,68,68,.12)'
        });
    };

    renderCharts();
    window.addEventListener('resize', renderCharts);
});
        (function () {
            var btn = document.getElementById('toggleIndividualRows');
            var table = document.getElementById('productionHistoryTable');
            if (!btn || !table) { return; }
            var count = btn.getAttribute('data-count');
            btn.addEventListener('click', function () {
                var hidden = table.classList.toggle('hide-individual');
                btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
                btn.textContent = (hidden ? 'Ver ' : 'Ocultar ') + count + ' registros individuales';
            });
        })();
    </script>

<script>
(function () {
    var selectAll = document.getElementById('milkSelectAll');
    var bulkBtn = document.getElementById('bulkDeleteMilkBtn');
    var bulkForm = document.getElementById('bulkMilkForm');
    var countEl = document.getElementById('bulkMilkCount');
    if (!bulkBtn || !bulkForm) return;
    function checks() { return Array.prototype.slice.call(document.querySelectorAll('.milk-bulk-check')); }
    function updateCount() { if (countEl) countEl.textContent = checks().filter(function (c) { return c.checked; }).length; }
    if (selectAll) { selectAll.addEventListener('change', function () { checks().forEach(function (c) { c.checked = selectAll.checked; }); updateCount(); }); }
    document.addEventListener('change', function (e) { if (e.target && e.target.classList && e.target.classList.contains('milk-bulk-check')) updateCount(); });
    bulkBtn.addEventListener('click', function () {
        var selected = checks().filter(function (c) { return c.checked; });
        if (selected.length === 0) { alert('Selecciona al menos un registro de leche para borrar.'); return; }
        if (!confirm('¿Borrar ' + selected.length + ' registro(s) de leche seleccionados? Esta acción no se puede deshacer.')) return;
        bulkForm.querySelectorAll('input[name="targets[]"]').forEach(function (i) { i.remove(); });
        selected.forEach(function (c) { var input = document.createElement('input'); input.type = 'hidden'; input.name = 'targets[]'; input.value = c.getAttribute('data-target'); bulkForm.appendChild(input); });
        bulkForm.submit();
    });
    updateCount();
})();
</script>
@endsection
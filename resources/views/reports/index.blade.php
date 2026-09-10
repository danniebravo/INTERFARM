@extends('layouts.app')

@php
    $reportTypeLabels = [
        'general' => 'General',
        'inventario' => 'Inventario',
        'produccion' => 'Producción',
        'reproduccion' => 'Reproducción y crías',
        'finanzas' => 'Finanzas',
        'eventos' => 'Eventos',
    ];

    $exportModules = [
        'todo' => 'Todo',
        'inventario' => 'Inventario',
        'lotes' => 'Lotes',
        'produccion' => 'Producción',
        'finanzas' => 'Finanzas',
        'eventos' => 'Eventos',
        'lactancia' => 'Lactancia',
        'prenez' => 'Preñez',
        'crias' => 'Crías',
    ];

    $purposeLabels = [
        'leche' => 'Leche',
        'carne' => 'Carne',
        'doble_proposito' => 'Doble propósito',
        'crianza' => 'Crianza',
    ];

    $statusLabels = [
        'activo' => 'Activo',
        'vendido' => 'Vendido',
        'fallecido' => 'Fallecido',
    ];

    $showInventory = in_array($filters['report_type'], ['general', 'inventario'], true);
    $showProduction = in_array($filters['report_type'], ['general', 'produccion'], true);
    $showFinance = in_array($filters['report_type'], ['general', 'finanzas'], true);
    $showEvents = in_array($filters['report_type'], ['general', 'eventos'], true);
    $showReproduction = in_array($filters['report_type'], ['general', 'reproduccion'], true);
    $productionChartMax = max(1, (float) collect($chartData['production'])->flatMap(fn ($row) => [$row['milk'], $row['meat']])->max());
    $financeChartMax = max(1, (float) collect($chartData['finance'])->flatMap(fn ($row) => [$row['income'], $row['expense']])->max());
    $statusChartMax = max(1, (float) collect($chartData['status'])->max('value'));
    $printFileName = \Illuminate\Support\Str::slug('reporte-' . $farm->name . '-' . now()->format('Y-m-d'), '-');
    $reportLogoFile = public_path('images/logo.png');
    $reportLogoSrc = asset('images/logo.png');

    if (file_exists($reportLogoFile)) {
        $reportLogoSrc = 'data:' . mime_content_type($reportLogoFile) . ';base64,' . base64_encode(file_get_contents($reportLogoFile));
    }
@endphp

@section('title', $printFileName)

@section('content')

<style>
    .report-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.66);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .report-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.88);
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        outline: none;
    }

    .report-input:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 4px rgba(34,197,94,.12);
    }

    .report-select-wrap {
        position: relative;
    }

    .report-select-wrap select {
        appearance: none;
        padding-right: 38px;
    }

    .report-select-wrap::after {
        content: "";
        position: absolute;
        right: 14px;
        top: 50%;
        width: 9px;
        height: 9px;
        border-right: 2px solid #64748b;
        border-bottom: 2px solid #64748b;
        transform: translateY(-70%) rotate(45deg);
        pointer-events: none;
    }

    .report-chart-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 16px;
    }

    @media (min-width: 1024px) {
        .report-chart-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .report-chart-bars {
        height: 210px;
        display: flex;
        align-items: flex-end;
        gap: 10px;
        padding-top: 16px;
    }

    .report-chart-group {
        min-width: 0;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .report-chart-columns {
        width: 100%;
        height: 160px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 4px;
    }

    .report-bar {
        width: 11px;
        min-height: 4px;
        border-radius: 999px 999px 4px 4px;
        box-shadow: inset 0 -1px 0 rgba(15,23,42,.16);
    }

    .report-bar.milk {
        background: #15803d;
    }

    .report-bar.meat {
        background: #0f766e;
    }

    .report-bar.income {
        background: #166534;
    }

    .report-bar.expense {
        background: #b91c1c;
    }

    .report-chart-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
        font-size: 11px;
        font-weight: 900;
        color: #475569;
    }

    .report-chart-legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .report-chart-legend i {
        width: 10px;
        height: 10px;
        border-radius: 999px;
        display: inline-block;
    }

    .report-print-header {
        display: none;
    }

    .report-print-footer {
        display: none;
    }

    .report-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .report-table {
        min-width: 980px;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .report-table th {
        background: rgba(248,250,252,.9);
        color: #475569;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .04em;
        text-transform: uppercase;
        text-align: left;
        padding: 12px;
        border-bottom: 1px solid rgba(148,163,184,.24);
    }

    .report-table td {
        padding: 12px;
        border-bottom: 1px solid rgba(148,163,184,.18);
        font-size: 13px;
        font-weight: 650;
        color: #334155;
        vertical-align: top;
    }

    .dark .report-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .report-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .report-select-wrap::after {
        border-color: #cbd5e1;
    }

    .dark .report-table th {
        background: rgba(30,41,59,.92);
        color: #cbd5e1;
        border-bottom-color: rgba(148,163,184,.24);
    }

    .dark .report-table td {
        color: #e2e8f0;
        border-bottom-color: rgba(148,163,184,.16);
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 28mm 10mm 16mm;
        }

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html,
        body {
            background: #fff !important;
            color: #111827 !important;
        }

        aside, header, .no-print, .report-screen-hero, .whatsapp-help-float {
            display: none !important;
        }

        main {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
        }

        .space-y-6 {
            padding-top: 22mm !important;
        }

        .space-y-6 > :not([hidden]) ~ :not([hidden]) {
            margin-top: 12px !important;
        }

        .report-card {
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            background: #fff !important;
            border-radius: 10px !important;
            page-break-inside: avoid;
            break-inside: avoid;
            padding: 12px !important;
        }

        .report-card,
        .report-card div,
        .report-card span,
        .report-card p,
        .report-card h1,
        .report-card h2,
        .report-card h3,
        .report-card td {
            color: #0f172a !important;
            opacity: 1 !important;
        }

        .report-card .text-gray-500,
        .report-card .text-gray-400,
        .report-card .text-gray-300,
        .report-card .dark\:text-gray-300,
        .report-card .dark\:text-gray-400 {
            color: #334155 !important;
        }

        .report-card .text-green-700,
        .report-card .dark\:text-green-200,
        .report-card .text-green-600 {
            color: #15803d !important;
        }

        .report-card .text-red-600,
        .report-card .dark\:text-red-300 {
            color: #b91c1c !important;
        }

        .report-card .text-white,
        .report-card .dark\:text-white {
            color: #0f172a !important;
        }

        section.report-card {
            page-break-inside: auto;
            break-inside: auto;
        }

        .report-chart-grid .report-card {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .report-print-header {
            display: flex !important;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            height: 17mm;
            padding: 0 0 9px;
            border-bottom: 2px solid #166534;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            z-index: 50;
        }

        .report-print-brand {
            display: flex !important;
            align-items: center;
            gap: 10px;
        }

        .report-print-logo-box {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 150px;
            height: 50px;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            background: #fff;
            padding: 6px;
        }

        .report-print-header img {
            display: block !important;
            max-height: 40px;
            max-width: 136px;
            width: auto;
            object-fit: contain;
        }

        .report-print-kicker {
            font-size: 9px !important;
            font-weight: 900 !important;
            letter-spacing: .08em !important;
            color: #166534 !important;
            text-transform: uppercase !important;
        }

        .report-print-title {
            font-size: 16px !important;
            line-height: 1.15 !important;
            font-weight: 950 !important;
            color: #0f172a !important;
        }

        .report-print-meta {
            font-size: 9.5px !important;
            font-weight: 800 !important;
            color: #475569 !important;
        }

        .report-print-footer {
            display: flex !important;
            position: fixed;
            right: 0;
            bottom: -11mm;
            left: 0;
            justify-content: space-between;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            font-size: 9px;
            font-weight: 800;
            color: #64748b;
            background: #fff;
        }

        .grid,
        .report-chart-grid {
            gap: 10px !important;
        }

        .report-chart-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .report-chart-bars {
            height: 145px !important;
            gap: 6px !important;
            padding-top: 8px !important;
        }

        .report-chart-columns {
            height: 105px !important;
        }

        .report-bar {
            width: 8px !important;
            max-height: 105px !important;
            border: 1px solid rgba(15,23,42,.28) !important;
            box-shadow: none !important;
        }

        .report-bar.milk {
            background: #14532d !important;
        }

        .report-bar.meat {
            background: #115e59 !important;
        }

        .report-bar.income {
            background: #166534 !important;
        }

        .report-bar.expense {
            background: #991b1b !important;
        }

        .report-chart-legend {
            color: #334155 !important;
            font-size: 9px !important;
            margin-top: 6px !important;
        }

        .report-table-wrap {
            overflow: visible !important;
            margin-top: 8px !important;
        }

        .report-table {
            min-width: 0 !important;
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
            border-spacing: 0 !important;
        }

        .report-table thead {
            display: table-header-group;
        }

        .report-table tbody {
            display: table-row-group;
        }

        .report-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .report-table th,
        .report-table td {
            padding: 6px 7px !important;
            font-size: 9.5px !important;
            line-height: 1.25 !important;
            white-space: normal !important;
            overflow-wrap: anywhere !important;
            border: 1px solid #94a3b8 !important;
            color: #0f172a !important;
            opacity: 1 !important;
        }

        .report-table th {
            background: #ecfdf5 !important;
            color: #052e16 !important;
            border-color: #64748b !important;
            font-weight: 950 !important;
        }

        .report-table td {
            background: #fff !important;
            font-weight: 800 !important;
        }

        .report-table tbody tr:nth-child(even) td {
            background: #f8fafc !important;
        }

        h1 {
            font-size: 22px !important;
        }

        h2 {
            font-size: 14px !important;
            break-after: avoid;
            page-break-after: avoid;
        }

        p,
        .text-sm {
            font-size: 10px !important;
        }

        .text-3xl {
            font-size: 20px !important;
            line-height: 1.15 !important;
        }
    }
</style>

<div class="space-y-6">
    <div class="report-print-header">
        <div class="report-print-brand">
            <div class="report-print-logo-box">
                <img src="{{ $reportLogoSrc }}" alt="InterFarm">
            </div>
            <div>
                <div class="report-print-kicker">Informe operativo</div>
                <div class="report-print-title">Reporte InterFarm</div>
            </div>
        </div>
        <div class="text-right">
            <div class="report-print-title">{{ $farm->name }}</div>
            <div class="report-print-meta">{{ $filters['start_date'] }} a {{ $filters['end_date'] }}</div>
            <div class="report-print-meta">Generado el {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    <div class="report-print-footer">
        <span>InterFarm · {{ $farm->name }}</span>
        <span>Generado el {{ now()->format('d/m/Y H:i') }}</span>
    </div>

    <div class="report-screen-hero flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                {{ $farm->name }}
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Genera reportes de tu finca</h1>
            <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-300">
                Analiza lo que está pasando en tu operación: animales, lotes, producción, finanzas y eventos en un solo lugar.
            </p>
        </div>

        <button type="button"
                id="printReportButton"
                data-print-title="{{ $printFileName }}"
                class="no-print inline-flex items-center justify-center rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
            Descargar o imprimir
        </button>
    </div>

    <section class="report-card no-print p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Exportar datos</h2>
                <p class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">
                    Descarga en CSV o Excel (.xlsx) con los filtros actuales, por modulo.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($exportModules as $module => $label)
                    <div class="inline-flex h-[38px] items-center gap-2 rounded-xl border border-green-600/20 bg-green-50 px-3 dark:border-green-400/20 dark:bg-green-500/10">
                        <span class="text-xs font-black text-green-700 dark:text-green-200">{{ $label }}</span>
                        <a href="{{ route('reports.export', array_merge(['module' => $module], request()->except('page'))) }}"
                           class="text-[11px] font-black text-green-700 underline transition hover:text-green-900 dark:text-green-200">CSV</a>
                        <a href="{{ route('reports.export', array_merge(['module' => $module, 'format' => 'xlsx'], request()->except('page'))) }}"
                           class="text-[11px] font-black text-green-700 underline transition hover:text-green-900 dark:text-green-200">Excel</a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <form method="GET" action="{{ route('reports.index') }}" class="report-card no-print p-5" data-auto-filter>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Desde</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="report-input mt-1">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Hasta</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="report-input mt-1">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Tipo</label>
                <div class="report-select-wrap mt-1">
                    <select name="report_type" class="report-input">
                        @foreach($reportTypeLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['report_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Animal</label>
                <div class="report-select-wrap mt-1">
                    <select name="animal_id" class="report-input">
                        <option value="">Todos</option>
                        @foreach($animalsForFilter as $animal)
                            <option value="{{ $animal->id }}" @selected((int) $filters['animal_id'] === (int) $animal->id)>
                                {{ $animal->name ?: 'Animal #' . $animal->id }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Lote</label>
                <div class="report-select-wrap mt-1">
                    <select name="lot_id" class="report-input">
                        <option value="">Todos</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" @selected((int) $filters['lot_id'] === (int) $lot->id)>{{ $lot->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Buscar</label>
                <input type="search" name="search" value="{{ $filters['search'] }}" class="report-input mt-1" placeholder="Nombre, código..." data-live-search>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Sexo</label>
                <div class="report-select-wrap mt-1">
                    <select name="sex" class="report-input">
                        <option value="">Todos</option>
                        <option value="hembra" @selected($filters['sex'] === 'hembra')>Hembras</option>
                        <option value="macho" @selected($filters['sex'] === 'macho')>Machos</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Propósito</label>
                <div class="report-select-wrap mt-1">
                    <select name="purpose" class="report-input">
                        <option value="">Todos</option>
                        @foreach($purposeLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['purpose'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Estado</label>
                <div class="report-select-wrap mt-1">
                    <select name="status" class="report-input">
                        <option value="">Todos</option>
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-end gap-2 xl:col-span-3">
                <button class="inline-flex h-[42px] items-center justify-center rounded-xl bg-green-600 px-4 text-sm font-black text-white transition hover:bg-green-700">
                    Ver reporte
                </button>
                <a href="{{ route('reports.index') }}" class="inline-flex h-[42px] items-center justify-center rounded-xl border border-gray-200 bg-white px-4 text-sm font-black text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-slate-950 dark:text-gray-100">
                    Restablecer
                </a>
            </div>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="report-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Animales encontrados</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ $summary['animals'] }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">{{ $summary['female_animals'] }} hembras · {{ $summary['male_animals'] }} machos</div>
        </div>

        <div class="report-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Leche registrada</div>
            <div class="mt-2 text-3xl font-black text-green-700 dark:text-green-200">{{ number_format($summary['milk_liters'], 1, ',', '.') }} L</div>
        </div>

        <div class="report-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Peso registrado</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ number_format($summary['meat_weight'], 1, ',', '.') }} kg</div>
        </div>

        <div class="report-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Resultado financiero</div>
            <div class="mt-2 text-3xl font-black {{ $summary['balance'] < 0 ? 'text-red-600 dark:text-red-300' : 'text-green-700 dark:text-green-200' }}">
                ${{ number_format($summary['balance'], 0, ',', '.') }}
            </div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">
                +${{ number_format($summary['income'], 0, ',', '.') }} · -${{ number_format($summary['expense'], 0, ',', '.') }}
            </div>
        </div>
    </div>

    <section class="report-chart-grid">
        <div class="report-card p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Producción</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Compara los registros productivos del periodo.</p>
                </div>
                <div class="text-xs font-black text-green-700 dark:text-green-200">Últimos 12 puntos</div>
            </div>

            <div class="report-chart-bars">
                @forelse($chartData['production'] as $row)
                    <div class="report-chart-group">
                        <div class="report-chart-columns">
                            <div class="report-bar milk" style="height: {{ max(4, ($row['milk'] / $productionChartMax) * 150) }}px"></div>
                            <div class="report-bar meat" style="height: {{ max(4, ($row['meat'] / $productionChartMax) * 150) }}px"></div>
                        </div>
                        <div class="text-[11px] font-black text-gray-500 dark:text-gray-400">{{ $row['label'] }}</div>
                    </div>
                @empty
                    <div class="flex h-full w-full items-center justify-center text-sm font-bold text-gray-500 dark:text-gray-300">Sin datos de producción</div>
                @endforelse
            </div>
            <div class="report-chart-legend">
                <span><i style="background:#15803d"></i> Leche</span>
                <span><i style="background:#0f766e"></i> Carne / peso</span>
            </div>
        </div>

        <div class="report-card p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Finanzas</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Revisa cómo se movió el dinero en el rango elegido.</p>
                </div>
                <div class="text-xs font-black text-green-700 dark:text-green-200">Balance</div>
            </div>

            <div class="report-chart-bars">
                @forelse($chartData['finance'] as $row)
                    <div class="report-chart-group">
                        <div class="report-chart-columns">
                            <div class="report-bar income" style="height: {{ max(4, ($row['income'] / $financeChartMax) * 150) }}px"></div>
                            <div class="report-bar expense" style="height: {{ max(4, ($row['expense'] / $financeChartMax) * 150) }}px"></div>
                        </div>
                        <div class="text-[11px] font-black text-gray-500 dark:text-gray-400">{{ $row['label'] }}</div>
                    </div>
                @empty
                    <div class="flex h-full w-full items-center justify-center text-sm font-bold text-gray-500 dark:text-gray-300">Sin movimientos financieros</div>
                @endforelse
            </div>
            <div class="report-chart-legend">
                <span><i style="background:#166534"></i> Ingresos</span>
                <span><i style="background:#b91c1c"></i> Gastos</span>
            </div>
        </div>

        <div class="report-card p-5">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Situación del inventario</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Estado actual de los animales filtrados.</p>
            </div>

            <div class="mt-5 space-y-4">
                @foreach($chartData['status'] as $row)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-sm font-black text-gray-700 dark:text-gray-200">
                            <span>{{ $row['label'] }}</span>
                            <span>{{ $row['value'] }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full" style="width: {{ ($row['value'] / $statusChartMax) * 100 }}%; background: {{ $row['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if($showInventory)
        <section class="report-card p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Animales por lote</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Identifica cómo está distribuido el ganado en la finca.</p>
                </div>
            </div>

            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Total</th>
                            <th>Hembras</th>
                            <th>Machos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($animalsByLot as $row)
                            <tr>
                                <td>{{ $row['lot'] }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $row['females'] }}</td>
                                <td>{{ $row['males'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No encontramos animales con la combinación de filtros seleccionada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Listado del inventario</h2>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Código</th>
                            <th>Lote</th>
                            <th>Sexo</th>
                            <th>Propósito</th>
                            <th>Estado</th>
                            <th>Peso actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($animals as $animal)
                            <tr>
                                <td>{{ $animal->name ?: 'Animal #' . $animal->id }}</td>
                                <td>{{ $animal->internal_code ?: $animal->ear_tag ?: '—' }}</td>
                                <td>{{ $animal->lot?->name ?: 'Sin lote' }}</td>
                                <td>{{ ucfirst($animal->sex ?? '—') }}</td>
                                <td>{{ $purposeLabels[$animal->purpose] ?? ($animal->purpose ?: '—') }}</td>
                                <td>{{ $animal->statusLabel() }}</td>
                                <td>{{ $animal->weight_current ? number_format((float) $animal->weight_current, 1, ',', '.') . ' kg' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No hay animales para mostrar en este reporte.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($showReproduction)
        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Reporte de lactancia</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Vacas en lactancia: fecha del último parto y cuánto llevan dando leche.</p>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Arete / código</th>
                            <th>Fecha de parto</th>
                            <th>Tiempo dando leche</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lactationReport as $row)
                            <tr>
                                <td>{{ $row['animal']->name ?: 'Animal #' . $row['animal']->id }}</td>
                                <td>{{ $row['animal']->ear_tag ?: ($row['animal']->internal_code ?: '—') }}</td>
                                <td>{{ optional($row['calving_date'])->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $row['milk_time'] }}</td>
                                <td>{{ $row['dried'] ? 'Seca' : 'En producción' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No hay vacas en lactancia registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Reporte de preñez</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Vacas preñadas: fecha de preñez, cómo o quién la preñó y fecha de secado.</p>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Arete / código</th>
                            <th>Fecha de preñez</th>
                            <th>Servicio / padre</th>
                            <th>Fecha de secado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pregnancyReport as $row)
                            <tr>
                                <td>{{ $row['animal']->name ?: 'Animal #' . $row['animal']->id }}</td>
                                <td>{{ $row['animal']->ear_tag ?: ($row['animal']->internal_code ?: '—') }}</td>
                                <td>{{ optional($row['service_date'])->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $row['how'] }}</td>
                                <td>{{ optional($row['dry_off_date'])->format('d/m/Y') ?: '—' }} <span class="text-xs text-gray-400">({{ $row['dry_off_confirmed'] ? 'confirmado' : 'estimado' }})</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No hay vacas preñadas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Reporte de crías</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Crías con su madre, fecha de nacimiento y edad.</p>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Cría</th>
                            <th>Arete / código</th>
                            <th>Fecha de nacimiento</th>
                            <th>Madre</th>
                            <th>Edad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offspringReport as $row)
                            <tr>
                                <td>{{ $row['animal']->name ?: 'Animal #' . $row['animal']->id }}</td>
                                <td>{{ $row['animal']->ear_tag ?: ($row['animal']->internal_code ?: '—') }}</td>
                                <td>{{ optional($row['birth_date'])->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $row['dam'] }}</td>
                                <td>{{ $row['age'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No hay crías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($showProduction)
        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Rendimiento por animal</h2>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Lote</th>
                            <th>Leche</th>
                            <th>Carne / ganancia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productionByAnimal as $row)
                            <tr>
                                <td>{{ $row['animal']?->name ?: 'Animal' }}</td>
                                <td>{{ $row['animal']?->lot?->name ?: 'Sin lote' }}</td>
                                <td>{{ number_format($row['milk'], 1, ',', '.') }} L</td>
                                <td>{{ number_format($row['meat'], 1, ',', '.') }} kg</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No hay producción registrada para este rango y filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($showFinance)
        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Detalle de ingresos y gastos</h2>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($financialRecords as $record)
                            <tr>
                                <td>{{ optional($record->transaction_date)->format('d/m/Y') }}</td>
                                <td>{{ in_array($record->type, ['income', 'ingreso'], true) ? 'Ingreso' : 'Gasto' }}</td>
                                <td>{{ $record->title }}</td>
                                <td>{{ $record->category ?: '—' }}</td>
                                <td>${{ number_format((float) $record->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">No hay ingresos ni gastos registrados en este rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($showEvents)
        <section class="report-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Eventos y actividades</h2>
            <div class="report-table-wrap mt-4">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Evento</th>
                            <th>Animal</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr>
                                <td>{{ optional($event->start_datetime ?: $event->event_date)->format('d/m/Y') }}</td>
                                <td>{{ $event->title }}</td>
                                <td>{{ $event->animal?->name ?: '—' }}</td>
                                <td>{{ ucfirst($event->type ?? 'general') }}</td>
                                <td>{{ ucfirst($event->status ?? 'pending') }}</td>
                                <td>{{ ucfirst($event->priority ?? 'medium') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No hay eventos programados o registrados en este rango.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const printButton = document.getElementById('printReportButton');
    const originalTitle = document.title;

    if (!printButton) return;

    printButton.addEventListener('click', async () => {
        document.title = printButton.dataset.printTitle || originalTitle;

        const images = Array.from(document.querySelectorAll('.report-print-header img'));

        await Promise.all(images.map((image) => {
            if (image.complete) return Promise.resolve();
            if (image.decode) return image.decode().catch(() => {});

            return new Promise((resolve) => {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            });
        }));

        window.print();
    });

    window.addEventListener('afterprint', () => {
        document.title = originalTitle;
    });
});
</script>
@endsection
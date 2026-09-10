@extends('layouts.app')

@section('title', 'Administración SaaS')

@section('content')
@php
    $revenueChartMax = max(1, (float) $revenueChart->max('value'));
    $plansUrl = Route::has('admin.saas.plans.index') ? route('admin.saas.plans.index') : route('admin.saas.index');
    $billingUrl = Route::has('admin.saas.billing.index') ? route('admin.saas.billing.index') : route('admin.saas.index');
    $settingsUrl = Route::has('admin.saas.settings.index') ? route('admin.saas.settings.index') : route('admin.saas.index');
    $clientChartValues = $clientChart->pluck('value')->values();
    $lineChartW = 360;
    $lineChartH = 150;
    $linePadX = 18;
    $linePadTop = 18;
    $linePadBottom = 24;
    $lineUsableW = $lineChartW - ($linePadX * 2);
    $lineUsableH = $lineChartH - $linePadTop - $linePadBottom;
    $lineMax = max(1, (int) $clientChartValues->max());
    $clientPoints = $clientChart->values()->map(function ($point, $index) use ($clientChart, $linePadX, $linePadTop, $lineUsableW, $lineUsableH, $lineMax) {
        $steps = max(1, $clientChart->count() - 1);
        $x = $linePadX + (($lineUsableW / $steps) * $index);
        $y = $linePadTop + ($lineUsableH - (((int) $point['value'] / $lineMax) * $lineUsableH));

        return [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'label' => $point['label'],
            'value' => (int) $point['value'],
        ];
    });
    $clientLinePath = $clientPoints->map(fn ($point, $index) => ($index === 0 ? 'M' : 'L') . " {$point['x']} {$point['y']}")->implode(' ');
    $clientAreaPath = $clientPoints->isNotEmpty()
        ? $clientLinePath . " L {$clientPoints->last()['x']} " . ($lineChartH - 10) . " L {$clientPoints->first()['x']} " . ($lineChartH - 10) . ' Z'
        : '';
@endphp

<style>
    .saas-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.66);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .saas-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.88);
        padding: 11px 13px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        outline: none;
    }

    .saas-input:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 4px rgba(34,197,94,.12);
    }

    .saas-select-wrap {
        position: relative;
    }

    .saas-select-wrap select {
        appearance: none;
        padding-right: 38px;
    }

    .saas-select-wrap::after {
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

    .saas-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 900;
    }

    .saas-chart-bar {
        min-height: 10px;
        border-radius: 999px;
        background: linear-gradient(90deg, #16a34a, #22c55e);
        box-shadow: 0 10px 20px rgba(22,163,74,.18);
    }

    .saas-line-chart {
        position: relative;
        overflow: hidden;
        border-radius: 20px;
        border: 1px solid rgba(148,163,184,.18);
        background: linear-gradient(180deg, rgba(34,197,94,.08), rgba(255,255,255,.24));
    }

    .saas-line-labels {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 8px;
        color: #64748b;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
    }

    .saas-subnav-link {
        border-radius: 999px;
        border: 1px solid rgba(148,163,184,.32);
        background: rgba(255,255,255,.66);
        color: #475569;
        padding: 9px 13px;
        font-size: 12px;
        font-weight: 900;
        transition: .18s ease;
    }

    .saas-subnav-link.active,
    .saas-subnav-link:hover {
        border-color: rgba(34,197,94,.42);
        background: rgba(34,197,94,.12);
        color: #15803d;
    }

    .dark .saas-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .saas-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .saas-select-wrap::after {
        border-color: #cbd5e1;
    }

    .dark .saas-subnav-link {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.82);
        color: #cbd5e1;
    }

    .dark .saas-subnav-link.active,
    .dark .saas-subnav-link:hover {
        border-color: rgba(74,222,128,.35);
        background: rgba(34,197,94,.14);
        color: #bbf7d0;
    }

    .dark .saas-card .bg-white\/70,
    .dark .saas-card .bg-white {
        background: rgba(2,6,23,.58) !important;
    }

    .dark .saas-card .text-gray-900,
    .dark .saas-card .text-gray-700 {
        color: #f8fafc !important;
    }

    .dark .saas-line-chart {
        border-color: rgba(148,163,184,.18);
        background: linear-gradient(180deg, rgba(34,197,94,.10), rgba(2,6,23,.34));
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Super administrador
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Centro de administración SaaS</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Gestiona clientes, planes comerciales, configuración de pagos y el canal de ayuda de InterFarm desde un solo lugar.
            </p>
        </div>

        <a href="{{ route('admin.users.index') }}"
           class="inline-flex items-center justify-center rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
            Ver clientes
        </a>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.saas.index') }}" class="saas-subnav-link {{ $activeSection === 'overview' ? 'active' : '' }}">Resumen</a>
        <a href="{{ route('admin.users.index') }}" class="saas-subnav-link">Clientes</a>
        <a href="{{ $billingUrl }}" class="saas-subnav-link">Facturación</a>
        <a href="{{ $plansUrl }}" class="saas-subnav-link {{ $activeSection === 'plans' ? 'active' : '' }}">Planes</a>
        <a href="{{ $settingsUrl }}" class="saas-subnav-link">Configuración</a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if(! $plansReady || ! $settingsReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            Faltan migraciones del módulo SaaS. Ejecuta las migraciones para habilitar creación de planes y configuración global.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="saas-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Clientes</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ $stats['clients'] }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">{{ $stats['active_clients'] }} activos</div>
        </div>

        <div class="saas-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Fincas creadas</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ $stats['farms'] }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">Operaciones registradas</div>
        </div>

        <div class="saas-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Animales totales</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ number_format((int) $stats['animals'], 0, ',', '.') }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">Entre todos los clientes</div>
        </div>

        <div class="saas-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Paquetes</div>
            <div class="mt-2 text-3xl font-black text-green-700 dark:text-green-200">{{ $stats['plans'] }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">Planes comerciales</div>
        </div>

        <div class="saas-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Ingresos estimados</div>
            <div class="mt-2 text-3xl font-black text-green-700 dark:text-green-200">
                ${{ number_format((float) $stats['estimated_mrr'], 0, ',', '.') }}
            </div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">
                {{ $stats['billable_clients'] ?? 0 }} cliente(s) con plan activo.
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="saas-card p-5">
            <div class="mb-5">
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Clientes nuevos</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Registros de los últimos 6 meses.</p>
            </div>

            <div class="saas-line-chart p-4">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <div class="text-3xl font-black text-gray-900 dark:text-white">{{ $clientChart->sum('value') }}</div>
                        <div class="text-xs font-bold text-gray-500 dark:text-gray-300">Clientes registrados en el periodo</div>
                    </div>
                    <span class="rounded-full bg-green-100 px-3 py-1 text-xs font-black text-green-700 dark:bg-green-500/10 dark:text-green-200">
                        6 meses
                    </span>
                </div>

                <svg viewBox="0 0 {{ $lineChartW }} {{ $lineChartH }}" class="h-[150px] w-full">
                    <defs>
                        <linearGradient id="clientAreaGradient" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#22c55e" stop-opacity=".22"/>
                            <stop offset="100%" stop-color="#22c55e" stop-opacity="0"/>
                        </linearGradient>
                    </defs>

                    @for($i = 0; $i < 4; $i++)
                        @php $gridY = $linePadTop + (($lineUsableH / 3) * $i); @endphp
                        <line x1="{{ $linePadX }}" y1="{{ $gridY }}" x2="{{ $lineChartW - $linePadX }}" y2="{{ $gridY }}" stroke="currentColor" class="text-slate-200 dark:text-slate-700" stroke-width="1" stroke-dasharray="4 8"/>
                    @endfor

                    <path d="{{ $clientAreaPath }}" fill="url(#clientAreaGradient)"/>
                    <path d="{{ $clientLinePath }}" fill="none" stroke="#16a34a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>

                    @foreach($clientPoints as $point)
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4.5" fill="#16a34a" stroke="white" stroke-width="3"/>
                    @endforeach
                </svg>

                <div class="saas-line-labels">
                    @foreach($clientPoints as $point)
                        <span>{{ $point['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="saas-card p-5">
            <div class="mb-5">
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Ingresos por planes</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Valor mensual estimado solo con clientes activos fuera del periodo de prueba.</p>
            </div>

            <div class="space-y-4">
                @forelse($revenueChart as $point)
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div class="truncate text-sm font-black text-gray-900 dark:text-white">{{ $point['label'] }}</div>
                            <div class="text-sm font-black text-green-700 dark:text-green-200">${{ number_format((float) $point['value'], 0, ',', '.') }}</div>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-slate-950">
                            <div class="saas-chart-bar h-full" style="width: {{ max(5, ($point['value'] / $revenueChartMax) * 100) }}%;"></div>
                        </div>
                        <div class="mt-1 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $point['subscribers'] }} cliente(s)</div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300">
                        Asigna planes a clientes para ver la gráfica de ingresos.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="saas-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Clientes recientes</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Últimos usuarios registrados y sus fincas.</p>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            @forelse($recentClients as $client)
                <div class="rounded-2xl border border-gray-200 bg-white/70 p-4 transition hover:border-green-300 hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:border-green-400/40">
                    <div class="truncate text-sm font-black text-gray-900 dark:text-white">{{ $client->full_name ?: $client->email }}</div>
                    <div class="truncate text-xs font-bold text-gray-500 dark:text-gray-400">{{ $client->email }}</div>
                    <div class="mt-2 text-xs font-bold text-gray-500 dark:text-gray-400">{{ $client->farms->count() }} finca(s)</div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('admin.users.show', $client) }}"
                           class="inline-flex rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-black text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-slate-950 dark:text-gray-100">
                            Gestionar
                        </a>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300 md:col-span-2 xl:col-span-4">
                    Aún no hay clientes registrados.
                </div>
            @endforelse
        </div>
    </section>

</div>
@endsection

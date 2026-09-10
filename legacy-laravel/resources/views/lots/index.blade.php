@extends('layouts.app')

@section('title', 'Lotes')

@section('content')

<link rel="stylesheet" href="/vendor/leaflet.css">

@php
    $lots = $lots ?? collect();

    if (! function_exists('interfarmNormalizePolygonForPreview')) {
        function interfarmNormalizePolygonForPreview($polygon) {
            if (! is_array($polygon) || count($polygon) < 3) {
                return null;
            }

            $points = collect($polygon)
                ->map(function ($point) {
                    return [
                        'lat' => isset($point['lat']) ? (float) $point['lat'] : null,
                        'lng' => isset($point['lng']) ? (float) $point['lng'] : null,
                    ];
                })
                ->filter(fn ($point) => $point['lat'] !== null && $point['lng'] !== null)
                ->values();

            if ($points->count() < 3) {
                return null;
            }

            $minLat = $points->min('lat');
            $maxLat = $points->max('lat');
            $minLng = $points->min('lng');
            $maxLng = $points->max('lng');

            $midLat = ($minLat + $maxLat) / 2;
            $kx = max(cos(deg2rad($midLat)), 0.01);
            $w = max(($maxLng - $minLng) * $kx, 0.0000001);
            $h = max(($maxLat - $minLat), 0.0000001);

            $padding = 12;
            $canvas = 100 - ($padding * 2);
            $scale = min($canvas / $w, $canvas / $h);
            $offX = $padding + ($canvas - $w * $scale) / 2;
            $offY = $padding + ($canvas - $h * $scale) / 2;

            return $points->map(function ($point) use ($minLng, $maxLat, $kx, $scale, $offX, $offY) {
                $x = $offX + (($point['lng'] - $minLng) * $kx) * $scale;
                $y = $offY + (($maxLat - $point['lat'])) * $scale;

                return [
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                ];
            })->values();
        }
    }

    $totalLots = $lots->count();
    $activeLots = $lots->where('status', 'activo')->count();
    $totalAnimals = $lots->sum(fn ($lot) => (int) ($lot->animals_count ?? 0));
    $totalArea = $lots->sum(function ($lot) {
        return (float) ($lot->area_manual ?: $lot->area_calculated ?: 0);
    });
@endphp

<style>
    .lot-metric-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,.08);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(163,230,53,.09), transparent 42%),
            linear-gradient(135deg, rgba(255,255,255,.80), rgba(255,255,255,.62));
        border-radius: 24px;
        padding: 18px;
        box-shadow: 0 12px 28px rgba(15,23,42,.06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
    }

    .lot-metric-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .lot-metric-value {
        font-size: 28px;
        line-height: 1;
        font-weight: 900;
        letter-spacing: -.03em;
        color: #111827;
    }

    .lots-shell {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .lot-filter-input,
    .lot-filter-select {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,.10);
        background:
            linear-gradient(135deg, rgba(255,255,255,.92), rgba(255,255,255,.72));
        padding: 12px 16px;
        outline: none;
        transition: .18s ease;
        color: #111827;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72), 0 8px 18px rgba(15,23,42,.04);
    }

    .lot-filter-input:focus,
    .lot-filter-select:focus {
        border-color: rgba(22,101,52,.25);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .lot-select-shell {
        position: relative;
    }

    .lot-select-shell::after {
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

    .lot-filter-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 44px;
        cursor: pointer;
    }

    .lot-filter-select:hover,
    .lot-filter-input:hover {
        border-color: rgba(22,101,52,.18);
        background:
            linear-gradient(135deg, rgba(255,255,255,.98), rgba(255,255,255,.78));
    }

    .lot-card-premium {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,.08);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(163,230,53,.09), transparent 42%),
            linear-gradient(135deg, rgba(255,255,255,.80), rgba(255,255,255,.60));
        border-radius: 30px;
        padding: 22px;
        box-shadow: 0 16px 40px rgba(15,23,42,.08);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .lot-card-premium::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(120deg, transparent, rgba(163,230,53,.08), transparent);
        opacity: 0;
        transition: .30s ease;
        pointer-events: none;
    }

    .lot-card-premium:hover {
        transform: translateY(-2px);
        box-shadow: 0 22px 48px rgba(15,23,42,.12);
        border-color: rgba(22,101,52,.12);
    }

    .lot-card-premium:hover::before {
        opacity: 1;
    }

    .lot-card-grid {
        display: grid;
        grid-template-columns: 148px minmax(0, 1fr) auto;
        gap: 20px;
        align-items: center;
    }

    .lot-preview {
        width: 148px;
        height: 148px;
        border-radius: 26px;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,.08);
        background:
            radial-gradient(circle at 20% 20%, rgba(34,197,94,.10), transparent 34%),
            linear-gradient(135deg, rgba(250,250,250,.96), rgba(243,244,246,.95));
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .lot-preview::after {
        content: "";
        position: absolute;
        inset: 10px;
        border-radius: 18px;
        border: 1px dashed rgba(22,101,52,.08);
        pointer-events: none;
    }

    .lot-preview svg {
        width: 100%;
        height: 100%;
        display: block;
    }

    .lot-preview-map {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    .lot-preview-svg {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
        pointer-events: none;
    }

    .lot-preview .leaflet-container { background: transparent !important; }

    .lot-preview-label { position: absolute; inset: 0; z-index: 3; display: flex; align-items: center; justify-content: center; text-align: center; padding: 6px; pointer-events: none; }
    .lot-preview-label .l-name { color: #fff; font-size: 10px; font-weight: 800; line-height: 1.25; text-shadow: 0 1px 3px rgba(0,0,0,.95), 0 0 2px rgba(0,0,0,.95); }
    .lot-preview-label .l-area { display: block; color: #39ff14; font-weight: 700; font-size: 10px; text-shadow: 0 1px 3px rgba(0,0,0,.95), 0 0 2px rgba(0,0,0,.95); }
    .lot-preview.has-map { background: #dbe4dd; }
    .lot-preview.has-map::after { display: none; }
    .lot-preview-map .leaflet-control-attribution { font-size: 8px; padding: 0 3px; }
    .lot-preview-map .leaflet-attribution-flag { display: none !important; }
    .lot-map-marker { width: 0 !important; height: 0 !important; }
    .lot-map-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        color: #fff;
        font-size: 10px;
        font-weight: 800;
        line-height: 1.25;
        white-space: nowrap;
        text-shadow: 0 1px 3px rgba(0,0,0,.95), 0 0 2px rgba(0,0,0,.95);
        transform: translate(-50%, -50%);
        text-align: center;
    }
    .lot-map-label-area { color: #39ff14; font-weight: 700; }

    .lot-preview-empty {
        text-align: center;
        padding: 18px;
        color: #6b7280;
        z-index: 1;
    }

    .lot-preview-empty-icon {
        width: 36px;
        height: 36px;
        margin: 0 auto 8px auto;
        opacity: .7;
    }

    .lot-preview-empty-text {
        font-size: 11px;
        font-weight: 800;
        line-height: 1.35;
        letter-spacing: .01em;
    }

    .lot-main {
        min-width: 0;
    }

    .lot-topline {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .lot-name {
        font-size: 24px;
        line-height: 1.05;
        font-weight: 900;
        color: #111827;
        letter-spacing: -.03em;
        word-break: break-word;
    }

    .lot-code {
        font-size: 12px;
        font-weight: 800;
        color: #6b7280;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.60);
        padding: 6px 10px;
        border-radius: 999px;
    }

    .lot-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }

    .lot-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
        letter-spacing: .02em;
    }

    .lot-pill.type {
        background: rgba(37,99,235,.10);
        color: #1d4ed8;
    }

    .lot-pill.active {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .lot-pill.inactive {
        background: rgba(107,114,128,.14);
        color: #4b5563;
    }

    .lot-pill.animals {
        background: rgba(124,58,237,.10);
        color: #6d28d9;
    }

    .lot-insights {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .lot-insight {
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.54);
        border-radius: 18px;
        padding: 14px;
    }

    .lot-insight-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .lot-insight-value {
        font-size: 16px;
        font-weight: 900;
        color: #111827;
        line-height: 1.25;
        word-break: break-word;
    }

    .lot-insight-value.area {
        font-size: 28px;
        letter-spacing: -.03em;
        line-height: 1;
    }

    .lot-description {
        margin-top: 14px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.55;
        max-width: 900px;
    }

    .lot-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
        align-items: stretch;
        min-width: 150px;
    }

    .lot-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 900;
        transition: .18s ease;
        text-decoration: none;
    }

    .lot-action-btn.primary {
        background: linear-gradient(135deg, #166534, #14532d);
        color: #fff;
        box-shadow: 0 10px 24px rgba(22,101,52,.22);
    }

    .lot-action-btn.primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(22,101,52,.28);
    }

    .lot-action-btn.secondary {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.82);
        color: #374151;
    }

    .lot-action-btn.secondary:hover {
        background: rgba(255,255,255,1);
    }

    .lot-empty {
        border: 1px dashed rgba(0,0,0,.10);
        background: rgba(255,255,255,.46);
        border-radius: 30px;
        padding: 48px 24px;
        text-align: center;
        color: #6b7280;
    }

    .lot-empty-title {
        font-size: 22px;
        font-weight: 900;
        color: #111827;
        margin-bottom: 10px;
        letter-spacing: -.02em;
    }

    .lot-empty-text {
        max-width: 620px;
        margin: 0 auto;
        font-size: 14px;
        line-height: 1.6;
    }

    .dark .lot-filter-input,
    .dark .lot-filter-select {
        border-color: rgba(148,163,184,.24);
        background: rgba(15,23,42,.78);
        color: #f8fafc;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
    }

    .dark .lot-filter-input::placeholder {
        color: #94a3b8;
    }

    .dark .lot-select-shell::after {
        border-color: #86efac;
    }

    @media (max-width: 1180px) {
        .lot-card-grid {
            grid-template-columns: 120px minmax(0, 1fr);
        }

        .lot-actions {
            grid-column: 1 / -1;
            flex-direction: row;
            min-width: 0;
        }

        .lot-action-btn {
            flex: 1;
        }

        .lot-preview {
            width: 120px;
            height: 120px;
        }
    }

    @media (max-width: 860px) {
        .lot-card-grid {
            grid-template-columns: 1fr;
        }

        .lot-preview {
            width: 100%;
            height: 180px;
        }

        .lot-insights {
            grid-template-columns: 1fr;
        }

        .lot-actions {
            flex-direction: column;
        }

        .lot-action-btn {
            width: 100%;
        }

        .lot-name {
            font-size: 22px;
        }
    }
</style>

<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Lotes</h1>
            <p class="text-sm text-gray-500 mt-1">
                Visualiza rápido el estado, el área y la ocupación de cada lote de tu finca.
            </p>
        </div>

        <a href="{{ route('lots.create') }}"
           class="inline-flex rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
            Crear lote
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="lot-metric-card">
            <div class="lot-metric-label">Total lotes</div>
            <div class="lot-metric-value">{{ $totalLots }}</div>
        </div>

        <div class="lot-metric-card">
            <div class="lot-metric-label">Activos</div>
            <div class="lot-metric-value text-green-700">{{ $activeLots }}</div>
        </div>

        <div class="lot-metric-card">
            <div class="lot-metric-label">Animales</div>
            <div class="lot-metric-value text-indigo-600">{{ $totalAnimals }}</div>
        </div>

        <div class="lot-metric-card">
            <div class="lot-metric-label">Área total</div>
            <div class="lot-metric-value">
                {{ $totalArea > 0 ? number_format($totalArea, 0, ',', '.') . ' m²' : '—' }}
            </div>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Filtros de lotes</h2>
            <p class="text-sm text-gray-500 mt-1">Organiza los lotes por nombre, estado, ocupación o área.</p>
        </div>

        <form method="GET" action="{{ route('lots.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4" data-auto-filter>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar</label>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Nombre, código, tipo o descripción"
                    class="lot-filter-input"
                    data-live-search>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Estado</label>
                <div class="lot-select-shell">
                    <select name="status" class="lot-filter-select">
                        <option value="">Todos</option>
                        <option value="activo" {{ $status === 'activo' ? 'selected' : '' }}>Activos</option>
                        <option value="inactivo" {{ $status === 'inactivo' ? 'selected' : '' }}>Inactivos</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Ordenar</label>
                <div class="lot-select-shell">
                    <select name="sort" class="lot-filter-select">
                        <option value="latest" {{ $sort === 'latest' ? 'selected' : '' }}>Más recientes</option>
                        <option value="name_asc" {{ $sort === 'name_asc' ? 'selected' : '' }}>Nombre A-Z</option>
                        <option value="animals_desc" {{ $sort === 'animals_desc' ? 'selected' : '' }}>Más animales</option>
                        <option value="area_desc" {{ $sort === 'area_desc' ? 'selected' : '' }}>Mayor área</option>
                    </select>
                </div>
            </div>

            <div class="md:col-span-4 flex flex-wrap gap-3">
                <button type="submit"
                        class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Aplicar filtros
                </button>

                <a href="{{ route('lots.index') }}"
                   class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <div class="lots-shell">
        @forelse($lots as $lot)
            @php
                $previewPoints = interfarmNormalizePolygonForPreview($lot->polygon);
                $displayArea = $lot->area_manual ?: $lot->area_calculated;
                $animalsCount = (int) ($lot->animals_count ?? 0);
                $typeLabel = $lot->type ? ucfirst(str_replace('_', ' ', $lot->type)) : 'Sin tipo';
                $statusIsActive = ($lot->status === 'activo');
            @endphp

            <div class="lot-card-premium">
                <div class="lot-card-grid">
                    <div class="lot-preview {{ $previewPoints ? 'has-map' : '' }}">
                        @if($previewPoints)
                            <svg class="lot-preview-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><polygon points="{{ collect($previewPoints)->map(fn($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="rgba(57,255,20,0.14)" stroke="#39ff14" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke" /></svg>
                            <div class="lot-preview-label"><div><span class="l-name">{{ $lot->name }}</span>@if($displayArea)<span class="l-area">{{ number_format((float) $displayArea, 0, ',', '.') }} m²{{ (float) $displayArea >= 10000 ? ' · '.number_format((float) $displayArea / 10000, 2, ',', '.').' ha' : '' }}</span>@endif</div></div>
                            <div class="lot-preview-map"
                                 data-lot-map
                                 data-polygon="{{ json_encode($lot->polygon) }}"
                                 data-name="{{ $lot->name }}"
                                 data-area="{{ $displayArea ? number_format((float) $displayArea, 0, ',', '.') . ' m²' . ((float) $displayArea >= 10000 ? ' · ' . number_format((float) $displayArea / 10000, 2, ',', '.') . ' ha' : '') : '' }}"
                                 data-center-lat="{{ $lot->center_lat }}"
                                 data-center-lng="{{ $lot->center_lng }}"></div>
                        @else
                            <div class="lot-preview-empty">
                                <svg class="lot-preview-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 0 1 3 16.382V5.618a1 1 0 0 1 1.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.553 2.776A1 1 0 0 0 22 18.882V8.118a1 1 0 0 0-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                </svg>
                                <div class="lot-preview-empty-text">
                                    Sin croquis
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="lot-main">
                        <div class="lot-topline">
                            <div class="lot-name">{{ $lot->name }}</div>

                            @if($lot->code)
                                <div class="lot-code">{{ $lot->code }}</div>
                            @endif
                        </div>
                        <div class="lot-name-area" style="margin-top:4px;font-size:13px;font-weight:800;color:#166534;">{{ $displayArea ? number_format((float) $displayArea, 0, ',', '.') . ' m²' . ((float) $displayArea >= 10000 ? ' · ' . number_format((float) $displayArea / 10000, 2, ',', '.') . ' ha' : '') : 'Área pendiente' }}</div>

                        <div class="lot-meta-row">
                            <span class="lot-pill type">{{ $typeLabel }}</span>

                            <span class="lot-pill {{ $statusIsActive ? 'active' : 'inactive' }}">
                                {{ $statusIsActive ? 'Activo' : 'Inactivo' }}
                            </span>

                            <span class="lot-pill animals">
                                {{ $animalsCount }} {{ $animalsCount === 1 ? 'animal' : 'animales' }}
                            </span>
                        </div>

                        <div class="lot-insights">
                            <div class="lot-insight">
                                <div class="lot-insight-label">Área principal</div>
                                <div class="lot-insight-value area">
                                    {{ $displayArea ? number_format((float) $displayArea, 0, ',', '.') . ' m²' : 'Pendiente' }}
                                </div>
                            </div>

                            <div class="lot-insight">
                                <div class="lot-insight-label">Área manual</div>
                                <div class="lot-insight-value">
                                    {{ $lot->area_manual ? number_format((float) $lot->area_manual, 0, ',', '.') . ' m²' : 'No definida' }}
                                </div>
                            </div>

                            <div class="lot-insight">
                                <div class="lot-insight-label">Área calculada</div>
                                <div class="lot-insight-value">
                                    {{ $lot->area_calculated ? number_format((float) $lot->area_calculated, 0, ',', '.') . ' m²' : 'No calculada' }}
                                </div>
                            </div>
                        </div>

                        <div class="lot-description">
                            {{ $lot->description ? \Illuminate\Support\Str::limit($lot->description, 160) : 'Este lote todavía no tiene una descripción registrada.' }}
                        </div>
                    </div>

                    <div class="lot-actions">
                        <a href="{{ route('lots.show', $lot) }}" class="lot-action-btn primary">
                            Ver detalle
                        </a>

                        <a href="{{ route('lots.edit', $lot) }}" class="lot-action-btn secondary">
                            Editar
                        </a>

                        <form method="POST" action="{{ route('lots.destroy', $lot) }}" style="display:contents;"
                              onsubmit="return confirm('¿Eliminar el lote &quot;{{ $lot->name }}&quot;?@if($animalsCount) Los {{ $animalsCount }} animal(es) del lote quedarán sin lote asignado (no se eliminan).@endif');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="lot-action-btn" style="background:#dc2626;color:#fff;border:none;cursor:pointer;">Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="lot-empty">
                <div class="lot-empty-title">Todavía no tienes lotes creados</div>
                <div class="lot-empty-text">
                    Empieza creando tu primer lote para organizar mejor la finca, dibujar su perímetro y asociar animales a cada espacio.
                </div>

                <div class="mt-6">
                    <a href="{{ route('lots.create') }}"
                       class="inline-flex rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                        Crear primer lote
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>

<script src="/vendor/leaflet.js"></script>
<script>
(function () {
    function initLotMaps() {
        if (typeof L === 'undefined') { return; }
        var els = document.querySelectorAll('[data-lot-map]');
        els.forEach(function (el) {
            if (el.dataset.mapReady) { return; }
            var poly;
            try { poly = JSON.parse(el.dataset.polygon || '[]'); } catch (e) { poly = []; }
            if (!Array.isArray(poly) || poly.length < 3) { return; }
            el.dataset.mapReady = '1';
            var latlngs = poly.map(function (p) { return [Number(p.lat), Number(p.lng)]; });
            var map = L.map(el, {
                zoomControl: false,
                attributionControl: true,
                dragging: false,
                scrollWheelZoom: false,
                doubleClickZoom: false,
                boxZoom: false,
                keyboard: false,
                touchZoom: false,
                tap: false
            });
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri'
            }).addTo(map);
            var bounds = L.latLngBounds(latlngs);
            map.fitBounds(bounds, { padding: [8, 8], maxZoom: 17 });
            // Etiqueta (nombre/área) se renderiza server-side en .lot-preview-label, encima del contorno.
            setTimeout(function () { map.invalidateSize(); }, 120);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLotMaps);
    } else {
        initLotMaps();
    }
})();
</script>

@endsection

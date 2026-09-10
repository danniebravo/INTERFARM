@extends('layouts.app')

@section('title', 'Detalle del lote')

@section('content')

@php
    $googleMapsApiKey = \App\Models\PlatformSetting::googleMapsApiKey();
    $polygonJson = $lot->polygon ? json_encode($lot->polygon, JSON_UNESCAPED_UNICODE) : '';
    $animals = $lot->animals ?? collect();
@endphp

<style>
    .lot-show-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.62);
        border-radius: 28px;
        padding: 24px;
        box-shadow: 0 14px 32px rgba(0,0,0,.08);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
    }

    .lot-show-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .lot-show-stat {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.66);
        border-radius: 18px;
        padding: 16px;
    }

    .lot-show-stat-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .lot-show-stat-value {
        font-size: 15px;
        font-weight: 800;
        color: #111827;
        line-height: 1.45;
        word-break: break-word;
    }

    .lot-map-shell {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.58);
        border-radius: 28px;
        padding: 24px;
        box-shadow: 0 14px 32px rgba(0,0,0,.08);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }

    .lot-map-box {
        position: relative;
        width: 100%;
        height: 520px;
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid rgba(0,0,0,.10);
        background: linear-gradient(135deg, rgba(255,255,255,.75), rgba(243,244,246,.95));
    }

    .lot-map {
        width: 100%;
        height: 100%;
    }

    .lot-map-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: linear-gradient(180deg, rgba(255,255,255,.72), rgba(255,255,255,.60));
        z-index: 2;
    }

    .lot-animal-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.56);
        border-radius: 22px;
        padding: 18px;
        box-shadow: 0 10px 24px rgba(0,0,0,.06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }

    .lot-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 800;
    }

    .lot-status-badge.active {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .lot-status-badge.inactive {
        background: rgba(107,114,128,.14);
        color: #4b5563;
    }

    @media (max-width: 1024px) {
        .lot-show-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .lot-show-stat-grid {
            grid-template-columns: 1fr;
        }

        .lot-map-box {
            height: 420px;
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
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">{{ $lot->name }}</h1>

                <span class="lot-status-badge {{ ($lot->status === 'activo' ? 'active' : 'inactive') }}">
                    {{ $lot->status === 'activo' ? 'Activo' : 'Inactivo' }}
                </span>
            </div>

            <p class="text-sm text-gray-500 mt-1">
                Información general, croquis y animales asociados al lote.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('lots.edit', $lot) }}"
               class="rounded-xl bg-brand hover:bg-brand-dark transition px-4 py-2.5 text-sm font-semibold text-white shadow-glow">
                Editar lote
            </a>

            <form method="POST" action="{{ route('lots.destroy', $lot) }}"
                  onsubmit="return confirm('¿Eliminar el lote &quot;{{ $lot->name }}&quot;?@if($lot->animals->count()) Los {{ $lot->animals->count() }} animal(es) del lote quedarán sin lote asignado (no se eliminan).@endif');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded-xl bg-red-600 hover:bg-red-700 transition px-4 py-2.5 text-sm font-semibold text-white shadow">
                    Eliminar lote
                </button>
            </form>

            <a href="{{ route('lots.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver
            </a>
        </div>
    </div>

    <div class="lot-show-card">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Resumen del lote</h2>
            <p class="text-sm text-gray-500 mt-1">Datos generales y métricas del lote.</p>
        </div>

        <div class="lot-show-stat-grid">
            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Código</div>
                <div class="lot-show-stat-value">{{ $lot->code ?: 'Sin código' }}</div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Tipo</div>
                <div class="lot-show-stat-value">{{ $lot->type ?: 'Sin tipo' }}</div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Área manual</div>
                <div class="lot-show-stat-value">
                    {{ $lot->area_manual ? number_format((float) $lot->area_manual, 2, ',', '.') . ' m²' : 'No registrada' }}
                </div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Área calculada</div>
                <div class="lot-show-stat-value">
                    {{ $lot->area_calculated ? number_format((float) $lot->area_calculated, 2, ',', '.') . ' m²' : 'No calculada' }}
                </div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Centro latitud</div>
                <div class="lot-show-stat-value">{{ $lot->center_lat ?: '—' }}</div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Centro longitud</div>
                <div class="lot-show-stat-value">{{ $lot->center_lng ?: '—' }}</div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Animales asociados</div>
                <div class="lot-show-stat-value">{{ $animals->count() }}</div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Descripción</div>
                <div class="lot-show-stat-value">{{ $lot->description ?: 'Sin descripción' }}</div>
            </div>

            <div class="lot-show-stat">
                <div class="lot-show-stat-label">Observaciones</div>
                <div class="lot-show-stat-value">{{ $lot->notes ?: 'Sin observaciones' }}</div>
            </div>
        </div>
    </div>

    <div class="lot-map-shell">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Mapa del lote</h2>
            <p class="text-sm text-gray-500 mt-1">
                Visualización del polígono guardado sobre el mapa.
            </p>
        </div>

        <div class="lot-map-box">
            @if(!$polygonJson)
                <div class="lot-map-overlay">
                    <div class="max-w-lg text-center">
                        <div class="text-xl font-extrabold text-gray-900">Este lote aún no tiene mapa delimitado</div>
                        <p class="text-sm text-gray-600 mt-3">
                            Puedes editar el lote y dibujar el polígono para visualizarlo aquí.
                        </p>
                    </div>
                </div>
            @endif

            <div id="lotShowMap" class="lot-map"></div>
        </div>
    </div>

    <div class="lot-show-card">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Animales en este lote</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Inventario de animales asociados actualmente.
                </p>
            </div>
        </div>

        @if($animals->count())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($animals as $animal)
                    <a href="{{ route('animals.show', $animal) }}" class="lot-animal-card block hover:bg-white/80 transition">
                        <div class="font-bold text-gray-900">
                            {{ $animal->name ?: 'Sin nombre' }}
                        </div>

                        <div class="text-sm text-gray-500 mt-2 space-y-1">
                            <div>Arete: {{ $animal->ear_tag ?: 'Sin arete' }}</div>
                            <div>Sexo: {{ $animal->sex ?: '—' }}</div>
                            <div>Raza: {{ $animal->breed ?: '—' }}</div>
                            <div>Propósito: {{ $animal->purpose ?: '—' }}</div>
                            <div>En el lote desde: {{ $animal->lot_assigned_at ? \Carbon\Carbon::parse($animal->lot_assigned_at)->format('d/m/Y') : '—' }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-5 text-sm text-gray-500">
                Aún no hay animales asociados a este lote.
            </div>
        @endif
    </div>

    @isset($lotHistory)
    <div class="lot-show-card">
        <div class="mb-4">
            <h2 class="text-lg font-extrabold text-gray-900">Ingresos y salidas del lote</h2>
            <p class="text-sm text-gray-500 mt-1">Historial de cuándo entró y salió cada animal de este lote.</p>
        </div>
        @if($lotHistory->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-black/10">
                            <th class="py-2 pr-4 font-semibold">Animal</th>
                            <th class="py-2 px-4 font-semibold">Arete</th>
                            <th class="py-2 px-4 font-semibold">Fecha de ingreso</th>
                            <th class="py-2 px-4 font-semibold">Fecha de salida</th>
                            <th class="py-2 pl-4 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lotHistory as $h)
                        <tr class="border-b border-black/5">
                            <td class="py-2 pr-4 font-semibold text-gray-900">{{ $h->animal_name ?: ('Animal #'.$h->animal_id) }}</td>
                            <td class="py-2 px-4 text-gray-600">{{ $h->animal_ear_tag ?: '—' }}</td>
                            <td class="py-2 px-4 text-gray-700">{{ $h->entered_at ? \Carbon\Carbon::parse($h->entered_at)->format('d/m/Y') : '—' }}</td>
                            <td class="py-2 px-4">
                                @if($h->exited_at)
                                    <span class="font-semibold text-amber-700">{{ \Carbon\Carbon::parse($h->exited_at)->format('d/m/Y') }}</span>
                                @else
                                    <span class="inline-block rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700">En el lote</span>
                                @endif
                            </td>
                            <td class="py-2 pl-4 text-right">
                                <button type="button"
                                    class="hist-edit-btn rounded-lg border border-black/10 bg-white px-3 py-1 text-xs font-bold text-gray-700 hover:bg-gray-50"
                                    data-hist-id="{{ $h->id ?? '' }}"
                                    data-animal="{{ $h->animal_name ?: ('Animal #'.$h->animal_id) }}"
                                    data-entered="{{ $h->entered_at ? \Carbon\Carbon::parse($h->entered_at)->format('Y-m-d') : '' }}"
                                    data-exited="{{ $h->exited_at ? \Carbon\Carbon::parse($h->exited_at)->format('Y-m-d') : '' }}">Editar</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-4 text-sm text-gray-500">Aún no hay movimientos registrados para este lote.</div>
        @endif

        <style>
            .hist-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
            .hist-modal-box { background: #fff; border-radius: 22px; max-width: 400px; width: 100%; padding: 24px; box-shadow: 0 24px 60px rgba(0,0,0,.32); }
            .hist-modal-title { font-size: 1.1rem; font-weight: 900; color: #111827; }
            .hist-modal-sub { margin-top: 4px; color: #6b7280; font-size: .9rem; font-weight: 700; }
            .hist-field { display: block; margin-top: 16px; }
            .hist-field > span { display: block; font-size: .8rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
            .hist-field input { width: 100%; border: 1px solid rgba(0,0,0,.15); border-radius: 12px; padding: 10px 12px; font-size: .95rem; }
            .hist-modal-actions { margin-top: 22px; display: flex; justify-content: flex-end; gap: 10px; }
            .hist-modal-cancel { border-radius: 12px; border: 1px solid rgba(0,0,0,.12); background: #fff; padding: 10px 18px; font-weight: 800; color: #374151; cursor: pointer; }
            .hist-modal-ok { border-radius: 12px; background: #16a34a; padding: 10px 18px; font-weight: 900; color: #fff; cursor: pointer; border: none; }
        </style>

        <div id="histEditModal" class="hist-modal-overlay" style="display:none;">
            <div class="hist-modal-box">
                <div class="hist-modal-title">Editar fechas del lote</div>
                <div class="hist-modal-sub" id="histModalAnimal">Animal</div>
                <form method="POST" action="{{ route('lots.history.update', $lot) }}" id="histEditForm">
                    @csrf
                    <input type="hidden" name="history_id" id="histId">
                    <label class="hist-field">
                        <span>Fecha de ingreso</span>
                        <input type="date" name="entered_at" id="histEntered" required>
                    </label>
                    <label class="hist-field" id="histExitedWrap">
                        <span>Fecha de salida</span>
                        <input type="date" name="exited_at" id="histExited">
                    </label>
                    <div class="hist-modal-actions">
                        <button type="button" class="hist-modal-cancel" id="histCancel">Cancelar</button>
                        <button type="submit" class="hist-modal-ok">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        (function () {
            var modal = document.getElementById('histEditModal');
            if (!modal) return;
            if (modal.parentElement !== document.body) { document.body.appendChild(modal); }
            var idEl = document.getElementById('histId');
            var animalEl = document.getElementById('histModalAnimal');
            var enteredEl = document.getElementById('histEntered');
            var exitedEl = document.getElementById('histExited');
            var exitedWrap = document.getElementById('histExitedWrap');
            function closeModal() { modal.style.display = 'none'; }
            document.getElementById('histCancel').addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
            document.querySelectorAll('.hist-edit-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    idEl.value = btn.getAttribute('data-hist-id') || '';
                    animalEl.textContent = btn.getAttribute('data-animal') || '';
                    enteredEl.value = btn.getAttribute('data-entered') || '';
                    var ex = btn.getAttribute('data-exited') || '';
                    if (ex) { exitedWrap.style.display = ''; exitedEl.value = ex; }
                    else { exitedWrap.style.display = 'none'; exitedEl.value = ''; }
                    modal.style.display = 'flex';
                });
            });
        })();
        </script>
    </div>

    @endisset

    @isset($candidateAnimals)
    @php
        $unassigned = $candidateAnimals->filter(fn ($c) => empty($c->lot_id))->values();
        $inOtherLots = $candidateAnimals->filter(fn ($c) => ! empty($c->lot_id))->values();
    @endphp

    <style>
        .lot-confirm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
        .lot-confirm-box { background: #fff; border-radius: 24px; max-width: 430px; width: 100%; padding: 26px; box-shadow: 0 24px 60px rgba(0,0,0,.32); }
        .lot-confirm-title { font-size: 1.15rem; font-weight: 900; color: #111827; }
        .lot-confirm-text { margin-top: 10px; color: #4b5563; font-size: .95rem; line-height: 1.55; }
        .lot-confirm-actions { margin-top: 22px; display: flex; justify-content: flex-end; gap: 10px; }
        .lot-confirm-cancel { border-radius: 12px; border: 1px solid rgba(0,0,0,.12); background: #fff; padding: 10px 18px; font-weight: 800; color: #374151; cursor: pointer; }
        .lot-confirm-ok { border-radius: 12px; background: #16a34a; padding: 10px 18px; font-weight: 900; color: #fff; cursor: pointer; }
        .lot-confirm-ok.is-warn { background: #d97706; }
    </style>

    <div class="lot-show-card">
        <div class="mb-4">
            <h2 class="text-lg font-extrabold text-gray-900">Agregar animales al lote</h2>
            <p class="text-sm text-gray-500 mt-1">Animales que aún no están en ningún lote (elige la finca de origen arriba). Selecciónalos para agregarlos a <strong>{{ $lot->name }}</strong>.</p>
        </div>

        @if($unassigned->count())
            <form method="POST" action="{{ route('lots.animals.assign', $lot) }}" data-lot-action="agregar" data-lot-dest="{{ $lot->name }}">
                @csrf
                <input type="hidden" name="target_lot_id" value="{{ $lot->id }}">

                @if(isset($ownerFarms) && $ownerFarms->count() > 1)
                    <div class="mb-3 flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Finca de origen:</label>
                        <select class="lot-farm-filter rounded-xl border border-black/10 bg-white/80 px-3 py-2 text-sm font-semibold text-gray-800">
                            @foreach($ownerFarms as $of)
                                <option value="{{ $of->id }}" {{ $of->id == $lot->farm_id ? 'selected' : '' }}>{{ $of->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                        <input type="checkbox" class="lot-select-all rounded border-gray-300"> Seleccionar todos
                    </label>
                    <button type="submit" class="rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white shadow hover:bg-green-700 transition">Agregar seleccionados</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[360px] overflow-y-auto pr-1">
                    @foreach($unassigned as $cand)
                        <label class="flex items-start gap-3 rounded-2xl border border-black/10 bg-white/60 p-3 cursor-pointer hover:bg-white transition" data-farm-id="{{ $cand->farm_id }}">
                            <input type="checkbox" name="animal_ids[]" value="{{ $cand->id }}" class="lot-cand-check mt-1 rounded border-gray-300">
                            <span class="min-w-0">
                                <span class="block font-bold text-gray-900 truncate">{{ $cand->name ?: 'Sin nombre' }}</span>
                                <span class="block text-xs text-gray-500">Arete: {{ $cand->ear_tag ?: '—' }}</span>
                                <span class="block text-[11px] font-bold text-emerald-600">🏠 {{ optional($cand->farm)->name }}</span>
                                <span class="mt-1 inline-block rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-bold text-green-700">Sin lote</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </form>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-4 text-sm text-gray-500">Todos los animales de la finca ya están en un lote. No hay animales sueltos para agregar.</div>
        @endif
    </div>

    <div class="lot-show-card">
        <div class="mb-4">
            <h2 class="text-lg font-extrabold text-gray-900">Trasladar animales de otro lote</h2>
            <p class="text-sm text-gray-500 mt-1">Mueve animales que ya están en otro lote (de cualquiera de tus fincas) hacia el lote que elijas.</p>
        </div>

        @if($inOtherLots->count() && isset($farmLots) && $farmLots->count())
            <form method="POST" action="{{ route('lots.animals.assign', $lot) }}" data-lot-action="trasladar">
                @csrf

                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Trasladar a:</label>
                        <select name="target_lot_id" class="lot-dest-select rounded-xl border border-black/10 bg-white/80 px-3 py-2 text-sm font-semibold text-gray-800">
                            @foreach($farmLots as $fl)
                                <option value="{{ $fl->id }}" {{ $fl->id == $lot->id ? 'selected' : '' }}>{{ $fl->name }}{{ $fl->id == $lot->id ? ' (este lote)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-black text-white shadow hover:bg-amber-600 transition">Trasladar seleccionados</button>
                </div>


                @if(isset($ownerFarms) && $ownerFarms->count() > 1)
                    <div class="mb-3 flex items-center gap-2">
                        <label class="text-sm font-semibold text-gray-700">Finca de origen:</label>
                        <select class="lot-farm-filter rounded-xl border border-black/10 bg-white/80 px-3 py-2 text-sm font-semibold text-gray-800">
                            @foreach($ownerFarms as $of)
                                <option value="{{ $of->id }}" {{ $of->id == $lot->farm_id ? 'selected' : '' }}>{{ $of->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <label class="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-gray-700">
                    <input type="checkbox" class="lot-select-all rounded border-gray-300"> Seleccionar todos
                </label>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[360px] overflow-y-auto pr-1">
                    @foreach($inOtherLots as $cand)
                        <label class="flex items-start gap-3 rounded-2xl border border-black/10 bg-white/60 p-3 cursor-pointer hover:bg-white transition" data-farm-id="{{ $cand->farm_id }}">
                            <input type="checkbox" name="animal_ids[]" value="{{ $cand->id }}" class="lot-cand-check mt-1 rounded border-gray-300">
                            <span class="min-w-0">
                                <span class="block font-bold text-gray-900 truncate">{{ $cand->name ?: 'Sin nombre' }}</span>
                                <span class="block text-xs text-gray-500">Arete: {{ $cand->ear_tag ?: '—' }}</span>
                                <span class="block text-[11px] font-bold text-emerald-600">🏠 {{ optional($cand->farm)->name }}</span>
                                <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-700">En {{ optional($cand->lot)->name ?: 'otro lote' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </form>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-4 text-sm text-gray-500">No hay animales en otros lotes de la finca para trasladar.</div>
        @endif
    </div>

    <script>
        (function () {
            document.querySelectorAll('.lot-farm-filter').forEach(function (sel) {
                var form = sel.closest('form');
                if (!form) return;
                function apply() {
                    var fid = sel.value;
                    form.querySelectorAll('label[data-farm-id]').forEach(function (lab) {
                        var show = lab.getAttribute('data-farm-id') === fid;
                        lab.style.display = show ? '' : 'none';
                        if (!show) { var cb = lab.querySelector('input[type=checkbox]'); if (cb) cb.checked = false; }
                    });
                }
                sel.addEventListener('change', apply);
                apply();
            });
        })();
    </script>
    <div id="lotConfirmModal" class="lot-confirm-overlay" style="display:none;">
        <div class="lot-confirm-box">
            <div class="lot-confirm-title" id="lotConfirmTitle">Confirmar</div>
            <div class="lot-confirm-text" id="lotConfirmText">¿Estás seguro?</div>
            <div class="lot-confirm-actions">
                <button type="button" class="lot-confirm-cancel" id="lotConfirmCancel">Cancelar</button>
                <button type="button" class="lot-confirm-ok" id="lotConfirmOk">Sí, confirmar</button>
            </div>
        </div>
    </div>

    <script>
    (function () {
        document.querySelectorAll('.lot-select-all').forEach(function (all) {
            all.addEventListener('change', function () {
                var form = all.closest('form');
                if (!form) return;
                form.querySelectorAll('.lot-cand-check').forEach(function (c) { c.checked = all.checked; });
            });
        });

        var modal = document.getElementById('lotConfirmModal');
        if (modal && modal.parentElement !== document.body) { document.body.appendChild(modal); }
        var titleEl = document.getElementById('lotConfirmTitle');
        var textEl = document.getElementById('lotConfirmText');
        var okBtn = document.getElementById('lotConfirmOk');
        var cancelBtn = document.getElementById('lotConfirmCancel');
        var pendingForm = null;

        function closeModal() { modal.style.display = 'none'; pendingForm = null; }

        cancelBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        okBtn.addEventListener('click', function () {
            if (pendingForm) { var f = pendingForm; pendingForm = null; modal.style.display = 'none'; f.submit(); }
        });

        document.querySelectorAll('form[data-lot-action]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var checked = form.querySelectorAll('.lot-cand-check:checked').length;
                if (checked === 0) {
                    titleEl.textContent = 'Selecciona animales';
                    textEl.textContent = 'No has seleccionado ningún animal. Marca al menos uno.';
                    okBtn.style.display = 'none';
                    pendingForm = null;
                    modal.style.display = 'flex';
                    return;
                }
                okBtn.style.display = '';
                var action = form.getAttribute('data-lot-action');
                if (action === 'trasladar') {
                    var sel = form.querySelector('.lot-dest-select');
                    var dest = sel ? sel.options[sel.selectedIndex].text.replace(' (este lote)', '') : '';
                    titleEl.textContent = '¿Trasladar animales?';
                    textEl.textContent = '¿Estás seguro que quieres TRASLADAR ' + checked + ' animal(es) al lote "' + dest + '"?';
                    okBtn.classList.add('is-warn');
                } else {
                    var destName = form.getAttribute('data-lot-dest') || '';
                    titleEl.textContent = '¿Agregar animales?';
                    textEl.textContent = '¿Estás seguro que quieres AGREGAR ' + checked + ' animal(es) al lote "' + destName + '"?';
                    okBtn.classList.remove('is-warn');
                }
                pendingForm = form;
                modal.style.display = 'flex';
            });
        });
    })();
    </script>
    @endisset
</div>

@if($polygonJson)
<script>
    function initLotShowMap() {
        const polygonJson = @json($polygonJson);
        const parsed = JSON.parse(polygonJson);

        if (!Array.isArray(parsed) || parsed.length < 3) {
            return;
        }

        const centerLat = Number(@json($lot->center_lat)) || parsed[0].lat;
        const centerLng = Number(@json($lot->center_lng)) || parsed[0].lng;

        const map = new google.maps.Map(document.getElementById('lotShowMap'), {
            center: { lat: centerLat, lng: centerLng },
            zoom: 18,
            mapTypeId: 'satellite',
            streetViewControl: false,
            fullscreenControl: true,
            mapTypeControl: true,
        });

        const bounds = new google.maps.LatLngBounds();

        // Solo el contorno del lote (líneas), sin marcadores de vértices.
        parsed.forEach(point => {
            bounds.extend(point);
        });

        new google.maps.Polygon({
            paths: parsed,
            strokeColor: '#39ff14',
            strokeOpacity: 1,
            strokeWeight: 4,
            fillColor: '#39ff14',
            fillOpacity: 0.12,
            map: map,
        });

        @php
            $showAreaVal = $lot->area_manual ?: $lot->area_calculated;
            $showAreaText = $showAreaVal ? number_format((float) $showAreaVal, 0, ',', '.').' m²'.((float) $showAreaVal >= 10000 ? ' · '.number_format((float) $showAreaVal / 10000, 2, ',', '.').' ha' : '') : '';
            $showLabelHtml = '<div style="font-weight:800;font-size:13px;">'.e($lot->name).'</div>'.($showAreaText ? '<div style="font-weight:700;font-size:11px;color:#39ff14;margin-top:2px;">'.$showAreaText.'</div>' : '');
        @endphp
        var centerLabel = document.createElement('div');
        centerLabel.style.cssText = 'position:absolute;transform:translate(-50%,-50%);color:#fff;white-space:nowrap;text-align:center;pointer-events:none;font-family:system-ui,sans-serif;text-shadow:0 1px 3px rgba(0,0,0,.95), 0 0 2px rgba(0,0,0,.95);';
        centerLabel.innerHTML = {!! json_encode($showLabelHtml) !!};
        var labelOverlay = new google.maps.OverlayView();
        labelOverlay.onAdd = function () { this.getPanes().floatPane.appendChild(centerLabel); };
        labelOverlay.onRemove = function () { if (centerLabel.parentNode) centerLabel.parentNode.removeChild(centerLabel); };
        labelOverlay.draw = function () { var proj = this.getProjection(); if (!proj) return; var pt = proj.fromLatLngToDivPixel(bounds.getCenter()); if (!pt) return; centerLabel.style.left = pt.x + 'px'; centerLabel.style.top = pt.y + 'px'; };
        labelOverlay.setMap(map);

        map.fitBounds(bounds);
    }
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&callback=initLotShowMap"
    async
    defer
></script>
@endif

@endsection

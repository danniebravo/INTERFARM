@extends('layouts.app')

@section('title', 'Editar lote')

@section('content')

@php
    $googleMapsApiKey = \App\Models\PlatformSetting::googleMapsApiKey();
    $polygonJson = old('polygon_json', $lot->polygon ? json_encode($lot->polygon, JSON_UNESCAPED_UNICODE) : '');
@endphp

<style>
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
        transition: .2s ease;
    }

    .lot-map-overlay.hidden {
        display: none;
    }

    .lot-map-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .lot-map-search {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        margin-bottom: 16px;
    }

    .lot-map-search-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.82);
        padding: 12px 14px;
        font-size: 14px;
        font-weight: 600;
        color: #111827;
        outline: none;
    }

    .lot-map-search-input:focus {
        border-color: rgba(22,101,52,.35);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .lot-location-results {
        display: grid;
        gap: 8px;
        margin: -4px 0 16px;
    }

    .lot-location-results.hidden {
        display: none;
    }

    .lot-location-result {
        width: 100%;
        border: 1px solid rgba(17,24,39,.10);
        border-radius: 14px;
        background: rgba(255,255,255,.86);
        color: #111827;
        cursor: pointer;
        font-size: 13px;
        font-weight: 700;
        line-height: 1.4;
        padding: 11px 13px;
        text-align: left;
        transition: .2s ease;
    }

    .lot-location-result:hover,
    .lot-location-result:focus {
        background: #fff;
        border-color: rgba(22,101,52,.35);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
        outline: none;
    }

    .lot-map-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.82);
        padding: 12px 16px;
        font-size: 14px;
        font-weight: 700;
        color: #374151;
        transition: .2s ease;
    }

    .lot-map-btn:hover {
        background: rgba(255,255,255,1);
    }

    .lot-map-btn.primary {
        background: rgba(22,101,52,.94);
        border-color: rgba(22,101,52,.94);
        color: #fff;
    }

    .lot-map-btn.primary:hover {
        background: rgba(20,83,45,1);
    }

    .lot-map-btn.warning {
        background: rgba(245,158,11,.12);
        color: #b45309;
        border-color: rgba(245,158,11,.20);
    }

    .lot-map-btn.danger {
        background: rgba(220,38,38,.10);
        color: #dc2626;
        border-color: rgba(220,38,38,.18);
    }

    .lot-map-btn:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .lot-map-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .lot-map-stat {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.66);
        border-radius: 18px;
        padding: 16px;
    }

    .lot-map-stat-label {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
        margin-bottom: 8px;
    }

    .lot-map-stat-value {
        font-size: 15px;
        font-weight: 800;
        color: #111827;
        line-height: 1.4;
        word-break: break-word;
    }

    .lot-map-help {
        border: 1px solid rgba(22,101,52,.10);
        background: rgba(22,101,52,.05);
        border-radius: 18px;
        padding: 16px;
        color: #166534;
        font-size: 14px;
        font-weight: 600;
    }

    .lot-native-select {
        width: 100%;
        min-height: 46px;
        appearance: none;
        -webkit-appearance: none;
        border-radius: 14px;
        border: 1px solid rgba(17,24,39,.12);
        background-color: rgba(255,255,255,.86);
        background-image:
            linear-gradient(45deg, transparent 50%, #166534 50%),
            linear-gradient(135deg, #166534 50%, transparent 50%);
        background-position:
            calc(100% - 20px) 20px,
            calc(100% - 14px) 20px;
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
        color: #111827;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.3;
        outline: none;
        padding: 11px 42px 11px 14px;
        transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
    }

    .lot-native-select:hover {
        background-color: #fff;
        border-color: rgba(22,101,52,.25);
    }

    .lot-native-select:focus {
        background-color: #fff;
        border-color: rgba(22,101,52,.45);
        box-shadow: 0 0 0 4px rgba(22,101,52,.10);
    }

    @media (max-width: 1024px) {
        .lot-map-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .lot-map-box {
            height: 420px;
        }

        .lot-map-stats {
            grid-template-columns: 1fr;
        }

        .lot-map-search {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="space-y-6">
    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            Revisa los campos marcados. Hay información pendiente o inválida.
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Editar lote</h1>
            <p class="text-sm text-gray-500 mt-1">
                Ajusta los datos del lote y actualiza el croquis si lo necesitas.
            </p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('lots.show', $lot) }}"
               class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Ver lote
            </a>

            <a href="{{ route('lots.index') }}"
               class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('lots.update', $lot) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="glass rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Información principal</h2>
                <p class="text-sm text-gray-500 mt-1">Datos básicos del lote.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                <div class="xl:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre del lote *</label>
                    <input type="text"
                           name="name"
                           value="{{ old('name', $lot->name) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                    @error('name')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Código único</label>
                    <input type="text"
                           name="code"
                           value="{{ old('code', $lot->code) }}"
                           @if($lot->code) readonly @endif
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40 {{ $lot->code ? 'cursor-not-allowed opacity-70' : '' }}"
                           placeholder="Ej: LT-01">
                    <p class="mt-1 text-xs font-semibold text-gray-500">
                        {{ $lot->code ? 'El código del lote es único y no se puede reemplazar.' : 'Puedes asignar un código único una sola vez.' }}
                    </p>
                    @error('code')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Estado</label>
                    <select name="status"
                            class="lot-native-select">
                        <option value="activo" {{ old('status', $lot->status) === 'activo' ? 'selected' : '' }}>Activo</option>
                        <option value="inactivo" {{ old('status', $lot->status) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                    @error('status')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo</label>
                    <select name="type"
                            class="lot-native-select">
                        <option value="">Selecciona un tipo</option>
                        <option value="pradera" {{ old('type', $lot->type) === 'pradera' ? 'selected' : '' }}>Pradera</option>
                        <option value="potrero" {{ old('type', $lot->type) === 'potrero' ? 'selected' : '' }}>Potrero</option>
                        <option value="corral" {{ old('type', $lot->type) === 'corral' ? 'selected' : '' }}>Corral</option>
                        <option value="descanso" {{ old('type', $lot->type) === 'descanso' ? 'selected' : '' }}>Descanso</option>
                        <option value="otro" {{ old('type', $lot->type) === 'otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('type')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="mt-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción</label>
                <textarea name="description"
                          rows="4"
                          class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-3 outline-none focus:border-brand/40"
                          placeholder="Notas del lote, observaciones, uso o detalles...">{{ old('description', $lot->description) }}</textarea>
                @error('description')
                    <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="mt-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Observaciones</label>
                <textarea name="notes"
                          rows="4"
                          class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-3 outline-none focus:border-brand/40"
                          placeholder="Anota aquí cualquier detalle, recordatorio o pendiente del lote...">{{ old('notes', $lot->notes) }}</textarea>
                @error('notes')
                    <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="lot-map-shell">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Mapa y croquis del lote</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Puedes actualizar el polígono dibujando uno nuevo o dejar el actual.
                </p>
            </div>

            <div class="lot-map-toolbar">
                <button type="button" id="showMapBtn" class="lot-map-btn primary">Agregar mapa</button>
                <button type="button" id="locateBtn" class="lot-map-btn" disabled>Mi ubicación</button>
                <button type="button" id="focusMapBtn" class="lot-map-btn" disabled>Enfocar croquis</button>
            </div>

            <div class="lot-map-help mb-4" id="mapHelpText">
                Puedes buscar una dirección o vereda para centrar el mapa sin usar GPS. Si el lote ya tiene croquis, se cargará cuando abras el mapa.
            </div>

            <div class="lot-map-search">
                <input type="text"
                       id="locationSearchInput"
                       class="lot-map-search-input"
                       placeholder="Busca una dirección, finca, vereda o municipio">
                <button type="button" id="searchLocationBtn" class="lot-map-btn">
                    Buscar ubicación
                </button>
            </div>
            <div id="locationSearchResults" class="lot-location-results hidden"></div>

            <div class="lot-map-box">
                <div id="lotMapOverlay" class="lot-map-overlay">
                    <div class="max-w-lg text-center">
                        <div class="text-xl font-extrabold text-gray-900">Croquis opcional del lote</div>
                        <p class="text-sm text-gray-600 mt-3">
                            Puedes buscar una ubicación, ver el polígono actual y volver a dibujarlo si necesitas ajustarlo.
                        </p>
                    </div>
                </div>

                <div id="lotMap" class="lot-map"></div>
            </div>

            <div class="lot-map-stats mt-4">
                <div class="lot-map-stat">
                    <div class="lot-map-stat-label">Puntos del polígono</div>
                    <div id="polygonPointsText" class="lot-map-stat-value">0 puntos</div>
                </div>

                <div class="lot-map-stat">
                    <div class="lot-map-stat-label">Área calculada</div>
                    <div id="polygonAreaText" class="lot-map-stat-value">Sin calcular</div>
                </div>

                <div class="lot-map-stat">
                    <div class="lot-map-stat-label">Centro latitud</div>
                    <div id="centerLatText" class="lot-map-stat-value">—</div>
                </div>

                <div class="lot-map-stat">
                    <div class="lot-map-stat-label">Centro longitud</div>
                    <div id="centerLngText" class="lot-map-stat-value">—</div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 mt-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Área manual (m²)</label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           name="area_manual"
                           value="{{ old('area_manual', $lot->area_manual) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                    @error('area_manual')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Área calculada (m²)</label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           id="areaCalculatedInput"
                           name="area_calculated"
                           value="{{ old('area_calculated', $lot->area_calculated) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                    @error('area_calculated')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Latitud centro</label>
                    <input type="number"
                           step="0.0000001"
                           id="centerLatInput"
                           name="center_lat"
                           value="{{ old('center_lat', $lot->center_lat) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                    @error('center_lat')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Longitud centro</label>
                    <input type="number"
                           step="0.0000001"
                           id="centerLngInput"
                           name="center_lng"
                           value="{{ old('center_lng', $lot->center_lng) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                    @error('center_lng')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <input type="hidden" id="polygonJsonInput" name="polygon_json" value='{{ $polygonJson }}'>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                Guardar cambios
            </button>

            <a href="{{ route('lots.show', $lot) }}"
               class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
    let lotMap;
    let lotPolygon = null;
    let pointMarkers = [];
    let locationSearchMarker = null;
    let polygonPath = [];
    let drawingEnabled = false;
    let mapLoaded = false;

    const defaultCenter = { lat: 6.244203, lng: -75.581212 };

    function initLotEditMap() {
        const mapEl = document.getElementById('lotMap');
        const showMapBtn = document.getElementById('showMapBtn');
        const focusMapBtn = document.getElementById('focusMapBtn');
        const locateBtn = document.getElementById('locateBtn');
        const startDrawBtn = document.getElementById('startDrawBtn');
        const closePolygonBtn = document.getElementById('closePolygonBtn');
        const undoPointBtn = document.getElementById('undoPointBtn');
        const clearPolygonBtn = document.getElementById('clearPolygonBtn');
        const locationSearchInput = document.getElementById('locationSearchInput');
        const searchLocationBtn = document.getElementById('searchLocationBtn');
        const locationSearchResults = document.getElementById('locationSearchResults');
        const overlay = document.getElementById('lotMapOverlay');
        const helpText = document.getElementById('mapHelpText');
        const locationSearchUrl = @json(route('lots.location-search'));

        const polygonPointsText = document.getElementById('polygonPointsText');
        const polygonAreaText = document.getElementById('polygonAreaText');
        const centerLatText = document.getElementById('centerLatText');
        const centerLngText = document.getElementById('centerLngText');

        const areaCalculatedInput = document.getElementById('areaCalculatedInput');
        const centerLatInput = document.getElementById('centerLatInput');
        const centerLngInput = document.getElementById('centerLngInput');
        const polygonJsonInput = document.getElementById('polygonJsonInput');

        const formatNumber = (value, digits = 2) => {
            if (value === null || value === undefined || isNaN(value)) return '—';
            return Number(value).toLocaleString('es-CO', {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            });
        };

        const updateButtons = () => {
            const hasMap = !!lotMap;
            if (focusMapBtn) focusMapBtn.disabled = !hasMap;
            if (locateBtn) locateBtn.disabled = !hasMap;
        };

        const setSearchedCenter = (location) => {
            const lat = location.lat();
            const lng = location.lng();

            if (!polygonPath.length) {
                centerLatInput.value = lat.toFixed(7);
                centerLngInput.value = lng.toFixed(7);
                centerLatText.textContent = lat.toFixed(7);
                centerLngText.textContent = lng.toFixed(7);
            }
        };

        const markSearchedLocation = (location, title = 'Ubicación buscada') => {
            if (locationSearchMarker) {
                locationSearchMarker.setMap(null);
            }

            locationSearchMarker = new google.maps.Marker({
                position: location,
                map: lotMap,
                title,
            });

            setSearchedCenter(location);
        };

        const clearLocationResults = () => {
            if (!locationSearchResults) return;

            locationSearchResults.innerHTML = '';
            locationSearchResults.classList.add('hidden');
        };

        const focusSearchResult = (result) => {
            const location = new google.maps.LatLng(Number(result.lat), Number(result.lng));
            let viewport = null;

            if (Array.isArray(result.boundingbox) && result.boundingbox.length === 4) {
                viewport = new google.maps.LatLngBounds(
                    new google.maps.LatLng(Number(result.boundingbox[0]), Number(result.boundingbox[2])),
                    new google.maps.LatLng(Number(result.boundingbox[1]), Number(result.boundingbox[3]))
                );
            }

            focusSearchedLocation(location, viewport, result.name || 'Ubicación buscada');
            clearLocationResults();
        };

        const renderLocationResults = (results) => {
            if (!locationSearchResults || !Array.isArray(results) || !results.length) {
                clearLocationResults();
                return false;
            }

            locationSearchResults.innerHTML = '';
            results.slice(0, 5).forEach((result) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'lot-location-result';
                button.textContent = result.name || 'Ubicación buscada';
                button.addEventListener('click', () => focusSearchResult(result));
                locationSearchResults.appendChild(button);
            });

            locationSearchResults.classList.remove('hidden');
            helpText.textContent = 'Selecciona la ubicación correcta para centrar el mapa.';

            return true;
        };

        const focusSearchedLocation = (location, viewport = null, title = 'Ubicación buscada') => {
            if (viewport) {
                lotMap.fitBounds(viewport);
            } else {
                lotMap.setCenter(location);
                lotMap.setZoom(18);
            }

            markSearchedLocation(location, title);
            helpText.textContent = 'Ubicación encontrada. Ahora puedes acercar el mapa y redibujar o ajustar el lote.';
        };

        let midpointMarkers = [];
        let areaChip = null;
        let areaLabel = null;
        let mapControlsBuilt = false;
        let undoMapBtn = null, redoMapBtn = null, clearMapBtn = null;
        let deleteInfo = null;
        let history = [];
        let histIndex = -1;
        let suppressHistory = false;
        let selectedIndex = -1;
        let moveHandle = null; let rotScale = 1;
        let rot = { fix: function(x){ return x; }, active: function(){ return false; }, apply: function(){}, set: function(){} };

        const POLY_STYLE = { strokeColor: '#39ff14', strokeOpacity: 1, strokeWeight: 4, fillColor: '#39ff14', fillOpacity: 0.14, clickable: false, editable: false, zIndex: 5 };
        const vertexIcon = () => ({ path: google.maps.SymbolPath.CIRCLE, scale: 7 / (rotScale || 1), fillColor: '#ffffff', fillOpacity: 1, strokeColor: '#166534', strokeWeight: 2.5 });
        const midpointIcon = () => ({ path: google.maps.SymbolPath.CIRCLE, scale: 6 / (rotScale || 1), fillColor: '#facc15', fillOpacity: 0.95, strokeColor: '#166534', strokeWeight: 1.5 });
        const moveHandleIcon = () => {
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="52" viewBox="0 0 40 52"><path d="M20 1 C20 1 5 21 5 33 A15 15 0 1 0 35 33 C35 21 20 1 20 1 Z" fill="#2563eb" fill-opacity="0.95" stroke="#ffffff" stroke-width="2.5" stroke-linejoin="round"/><circle cx="20" cy="33" r="6" fill="#ffffff"/></svg>';
            return { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), scaledSize: new google.maps.Size(40 / (rotScale || 1), 52 / (rotScale || 1)), anchor: new google.maps.Point(20 / (rotScale || 1), 1 / (rotScale || 1)) };
        };

        const makeCenterLabel = (map) => {
            const ov = new google.maps.OverlayView();
            const div = document.createElement('div');
            div.className = 'lot-area-label';
            div.style.cssText = 'position:absolute;transform:translate(-50%,-50%);display:none;color:#39ff14;font:800 12px system-ui,sans-serif;line-height:1.15;text-shadow:0 1px 3px rgba(0,0,0,.9), 0 0 2px rgba(0,0,0,.9);white-space:nowrap;pointer-events:none;text-align:center;';
            ov.onAdd = function () { this.getPanes().floatPane.appendChild(div); };
            ov.onRemove = function () { if (div.parentNode) div.parentNode.removeChild(div); };
            ov.draw = function () { const proj = this.getProjection(); if (!proj || !this._pos) { div.style.display = 'none'; return; } const pt = proj.fromLatLngToDivPixel(this._pos); if (!pt) return; div.style.left = pt.x + 'px'; div.style.top = pt.y + 'px'; div.style.display = this._html ? 'block' : 'none'; };
            ov.setContent = function (html, pos) { this._html = html; this._pos = pos; div.innerHTML = html || ''; this.draw(); };
            ov.setMap(map);
            return ov;
        };

        const formatArea = (value) => { const a = Number(value) || 0; if (a >= 10000) { return formatNumber(a, 2) + ' m² · ' + formatNumber(a / 10000, 3) + ' ha'; } return formatNumber(a, 2) + ' m²'; };
        const polygonArea = () => (polygonPath.length >= 3 && window.google?.maps?.geometry?.spherical) ? google.maps.geometry.spherical.computeArea(polygonPath.map(p => new google.maps.LatLng(p.lat, p.lng))) : 0;

        const serializePolygon = () => { polygonJsonInput.value = polygonPath.length >= 3 ? JSON.stringify(polygonPath) : ''; };

        const updateStats = () => {
            polygonPointsText.textContent = polygonPath.length + (polygonPath.length === 1 ? ' punto' : ' puntos');
            const area = polygonArea();
            if (area > 0) { areaCalculatedInput.value = area.toFixed(2); polygonAreaText.textContent = formatArea(area); }
            else if (!areaCalculatedInput.value) { polygonAreaText.textContent = 'Sin calcular'; }
            else { polygonAreaText.textContent = formatArea(areaCalculatedInput.value); }
            if (areaLabel) { if (area > 0) { const b = new google.maps.LatLngBounds(); polygonPath.forEach(p => b.extend(p)); areaLabel.setContent(formatArea(area).split(' · ').join('<br>'), b.getCenter()); } else { areaLabel.setContent('', null); } }
            if (polygonPath.length >= 1) {
                const bounds = new google.maps.LatLngBounds(); polygonPath.forEach(point => bounds.extend(point)); const center = bounds.getCenter();
                centerLatInput.value = center.lat().toFixed(7); centerLngInput.value = center.lng().toFixed(7);
                centerLatText.textContent = center.lat().toFixed(7); centerLngText.textContent = center.lng().toFixed(7);
            } else {
                centerLatInput.value = ''; centerLngInput.value = ''; centerLatText.textContent = '—'; centerLngText.textContent = '—';
                if (!areaCalculatedInput.dataset.manualTouched) { areaCalculatedInput.value = ''; }
            }
            serializePolygon(); updateButtons(); updateUndoRedoButtons();
        };

        const clearMarkers = () => { pointMarkers.forEach(m => m.setMap(null)); pointMarkers = []; midpointMarkers.forEach(m => m.setMap(null)); midpointMarkers = []; };

        const drawDisplayPolygon = () => {
            if (!lotMap) return;
            if (polygonPath.length >= 2) { if (lotPolygon) { lotPolygon.setPath(polygonPath); } else { lotPolygon = new google.maps.Polygon(Object.assign({ paths: polygonPath, map: lotMap }, POLY_STYLE)); } }
            else if (lotPolygon) { lotPolygon.setMap(null); lotPolygon = null; }
        };

        const showAdjacentMidpoints = (i) => {
            midpointMarkers.forEach(m => m.setMap(null)); midpointMarkers = [];
            const n = polygonPath.length;
            if (!lotMap || n < 2) return;
            const mid = (a, b) => ({ lat: (a.lat + b.lat) / 2, lng: (a.lng + b.lng) / 2 });
            const edges = [];
            if (n === 2) {
                edges.push({ pos: mid(polygonPath[0], polygonPath[1]), insertAt: 1 });
            } else {
                const prev = (i - 1 + n) % n, next = (i + 1) % n;
                edges.push({ pos: mid(polygonPath[prev], polygonPath[i]), insertAt: (i === 0 ? n : i) });
                edges.push({ pos: mid(polygonPath[i], polygonPath[next]), insertAt: i + 1 });
            }
            edges.forEach((edge) => {
                const mk = new google.maps.Marker({ position: edge.pos, map: lotMap, draggable: true, icon: midpointIcon(), zIndex: 25, cursor: 'crosshair', title: 'Arrastra para agregar un punto en esta línea' });
                mk._insertAt = edge.insertAt;
                mk.addListener('dragstart', () => {
                    const p = mk.getPosition();
                    polygonPath.splice(mk._insertAt, 0, { lat: p.lat(), lng: p.lng() });
                    mk._newIndex = mk._insertAt;
                    selectedIndex = mk._newIndex;
                    if (moveHandle) moveHandle.setMap(null);
                });
                mk.addListener('drag', (e) => {
                    if (mk._newIndex == null) return;
                    const __ll = rot.fix(e.latLng);
                    polygonPath[mk._newIndex] = { lat: __ll.lat(), lng: __ll.lng() };
                    if (rot.active()) mk.setPosition(polygonPath[mk._newIndex]);
                    drawDisplayPolygon(); updateStats();
                });
                mk.addListener('dragend', () => {
                    const ni = mk._newIndex; mk._newIndex = null;
                    deselect(); rebuildMarkers(); updateStats(); pushHistory();
                    if (ni != null) selectVertex(ni);
                });
                midpointMarkers.push(mk);
            });
        };

        const repositionMidpoints = () => {
            if (selectedIndex < 0 || midpointMarkers.length === 0) return;
            const i = selectedIndex, n = polygonPath.length;
            const mid = (a, b) => ({ lat: (a.lat + b.lat) / 2, lng: (a.lng + b.lng) / 2 });
            if (n === 2 && midpointMarkers[0]) { midpointMarkers[0].setPosition(mid(polygonPath[0], polygonPath[1])); return; }
            const prev = (i - 1 + n) % n, next = (i + 1) % n;
            if (midpointMarkers[0]) midpointMarkers[0].setPosition(mid(polygonPath[prev], polygonPath[i]));
            if (midpointMarkers[1]) midpointMarkers[1].setPosition(mid(polygonPath[i], polygonPath[next]));
        };

        const buildMidpoints = () => {
            midpointMarkers.forEach(m => m.setMap(null)); midpointMarkers = [];
        };

        const rebuildMarkers = () => {
            deselect();
            clearMarkers();
            drawDisplayPolygon();
            if (!lotMap) return;
            polygonPath.forEach((point, index) => {
                const marker = new google.maps.Marker({ position: point, map: lotMap, draggable: false, icon: vertexIcon(), zIndex: 20, title: 'Toca para mover o borrar este punto' });
                marker.addListener('click', () => { selectVertex(index); });
                pointMarkers.push(marker);
            });
            buildMidpoints();
        };

        const ensureMoveHandle = () => {
            if (moveHandle) return;
            moveHandle = new google.maps.Marker({ map: null, draggable: true, icon: moveHandleIcon(), zIndex: 30, cursor: 'move', title: 'Arrastra para mover el punto' });
            moveHandle.addListener('drag', (e) => {
                if (selectedIndex < 0) return;
                const __ll = rot.fix(e.latLng);
                polygonPath[selectedIndex] = { lat: __ll.lat(), lng: __ll.lng() };
                if (rot.active()) moveHandle.setPosition(polygonPath[selectedIndex]);
                if (pointMarkers[selectedIndex]) pointMarkers[selectedIndex].setPosition(polygonPath[selectedIndex]);
                drawDisplayPolygon(); repositionMidpoints(); updateStats();
            });
            moveHandle.addListener('dragend', () => { pushHistory(); });
        };

        const selectVertex = (index) => {
            ensureMoveHandle();
            selectedIndex = index;
            moveHandle.setPosition(polygonPath[index]);
            moveHandle.setMap(lotMap);
            showAdjacentMidpoints(index);
        };

        const deselect = () => {
            selectedIndex = -1;
            if (moveHandle) moveHandle.setMap(null);
            midpointMarkers.forEach(m => m.setMap(null)); midpointMarkers = [];
        };

        const deleteVertex = (index) => { polygonPath.splice(index, 1); deselect(); rebuildMarkers(); updateStats(); pushHistory(); };

        const addPoint = (latLng) => { polygonPath.push({ lat: latLng.lat(), lng: latLng.lng() }); rebuildMarkers(); updateStats(); pushHistory(); };

        const setPolygonPath = (points, pushHist = true) => { polygonPath = points.map(p => ({ lat: Number(p.lat), lng: Number(p.lng) })); rebuildMarkers(); updateStats(); if (pushHist) pushHistory(); };

        const clearPolygon = () => { polygonPath = []; deselect(); if (lotPolygon) { lotPolygon.setMap(null); lotPolygon = null; } clearMarkers(); updateStats(); pushHistory(); };

        const focusCurrentSketch = () => {
            if (!lotMap) return;
            if (polygonPath.length > 0) { const bounds = new google.maps.LatLngBounds(); polygonPath.forEach(point => bounds.extend(point)); lotMap.fitBounds(bounds); helpText.textContent = 'Vista enfocada en el croquis del lote.'; return; }
            const lat = Number(centerLatInput.value); const lng = Number(centerLngInput.value);
            if (!Number.isNaN(lat) && !Number.isNaN(lng) && lat && lng) { lotMap.setCenter({ lat, lng }); lotMap.setZoom(18); helpText.textContent = 'Vista enfocada en el centro guardado.'; return; }
            helpText.textContent = 'Aún no hay puntos. Toca el mapa para dibujar o usa Mi ubicación.';
        };

        const closePolygon = () => { focusCurrentSketch(); };
        const undoLastPoint = () => { undo(); };

        const snapshot = () => JSON.parse(JSON.stringify(polygonPath));
        const pushHistory = () => { if (suppressHistory) return; history = history.slice(0, histIndex + 1); history.push(snapshot()); histIndex = history.length - 1; updateUndoRedoButtons(); };
        const restoreHistory = () => { suppressHistory = true; setPolygonPath(history[histIndex] || [], false); suppressHistory = false; updateUndoRedoButtons(); };
        const undo = () => { if (histIndex > 0) { histIndex--; restoreHistory(); } };
        const redo = () => { if (histIndex < history.length - 1) { histIndex++; restoreHistory(); } };
        const updateUndoRedoButtons = () => {
            if (undoMapBtn) { undoMapBtn.disabled = histIndex <= 0; undoMapBtn.style.opacity = undoMapBtn.disabled ? .4 : 1; }
            if (redoMapBtn) { redoMapBtn.disabled = histIndex >= history.length - 1; redoMapBtn.style.opacity = redoMapBtn.disabled ? .4 : 1; }
        };

        let isFs = false;
        let fsParent = null, fsNext = null;
        const toggleFullscreen = () => {
            const box = mapEl.parentNode;
            isFs = !isFs;
            if (isFs) {
                fsParent = box.parentNode; fsNext = box.nextSibling;
                document.body.appendChild(box);
                box.style.position = 'fixed'; box.style.top = '0'; box.style.left = '0'; box.style.right = '0'; box.style.bottom = '0';
                box.style.width = '100vw'; box.style.height = '100vh'; box.style.zIndex = '2147483647'; box.style.borderRadius = '0'; box.style.margin = '0'; box.style.maxWidth = 'none';
                document.body.style.overflow = 'hidden';
                if (fsBtn) fsBtn.style.top = 'max(env(safe-area-inset-top, 0px), 48px)';
                { const rw = box.querySelector('.lotmap-rot'); if (rw) rw.style.top = 'calc(max(env(safe-area-inset-top, 0px), 48px) + 54px)'; }
                if (fsBtn) fsBtn.textContent = '✕';
            } else {
                box.style.position = ''; box.style.top = ''; box.style.left = ''; box.style.right = ''; box.style.bottom = '';
                box.style.width = ''; box.style.height = ''; box.style.zIndex = ''; box.style.borderRadius = ''; box.style.margin = ''; box.style.maxWidth = '';
                if (fsParent) { if (fsNext && fsNext.parentNode === fsParent) fsParent.insertBefore(box, fsNext); else fsParent.appendChild(box); }
                document.body.style.overflow = '';
                if (fsBtn) fsBtn.style.top = '12px';
                { const rw = box.querySelector('.lotmap-rot'); if (rw) rw.style.top = '66px'; }
                if (fsBtn) fsBtn.textContent = '⤢';
                if (rot) rot.set(0);
            }
            setTimeout(() => { google.maps.event.trigger(lotMap, 'resize'); if (rot) rot.apply(); }, 80);
        };
        let fsBtn = null;

        const buildMapControls = () => {
            if (!lotMap || mapControlsBuilt) return;
            mapControlsBuilt = true;
            areaLabel = makeCenterLabel(lotMap);
            const box = mapEl.parentNode;
            const overlayCtl = document.createElement('div');
            overlayCtl.className = 'lot-map-controls';
            overlayCtl.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:6;';
            box.appendChild(overlayCtl);
            const mk = (label, title) => { const b = document.createElement('button'); b.type='button'; b.title=title; b.textContent=label; b.style.cssText='pointer-events:auto;width:42px;height:42px;border:0;border-radius:12px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.3);font:800 20px system-ui,sans-serif;line-height:1;cursor:pointer;color:#111827;'; return b; };
            const bar = document.createElement('div');
            bar.style.cssText = 'position:absolute;left:12px;bottom:12px;display:flex;gap:6px;';
            undoMapBtn = mk('↶','Deshacer'); redoMapBtn = mk('↷','Rehacer'); clearMapBtn = mk('🗑','Borrar punto seleccionado (o todo)');
            undoMapBtn.addEventListener('click', undo); redoMapBtn.addEventListener('click', redo);
            clearMapBtn.addEventListener('click', () => { if (selectedIndex >= 0) { deleteVertex(selectedIndex); return; } if (polygonPath.length && confirm('¿Borrar todo el croquis?')) clearPolygon(); });
            bar.append(clearMapBtn, undoMapBtn, redoMapBtn);
            overlayCtl.appendChild(bar);
            fsBtn = mk('⤢','Pantalla completa');
            fsBtn.className='lotmap-fsbtn'; fsBtn.style.position='absolute'; fsBtn.style.right='12px'; fsBtn.style.top='12px';
            fsBtn.addEventListener('click', toggleFullscreen);
            overlayCtl.appendChild(fsBtn);
            const rotWrap = document.createElement('div');
            rotWrap.className='lotmap-rot'; rotWrap.style.cssText='position:absolute;right:12px;top:66px;display:flex;flex-direction:column;gap:6px;align-items:flex-end;';
            const compass = document.createElement('button');
            compass.type='button'; compass.title='Volver al norte (0°)';
            compass.style.cssText='pointer-events:auto;width:52px;height:52px;border:0;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.3);cursor:pointer;position:relative;';
            compass.innerHTML='<span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:800 11px system-ui;color:#6b7280;padding-top:14px;">N</span><span class="__needle" style="position:absolute;left:50%;top:7px;width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;border-bottom:15px solid #dc2626;transform-origin:50% 19px;transform:translateX(-50%);"></span>';
            const rowBtns = document.createElement('div');
            rowBtns.style.cssText='display:flex;gap:6px;';
            const ccw = mk('⟲','Girar 15° izquierda'); const cw = mk('⟳','Girar 15° derecha');
            ccw.style.width='42px'; ccw.style.height='34px'; ccw.style.fontSize='18px'; cw.style.width='42px'; cw.style.height='34px'; cw.style.fontSize='18px';
            rowBtns.append(ccw, cw);
            rotWrap.append(compass, rowBtns);
            overlayCtl.appendChild(rotWrap);
            let theta = 0;
            const needle = compass.querySelector('.__needle');
            const proj = () => {
                const p = lotMap.getProjection(); if(!p) return null;
                const scale = Math.pow(2, lotMap.getZoom());
                const w = mapEl.offsetWidth, h = mapEl.offsetHeight;
                const cW = p.fromLatLngToPoint(lotMap.getCenter());
                const tl = new google.maps.Point(cW.x - (w/2)/scale, cW.y - (h/2)/scale);
                return { w:w, h:h, toPx:function(ll){ const wp=p.fromLatLngToPoint(ll); return {x:(wp.x-tl.x)*scale, y:(wp.y-tl.y)*scale}; }, toLL:function(x,y){ return p.fromPointToLatLng(new google.maps.Point(tl.x+x/scale, tl.y+y/scale)); } };
            };
            rot = {
                active: function(){ return Math.abs(theta) > 0.001; },
                get: function(){ return theta; },
                fix: function(latLng){
                    if(!this.active()) return latLng;
                    const P = proj(); if(!P) return latLng;
                    const __cc = Math.abs(Math.cos(theta*Math.PI/180)), __ss = Math.abs(Math.sin(theta*Math.PI/180)), __W = mapEl.offsetWidth||1, __H = mapEl.offsetHeight||1; const s = Math.max((__W*__cc + __H*__ss)/__W, (__W*__ss + __H*__cc)/__H);
                    const px = P.toPx(latLng);
                    const cx = P.w/2, cy = P.h/2, a = -theta*Math.PI/180;
                    const dx = (px.x-cx)/s, dy = (px.y-cy)/s;
                    const rx = Math.cos(a)*dx - Math.sin(a)*dy, ry = Math.sin(a)*dx + Math.cos(a)*dy;
                    return P.toLL(cx+rx, cy+ry);
                },
                apply: function(){
                    const __cc = Math.abs(Math.cos(theta*Math.PI/180)), __ss = Math.abs(Math.sin(theta*Math.PI/180)), __W = mapEl.offsetWidth||1, __H = mapEl.offsetHeight||1; const s = Math.max((__W*__cc + __H*__ss)/__W, (__W*__ss + __H*__cc)/__H);
                    mapEl.style.transformOrigin = 'center center';
                    mapEl.style.transform = theta ? ('rotate('+theta+'deg) scale('+s+')') : '';
                    if(needle) needle.style.transform = 'translateX(-50%) rotate('+(-theta)+'deg)';
                    const lbl = box.querySelector('.lot-area-label');
                    if(lbl) lbl.style.transform = 'translate(-50%,-50%) rotate('+(-theta)+'deg) scale('+(1/s)+')';
                    rotScale = s;
                    pointMarkers.forEach(function(mk){ mk.setIcon(vertexIcon()); });
                    midpointMarkers.forEach(function(mk){ mk.setIcon(midpointIcon()); });
                    if(moveHandle) moveHandle.setIcon(moveHandleIcon());
                    if(lotPolygon) lotPolygon.setOptions({ strokeWeight: 4 / (s||1) });
                },
                set: function(v){ theta = ((v % 360) + 360) % 360; if(theta > 180) theta -= 360; this.apply(); }
            };
            ccw.addEventListener('click', function(){ rot.set(theta - 15); });
            cw.addEventListener('click', function(){ rot.set(theta + 15); });
            compass.addEventListener('click', function(){ rot.set(0); });
            let tPrev = null;
            mapEl.addEventListener('touchmove', function(ev){
                if(ev.touches.length !== 2){ tPrev = null; return; }
                const a = ev.touches[0], b = ev.touches[1];
                const ang = Math.atan2(b.clientY - a.clientY, b.clientX - a.clientX) * 180 / Math.PI;
                if(tPrev !== null){ var __d = ang - tPrev; while(__d > 180){ __d -= 360; } while(__d < -180){ __d += 360; } rot.set(theta + __d); }
                tPrev = ang;
            }, { passive: true });
            mapEl.addEventListener('touchend', function(){ tPrev = null; });
            updateUndoRedoButtons();
        };

        const activateMap = () => {
            if (mapLoaded) {
                overlay.classList.add('hidden');
                return;
            }

            const initialLat = Number(centerLatInput.value) || defaultCenter.lat;
            const initialLng = Number(centerLngInput.value) || defaultCenter.lng;

            lotMap = new google.maps.Map(mapEl, {
                center: defaultCenter,
                zoom: 16,
                mapTypeId: 'satellite',
                disableDefaultUI: true,
                mapTypeControl: true,
                tilt: 0,
            });

            lotMap.addListener('click', (event) => {
                if (selectedIndex >= 0) { deselect(); return; }
                addPoint(rot.fix(event.latLng));
            });

            buildMapControls();

            mapLoaded = true;
            overlay.classList.add('hidden');
            helpText.textContent = 'Mapa activo. Puedes buscar una ubicación, enfocar el croquis, arrastrar puntos o redibujarlo.';
            updateButtons();

            const oldPolygonJson = polygonJsonInput.value;

            if (oldPolygonJson) {
                try {
                    const parsed = JSON.parse(oldPolygonJson);

                    if (Array.isArray(parsed) && parsed.length >= 3) {
                        setPolygonPath(parsed.map(point => ({
                            lat: Number(point.lat),
                            lng: Number(point.lng),
                        })));

                        const bounds = new google.maps.LatLngBounds();
                        polygonPath.forEach(point => bounds.extend(point));
                        lotMap.fitBounds(bounds);

                        helpText.textContent = 'Se cargó el croquis actual. Puedes arrastrar sus puntos para reubicarlos.';
                    }
                } catch (error) {
                    console.error('No se pudo cargar el polígono inicial:', error);
                }
            }
        };

        const searchLocation = async () => {
            const query = (locationSearchInput?.value || '').trim();

            if (!query) {
                helpText.textContent = 'Escribe una dirección, vereda, municipio o referencia para buscar.';
                return;
            }

            activateMap();
            helpText.textContent = 'Buscando ubicación...';
            searchLocationBtn.disabled = true;
            clearLocationResults();

            const searchWithServer = async () => {
                try {
                    const url = new URL(locationSearchUrl, window.location.origin);
                    url.searchParams.set('q', query);

                    const response = await fetch(url.toString(), {
                        cache: 'no-store',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return false;
                    }

                    const payload = await response.json();
                    const results = Array.isArray(payload.results)
                        ? payload.results.filter((result) => result?.lat && result?.lng)
                        : [];

                    return renderLocationResults(results);
                } catch (error) {
                    console.error('Error buscando ubicación en el servidor:', error);
                }

                return false;
            };

            const searchWithBrowserGeocoder = async () => {
                const geocoder = new google.maps.Geocoder();
                const queries = [
                    query,
                    `${query}, Colombia`,
                ];

                for (const address of queries) {
                    const found = await new Promise((resolve) => {
                        geocoder.geocode({
                            address,
                            region: 'CO',
                            componentRestrictions: { country: 'CO' },
                        }, (results, status) => {
                            if (status !== 'OK' || !results?.length) {
                                resolve(false);
                                return;
                            }

                            const result = results[0];
                            const location = result.geometry.location;

                            clearLocationResults();
                            focusSearchedLocation(location, result.geometry.viewport || null, result.formatted_address || 'Ubicación buscada');
                            resolve(true);
                        });
                    });

                    if (found) {
                        return true;
                    }
                }

                return false;
            };

            let found = false;

            try {
                found = await searchWithServer() || await searchWithBrowserGeocoder();

                if (!found) {
                    helpText.textContent = 'No encontramos esa ubicación. Prueba con el nombre del lugar, dirección, municipio y departamento.';
                }
            } finally {
                searchLocationBtn.disabled = false;
            }
        };


        showMapBtn.addEventListener('click', () => {
            activateMap();
        });

        locateBtn.addEventListener('click', () => {
            if (!lotMap || !navigator.geolocation) return;

            navigator.geolocation.getCurrentPosition((position) => {
                const coords = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };

                lotMap.setCenter(coords);
                lotMap.setZoom(18);
                helpText.textContent = 'Mapa centrado en tu ubicación actual.';
            }, () => {
                helpText.textContent = 'No se pudo obtener tu ubicación. Puedes mover el mapa manualmente.';
            });
        });

        searchLocationBtn.addEventListener('click', () => {
            searchLocation();
        });

        locationSearchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;

            event.preventDefault();
            searchLocation();
        });

        focusMapBtn.addEventListener('click', () => {
            focusCurrentSketch();
        });

        areaCalculatedInput.addEventListener('input', () => {
            areaCalculatedInput.dataset.manualTouched = '1';
            polygonAreaText.textContent = areaCalculatedInput.value
                ? formatNumber(areaCalculatedInput.value, 2) + ' m²'
                : 'Sin calcular';
        });

        try {
            const _pj = polygonJsonInput.value;
            if (_pj) { const _p = JSON.parse(_pj); if (Array.isArray(_p) && _p.length >= 3) { activateMap(); } }
        } catch (e) {}

        updateButtons();
        updateStats();
        if (!history.length) pushHistory();
    }
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=geometry&callback=initLotEditMap"
    async
    defer
></script>

@endsection

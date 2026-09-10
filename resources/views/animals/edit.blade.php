@extends('layouts.app')

@section('title', 'Editar animal')

@section('content')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

@php
    $breeds = [
        'Aberdeen Angus','Angus','Ankole','Ayrshire','Beefmaster','Belgian Blue','Blanco Orejinegro (BON)',
        'Bonsmara','Boran','Braford','Brangus','Brahman','Brahman Gris','Brahman Rojo','Brown Swiss',
        'Carora','Charbray','Charolais','Chianina','Chino Santandereano','Clavel','Costeño con Cuernos',
        'Criollo','Criollo Colombiano','Criollo / Mestizo','Cruce','Dexter','Fleckvieh','Gelbvieh',
        'Girolando','Gir','Gir Lechero','Guzerá','Hartón del Valle','Hereford','Holstein','Holstein Rojo',
        'Indubrasil','Jersey','Limousin','Lucerna','Lowline Angus','Maine-Anjou','Marchigiana','Mestizo',
        'Montbéliarde','Nelore','Normando','Pardo Suizo','Piemontese','Pinzgauer','Red Angus','Red Poll',
        'Retinta','Romagnola','Romosinuano','Sanmartinero','Santa Gertrudis','Senepol','Shorthorn',
        'Simbrah','Simbrah Rojo','Simmental','South Devon','Wagyu','Otra',
    ];

    $purposes = [
        'carne' => 'Carne',
        'leche' => 'Leche',
        'doble_proposito' => 'Doble propósito',
        'crianza' => 'Crianza',
    ];

    $systemDams = collect($eligibleDams ?? []);
    $systemSires = collect($eligibleSires ?? []);
    $lots = collect($lots ?? []);
    $transferFarms = collect($transferFarms ?? []);
    $transferPanelOpen = $errors->has('target_farm_id') || $errors->has('target_lot_id');
    $transferFarmsForJson = $transferFarms->map(fn ($farm) => [
        'id' => $farm->id,
        'name' => $farm->name,
        'lots' => $farm->lots->map(fn ($lot) => [
            'id' => $lot->id,
            'name' => $lot->name,
        ])->values(),
    ])->values();
@endphp

<style>
    .animal-section { overflow: visible !important; }
    .animal-select-wrap { position: relative; z-index: 30; }
    .animal-select-wrap:focus-within { z-index: 80; }
    .ts-dropdown, .ts-dropdown-content { z-index: 9999 !important; }

    .upload-dropzone{
        border:1.5px dashed rgba(22,101,52,.22);
        background:linear-gradient(135deg,rgba(255,255,255,.72),rgba(243,244,246,.85));
        transition:all .2s ease;
    }

    .upload-dropzone.dragover{
        border-color:rgba(22,101,52,.55);
        background:rgba(22,101,52,.06);
        transform:translateY(-1px);
    }

    .animal-photo-card{
        position:relative;
        overflow:hidden;
        border-radius:20px;
        border:1px solid rgba(0,0,0,.10);
        background:#fff;
        box-shadow:0 12px 24px rgba(15,23,42,.06);
        transition:all .18s ease;
    }

    .animal-photo-card.is-main{
        border-color:rgba(22,101,52,.36);
        box-shadow:0 14px 30px rgba(22,101,52,.12);
    }

    .animal-photo-card.is-removed{
        opacity:.45;
        filter:grayscale(1);
    }

    .animal-photo-card.is-removed::after{
        content:"Se eliminará";
        position:absolute;
        inset:0;
        display:flex;
        align-items:center;
        justify-content:center;
        background:rgba(17,24,39,.54);
        color:#fff;
        font-size:12px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.04em;
        pointer-events:none;
    }

    .animal-photo-action{
        display:inline-flex;
        min-height:34px;
        align-items:center;
        justify-content:center;
        border-radius:999px;
        padding:7px 10px;
        font-size:12px;
        font-weight:800;
        transition:all .18s ease;
    }

    .animal-status-field{
        width:100%;
        min-height:46px;
        border-radius:14px;
        border:1px solid rgba(148,163,184,.38);
        background:rgba(255,255,255,.92);
        color:#111827;
        padding:11px 14px;
        font-size:14px;
        font-weight:750;
        outline:none;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.74);
        transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .animal-status-field:focus{
        border-color:rgba(22,101,52,.50);
        background:#fff;
        box-shadow:0 0 0 4px rgba(22,101,52,.10);
    }

    .animal-status-select-wrap{
        position:relative;
    }

    .animal-status-select-wrap::after{
        content:"";
        position:absolute;
        right:16px;
        top:50%;
        width:9px;
        height:9px;
        border-right:2px solid rgba(22,101,52,.78);
        border-bottom:2px solid rgba(22,101,52,.78);
        transform:translateY(-66%) rotate(45deg);
        pointer-events:none;
    }

    .animal-status-select{
        appearance:none;
        -webkit-appearance:none;
        -moz-appearance:none;
        padding-right:46px;
        cursor:pointer;
    }

    .animal-status-field::placeholder{
        color:#94a3b8;
        font-weight:700;
    }

    .dark .animal-status-field{
        border-color:rgba(148,163,184,.24);
        background:rgba(15,23,42,.82);
        color:#f8fafc;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.04);
    }

    .dark .animal-status-field:focus{
        border-color:rgba(74,222,128,.48);
        background:rgba(2,6,23,.94);
        box-shadow:0 0 0 4px rgba(34,197,94,.14);
    }

    .dark .animal-status-field::placeholder{
        color:#64748b;
    }

    .dark .animal-status-select option{
        background:#020617;
        color:#f8fafc;
    }

    .flatpickr-input[readonly]{
        background:rgba(255,255,255,.70);
    }

    .repro-card{
        border:1px solid rgba(22,101,52,.10);
        background:rgba(255,255,255,.52);
        border-radius:20px;
        padding:16px;
    }

    .lot-helper-card{
        border:1px solid rgba(22,101,52,.12);
        background:rgba(22,101,52,.05);
        border-radius:18px;
        padding:14px 16px;
    }

    .animal-transfer-panel{
        border:1px solid rgba(22,101,52,.12);
        background:rgba(22,101,52,.05);
        border-radius:20px;
        padding:16px;
    }

    .animal-transfer-panel.is-hidden{
        display:none;
    }
</style>

<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Editar animal</h1>
            <p class="text-sm text-gray-500 mt-1">Actualiza la información del animal y conserva su historial.</p>
        </div>

        <a href="{{ route('animals.index') }}"
           class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
            Volver al listado
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            Revisa los campos marcados. Hay información pendiente o inválida.
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <form id="animalTransferForm" method="POST" action="{{ route('animals.transfer', $animal) }}" class="hidden">
        @csrf
        @method('PATCH')
    </form>

    <form method="POST"
          action="{{ route('animals.update', $animal) }}"
          enctype="multipart/form-data"
        class="space-y-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="photo_management_present" value="1">
        <input type="hidden" name="main_photo_id" id="main_photo_id" value="{{ old('main_photo_id', optional($animal->photos->firstWhere('is_main', true) ?? $animal->photos->first())->id) }}">

        {{-- IDENTIFICACIÓN --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Identificación</h2>
                <p class="text-sm text-gray-500 mt-1">Datos básicos para reconocer el animal.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Código interno</label>
                    <input type="text" name="internal_code" value="{{ old('internal_code', $animal->internal_code) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: INT-001">
                    @error('internal_code') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Arete</label>
                    <input type="text" name="ear_tag" value="{{ old('ear_tag', $animal->ear_tag) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: 1025">
                    @error('ear_tag') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre o alias</label>
                    <input type="text" name="name" value="{{ old('name', $animal->name) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: Lucera">
                    @error('name') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- FOTOS --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Fotos del animal</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Arrastra tus imágenes o selecciónalas. La que marques como principal quedará de portada.
                </p>
            </div>

            @if($animal->photos->count())
                <div class="mb-6">
                    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-900">Fotos actuales</h3>
                            <p class="text-xs font-semibold text-gray-500">Marca una como principal o elimina las que se subieron por error.</p>
                        </div>
                        <span id="existing-photos-count" class="text-xs font-bold text-gray-500">{{ $animal->photos->count() }} imagen(es)</span>
                    </div>

                    <div id="existing-photos-grid" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                        @foreach($animal->photos as $photo)
                            @php
                                $oldKeepPhotos = old('photo_management_present')
                                    ? old('keep_existing_photos', [])
                                    : $animal->photos->pluck('id')->map(fn ($id) => (string) $id)->all();
                                $keepChecked = in_array((string) $photo->id, $oldKeepPhotos ?? [], true);
                                $isMain = (string) old('main_photo_id', optional($animal->photos->firstWhere('is_main', true) ?? $animal->photos->first())->id) === (string) $photo->id;
                            @endphp
                            <div class="animal-photo-card {{ $isMain ? 'is-main' : '' }} {{ $keepChecked ? '' : 'is-removed' }}" data-existing-photo-card data-photo-id="{{ $photo->id }}">
                                <input type="checkbox" name="keep_existing_photos[]" value="{{ $photo->id }}" class="hidden" data-keep-photo @checked($keepChecked)>

                                <div class="flex items-center justify-between gap-2 bg-white px-3 py-2">
                                    <span class="text-xs font-extrabold {{ $isMain ? 'text-brand' : 'text-gray-500' }}" data-photo-status>
                                        {{ $isMain ? 'Principal' : 'Foto actual' }}
                                    </span>
                                </div>

                                <button type="button" class="block aspect-square w-full bg-white" data-main-photo-button aria-label="Marcar como principal">
                                    <img src="{{ asset('storage/' . $photo->path) }}" alt="Foto actual del animal" class="h-full w-full object-cover">
                                </button>

                                <div class="grid grid-cols-1 gap-2 bg-white p-3">
                                    <button type="button" class="animal-photo-action border border-brand/20 bg-white text-brand hover:bg-brand/10" data-main-photo-button>
                                        Principal
                                    </button>
                                    <button type="button" class="animal-photo-action border border-red-200 bg-white text-red-600 hover:bg-red-50" data-remove-existing-photo>
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="mb-6 rounded-2xl border border-dashed border-black/10 bg-white/55 px-4 py-5 text-sm font-semibold text-gray-500">
                    Este animal aún no tiene fotos guardadas.
                </div>
            @endif

            <div id="dropzone" class="upload-dropzone rounded-[24px] p-6">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 16V4m0 0l-4 4m4-4l4 4M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/>
                        </svg>
                    </div>

                    <h3 class="text-base font-bold text-gray-900">Agrega nuevas fotos</h3>

                    <p class="mt-2 text-sm text-gray-500 max-w-xl">
                        Puedes agregar fotos nuevas. Las fotos actuales se conservan automáticamente.
                    </p>

                    <div class="mt-5">
                        <label for="photos"
                               class="inline-flex cursor-pointer rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark transition">
                            Seleccionar imágenes
                        </label>
                    </div>

                    <input id="photos" type="file" name="photos[]" accept="image/*,.jpg,.jpeg,.png,.webp,.gif,.bmp,.tif,.tiff,.avif,.heic,.heif" multiple class="hidden">
                </div>
            </div>

            @error('photos') <span class="mt-3 block text-sm text-red-600">{{ $message }}</span> @enderror
            @error('photos.*') <span class="mt-3 block text-sm text-red-600">{{ $message }}</span> @enderror

            <div id="photos-help" class="mt-4 text-xs text-gray-500">Aún no has seleccionado imágenes nuevas.</div>
            <div id="preview-grid" class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4"></div>
        </div>

        {{-- CLASIFICACIÓN --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Clasificación</h2>
                <p class="text-sm text-gray-500 mt-1">Datos productivos y biológicos principales.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div class="animal-select-wrap">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Raza</label>
                    <select name="breed"
                            class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                        <option value="">Selecciona una raza</option>
                        @foreach($breeds as $breed)
                            <option value="{{ $breed }}" {{ old('breed', $animal->breed) === $breed ? 'selected' : '' }}>
                                {{ $breed }}
                            </option>
                        @endforeach
                    </select>
                    @error('breed') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="animal-select-wrap">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Sexo *</label>
                    <select id="sex" name="sex" required
                            class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                        <option value="">Selecciona el sexo</option>
                        <option value="macho" {{ old('sex', $animal->sex) === 'macho' ? 'selected' : '' }}>Macho</option>
                        <option value="hembra" {{ old('sex', $animal->sex) === 'hembra' ? 'selected' : '' }}>Hembra</option>
                    </select>
                    @error('sex') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="animal-select-wrap">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Propósito</label>
                    <select name="purpose"
                            class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                        <option value="">Selecciona un propósito</option>
                        @foreach($purposes as $value => $label)
                            <option value="{{ $value }}" {{ old('purpose', $animal->purpose) === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('purpose') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- NACIMIENTO --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Nacimiento</h2>
                <p class="text-sm text-gray-500 mt-1">Selecciona la fecha fácilmente usando año, mes y día.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha de nacimiento</label>
                    <input type="text" id="birth_date" name="birth_date" value="{{ old('birth_date', optional($animal->birth_date)->format('Y-m-d')) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Selecciona la fecha" autocomplete="off">
                    @error('birth_date') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-3">
                    <div class="text-sm font-semibold text-gray-700">Edad calculada</div>
                    <div id="age-preview" class="text-xs text-gray-500 mt-1">
                        Completa la fecha de nacimiento para calcular la edad.
                    </div>
                </div>

                <div id="stage-badge-box" class="hidden rounded-2xl border border-brand/10 bg-brand/5 px-4 py-3">
                    <div class="text-sm font-semibold text-brand">Estado sugerido</div>
                    <div id="stage-badge-text" class="text-xs text-gray-600 mt-1"></div>
                </div>
            </div>
        </div>

        {{-- SEGUIMIENTO REPRODUCTIVO --}}
        <div id="female-panel" class="glass animal-section rounded-[28px] p-6 hidden">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Seguimiento reproductivo</h2>
                <p id="female-panel-text" class="text-sm text-gray-500 mt-1">
                    Este bloque se activa según la edad reproductiva del animal.
                </p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                <div id="pregnant-card" class="repro-card hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div id="pregnant-block" class="animal-select-wrap hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">¿Actualmente está preñada?</label>
                            <select id="is_pregnant" name="is_pregnant"
                                    class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                                <option value="">Selecciona una opción</option>
                                <option value="no" {{ old('is_pregnant', $animal->is_pregnant) === 'no' ? 'selected' : '' }}>No</option>
                                <option value="si" {{ old('is_pregnant', $animal->is_pregnant) === 'si' ? 'selected' : '' }}>Sí</option>
                            </select>
                        </div>

                        <div id="pregnancy-date-block" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha de preñez / servicio</label>
                            <input type="text" id="pregnancy_date" name="pregnancy_date" value="{{ old('pregnancy_date', optional($animal->pregnancy_date)->format('Y-m-d')) }}"
                                   class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                                   placeholder="Selecciona la fecha" autocomplete="off">
                        </div>

                        <div id="service-type-block" class="animal-select-wrap hidden" data-preg-extra>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo de servicio</label>
                            <select id="service_type" name="service_type"
                                    class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                                <option value="">Selecciona una opción</option>
                                <option value="monta_natural" {{ old('service_type', $animal->service_type) === 'monta_natural' ? 'selected' : '' }}>Monta natural (toro)</option>
                                <option value="inseminacion" {{ old('service_type', $animal->service_type) === 'inseminacion' ? 'selected' : '' }}>Inseminación (pajilla)</option>
                                <option value="embrion" {{ old('service_type', $animal->service_type) === 'embrion' ? 'selected' : '' }}>Transferencia de embrión</option>
                            </select>
                        </div>

                        <div id="pregnancy-sire-block" class="animal-select-wrap hidden" data-preg-extra>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Toro (del sistema)</label>
                            <select name="pregnancy_sire_id"
                                    class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                                <option value="">Selecciona el toro</option>
                                @foreach($systemSires as $sire)
                                    <option value="{{ $sire->id }}" {{ (int) old('pregnancy_sire_id', $animal->pregnancy_sire_id) === (int) $sire->id ? 'selected' : '' }}>
                                        {{ $sire->ear_tag ?: 'Sin arete' }} - {{ $sire->name ?: 'Sin nombre' }}@if($sire->farm) · 🏠 {{ $sire->farm->name }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="pregnancy-sire-manual-block" class="hidden" data-preg-extra>
                            <label id="preg-detail-label" class="block text-sm font-semibold text-gray-700 mb-1">Detalle del servicio</label>
                            <input type="text" id="pregnancy_sire_name_manual" name="pregnancy_sire_name_manual" value="{{ old('pregnancy_sire_name_manual', $animal->pregnancy_sire_name_manual) }}"
                                   class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                                   placeholder="Ej: Toro Élite / Pajilla 784">
                        </div>
                    </div>
                </div>

                <div id="calving-card" class="repro-card hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div id="calved-block" class="animal-select-wrap hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">¿Ya ha tenido partos?</label>
                            <select id="has_calved_before" name="has_calved_before"
                                    class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                                <option value="">Selecciona una opción</option>
                                <option value="no" {{ old('has_calved_before', $animal->has_calved_before) === 'no' ? 'selected' : '' }}>No</option>
                                <option value="si" {{ old('has_calved_before', $animal->has_calved_before) === 'si' ? 'selected' : '' }}>Sí</option>
                            </select>
                        </div>

                        <div id="calving-date-block" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha del último parto</label>
                            <input type="text" id="last_calving_date" name="last_calving_date" value="{{ old('last_calving_date', optional($animal->last_calving_date)->format('Y-m-d')) }}"
                                   class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                                   placeholder="Selecciona la fecha" autocomplete="off">
                        </div>

                        <div id="calving-count-block" class="hidden md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">¿Cuántos partos ha tenido?</label>
                            <input type="number" min="0" max="25" step="1" id="calving_count" name="calving_count" value="{{ old('calving_count', $animal->calving_count) }}"
                                   class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                                   placeholder="Ej: 2">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-5 rounded-2xl border border-brand/10 bg-brand/5 px-4 py-3 text-sm text-gray-700">
                InterFarm calculará después: lista para preñarse, preparto, secado, parto probable, destete y nueva fertilidad.
            </div>
        </div>

        {{-- GENEALOGÍA --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Genealogía</h2>
                <p class="text-sm text-gray-500 mt-1">Puedes elegir padre y madre del sistema o escribirlos manualmente si aún no existen.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="space-y-3">
                    <div class="animal-select-wrap">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Madre (del sistema)</label>
                        <select name="dam_id"
                                class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                            <option value="">Selecciona la madre</option>
                            @foreach($systemDams as $dam)
                                <option value="{{ $dam->id }}" {{ (int) old('dam_id', $animal->dam_id) === (int) $dam->id ? 'selected' : '' }}>
                                    {{ $dam->ear_tag ?: 'Sin arete' }} - {{ $dam->name ?: 'Sin nombre' }}@if($dam->farm) · 🏠 {{ $dam->farm->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('dam_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="rounded-2xl border border-black/10 bg-white/40 px-4 py-3 text-xs text-gray-500">
                        Solo se muestran hembras con edad suficiente para haber sido madre.
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Madre (manual)</label>
                        <input type="text" name="dam_name_manual" value="{{ old('dam_name_manual', $animal->dam_name_manual) }}"
                               class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                               placeholder="Ej: Lucera 215 / Arete 991">
                    </div>
                </div>

                <div class="space-y-3">
                    <div class="animal-select-wrap">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Padre (del sistema)</label>
                        <select name="sire_id"
                                class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                            <option value="">Selecciona el padre</option>
                            @foreach($systemSires as $sire)
                                <option value="{{ $sire->id }}" {{ (int) old('sire_id', $animal->sire_id) === (int) $sire->id ? 'selected' : '' }}>
                                    {{ $sire->ear_tag ?: 'Sin arete' }} - {{ $sire->name ?: 'Sin nombre' }}@if($sire->farm) · 🏠 {{ $sire->farm->name }}@endif
                                </option>
                            @endforeach
                        </select>
                        @error('sire_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="rounded-2xl border border-black/10 bg-white/40 px-4 py-3 text-xs text-gray-500">
                        Solo se muestran machos con edad reproductiva.
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Padre (manual)</label>
                        <input type="text" name="sire_name_manual" value="{{ old('sire_name_manual', $animal->sire_name_manual) }}"
                               class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                               placeholder="Ej: Toro Élite / Pajilla 784">
                    </div>
                </div>
            </div>
        </div>

        {{-- UBICACIÓN Y MANEJO --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Ubicación y manejo</h2>
                <p class="text-sm text-gray-500 mt-1">Asigna el animal a un lote real de la finca y guarda referencias adicionales.</p>
            </div>

            @if($transferFarms->count())
                <div class="mb-5">
                    <button type="button"
                            class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition"
                            data-toggle-animal-transfer
                            aria-expanded="{{ $transferPanelOpen ? 'true' : 'false' }}"
                            aria-controls="singleAnimalTransferPanel">
                        Mover a otra finca
                    </button>

                    <div id="singleAnimalTransferPanel" class="animal-transfer-panel {{ $transferPanelOpen ? '' : 'is-hidden' }} mt-4">
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-4 md:items-end">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Finca destino</label>
                                <div class="animal-status-select-wrap">
                                    <select name="target_farm_id"
                                            form="animalTransferForm"
                                            class="animal-status-field animal-status-select"
                                            data-single-transfer-farm-select
                                            required>
                                        <option value="">Selecciona finca</option>
                                        @foreach($transferFarms as $farmOption)
                                            <option value="{{ $farmOption->id }}" @selected((string) old('target_farm_id') === (string) $farmOption->id)>
                                                {{ $farmOption->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('target_farm_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1">Lote destino</label>
                                <div class="animal-status-select-wrap">
                                    <select name="target_lot_id"
                                            form="animalTransferForm"
                                            class="animal-status-field animal-status-select"
                                            data-single-transfer-lot-select
                                            data-old-value="{{ old('target_lot_id') }}"
                                            disabled>
                                        <option value="">Primero selecciona una finca</option>
                                    </select>
                                </div>
                                @error('target_lot_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                            </div>

                            <button type="submit"
                                    form="animalTransferForm"
                                    class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                                Trasladar
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="animal-select-wrap">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lote</label>
                    <select name="lot_id"
                            class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                        <option value="">Sin lote asignado</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" {{ (string) old('lot_id', $animal->lot_id) === (string) $lot->id ? 'selected' : '' }}>
                                {{ $lot->name }}
                                @if(!empty($lot->code))
                                    - {{ $lot->code }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('lot_id') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror

                    @if($lots->isEmpty())
                        <div class="mt-3 lot-helper-card text-sm text-gray-700">
                            Aún no tienes lotes creados.
                            <a href="{{ route('lots.create') }}" class="font-semibold text-brand hover:underline">
                                Crear lote ahora
                            </a>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ubicación / referencia</label>
                    <input type="text" name="location" value="{{ old('location', $animal->location) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: Cerca al bebedero, corral 2, zona norte">
                    @error('location') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- ESTADO --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Estado del animal</h2>
                <p class="text-sm text-gray-500 mt-1">Marca si el animal está activo, vendido o fallecido.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div class="min-w-0">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Estado</label>
                    <div class="animal-status-select-wrap">
                        <select name="status" class="animal-status-field animal-status-select">
                            @foreach(\App\Models\Animal::statusOptions() as $value => $label)
                                <option value="{{ $value }}" {{ old('status', $animal->status ?: \App\Models\Animal::STATUS_ACTIVE) === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('status') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="min-w-0">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha del estado</label>
                    <input type="date" name="status_date" value="{{ old('status_date', optional($animal->status_date)->format('Y-m-d')) }}"
                           class="animal-status-field block min-w-0 max-w-full">
                    @error('status_date') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="min-w-0 md:col-span-2 xl:col-span-1">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Notas del estado</label>
                    <input type="text" name="status_notes" value="{{ old('status_notes', $animal->status_notes) }}"
                           class="animal-status-field"
                           placeholder="Ej: vendido, baja sanitaria, traslado...">
                    @error('status_notes') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- DATOS COMPLEMENTARIOS --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Datos complementarios</h2>
                <p class="text-sm text-gray-500 mt-1">Información útil para el manejo del animal.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Peso actual (kg)</label>
                    <input type="number" step="0.01" min="0" max="2000" name="weight_current" value="{{ old('weight_current', $animal->weight_current) }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: 285.5">
                    @error('weight_current') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="rounded-2xl border border-black/10 bg-white/40 px-4 py-3">
                    <div class="text-sm font-semibold text-gray-700">Sugerencia</div>
                    <div class="text-xs text-gray-500 mt-1">
                        Lo ideal es que cada animal quede ligado a un lote real del sistema y no solo a una referencia escrita.
                    </div>
                </div>
            </div>

            <div class="mt-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Observaciones</label>
                <textarea name="notes" rows="4"
                          class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-3 outline-none focus:border-brand/40"
                          placeholder="Notas adicionales del animal...">{{ old('notes', $animal->cleanNotes()) }}</textarea>
                @error('notes') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                Guardar cambios
            </button>

            <a href="{{ route('animals.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Cancelar
            </a>
        </div>
    </form>

    <div x-data="{ confirmDelete: false }"
         x-effect="document.body.classList.toggle('overflow-hidden', confirmDelete)"
         class="glass animal-section rounded-[28px] border border-red-200/70 p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-red-700 dark:text-red-300">Eliminar animal</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Elimina definitivamente el animal, sus fotos y sus registros asociados en esta ficha.
                </p>
            </div>

            <button type="button"
                    @click="confirmDelete = true"
                    class="rounded-xl bg-red-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700">
                Eliminar animal
            </button>
        </div>

        <template x-teleport="body">
            <div x-cloak
                 x-show="confirmDelete"
                 x-transition.opacity
                 class="fixed inset-0 z-[999999] flex items-center justify-center overflow-y-auto bg-black/55 px-4 py-6"
                 style="min-height: 100vh; min-height: 100dvh;">
                <div @click.outside="confirmDelete = false"
                     x-transition.scale.origin.center
                     class="w-full max-w-md rounded-[24px] border border-black/10 bg-white p-6 shadow-2xl dark:border-white/10 dark:bg-slate-950"
                     style="position: relative;">
                    <h3 class="text-xl font-extrabold text-gray-900 dark:text-white">Confirmar eliminación</h3>
                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        Esta acción eliminará permanentemente a
                        <strong>{{ $animal->name ?: ($animal->ear_tag ?: ($animal->internal_code ?: 'este animal')) }}</strong>.
                        No podrás deshacerla.
                    </p>

                    <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button"
                                @click="confirmDelete = false"
                                class="rounded-xl border border-black/10 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800">
                            Cancelar
                        </button>

                        <form method="POST" action="{{ route('animals.destroy', $animal) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 sm:w-auto">
                                Sí, eliminar definitivamente
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const transferFarms = @json($transferFarmsForJson);
    const sexSelect = document.getElementById('sex');

    const femalePanel = document.getElementById('female-panel');
    const femalePanelText = document.getElementById('female-panel-text');

    const pregnantCard = document.getElementById('pregnant-card');
    const pregnantBlock = document.getElementById('pregnant-block');
    const pregnancyDateBlock = document.getElementById('pregnancy-date-block');
    const isPregnantSelect = document.getElementById('is_pregnant');

    const calvingCard = document.getElementById('calving-card');
    const calvedBlock = document.getElementById('calved-block');
    const calvingDateBlock = document.getElementById('calving-date-block');
    const calvingCountBlock = document.getElementById('calving-count-block');
    const hasCalvedBeforeSelect = document.getElementById('has_calved_before');

    const stageBadgeBox = document.getElementById('stage-badge-box');
    const stageBadgeText = document.getElementById('stage-badge-text');

    const dropzone = document.getElementById('dropzone');
    const photosInput = document.getElementById('photos');
    const previewGrid = document.getElementById('preview-grid');
    const photosHelp = document.getElementById('photos-help');
    const mainPhotoInput = document.getElementById('main_photo_id');
    const existingPhotosGrid = document.getElementById('existing-photos-grid');
    const existingPhotosCount = document.getElementById('existing-photos-count');

    const birthDateInput = document.getElementById('birth_date');
    const pregnancyDateInput = document.getElementById('pregnancy_date');
    const lastCalvingDateInput = document.getElementById('last_calving_date');
    const agePreview = document.getElementById('age-preview');
    const animalTransferToggle = document.querySelector('[data-toggle-animal-transfer]');
    const animalTransferPanel = document.getElementById('singleAnimalTransferPanel');
    const animalTransferFarmSelect = document.querySelector('[data-single-transfer-farm-select]');
    const animalTransferLotSelect = document.querySelector('[data-single-transfer-lot-select]');

    let selectedFiles = [];
    const acceptedPhotoExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'avif', 'heic', 'heif'];

    const existingPhotoCards = () => Array.from(document.querySelectorAll('[data-existing-photo-card]'));

    const keptExistingPhotoCards = () => existingPhotoCards().filter((card) => {
        const checkbox = card.querySelector('[data-keep-photo]');

        return checkbox && checkbox.checked;
    });

    const updateExistingPhotosCount = () => {
        if (!existingPhotosCount) return;

        const kept = keptExistingPhotoCards().length;
        const total = existingPhotoCards().length;

        existingPhotosCount.textContent = `${kept} de ${total} imagen(es) conservadas`;
    };

    const rebuildAnimalTransferLots = () => {
        if (!animalTransferFarmSelect || !animalTransferLotSelect) return;

        const farm = transferFarms.find((item) => String(item.id) === String(animalTransferFarmSelect.value));
        const oldValue = animalTransferLotSelect.dataset.oldValue || '';
        animalTransferLotSelect.innerHTML = '';

        if (!farm) {
            animalTransferLotSelect.disabled = true;
            animalTransferLotSelect.append(new Option('Primero selecciona una finca', ''));
            return;
        }

        animalTransferLotSelect.disabled = false;
        animalTransferLotSelect.append(new Option('Sin lote asignado', ''));

        farm.lots.forEach((lot) => {
            const option = new Option(lot.name, lot.id);
            option.selected = String(oldValue) === String(lot.id);
            animalTransferLotSelect.append(option);
        });
    };

    animalTransferToggle?.addEventListener('click', () => {
        if (!animalTransferPanel) return;

        const isHidden = animalTransferPanel.classList.toggle('is-hidden');
        animalTransferToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
    });

    animalTransferFarmSelect?.addEventListener('change', () => {
        if (animalTransferLotSelect) {
            animalTransferLotSelect.dataset.oldValue = '';
        }

        rebuildAnimalTransferLots();
    });

    rebuildAnimalTransferLots();

    const firstAvailableMainValue = () => {
        const firstExisting = keptExistingPhotoCards()[0];

        if (firstExisting) {
            return firstExisting.dataset.photoId;
        }

        if (selectedFiles.length > 0) {
            return 'new_0';
        }

        return '';
    };

    const syncExistingPhotoUi = () => {
        const mainValue = mainPhotoInput ? mainPhotoInput.value : '';

        existingPhotoCards().forEach((card) => {
            const checkbox = card.querySelector('[data-keep-photo]');
            const status = card.querySelector('[data-photo-status]');
            const removeButton = card.querySelector('[data-remove-existing-photo]');
            const isKept = checkbox ? checkbox.checked : false;
            const isMain = isKept && mainValue === card.dataset.photoId;

            card.classList.toggle('is-removed', !isKept);
            card.classList.toggle('is-main', isMain);

            if (status) {
                status.textContent = isMain ? 'Principal' : (isKept ? 'Foto actual' : 'Se eliminará');
                status.classList.toggle('text-brand', isMain);
                status.classList.toggle('text-gray-500', !isMain);
                status.classList.toggle('text-red-600', !isKept);
            }

            if (removeButton) {
                removeButton.textContent = isKept ? 'Eliminar' : 'Conservar';
                removeButton.classList.toggle('border-red-200', isKept);
                removeButton.classList.toggle('bg-white', isKept);
                removeButton.classList.toggle('text-red-600', isKept);
                removeButton.classList.toggle('hover:bg-red-50', isKept);
                removeButton.classList.toggle('border-brand/20', !isKept);
                removeButton.classList.toggle('bg-white', !isKept);
                removeButton.classList.toggle('text-brand', !isKept);
                removeButton.classList.toggle('hover:bg-brand/10', !isKept);
            }
        });

        updateExistingPhotosCount();
    };

    const setMainPhotoValue = (value) => {
        if (!mainPhotoInput) return;

        mainPhotoInput.value = value || '';
        syncExistingPhotoUi();
        renderPreviews();
    };

    const ensureMainPhotoValue = () => {
        if (!mainPhotoInput) return;

        const value = mainPhotoInput.value;

        if (value && !value.startsWith('new_')) {
            const selectedExisting = existingPhotoCards().find((card) => card.dataset.photoId === value);
            const checkbox = selectedExisting?.querySelector('[data-keep-photo]');

            if (checkbox?.checked) {
                syncExistingPhotoUi();
                return;
            }
        }

        if (value?.startsWith('new_')) {
            const newIndex = Number(value.replace('new_', ''));

            if (Number.isInteger(newIndex) && selectedFiles[newIndex]) {
                syncExistingPhotoUi();
                return;
            }
        }

        mainPhotoInput.value = firstAvailableMainValue();
        syncExistingPhotoUi();
    };

    flatpickr('#birth_date', {
        locale: 'es',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        maxDate: 'today',
        defaultDate: birthDateInput.value || null,
        disableMobile: true,
        onChange: function(selectedDates, dateStr) {
            birthDateInput.value = dateStr;
            updateAgePreview();
            updateFemalePanel();
        }
    });

    flatpickr('#pregnancy_date', {
        locale: 'es',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        defaultDate: pregnancyDateInput.value || null,
        disableMobile: true
    });

    flatpickr('#last_calving_date', {
        locale: 'es',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',
        defaultDate: lastCalvingDateInput.value || null,
        disableMobile: true
    });

    const canSyncInputFiles = () => typeof DataTransfer !== 'undefined';

    const syncInputFiles = () => {
        if (!canSyncInputFiles()) {
            return false;
        }

        const dt = new DataTransfer();
        selectedFiles.forEach((file) => dt.items.add(file));
        photosInput.files = dt.files;

        return true;
    };

    const isHeicFile = (file) => {
        const name = (file.name || '').toLowerCase();
        const type = (file.type || '').toLowerCase();

        return name.endsWith('.heic') ||
            name.endsWith('.heif') ||
            type === 'image/heic' ||
            type === 'image/heif';
    };

    const isAcceptedImage = (file) => {
        const name = (file.name || '').toLowerCase();
        const type = (file.type || '').toLowerCase();
        const extension = name.includes('.') ? name.split('.').pop() : '';

        return type.startsWith('image/') ||
            acceptedPhotoExtensions.includes(extension);
    };

    const convertHeicToJpeg = async (file) => {
        if (!isHeicFile(file)) return file;

        photosHelp.textContent = 'Convirtiendo imagen HEIC, espera un momento...';

        const blob = await heic2any({
            blob: file,
            toType: 'image/jpeg',
            quality: 0.82
        });

        const convertedBlob = Array.isArray(blob) ? blob[0] : blob;
        const cleanName = (file.name || 'foto.heic').replace(/\.(heic|heif)$/i, '.jpg');

        return new File([convertedBlob], cleanName, {
            type: 'image/jpeg',
            lastModified: Date.now()
        });
    };

    const normalizeIncomingFiles = async (incomingFiles) => {
        const normalized = [];

        for (const file of incomingFiles) {
            if (!isAcceptedImage(file)) continue;

            try {
                normalized.push(await convertHeicToJpeg(file));
            } catch (error) {
                console.error('Error convirtiendo HEIC:', error);
                photosHelp.textContent = 'No se pudo convertir una imagen HEIC. Intenta con otra foto.';
            }
        }

        return normalized;
    };

    const calculateAgeInMonths = (birthDateValue) => {
        if (!birthDateValue) return null;

        const birthDate = new Date(birthDateValue + 'T00:00:00');
        const today = new Date();

        let months = (today.getFullYear() - birthDate.getFullYear()) * 12;
        months += today.getMonth() - birthDate.getMonth();

        if (today.getDate() < birthDate.getDate()) {
            months--;
        }

        return months >= 0 ? months : null;
    };

    const updateStageBadge = (months, sex) => {
        stageBadgeBox.classList.add('hidden');
        stageBadgeText.textContent = '';

        if (months === null) return;

        if (sex === 'hembra') {
            if (months >= 13 && months < 22) {
                stageBadgeBox.classList.remove('hidden');
                stageBadgeText.textContent = 'Novilla apta para iniciar seguimiento reproductivo.';
                return;
            }

            if (months >= 22 && months < 36) {
                stageBadgeBox.classList.remove('hidden');
                stageBadgeText.textContent = 'Hembra en etapa para registrar partos y reproducción.';
                return;
            }

            if (months >= 36) {
                stageBadgeBox.classList.remove('hidden');
                stageBadgeText.textContent = 'Hembra con historial reproductivo más avanzado.';
                return;
            }
        }

        if (sex === 'macho' && months >= 18) {
            stageBadgeBox.classList.remove('hidden');
            stageBadgeText.textContent = 'Macho con edad reproductiva.';
        }
    };

    const togglePregnancyDate = () => {
        const showPreg = isPregnantSelect && isPregnantSelect.value === 'si';
        if (showPreg) {
            pregnancyDateBlock.classList.remove('hidden');
        } else {
            pregnancyDateBlock.classList.add('hidden');
        }
        document.querySelectorAll('[data-preg-extra]').forEach((el) => {
            el.classList.toggle('hidden', ! showPreg);
        });

        const serviceTypeSelect = document.getElementById('service_type');
        const serviceType = serviceTypeSelect ? serviceTypeSelect.value : '';
        const sireBlock = document.getElementById('pregnancy-sire-block');
        if (sireBlock) {
            sireBlock.classList.toggle('hidden', ! (showPreg && serviceType === 'monta_natural'));
        }
        const detailLabel = document.getElementById('preg-detail-label');
        const detailInput = document.getElementById('pregnancy_sire_name_manual');
        if (detailLabel && detailInput) {
            if (serviceType === 'inseminacion') {
                detailLabel.textContent = '¿Qué pajilla fue?';
                detailInput.placeholder = 'Ej: Pajilla 784 / Toro Élite';
            } else if (serviceType === 'embrion') {
                detailLabel.textContent = 'Código o lote del embrión';
                detailInput.placeholder = 'Ej: EMB-2026-014';
            } else if (serviceType === 'monta_natural') {
                detailLabel.textContent = 'O escribe el toro (manual)';
                detailInput.placeholder = 'Ej: Toro Élite / arete 123';
            } else {
                detailLabel.textContent = 'Detalle del servicio';
                detailInput.placeholder = '';
            }
        }
    };

    const toggleCalvingFields = (months) => {
        if (hasCalvedBeforeSelect && hasCalvedBeforeSelect.value === 'si') {
            calvingDateBlock.classList.remove('hidden');
            calvingCountBlock.classList.remove('hidden');
        } else {
            calvingDateBlock.classList.add('hidden');
            calvingCountBlock.classList.add('hidden');
        }
    };

    const updateFemalePanel = () => {
        const sex = sexSelect ? sexSelect.value : '';
        const months = calculateAgeInMonths(birthDateInput ? birthDateInput.value : '');

        femalePanel.classList.add('hidden');
        pregnantCard.classList.add('hidden');
        pregnantBlock.classList.add('hidden');
        pregnancyDateBlock.classList.add('hidden');

        calvingCard.classList.add('hidden');
        calvedBlock.classList.add('hidden');
        calvingDateBlock.classList.add('hidden');
        calvingCountBlock.classList.add('hidden');

        updateStageBadge(months, sex);

        if (sex !== 'hembra' || months === null) {
            femalePanelText.textContent = 'Este bloque se activa según la edad reproductiva del animal.';
            return;
        }

        // Punto 3: para cualquier hembra con fecha de nacimiento, preñez y partos siempre disponibles
        femalePanel.classList.remove('hidden');
        pregnantCard.classList.remove('hidden');
        pregnantBlock.classList.remove('hidden');
        calvingCard.classList.remove('hidden');
        calvedBlock.classList.remove('hidden');
        femalePanelText.textContent = 'Puedes registrar si está preñada, si ya ha tenido partos y cuántos.';
        togglePregnancyDate();
        toggleCalvingFields(months);
        return;

        if (months >= 13 && months < 22) {
            femalePanel.classList.remove('hidden');
            pregnantCard.classList.remove('hidden');
            pregnantBlock.classList.remove('hidden');
            femalePanelText.textContent = 'Como es hembra y tiene 13 meses o más, ya puedes registrar si está preñada.';
            togglePregnancyDate();
            return;
        }

        if (months >= 22 && months < 24) {
            femalePanel.classList.remove('hidden');
            calvingCard.classList.remove('hidden');
            calvedBlock.classList.remove('hidden');
            femalePanelText.textContent = 'Como es hembra y tiene 22 meses o más, ya puedes registrar si ha tenido partos.';
            toggleCalvingFields(months);
            return;
        }

        if (months >= 24 && months < 36) {
            femalePanel.classList.remove('hidden');
            pregnantCard.classList.remove('hidden');
            pregnantBlock.classList.remove('hidden');
            calvingCard.classList.remove('hidden');
            calvedBlock.classList.remove('hidden');
            femalePanelText.textContent = 'Como es hembra y tiene 24 meses o más, ya puedes registrar preñez, partos y sus fechas.';
            togglePregnancyDate();
            toggleCalvingFields(months);
            return;
        }

        if (months >= 36) {
            femalePanel.classList.remove('hidden');
            pregnantCard.classList.remove('hidden');
            pregnantBlock.classList.remove('hidden');
            calvingCard.classList.remove('hidden');
            calvedBlock.classList.remove('hidden');
            femalePanelText.textContent = 'Como es hembra y ya tiene tiempo para varios ciclos reproductivos, también puedes registrar la cantidad de partos.';
            togglePregnancyDate();
            toggleCalvingFields(months);
        }
    };

    const updateAgePreview = () => {
        const months = calculateAgeInMonths(birthDateInput ? birthDateInput.value : '');

        if (months === null) {
            agePreview.textContent = 'Completa la fecha de nacimiento para calcular la edad.';
            return;
        }

        const years = Math.floor(months / 12);
        const remainingMonths = months % 12;

        if (years > 0) {
            agePreview.textContent = `${years} año(s) y ${remainingMonths} mes(es) (${months} meses).`;
        } else {
            agePreview.textContent = `${months} mes(es).`;
        }
    };

    const setMainPhoto = (index) => {
        if (index === 0) {
            setMainPhotoValue('new_0');
            return;
        }

        const selected = selectedFiles.splice(index, 1)[0];
        selectedFiles.unshift(selected);
        syncInputFiles();
        setMainPhotoValue('new_0');
    };

    const removePhoto = (index) => {
        const removedMain = mainPhotoInput && mainPhotoInput.value === `new_${index}`;
        selectedFiles.splice(index, 1);
        syncInputFiles();

        if (removedMain || mainPhotoInput?.value?.startsWith('new_')) {
            mainPhotoInput.value = firstAvailableMainValue();
        }

        renderPreviews();
    };

    const renderPreviews = () => {
        previewGrid.innerHTML = '';

        if (!selectedFiles.length) {
            photosHelp.textContent = 'Aún no has seleccionado imágenes.';
            ensureMainPhotoValue();
            return;
        }

        if (!mainPhotoInput.value && keptExistingPhotoCards().length === 0) {
            mainPhotoInput.value = 'new_0';
        }

        photosHelp.textContent = `${selectedFiles.length} imagen(es) nueva(s) seleccionada(s). Puedes marcar una como principal.`;

        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                const card = document.createElement('div');
                const isMain = mainPhotoInput && mainPhotoInput.value === `new_${index}`;
                card.className = `relative overflow-hidden rounded-2xl border ${isMain ? 'border-brand/40 shadow-lg shadow-brand/10' : 'border-black/10'} bg-white/80 cursor-pointer`;

                const badge = isMain
                    ? '<div class="px-3 py-2 text-xs font-semibold text-brand border-b border-black/5 bg-brand/5">Principal</div>'
                    : `<div class="px-3 py-2 text-xs font-semibold text-gray-500 border-b border-black/5 bg-white/70">Imagen ${index + 1}</div>`;

                card.innerHTML = `
                    ${badge}
                    <button type="button"
                            class="absolute top-2 right-2 z-10 h-8 w-8 rounded-full bg-white/95 text-gray-700 shadow hover:bg-red-50 hover:text-red-600 transition flex items-center justify-center">
                        ✕
                    </button>
                    <div class="aspect-[4/4] bg-gray-100">
                        <img src="${e.target.result}" alt="Preview" class="h-full w-full object-cover">
                    </div>
                    <div class="px-3 pt-3">
                        <button type="button"
                                class="w-full rounded-xl border border-brand/20 bg-brand/10 px-3 py-2 text-xs font-bold text-brand transition hover:bg-brand/15">
                            Marcar principal
                        </button>
                    </div>
                    <div class="px-3 py-2 text-xs text-gray-500 truncate">${file.name}</div>
                `;

                const removeButton = card.querySelector('button.absolute');
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    removePhoto(index);
                });

                const mainButton = card.querySelector('button:not(.absolute)');
                mainButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    setMainPhoto(index);
                });

                card.addEventListener('click', () => {
                    setMainPhoto(index);
                });

                previewGrid.appendChild(card);
            };

            reader.readAsDataURL(file);
        });
    };

    const appendFiles = async (incomingFiles) => {
        const imageFiles = await normalizeIncomingFiles(incomingFiles);
        if (!imageFiles.length) return;

        for (const file of imageFiles) {
            if (selectedFiles.length >= 6) break;

            const duplicate = selectedFiles.some(existing =>
                existing.name === file.name &&
                existing.size === file.size &&
                existing.lastModified === file.lastModified
            );

            if (!duplicate) selectedFiles.push(file);
        }

        if (selectedFiles.length > 6) {
            selectedFiles = selectedFiles.slice(0, 6);
        }

        syncInputFiles();
        renderPreviews();

        if (selectedFiles.length >= 6) {
            photosHelp.textContent = 'Ya tienes 6 imágenes seleccionadas.';
        }
    };

    if (existingPhotosGrid) {
        existingPhotosGrid.addEventListener('click', (event) => {
            const card = event.target.closest('[data-existing-photo-card]');
            if (!card) return;

            const checkbox = card.querySelector('[data-keep-photo]');

            if (event.target.closest('[data-remove-existing-photo]')) {
                event.preventDefault();

                if (checkbox) {
                    checkbox.checked = !checkbox.checked;

                    if (!checkbox.checked && mainPhotoInput?.value === card.dataset.photoId) {
                        mainPhotoInput.value = firstAvailableMainValue();
                    }

                    if (checkbox.checked && !mainPhotoInput?.value) {
                        mainPhotoInput.value = card.dataset.photoId;
                    }
                }

                ensureMainPhotoValue();
                renderPreviews();
                return;
            }

            if (event.target.closest('[data-main-photo-button]')) {
                event.preventDefault();

                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true;
                }

                setMainPhotoValue(card.dataset.photoId);
            }
        });
    }

    if (sexSelect) {
        sexSelect.addEventListener('change', updateFemalePanel);
    }

    if (isPregnantSelect) {
        isPregnantSelect.addEventListener('change', togglePregnancyDate);
        var serviceTypeChangeEl = document.getElementById('service_type');
        if (serviceTypeChangeEl) { serviceTypeChangeEl.addEventListener('change', togglePregnancyDate); }
    }

    if (hasCalvedBeforeSelect) {
        hasCalvedBeforeSelect.addEventListener('change', () => {
            const months = calculateAgeInMonths(birthDateInput ? birthDateInput.value : '');
            toggleCalvingFields(months);
        });
    }

    if (birthDateInput) {
        birthDateInput.addEventListener('change', () => {
            updateAgePreview();
            updateFemalePanel();
        });
        updateAgePreview();
    }

    updateFemalePanel();
    ensureMainPhotoValue();
    renderPreviews();

    if (photosInput) {
        photosInput.addEventListener('change', () => {
            const files = Array.from(photosInput.files || []);
            appendFiles(files);
        });
    }

    if (dropzone && photosInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer.files || []);
            appendFiles(files);
        });
    }
});
</script>

@endsection
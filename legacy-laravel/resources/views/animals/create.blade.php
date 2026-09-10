@extends('layouts.app')

@section('title', 'Nuevo animal')

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
</style>

<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Nuevo animal</h1>
            <p class="text-sm text-gray-500 mt-1">Registra un animal y agrégalo al inventario de tu finca.</p>
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

    <form method="POST"
          action="{{ route('animals.store') }}"
          enctype="multipart/form-data"
          class="space-y-6">
        @csrf

        {{-- IDENTIFICACIÓN --}}
        <div class="glass animal-section rounded-[28px] p-6">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Identificación</h2>
                <p class="text-sm text-gray-500 mt-1">Datos básicos para reconocer el animal.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Código interno *</label>
                    <input type="text" name="internal_code" value="{{ old('internal_code') }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: INT-001" required>
                    @error('internal_code') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Arete</label>
                    <input type="text" name="ear_tag" value="{{ old('ear_tag') }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: 1025">
                    @error('ear_tag') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre o alias</label>
                    <input type="text" name="name" value="{{ old('name') }}"
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

            <div id="dropzone" class="upload-dropzone rounded-[24px] p-6">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-7 w-7">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 16V4m0 0l-4 4m4-4l4 4M5 16v2a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-2"/>
                        </svg>
                    </div>

                    <h3 class="text-base font-bold text-gray-900">Sube hasta 5 fotos</h3>

                    <p class="mt-2 text-sm text-gray-500 max-w-xl">
                        Puedes arrastrarlas aquí o seleccionarlas manualmente. Luego puedes borrar cualquiera o cambiar la principal.
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

            <div id="photos-help" class="mt-4 text-xs text-gray-500">Aún no has seleccionado imágenes.</div>
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
                            <option value="{{ $breed }}" {{ old('breed') === $breed ? 'selected' : '' }}>
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
                        <option value="macho" {{ old('sex') === 'macho' ? 'selected' : '' }}>Macho</option>
                        <option value="hembra" {{ old('sex') === 'hembra' ? 'selected' : '' }}>Hembra</option>
                    </select>
                    @error('sex') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
                </div>

                <div class="animal-select-wrap">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Propósito</label>
                    <select name="purpose"
                            class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                        <option value="">Selecciona un propósito</option>
                        @foreach($purposes as $value => $label)
                            <option value="{{ $value }}" {{ old('purpose') === $value ? 'selected' : '' }}>
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
                    <input type="text" id="birth_date" name="birth_date" value="{{ old('birth_date') }}"
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
                                <option value="no" {{ old('is_pregnant') === 'no' ? 'selected' : '' }}>No</option>
                                <option value="si" {{ old('is_pregnant') === 'si' ? 'selected' : '' }}>Sí</option>
                            </select>
                        </div>

                        <div id="pregnancy-date-block" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha de preñez / servicio</label>
                            <input type="text" id="pregnancy_date" name="pregnancy_date" value="{{ old('pregnancy_date') }}"
                                   class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                                   placeholder="Selecciona la fecha" autocomplete="off">
                        </div>

                        <div id="service-type-block" class="animal-select-wrap hidden" data-preg-extra>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo de servicio</label>
                            <select id="service_type" name="service_type"
                                    class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                                <option value="">Selecciona una opción</option>
                                <option value="monta_natural" {{ old('service_type') === 'monta_natural' ? 'selected' : '' }}>Monta natural (toro)</option>
                                <option value="inseminacion" {{ old('service_type') === 'inseminacion' ? 'selected' : '' }}>Inseminación (pajilla)</option>
                                <option value="embrion" {{ old('service_type') === 'embrion' ? 'selected' : '' }}>Transferencia de embrión</option>
                            </select>
                        </div>

                        <div id="pregnancy-sire-block" class="animal-select-wrap hidden" data-preg-extra>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Toro (del sistema)</label>
                            <select name="pregnancy_sire_id"
                                    class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                                <option value="">Selecciona el toro</option>
                                @foreach($systemSires as $sire)
                                    <option value="{{ $sire->id }}" {{ old('pregnancy_sire_id') == $sire->id ? 'selected' : '' }}>
                                        {{ $sire->ear_tag ?: 'Sin arete' }} - {{ $sire->name ?: 'Sin nombre' }}@if($sire->farm) · 🏠 {{ $sire->farm->name }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="pregnancy-sire-manual-block" class="hidden" data-preg-extra>
                            <label id="preg-detail-label" class="block text-sm font-semibold text-gray-700 mb-1">Detalle del servicio</label>
                            <input type="text" id="pregnancy_sire_name_manual" name="pregnancy_sire_name_manual" value="{{ old('pregnancy_sire_name_manual') }}"
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
                                <option value="no" {{ old('has_calved_before') === 'no' ? 'selected' : '' }}>No</option>
                                <option value="si" {{ old('has_calved_before') === 'si' ? 'selected' : '' }}>Sí</option>
                            </select>
                        </div>

                        <div id="calving-date-block" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Fecha del último parto</label>
                            <input type="text" id="last_calving_date" name="last_calving_date" value="{{ old('last_calving_date') }}"
                                   class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                                   placeholder="Selecciona la fecha" autocomplete="off">
                        </div>

                        <div id="calving-count-block" class="hidden md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">¿Cuántos partos ha tenido?</label>
                            <input type="number" min="0" max="25" step="1" id="calving_count" name="calving_count" value="{{ old('calving_count') }}"
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
                                <option value="{{ $dam->id }}" {{ old('dam_id') == $dam->id ? 'selected' : '' }}>
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
                        <input type="text" name="dam_name_manual" value="{{ old('dam_name_manual') }}"
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
                                <option value="{{ $sire->id }}" {{ old('sire_id') == $sire->id ? 'selected' : '' }}>
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
                        <input type="text" name="sire_name_manual" value="{{ old('sire_name_manual') }}"
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="animal-select-wrap">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lote</label>
                    <select name="lot_id"
                            class="select-search w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40">
                        <option value="">Sin lote asignado</option>
                        @foreach($lots as $lot)
                            <option value="{{ $lot->id }}" {{ (string) old('lot_id') === (string) $lot->id ? 'selected' : '' }}>
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
                    <input type="text" name="location" value="{{ old('location') }}"
                           class="w-full rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 outline-none focus:border-brand/40"
                           placeholder="Ej: Cerca al bebedero, corral 2, zona norte">
                    @error('location') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
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
                    <input type="number" step="0.01" min="0" max="2000" name="weight_current" value="{{ old('weight_current') }}"
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
                          placeholder="Notas adicionales del animal...">{{ old('notes') }}</textarea>
                @error('notes') <span class="mt-1 block text-sm text-red-600">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                Guardar animal
            </button>

            <a href="{{ route('animals.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script src="https://cdn.jsdelivr.net/npm/heic2any/dist/heic2any.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
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

    const birthDateInput = document.getElementById('birth_date');
    const pregnancyDateInput = document.getElementById('pregnancy_date');
    const lastCalvingDateInput = document.getElementById('last_calving_date');
    const agePreview = document.getElementById('age-preview');

    let selectedFiles = [];
    const acceptedPhotoExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'avif', 'heic', 'heif'];

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
        if (index === 0) return;
        const selected = selectedFiles.splice(index, 1)[0];
        selectedFiles.unshift(selected);
        syncInputFiles();
        renderPreviews();
    };

    const removePhoto = (index) => {
        selectedFiles.splice(index, 1);
        syncInputFiles();
        renderPreviews();
    };

    const renderPreviews = () => {
        previewGrid.innerHTML = '';

        if (!selectedFiles.length) {
            photosHelp.textContent = 'Aún no has seleccionado imágenes.';
            return;
        }

        photosHelp.textContent = `${selectedFiles.length} imagen(es) seleccionada(s). La primera será la principal.`;

        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                const card = document.createElement('div');
                card.className = 'relative overflow-hidden rounded-2xl border border-black/10 bg-white/80 cursor-pointer';

                const badge = index === 0
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
                    <div class="px-3 py-2 text-xs text-gray-500 truncate">${file.name}</div>
                `;

                const removeButton = card.querySelector('button');
                removeButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    removePhoto(index);
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
            if (selectedFiles.length >= 5) break;

            const duplicate = selectedFiles.some(existing =>
                existing.name === file.name &&
                existing.size === file.size &&
                existing.lastModified === file.lastModified
            );

            if (!duplicate) selectedFiles.push(file);
        }

        if (selectedFiles.length > 5) {
            selectedFiles = selectedFiles.slice(0, 5);
        }

        syncInputFiles();
        renderPreviews();

        if (selectedFiles.length >= 5) {
            photosHelp.textContent = 'Ya tienes 5 imágenes seleccionadas.';
        }
    };

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
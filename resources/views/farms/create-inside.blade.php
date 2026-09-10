@extends('layouts.app')

@section('title', 'Agregar finca')

@section('content')
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-extrabold uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                    Nueva finca
                </div>

                <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">
                    Agregar otra finca
                </h1>

                <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-300">
                    Crea una finca adicional para manejar sus animales, lotes, producción, gastos y eventos de forma independiente.
                </p>
            </div>

            <a href="{{ route('dashboard') }}"
               class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-extrabold text-gray-700 transition hover:border-green-300 hover:text-green-700 dark:border-white/10 dark:bg-slate-900 dark:text-gray-100 dark:hover:border-green-400/40 dark:hover:text-green-200">
                Volver al panel
            </a>
        </div>

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                Revisa los campos marcados. Hay información pendiente.
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <form method="POST"
                  action="{{ route('farms.store') }}"
                  class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                @csrf

                <div class="grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-extrabold text-gray-700 dark:text-gray-100">
                            Nombre de la finca
                        </label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-green-500 focus:ring-4 focus:ring-green-500/10 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:placeholder:text-gray-500"
                               placeholder="Ej: La Esperanza">
                        @error('name')
                            <p class="mt-1 text-sm font-semibold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-extrabold text-gray-700 dark:text-gray-100">
                            Ubicación
                        </label>
                        <input type="text"
                               name="location"
                               value="{{ old('location') }}"
                               class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-green-500 focus:ring-4 focus:ring-green-500/10 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:placeholder:text-gray-500"
                               placeholder="Ej: San Jerónimo, Antioquia">
                        @error('location')
                            <p class="mt-1 text-sm font-semibold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-extrabold text-gray-700 dark:text-gray-100">
                            Hectáreas
                        </label>
                        <input type="number"
                               step="0.01"
                               min="0"
                               name="hectares"
                               value="{{ old('hectares') }}"
                               class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-green-500 focus:ring-4 focus:ring-green-500/10 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:placeholder:text-gray-500"
                               placeholder="Ej: 18.5">
                        @error('hectares')
                            <p class="mt-1 text-sm font-semibold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-extrabold text-gray-700 dark:text-gray-100">
                            Tipo de producción
                        </label>

                        <div class="mt-2 grid gap-3 md:grid-cols-3">
                            <label class="group cursor-pointer">
                                <input type="radio"
                                       name="production_type"
                                       value="leche"
                                       required
                                       class="peer sr-only"
                                       @checked(old('production_type') === 'leche')>
                                <span class="flex h-full flex-col gap-2 rounded-2xl border border-gray-200 bg-white p-4 text-gray-700 shadow-sm transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800 peer-checked:ring-4 peer-checked:ring-green-500/10 hover:border-green-300 dark:border-white/10 dark:bg-slate-950 dark:text-gray-200 dark:peer-checked:border-green-400 dark:peer-checked:bg-green-500/10 dark:peer-checked:text-green-100">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-200">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l1.2 4H7.8L9 3Zm-1.2 6h8.4l-.9 11H8.7L7.8 9Z"/>
                                        </svg>
                                    </span>
                                    <span class="text-sm font-black">Leche</span>
                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Ordeño y litros diarios.</span>
                                </span>
                            </label>

                            <label class="group cursor-pointer">
                                <input type="radio"
                                       name="production_type"
                                       value="carne"
                                       required
                                       class="peer sr-only"
                                       @checked(old('production_type') === 'carne')>
                                <span class="flex h-full flex-col gap-2 rounded-2xl border border-gray-200 bg-white p-4 text-gray-700 shadow-sm transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800 peer-checked:ring-4 peer-checked:ring-green-500/10 hover:border-green-300 dark:border-white/10 dark:bg-slate-950 dark:text-gray-200 dark:peer-checked:border-green-400 dark:peer-checked:bg-green-500/10 dark:peer-checked:text-green-100">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M5 12c1.8-3.3 4.2-5 7-5s5.2 1.7 7 5c-1.8 3.3-4.2 5-7 5s-5.2-1.7-7-5Z"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M8 12h8"/>
                                        </svg>
                                    </span>
                                    <span class="text-sm font-black">Carne</span>
                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Peso, levante y engorde.</span>
                                </span>
                            </label>

                            <label class="group cursor-pointer">
                                <input type="radio"
                                       name="production_type"
                                       value="doble_proposito"
                                       required
                                       class="peer sr-only"
                                       @checked(old('production_type') === 'doble_proposito')>
                                <span class="flex h-full flex-col gap-2 rounded-2xl border border-gray-200 bg-white p-4 text-gray-700 shadow-sm transition peer-checked:border-green-500 peer-checked:bg-green-50 peer-checked:text-green-800 peer-checked:ring-4 peer-checked:ring-green-500/10 hover:border-green-300 dark:border-white/10 dark:bg-slate-950 dark:text-gray-200 dark:peer-checked:border-green-400 dark:peer-checked:bg-green-500/10 dark:peer-checked:text-green-100">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-lime-100 text-lime-700 dark:bg-lime-500/15 dark:text-lime-200">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M7 5h10M9 9h6M8 13h8M10 17h4"/>
                                        </svg>
                                    </span>
                                    <span class="text-sm font-black">Doble propósito</span>
                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Leche y carne juntos.</span>
                                </span>
                            </label>
                        </div>

                        @error('production_type')
                            <p class="mt-1 text-sm font-semibold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-extrabold text-gray-700 dark:text-gray-100">
                            Descripción
                        </label>
                        <textarea name="description"
                                  rows="4"
                                  class="mt-2 w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-green-500 focus:ring-4 focus:ring-green-500/10 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:placeholder:text-gray-500"
                                  placeholder="Ej: finca de ordeño, levante, cría o manejo mixto...">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm font-semibold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-extrabold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-slate-950 dark:text-gray-100 dark:hover:bg-white/5">
                        Cancelar
                    </a>

                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-green-600 px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                        Crear finca
                    </button>
                </div>
            </form>

            <aside class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-slate-900">
                <h2 class="text-lg font-black text-gray-900 dark:text-white">
                    Al crearla
                </h2>

                <div class="mt-4 space-y-4 text-sm text-gray-600 dark:text-gray-300">
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        La nueva finca quedará seleccionada automáticamente.
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        Sus animales, lotes, producción, finanzas y eventos quedarán separados de las demás fincas.
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                        Podrás cambiar entre fincas desde el selector del menú lateral.
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection

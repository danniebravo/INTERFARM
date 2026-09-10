<x-guest-layout>
    <div class="w-full max-w-5xl mx-auto glass-card rounded-2xl overflow-hidden grid md:grid-cols-2">

        <!-- HERO -->
        <div class="bg-brand text-white p-12 flex flex-col justify-center relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-28 -left-28 w-80 h-80 rounded-full bg-black/10 blur-3xl"></div>

            <h1 data-reveal class="text-3xl font-extrabold mb-4 leading-tight">
                Configura tu finca
            </h1>

            <p data-reveal class="text-green-100 mb-8 leading-relaxed max-w-md">
                Esta información nos permite organizar tu operación, tus animales y tus reportes.
                Puedes editarla más adelante.
            </p>

            <ul data-reveal class="space-y-3 text-green-100 text-sm">
                <li>✔ Estructura lista para ganado y módulos</li>
                <li>✔ Reportes por finca y por fechas</li>
                <li>✔ Alertas y calendario por eventos</li>
            </ul>
        </div>

        <!-- FORM -->
        <div class="p-12 flex flex-col justify-center">
            <h2 data-reveal class="text-2xl font-bold mb-2">Crear finca</h2>

            <p data-reveal class="text-gray-500 mb-6">
                Completa lo básico para empezar.
            </p>

            @if ($errors->any())
                <div data-reveal class="mb-6 text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                    Revisa los campos marcados. Hay información pendiente.
                </div>
            @endif

            <form method="POST" action="{{ route('farms.store') }}" class="space-y-5">
                @csrf

                <!-- Nombre -->
                <div data-reveal>
                    <label class="block text-sm font-medium text-gray-700">
                        Nombre de la finca
                    </label>

                    <input type="text"
                           name="name"
                           value="{{ old('name') }}"
                           required
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Ej: La Alquería">

                    @error('name')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Ubicación -->
                <div data-reveal>
                    <label class="block text-sm font-medium text-gray-700">
                        Ubicación (opcional)
                    </label>

                    <input type="text"
                           name="location"
                           value="{{ old('location') }}"
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Ej: San Jerónimo, Antioquia">

                    @error('location')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Hectáreas + Tipo producción -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- Hectáreas -->
                    <div data-reveal>
                        <label class="block text-sm font-medium text-gray-700">
                            Hectáreas (opcional)
                        </label>

                        <input type="number"
                               step="0.01"
                               min="0"
                               name="hectares"
                               value="{{ old('hectares') }}"
                               class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                               placeholder="Ej: 12.5">

                        @error('hectares')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Tipo producción -->
                    <div data-reveal>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de producción
                        </label>

                        <div class="relative">
                            <select name="production_type"
                                    required
                                    class="mt-1 w-full rounded-xl px-4 py-2 glass-input appearance-none pr-10">

                                <option value="" disabled {{ old('production_type') ? '' : 'selected' }}>
                                    Selecciona una opción
                                </option>

                                <option value="leche" {{ old('production_type')=='leche' ? 'selected' : '' }}>
                                    Producción de leche
                                </option>

                                <option value="carne" {{ old('production_type')=='carne' ? 'selected' : '' }}>
                                    Producción de carne
                                </option>

                                <option value="doble_proposito" {{ old('production_type')=='doble_proposito' ? 'selected' : '' }}>
                                    Doble propósito
                                </option>

                            </select>

                            <!-- flecha -->
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                ▼
                            </div>
                        </div>

                        @error('production_type')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                </div>

                <!-- Descripción -->
                <div data-reveal>
                    <label class="block text-sm font-medium text-gray-700">
                        Descripción (opcional)
                    </label>

                    <textarea name="description"
                              rows="3"
                              class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                              placeholder="Ej: finca de cría y levante, ordeño diario, etc.">{{ old('description') }}</textarea>

                    @error('description')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <button data-reveal
                        type="submit"
                        class="w-full text-white font-semibold py-3 rounded-xl glass-btn">
                    Crear finca y continuar
                </button>

                <p data-reveal class="text-xs text-gray-500 text-center">
                    Luego podrás registrar animales, eventos, producción y activar alertas.
                </p>

            </form>
        </div>

    </div>
</x-guest-layout>
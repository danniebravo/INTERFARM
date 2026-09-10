<x-guest-layout>
    <div class="w-full max-w-6xl mx-auto glass-card rounded-2xl overflow-hidden grid md:grid-cols-2">

        <!-- HERO -->
        <div class="bg-brand text-white p-12 flex flex-col justify-center relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
            <div class="absolute -bottom-28 -left-28 w-80 h-80 rounded-full bg-black/10 blur-3xl"></div>

            <div data-reveal class="mb-10">
                <x-brand-logo alt="Logo" class="w-[300px] max-w-full" />
            </div>

            <h1 data-reveal class="text-3xl font-extrabold mb-4 leading-tight">
                Tu finca, tu ganado, todo bajo control.
            </h1>

            <p data-reveal class="text-green-100 mb-8 leading-relaxed max-w-md">
                Centraliza inventario, salud, reproducción, producción y alertas.
                Empieza hoy y organiza tu operación como un sistema profesional.
            </p>

            <ul data-reveal class="space-y-3 text-green-100 text-sm">
                <li>✔ Acceso inmediato al panel</li>
                <li>✔ Historial completo por animal</li>
                <li>✔ Alertas inteligentes por fechas y eventos</li>
            </ul>
        </div>

        <!-- FORMULARIO -->
        <div class="p-12 flex flex-col justify-center">

            <h2 data-reveal class="text-2xl font-bold mb-2">Crear cuenta</h2>

            <p data-reveal class="text-gray-500 mb-6">
                Regístrate y configura tu primera finca en minutos.
            </p>

            @if ($errors->any())
                <div data-reveal class="mb-6 text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                    Revisa los campos marcados. Hay información pendiente.
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                <!-- Nombre + Apellidos -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div data-reveal>
                        <label class="block text-sm font-medium text-gray-700">
                            Nombre
                        </label>

                        <input type="text"
                               name="first_name"
                               value="{{ old('first_name') }}"
                               required autofocus
                               class="mt-1 w-full rounded-xl px-4 py-2 glass-input">

                        @error('first_name')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div data-reveal>
                        <label class="block text-sm font-medium text-gray-700">
                            Apellidos
                        </label>

                        <input type="text"
                               name="last_name"
                               value="{{ old('last_name') }}"
                               required
                               class="mt-1 w-full rounded-xl px-4 py-2 glass-input">

                        @error('last_name')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                </div>

                <!-- Tipo documento + Documento -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <div data-reveal>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de documento
                        </label>

                        <div class="relative">

                            <select name="document_type"
                                    required
                                    class="mt-1 w-full rounded-xl px-4 py-2 glass-input appearance-none pr-10">

                                <option value="" disabled {{ old('document_type') ? '' : 'selected' }}>
                                    Selecciona una opción
                                </option>

                                <option value="cc" {{ old('document_type')==='cc' ? 'selected' : '' }}>
                                    Cédula de ciudadanía (CC)
                                </option>

                                <option value="ce" {{ old('document_type')==='ce' ? 'selected' : '' }}>
                                    Cédula de extranjería (CE)
                                </option>

                                <option value="nit" {{ old('document_type')==='nit' ? 'selected' : '' }}>
                                    NIT
                                </option>

                                <option value="passport" {{ old('document_type')==='passport' ? 'selected' : '' }}>
                                    Pasaporte
                                </option>

                            </select>

                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                ▼
                            </div>

                        </div>

                        @error('document_type')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror

                    </div>

                    <div data-reveal>
                        <label class="block text-sm font-medium text-gray-700">
                            Documento
                        </label>

                        <input type="text"
                               name="document"
                               value="{{ old('document') }}"
                               required
                               class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                               placeholder="Ej: 1025000000">

                        @error('document')
                            <span class="text-sm text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                </div>

                <!-- Teléfono -->
                <div data-reveal>

                    <label class="block text-sm font-medium text-gray-700">
                        Teléfono (opcional)
                    </label>

                    <input type="text"
                           name="phone"
                           value="{{ old('phone') }}"
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Ej: 3001234567">

                    @error('phone')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror

                </div>

                <!-- Email -->
                <div data-reveal>

                    <label class="block text-sm font-medium text-gray-700">
                        Correo electrónico
                    </label>

                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Ej: usuario@correo.com">

                    @error('email')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror

                </div>

                <!-- Contraseña -->
                <div data-reveal>

                    <label class="block text-sm font-medium text-gray-700">
                        Contraseña
                    </label>

                    <input type="password"
                           name="password"
                           required
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input">

                    @error('password')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror

                </div>

                <!-- Confirmar contraseña -->
                <div data-reveal>

                    <label class="block text-sm font-medium text-gray-700">
                        Confirmar contraseña
                    </label>

                    <input type="password"
                           name="password_confirmation"
                           required
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input">

                </div>

                <button data-reveal type="submit"
                        class="w-full text-white font-semibold py-3 rounded-xl glass-btn">
                    Crear cuenta
                </button>

                <div data-reveal class="text-sm text-gray-600 text-center">
                    ¿Ya tienes cuenta?
                    <a href="{{ route('login') }}" class="text-brand hover:underline font-semibold">
                        Iniciar sesión
                    </a>
                </div>

            </form>

        </div>

    </div>
</x-guest-layout>

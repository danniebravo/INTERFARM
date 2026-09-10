<x-guest-layout>
    <div class="w-full max-w-xl mx-auto glass-card rounded-2xl overflow-hidden">
        <!-- Header -->
        <div class="p-8 border-b border-black/10">
            <h1 data-reveal class="text-2xl font-extrabold text-gray-900">
                Confirmar contraseña
            </h1>
            <p data-reveal class="mt-2 text-sm text-gray-600 leading-relaxed">
                Por seguridad, confirma tu contraseña para continuar.
            </p>
        </div>

        <!-- Body -->
        <div class="p-8">
            <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
                @csrf

                <!-- Password -->
                <div data-reveal>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Contraseña
                    </label>

                    <input id="password"
                           type="password"
                           name="password"
                           required
                           autocomplete="current-password"
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Escribe tu contraseña">

                    @error('password')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <button data-reveal type="submit" class="w-full text-white font-semibold py-3 rounded-xl glass-btn">
                    Confirmar y continuar
                </button>

                <div data-reveal class="text-center text-sm text-gray-600">
                    <a href="{{ route('dashboard') }}" class="text-brand font-semibold hover:underline">
                        Volver al panel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>

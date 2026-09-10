<x-guest-layout>
    <div class="w-full max-w-xl mx-auto glass-card rounded-2xl overflow-hidden">
        
        <!-- Header -->
        <div class="p-8 border-b border-black/10">
            <h1 data-reveal class="text-2xl font-extrabold text-gray-900">
                Restablecer contraseña
            </h1>
            <p data-reveal class="mt-2 text-sm text-gray-600 leading-relaxed">
                Crea una nueva contraseña segura para tu cuenta.
            </p>
        </div>

        <!-- Body -->
        <div class="p-8">
            <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
                @csrf

                <!-- Token oculto -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <!-- Email -->
                <div data-reveal>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Correo electrónico
                    </label>
                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email', $request->email) }}"
                           required
                           autofocus
                           autocomplete="username"
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input">

                    @error('email')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Nueva contraseña -->
                <div data-reveal>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Nueva contraseña
                    </label>
                    <input id="password"
                           type="password"
                           name="password"
                           required
                           autocomplete="new-password"
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Escribe tu nueva contraseña">

                    @error('password')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Confirmar contraseña -->
                <div data-reveal>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                        Confirmar contraseña
                    </label>
                    <input id="password_confirmation"
                           type="password"
                           name="password_confirmation"
                           required
                           autocomplete="new-password"
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="Repite tu contraseña">

                    @error('password_confirmation')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <button data-reveal type="submit"
                        class="w-full text-white font-semibold py-3 rounded-xl glass-btn">
                    Restablecer contraseña
                </button>

                <div data-reveal class="text-center text-sm text-gray-600">
                    <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">
                        Volver al inicio de sesión
                    </a>
                </div>
            </form>
        </div>

    </div>
</x-guest-layout>
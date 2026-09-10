<x-guest-layout>
    <div class="w-full max-w-xl mx-auto glass-card rounded-2xl overflow-hidden">
        <!-- Header -->
        <div class="p-8 border-b border-black/10">
            <h1 data-reveal class="text-2xl font-extrabold text-gray-900">
                Recuperar contraseña
            </h1>
            <p data-reveal class="mt-2 text-sm text-gray-600 leading-relaxed">
                Escribe tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
            </p>
        </div>

        <!-- Body -->
        <div class="p-8">
            @if (session('status'))
                <div data-reveal class="mb-6 text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf

                <!-- Email -->
                <div data-reveal>
                    <label for="email" class="block text-sm font-medium text-gray-700">
                        Correo electrónico
                    </label>

                    <input id="email"
                           type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           class="mt-1 w-full rounded-xl px-4 py-2 glass-input"
                           placeholder="tucorreo@ejemplo.com">

                    @error('email')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <button data-reveal type="submit" class="w-full text-white font-semibold py-3 rounded-xl glass-btn">
                    Enviar enlace de recuperación
                </button>

                <div data-reveal class="text-center text-sm text-gray-600">
                    ¿Ya la recordaste?
                    <a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">
                        Volver a iniciar sesión
                    </a>
                </div>

            </form>
        </div>
    </div>
</x-guest-layout>
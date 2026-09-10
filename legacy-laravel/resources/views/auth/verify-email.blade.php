<x-guest-layout>
    <div class="w-full max-w-xl mx-auto glass-card rounded-2xl overflow-hidden">

        <!-- Header -->
        <div class="p-8 border-b border-black/10">
            <h1 data-reveal class="text-2xl font-extrabold text-gray-900">
                Verifica tu correo
            </h1>
            <p data-reveal class="mt-2 text-sm text-gray-600 leading-relaxed">
                Te enviamos un enlace de verificación al correo que registraste.  
                Para continuar, revisa tu bandeja de entrada (y spam) y haz clic en el enlace.
            </p>
        </div>

        <!-- Body -->
        <div class="p-8 space-y-5">

            @if (session('status') == 'verification-link-sent')
                <div data-reveal class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                    Te enviamos un nuevo enlace de verificación al correo que registraste.
                </div>
            @endif

            <div data-reveal class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Reenviar -->
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="w-full text-white font-semibold py-3 rounded-xl glass-btn">
                        Reenviar correo de verificación
                    </button>
                </form>

                <!-- Cerrar sesión -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-xl px-4 py-3 font-semibold border border-black/10 bg-white/40 hover:bg-white/70 transition glass-card card-hover">
                        Cerrar sesión
                    </button>
                </form>
            </div>

            <div data-reveal class="text-xs text-gray-500 leading-relaxed">
                Si no te llega el correo, espera 1–2 minutos y vuelve a intentar reenviar.
                Asegúrate de que el correo esté bien escrito.
            </div>

        </div>
    </div>
</x-guest-layout>
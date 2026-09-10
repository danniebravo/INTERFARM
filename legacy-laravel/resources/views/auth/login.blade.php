<x-guest-layout>
    <style>
        @media (min-width: 768px) {
            .login-shell {
                background: rgba(255,255,255,.72);
                border: 1px solid rgba(255,255,255,.65);
                box-shadow: 0 18px 45px rgba(0,0,0,.10), inset 0 1px 0 rgba(255,255,255,.70);
                backdrop-filter: blur(18px);
                -webkit-backdrop-filter: blur(18px);
            }

            .login-register-link {
                background: rgba(255,255,255,.40);
                box-shadow: 0 18px 45px rgba(0,0,0,.10), inset 0 1px 0 rgba(255,255,255,.70);
                backdrop-filter: blur(18px);
                -webkit-backdrop-filter: blur(18px);
            }

            .login-register-link:hover {
                transform: translateY(-2px);
                box-shadow: 0 22px 55px rgba(0,0,0,.12);
            }
        }
    </style>

    <div class="login-shell w-full max-w-md md:max-w-6xl mx-auto md:rounded-2xl md:overflow-hidden md:grid md:grid-cols-2">

        <!-- HERO -->
        <div class="text-gray-900 md:bg-brand md:text-white px-1 pb-6 md:px-12 md:py-12 flex flex-col justify-center relative overflow-hidden">

            <div class="hidden md:block absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
            <div class="hidden md:block absolute -bottom-28 -left-28 w-80 h-80 rounded-full bg-black/10 blur-3xl"></div>

            <div data-reveal class="mb-5 md:mb-10">
                <x-brand-logo alt="Logo" class="w-[190px] md:w-[300px] max-w-full" />
            </div>

            <h1 data-reveal class="text-2xl md:text-3xl font-extrabold mb-2 md:mb-4 leading-tight">
                Gestión inteligente para tu finca
            </h1>

            <p data-reveal class="text-gray-500 md:text-green-100 text-sm md:text-lg mb-0 sm:mb-7 md:mb-8 leading-relaxed max-w-md">
                Controla tu operación ganadera desde una plataforma simple y profesional.
            </p>

            <ul data-reveal class="hidden md:block space-y-3 text-green-100 text-sm">
                <li>✔ Panel estratégico con indicadores en tiempo real</li>
                <li>✔ Historial completo por animal</li>
                <li>✔ Operación optimizada en campo</li>
            </ul>

        </div>

        <!-- FORMULARIO -->
        <div class="px-1 pb-2 md:p-12 flex flex-col justify-center">

            <h2 data-reveal class="text-[1.65rem] md:text-3xl font-bold mb-1 md:mb-2 leading-tight">
                Iniciar sesión
            </h2>

            <p data-reveal class="text-gray-500 mb-5 md:mb-6 text-sm md:text-base">
                Accede a tu plataforma de gestión ganadera.
            </p>

            @if (session('status'))
                <div data-reveal class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4 md:space-y-5">
                @csrf

                <!-- Email -->
                <div data-reveal>
                    <label class="block text-sm font-medium text-gray-700">
                        Correo electrónico
                    </label>

                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           autofocus
                           autocomplete="email"
                           class="mt-1.5 w-full rounded-xl px-4 py-3.5 md:py-2.5 glass-input">

                    @error('email')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Password -->
                <div data-reveal>
                    <label class="block text-sm font-medium text-gray-700">
                        Contraseña
                    </label>

                    <div class="relative mt-1">
                        <input id="loginPassword"
                               type="password"
                               name="password"
                               required
                               class="w-full rounded-xl px-4 py-3.5 md:py-2.5 pr-12 glass-input"
                               autocomplete="current-password">

                        <button type="button"
                                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-gray-500 transition hover:text-brand"
                                data-toggle-password="loginPassword"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false">
                            <svg data-password-icon="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg data-password-icon="hide" xmlns="http://www.w3.org/2000/svg" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10.7 5.1A10.6 10.6 0 0 1 12 5c7 0 10 7 10 7a13.2 13.2 0 0 1-2.2 3.2"/>
                                <path d="M6.6 6.6C3.5 8.7 2 12 2 12s3 7 10 7a9.8 9.8 0 0 0 5.4-1.6"/>
                                <path d="M14.1 14.1A3 3 0 0 1 9.9 9.9"/>
                                <path d="m2 2 20 20"/>
                            </svg>
                        </button>
                    </div>

                    @error('password')
                        <span class="text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Remember -->
                <div data-reveal class="flex items-center justify-between gap-3">

                    <label class="flex items-center gap-2 text-sm text-gray-600 select-none">
                        <input type="checkbox"
                               name="remember"
                               class="rounded border-gray-300 text-brand focus:ring-brand">
                        Recordarme
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                           class="text-sm text-brand font-medium hover:underline text-right">
                            ¿Olvidaste tu contraseña?
                        </a>
                    @endif

                </div>

                <!-- BOTÓN LOGIN -->
                <button data-reveal type="submit"
                        class="w-full text-white font-semibold py-3.5 md:py-3 rounded-xl glass-btn">
                    Entrar
                </button>

            </form>

            <div data-reveal class="my-5 md:my-6 flex items-center">
                <div class="flex-grow border-t border-black/10"></div>
                <span class="mx-4 text-sm text-gray-400">o</span>
                <div class="flex-grow border-t border-black/10"></div>
            </div>

            <a data-reveal href="{{ route('register') }}"
               class="login-register-link w-full block text-center rounded-xl px-4 py-3 font-semibold border border-black/10 bg-white/60 hover:bg-white/80 transition">
                Empieza gratis
            </a>

            <p data-reveal class="text-xs text-gray-500 text-center mt-4">
                Crea tu cuenta y empieza a gestionar tu finca en minutos.
            </p>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-toggle-password]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = document.getElementById(button.getAttribute('data-toggle-password'));
                    if (!input) return;

                    const isVisible = input.type === 'text';
                    input.type = isVisible ? 'password' : 'text';
                    button.setAttribute('aria-label', isVisible ? 'Mostrar contraseña' : 'Ocultar contraseña');
                    button.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
                    button.querySelector('[data-password-icon="show"]')?.classList.toggle('hidden', !isVisible);
                    button.querySelector('[data-password-icon="hide"]')?.classList.toggle('hidden', isVisible);
                });
            });
        });
    </script>
</x-guest-layout>

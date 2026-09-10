<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name', 'InterFarm') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="/vendor/tailwind.js"></script>
    @endif
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <main class="mx-auto flex min-h-screen max-w-5xl flex-col items-center justify-center gap-8 px-6 py-12 text-center">
        <x-brand-logo class="h-20 w-auto" />

        <div>
            <h1 class="text-4xl font-black tracking-tight">InterFarm</h1>
            <p class="mt-3 max-w-2xl text-base font-semibold text-gray-500">
                Plataforma para gestionar inventario, producción, salud, eventos y facturación de tu finca.
            </p>
        </div>

        @if (Route::has('login'))
            <nav class="flex flex-wrap justify-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}" class="rounded-xl bg-green-700 px-5 py-3 text-sm font-black text-white transition hover:bg-green-800">
                        Ir al panel
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl bg-green-700 px-5 py-3 text-sm font-black text-white transition hover:bg-green-800">
                        Iniciar sesión
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-black text-gray-700 transition hover:bg-gray-100">
                            Crear cuenta
                        </a>
                    @endif
                @endauth
            </nav>
        @endif
    </main>
</body>
</html>

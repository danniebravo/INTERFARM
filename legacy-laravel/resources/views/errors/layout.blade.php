@php
    $homeUrl = route('home');
    $primaryUrl = auth()->check() ? $homeUrl : route('login');
    $primaryLabel = auth()->check() ? 'Volver al panel' : 'Iniciar sesión';
@endphp

<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $code }} | {{ config('app.name', 'InterFarm') }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <script src="/vendor/tailwind.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#166534',
                            dark: '#14532d',
                            lime: '#a3e635'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="min-h-screen overflow-hidden bg-[radial-gradient(900px_520px_at_15%_12%,rgba(22,101,52,.14),transparent_58%),radial-gradient(760px_480px_at_88%_22%,rgba(163,230,53,.12),transparent_48%),linear-gradient(135deg,#f8fafc,#eef2f7_48%,#ffffff)]">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center px-5 py-10 sm:px-8">
            <section class="grid w-full overflow-hidden rounded-[28px] border border-white/70 bg-white/75 shadow-[0_24px_70px_rgba(15,23,42,.14)] backdrop-blur-xl md:grid-cols-[.9fr_1.1fr]">
                <div class="relative flex min-h-[320px] flex-col justify-between overflow-hidden bg-brand p-8 text-white sm:p-10">
                    <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl"></div>
                    <div class="absolute -bottom-28 -left-28 h-80 w-80 rounded-full bg-black/10 blur-3xl"></div>

                    <a href="{{ $homeUrl }}" class="relative inline-flex w-fit items-center" aria-label="Ir al inicio de InterFarm">
                        <x-brand-logo class="h-auto w-56 max-w-full" />
                    </a>

                    <div class="relative mt-16">
                        <p class="text-sm font-semibold uppercase tracking-[.22em] text-green-100">Error {{ $code }}</p>
                        <h1 class="mt-4 text-4xl font-black leading-tight sm:text-5xl">{{ $title }}</h1>
                    </div>
                </div>

                <div class="flex flex-col justify-center p-8 sm:p-10 lg:p-14">
                    <p class="max-w-xl text-lg leading-8 text-slate-600">
                        {{ $message }}
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $primaryUrl }}"
                           class="inline-flex items-center justify-center rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white shadow-lg shadow-green-900/20 transition hover:bg-brand-dark focus:outline-none focus:ring-4 focus:ring-green-700/20">
                            {{ $primaryLabel }}
                        </a>

                        <button type="button"
                                onclick="history.length > 1 ? history.back() : window.location.assign(@js($homeUrl))"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-300/40">
                            Volver atrás
                        </button>
                    </div>

                    <p class="mt-8 text-sm text-slate-500">
                        Si crees que esto es un error, vuelve al panel e intenta de nuevo.
                    </p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>

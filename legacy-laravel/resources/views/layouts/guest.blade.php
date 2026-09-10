<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'InterFarm') }}</title>

    <!-- Fuente -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Tailwind CDN (sin Node) -->
    <script src="/vendor/tailwind.js"></script>
    <script>
        (() => {
            const isStandalonePwa = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

            if (!isStandalonePwa) return;

            const viewport = document.querySelector('meta[name="viewport"]');
            if (!viewport) return;

            viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover');
        })();

        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: '#166534',
                            dark: '#14532d'
                        }
                    }
                }
            }
        }
    </script>

    <!-- Liquid UI Global -->
    <style>
        :root { --brand: 22,101,52; }

        /* Fondo */
        .liquid-bg {
            background:
                radial-gradient(1200px 600px at 15% 10%, rgba(var(--brand),.12), transparent 60%),
                radial-gradient(900px 500px at 85% 30%, rgba(0,0,0,.06), transparent 55%),
                linear-gradient(135deg, #f3f4f6, #ffffff, #eef2f7);
        }

        /* Tarjeta vidrio */
        .glass-card {
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(255,255,255,.65);
            box-shadow: 0 18px 45px rgba(0,0,0,.10), inset 0 1px 0 rgba(255,255,255,.70);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        /* Inputs */
        .glass-input {
            background: rgba(255,255,255,.60);
            border: 1px solid rgba(17,24,39,.12);
            box-shadow: 0 10px 20px rgba(0,0,0,.06), inset 0 1px 0 rgba(255,255,255,.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .glass-input:focus {
            outline: none;
            border-color: rgba(var(--brand),.45);
            box-shadow: 0 14px 26px rgba(0,0,0,.09), 0 0 0 4px rgba(var(--brand),.18), inset 0 1px 0 rgba(255,255,255,.70);
            transform: translateY(-1px);
        }

        @supports (-webkit-touch-callout: none) {
            input,
            select,
            textarea,
            .glass-input {
                font-size: 16px !important;
            }
        }

        /* Botón principal */
        .glass-btn {
            background: linear-gradient(135deg, rgba(var(--brand),.95), rgba(20,83,45,.95));
            box-shadow: 0 16px 30px rgba(var(--brand),.22);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .glass-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
            box-shadow: 0 18px 34px rgba(var(--brand),.28);
        }

        .glass-btn:active {
            transform: translateY(0px) scale(.99);
        }

        /* Hover cards */
        .card-hover {
            transition: transform .18s ease, box-shadow .18s ease;
        }

        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 55px rgba(0,0,0,.12);
        }

        /* Animación sutil */
        .reveal {
            opacity: 0;
            transform: translateY(10px) scale(.985);
            filter: blur(2px);
        }

        .reveal.is-in {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
            transition:
                opacity .55s ease,
                transform .55s cubic-bezier(.2,.9,.2,1),
                filter .55s ease;
            transition-delay: var(--d, 0ms);
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const items = document.querySelectorAll('[data-reveal]');
            items.forEach((el, i) => {
                el.classList.add('reveal');
                el.style.setProperty('--d', `${i * 70}ms`);
            });
            requestAnimationFrame(() => items.forEach(el => el.classList.add('is-in')));
        });
    </script>

</head>

<body class="font-sans text-gray-900 antialiased liquid-bg min-h-screen">

    <div class="min-h-screen flex items-center justify-center px-4 py-10">
        {{ $slot }}
    </div>

</body>
</html>

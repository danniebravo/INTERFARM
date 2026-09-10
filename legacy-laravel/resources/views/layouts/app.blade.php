<!DOCTYPE html>
<html lang="es"
      x-data="{
        dark: localStorage.getItem('interfarm_theme') === 'dark'
      }"
      x-init="
        if (dark) document.documentElement.classList.add('dark');
        $watch('dark', value => {
            localStorage.setItem('interfarm_theme', value ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', value);
            window.dispatchEvent(new CustomEvent('interfarm-theme-change', { detail: { dark: value } }));
        });
      ">
<head><meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $appThemeDefaults = [
            'app_primary_color' => '#166534',
            'app_primary_dark_color' => '#14532d',
            'app_accent_color' => '#a3e635',
            'app_background_color' => '#f6f8fb',
            'app_dark_background_color' => '#020617',
        ];

        $appThemeSettings = collect();

        if (class_exists(\App\Models\PlatformSetting::class)
            && \Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
            $appThemeSettings = \App\Models\PlatformSetting::query()
                ->whereIn('key', array_keys($appThemeDefaults))
                ->pluck('value', 'key');
        }

        $normalizeThemeColor = function (?string $value, string $fallback): string {
            $value = trim((string) $value);

            return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) ? strtolower($value) : $fallback;
        };

        $hexToRgb = function (string $hex): string {
            $hex = ltrim($hex, '#');

            return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
        };

        $appPrimaryColor = $normalizeThemeColor($appThemeSettings['app_primary_color'] ?? null, $appThemeDefaults['app_primary_color']);
        $appPrimaryDarkColor = $normalizeThemeColor($appThemeSettings['app_primary_dark_color'] ?? null, $appThemeDefaults['app_primary_dark_color']);
        $appAccentColor = $normalizeThemeColor($appThemeSettings['app_accent_color'] ?? null, $appThemeDefaults['app_accent_color']);
        $appBackgroundColor = $normalizeThemeColor($appThemeSettings['app_background_color'] ?? null, $appThemeDefaults['app_background_color']);
        $appDarkBackgroundColor = $normalizeThemeColor($appThemeSettings['app_dark_background_color'] ?? null, $appThemeDefaults['app_dark_background_color']);
        $appPrimaryRgb = $hexToRgb($appPrimaryColor);
        $appPrimaryDarkRgb = $hexToRgb($appPrimaryDarkColor);
        $appAccentRgb = $hexToRgb($appAccentColor);
    @endphp
    <meta name="theme-color" content="{{ $appBackgroundColor }}">
    <meta name="theme-color" content="{{ $appBackgroundColor }}" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="{{ $appDarkBackgroundColor }}" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="InterFarm">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>@yield('title', 'InterFarm')</title>

    <link rel="manifest" href="/manifest.webmanifest?v=4">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/apple-touch-icon.png?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon-32x32.png?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon-16x16.png?v=3">
    <link rel="shortcut icon" href="/favicon.ico?v=3">

    <script src="/vendor/tailwind.js"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    <script>
        (() => {
            const launchSource = new URLSearchParams(window.location.search).get('source') || '';
            if (launchSource.startsWith('pwa')) {
                localStorage.setItem('interfarm_pwa_launch', '1');
            }

            const isStandalonePwa = window.matchMedia('(display-mode: standalone)').matches
                || window.matchMedia('(display-mode: fullscreen)').matches
                || window.matchMedia('(display-mode: minimal-ui)').matches
                || window.matchMedia('(display-mode: window-controls-overlay)').matches
                || window.navigator.standalone === true
                || localStorage.getItem('interfarm_pwa_launch') === '1';

            if (!isStandalonePwa) return;

            const viewport = document.querySelector('meta[name="viewport"]');
            if (!viewport) return;

            viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover');
        })();

        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            DEFAULT: @js($appPrimaryColor),
                            dark: @js($appPrimaryDarkColor),
                            soft: @js($appPrimaryColor),
                            lime: @js($appAccentColor)
                        }
                    },
                    boxShadow: {
                        glass: '0 18px 45px rgba(0,0,0,.10), inset 0 1px 0 rgba(255,255,255,.70)',
                        soft: '0 10px 30px rgba(0,0,0,.08)',
                        glow: '0 0 30px rgba({{ $appPrimaryRgb }},.18)'
                    }
                }
            }
        }
    </script>

    <style>
        :root {
            --sidebar-w: 292px;
            --topbar-h: 88px;
            --brand: {{ $appPrimaryRgb }};
            --brand-dark: {{ $appPrimaryDarkRgb }};
            --lime: {{ $appAccentRgb }};
            --brand-hex: {{ $appPrimaryColor }};
            --brand-dark-hex: {{ $appPrimaryDarkColor }};
            --accent-hex: {{ $appAccentColor }};
            --app-bg: {{ $appBackgroundColor }};
            --app-dark-bg: {{ $appDarkBackgroundColor }};
        }

        html {
            background:
                radial-gradient(1100px 620px at 8% 8%, rgba(var(--brand), .12), transparent 60%),
                radial-gradient(900px 560px at 92% 10%, rgba(var(--lime), .06), transparent 46%),
                linear-gradient(135deg, color-mix(in srgb, var(--app-bg) 74%, #dbeafe), color-mix(in srgb, var(--app-bg) 88%, #ffffff) 38%, var(--app-bg) 72%, #ffffff);
        }

        html.dark {
            background:
                radial-gradient(1100px 620px at 8% 8%, rgba(var(--brand),.10), transparent 60%),
                radial-gradient(900px 560px at 92% 10%, rgba(var(--lime),.04), transparent 46%),
                linear-gradient(135deg, var(--app-dark-bg), color-mix(in srgb, var(--app-dark-bg) 82%, #1e293b) 38%, color-mix(in srgb, var(--app-dark-bg) 70%, #334155) 72%, #0f172a);
        }

        html, body {
            min-height: 100%;
        }

        body {
            background:
                radial-gradient(1100px 620px at 8% 8%, rgba(var(--brand), .12), transparent 60%),
                radial-gradient(900px 560px at 92% 10%, rgba(var(--lime), .06), transparent 46%),
                linear-gradient(135deg, color-mix(in srgb, var(--app-bg) 74%, #dbeafe), color-mix(in srgb, var(--app-bg) 88%, #ffffff) 38%, var(--app-bg) 72%, #ffffff);
            color: #111827;
        }

        .dark body {
            background:
                radial-gradient(1100px 620px at 8% 8%, rgba(var(--brand),.10), transparent 60%),
                radial-gradient(900px 560px at 92% 10%, rgba(var(--lime),.04), transparent 46%),
                linear-gradient(135deg, var(--app-dark-bg), color-mix(in srgb, var(--app-dark-bg) 82%, #1e293b) 38%, color-mix(in srgb, var(--app-dark-bg) 70%, #334155) 72%, #0f172a);
            color: #f3f4f6;
        }

        .glass {
            background: rgba(255,255,255,.68);
            border: 1px solid rgba(255,255,255,.62);
            box-shadow: 0 18px 45px rgba(0,0,0,.10), inset 0 1px 0 rgba(255,255,255,.70);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .dark .glass {
            background: rgba(15,23,42,.72);
            border: 1px solid rgba(255,255,255,.06);
            box-shadow: 0 18px 45px rgba(0,0,0,.26), inset 0 1px 0 rgba(255,255,255,.04);
        }

        .sidebar-shell {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-w);
            z-index: 50;
            background: linear-gradient(180deg, rgba(255,255,255,.52), rgba(255,255,255,.36));
            border-right: 1px solid rgba(0,0,0,.05);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .dark .sidebar-shell {
            background: linear-gradient(180deg, rgba(2,6,23,.88), rgba(15,23,42,.82));
            border-right: 1px solid rgba(255,255,255,.05);
        }

        .sidebar-inner {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .sidebar-top {
            height: 88px;
            display: flex;
            align-items: center;
            padding: 0 22px;
            border-bottom: 1px solid rgba(0,0,0,.05);
        }

        .dark .sidebar-top {
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 18px 14px 20px;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(0,0,0,.10);
            border-radius: 999px;
        }

        .dark .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,.12);
        }

        .farm-card {
            border-radius: 22px;
            padding: 16px;
            margin-bottom: 18px;
            background: rgba(255,255,255,.52);
            border: 1px solid rgba(0,0,0,.05);
            box-shadow: 0 10px 24px rgba(0,0,0,.05);
        }

        .dark .farm-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.05);
            box-shadow: none;
        }

        .nav-section-title {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: rgba(107,114,128,.84);
            padding: 12px 10px 8px;
        }

        .dark .nav-section-title {
            color: rgba(156,163,175,.72);
        }

        .nav-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
            color: #374151;
            font-size: 14px;
            font-weight: 700;
            border: 1px solid transparent;
            transition:
                transform .18s ease,
                background .18s ease,
                color .18s ease,
                border-color .18s ease,
                box-shadow .18s ease;
            opacity: 0;
            transform: translateX(-10px);
            animation: navCascade .5s ease forwards;
            animation-delay: var(--nav-delay, 0ms);
        }

        .dark .nav-item {
            color: rgba(243,244,246,.80);
        }

        .nav-item:hover {
            transform: translateX(3px);
            background: rgba(0,0,0,.04);
        }

        .dark .nav-item:hover {
            background: rgba(255,255,255,.05);
            color: #fff;
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(22,101,52,.14), rgba(34,197,94,.08));
            background: linear-gradient(135deg, rgba(var(--brand),.14), rgba(var(--lime),.08));
            border-color: rgba(var(--brand),.10);
            color: var(--brand-hex);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.35);
        }

        .dark .nav-item.active {
            background: linear-gradient(135deg, rgba(var(--brand),.16), rgba(var(--brand-dark),.10));
            border-color: rgba(var(--brand),.12);
            color: color-mix(in srgb, var(--brand-hex) 54%, #ffffff);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.03);
        }

        .nav-icon-wrap {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0,0,0,.04);
            border: 1px solid rgba(0,0,0,.04);
            transition: .18s ease;
        }

        .dark .nav-icon-wrap {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.05);
        }

        .nav-item.active .nav-icon-wrap {
            background: rgba(var(--brand),.10);
            border-color: rgba(var(--brand),.10);
        }

        .dark .nav-item.active .nav-icon-wrap {
            background: rgba(var(--brand),.10);
            border-color: rgba(var(--brand),.08);
        }

        .nav-support-list {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(0,0,0,.06);
        }

        .dark .nav-support-list {
            border-top-color: rgba(255,255,255,.08);
        }

        .nav-item.whatsapp-help-nav {
            color: #166534;
            background: linear-gradient(135deg, rgba(34,197,94,.16), rgba(22,101,52,.08));
            border-color: rgba(22,101,52,.14);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.42);
        }

        .nav-item.whatsapp-help-nav:hover {
            background: linear-gradient(135deg, rgba(34,197,94,.22), rgba(22,101,52,.12));
            border-color: rgba(22,101,52,.24);
            color: #14532d;
        }

        .nav-item.whatsapp-help-nav .nav-icon-wrap {
            background: linear-gradient(135deg, #22c55e, #166534);
            border-color: rgba(255,255,255,.70);
            color: #fff;
            box-shadow: 0 10px 20px rgba(22,101,52,.22), inset 0 1px 0 rgba(255,255,255,.28);
        }

        .dark .nav-item.whatsapp-help-nav {
            color: #dcfce7;
            background: linear-gradient(135deg, rgba(34,197,94,.20), rgba(22,101,52,.14));
            border-color: rgba(74,222,128,.20);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
        }

        .dark .nav-item.whatsapp-help-nav:hover {
            color: #f0fdf4;
            background: linear-gradient(135deg, rgba(34,197,94,.26), rgba(22,101,52,.18));
            border-color: rgba(74,222,128,.30);
        }

        .nav-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #16a34a;
            box-shadow: 0 0 0 4px rgba(22,163,74,.10);
        }

        .nav-sublist {
            margin: -2px 0 8px 0;
            display: grid;
            gap: 4px;
            padding-left: 52px;
        }

        .nav-subitem {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            border-radius: 12px;
            padding: 10px 12px;
            color: #64748b;
            font-size: 13px;
            font-weight: 800;
            transition: .18s ease;
        }

        .nav-subitem:hover {
            background: rgba(0,0,0,.04);
            color: #0f172a;
        }

        .nav-subitem.active {
            color: #166534;
            background: transparent;
        }

        .dark .nav-subitem {
            color: #cbd5e1;
        }

        .dark .nav-subitem:hover {
            background: rgba(255,255,255,.05);
            color: #fff;
        }

        .dark .nav-subitem.active {
            background: transparent;
            color: #bbf7d0;
        }

        .topbar-shell {
            position: fixed;
            left: var(--sidebar-w);
            right: 0;
            top: 0;
            z-index: 80;
            height: var(--topbar-h);
            padding: calc(14px + env(safe-area-inset-top, 0px)) 18px 0 18px;
        }

        .admin-viewing-client .topbar-shell {
            top: 44px;
        }

        .admin-viewing-client .content-shell {
            padding-top: calc(var(--topbar-h) + 62px);
        }

        .topbar-card {
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 0 18px;
            border-radius: 22px;
            position: relative;
            overflow: visible;
            background:
                linear-gradient(135deg, rgba(248,250,252,.94), rgba(229,236,244,.84));
            border: 1px solid rgba(255,255,255,.58);
            box-shadow:
                0 16px 34px rgba(15,23,42,.08),
                inset 0 1px 0 rgba(255,255,255,.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .topbar-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 1px;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,.75),
                transparent
            );
            pointer-events: none;
        }

        .dark .topbar-card {
            background:
                linear-gradient(135deg, rgba(10,15,28,.92), rgba(2,6,23,.88));
            border: 1px solid rgba(255,255,255,.06);
            box-shadow:
                0 16px 34px rgba(0,0,0,.30),
                inset 0 1px 0 rgba(255,255,255,.04);
        }

        .dark .topbar-card::before {
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,.08),
                transparent
            );
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .topbar-btn.mobile-menu-btn {
            display: none;
        }

        .mobile-brand {
            display: none;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .mobile-brand img {
            height: 38px;
            width: auto;
            display: block;
        }

        .mobile-drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 180;
            background: rgba(2,6,23,.44);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .22s ease;
        }

        .mobile-drawer-backdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .mobile-drawer {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: min(86vw, 320px);
            z-index: 190;
            transform: translateX(-102%);
            transition: transform .28s cubic-bezier(.22,.9,.2,1);
            background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,.88));
            border-right: 1px solid rgba(0,0,0,.06);
            box-shadow: 0 18px 45px rgba(0,0,0,.18);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            display: none;
            padding-top: env(safe-area-inset-top, 0px);
        }

        .dark .mobile-drawer {
            background: linear-gradient(180deg, rgba(2,6,23,.96), rgba(15,23,42,.94));
            border-right: 1px solid rgba(255,255,255,.06);
        }

        .mobile-drawer.is-open {
            transform: translateX(0);
        }

        .mobile-drawer-top {
            height: 88px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 0 18px;
            border-bottom: 1px solid rgba(0,0,0,.05);
        }

        .dark .mobile-drawer-top {
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .mobile-drawer-close {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,.06);
            background: rgba(255,255,255,.76);
            color: #374151;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: .18s ease;
        }

        .dark .mobile-drawer-close {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.05);
            color: #e5e7eb;
        }

        .mobile-drawer-close:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,.96);
            color: #111827;
        }

        .dark .mobile-drawer-close:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }

        .mobile-drawer-scroll {
            height: calc(100% - 88px - env(safe-area-inset-top, 0px));
            overflow-y: auto;
            padding: 18px 14px 20px;
        }

        .content-shell {
            margin-left: var(--sidebar-w);
            min-height: 100vh;
            padding-top: calc(var(--topbar-h) + 18px + env(safe-area-inset-top, 0px));
            position: relative;
            z-index: 1;
        }

        .content-inner {
            padding: 0 18px 24px;
        }

        .reveal {
            opacity: 0;
            transform: translateY(10px) scale(.985);
        }

        .reveal.is-in {
            opacity: 1;
            transform: translateY(0) scale(1);
            transition:
                opacity .55s ease,
                transform .55s cubic-bezier(.22,.9,.2,1);
            transition-delay: var(--d, 0ms);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 40px;
            padding: 0 14px;
            border-radius: 999px;
            background: rgba(255,255,255,.74);
            border: 1px solid rgba(0,0,0,.05);
            color: #374151;
            font-size: 13px;
            font-weight: 700;
        }

        .dark .status-pill {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.05);
            color: #e5e7eb;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,.12);
        }

        .status-pill.offline .status-dot {
            background: #ef4444;
            box-shadow: 0 0 0 4px rgba(239,68,68,.12);
        }

        .topbar-btn {
            position: relative;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 1px solid rgba(0,0,0,.06);
            background: rgba(255,255,255,.74);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #374151;
            transition: .18s ease;
            cursor: pointer;
        }

        .dark .topbar-btn {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.05);
            color: #e5e7eb;
        }

        .topbar-btn:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,.96);
            color: #111827;
        }

        .dark .topbar-btn:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }

        .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            padding: 0 5px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #fff;
            box-shadow: 0 10px 20px rgba(239,68,68,.18);
        }

        .dark .notif-badge {
            border-color: #020617;
        }

        .notif-shell {
            position: relative;
            z-index: 140;
        }

        .notif-panel {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: min(430px, calc(100vw - 32px));
            max-width: calc(100vw - 24px);
            border-radius: 24px;
            border: 1px solid rgba(0,0,0,.08);
            background: rgba(255,255,255,.96);
            box-shadow: 0 22px 50px rgba(0,0,0,.14);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            overflow: hidden;
            display: none;
            z-index: 160;
        }

        .dark .notif-panel {
            background: rgba(15,23,42,.96);
            border-color: rgba(255,255,255,.06);
        }

        .notif-panel.is-open {
            display: block;
        }

        .notif-header {
            padding: 16px 18px 14px 18px;
            border-bottom: 1px solid rgba(0,0,0,.06);
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.92));
        }

        .dark .notif-header {
            border-bottom-color: rgba(255,255,255,.06);
            background: linear-gradient(180deg, rgba(15,23,42,.98), rgba(15,23,42,.92));
        }

        .notif-list {
            max-height: 430px;
            overflow-y: auto;
            padding: 10px;
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
        }

        .notif-list::-webkit-scrollbar {
            width: 8px;
        }

        .notif-list::-webkit-scrollbar-track {
            background: rgba(0,0,0,.04);
            border-radius: 999px;
        }

        .notif-list::-webkit-scrollbar-thumb {
            background: rgba(22,101,52,.25);
            border-radius: 999px;
        }

        .notif-item {
            position: relative;
            display: block;
            width: 100%;
            min-width: 0;
            border: 1px solid rgba(0,0,0,.06);
            background: rgba(255,255,255,.72);
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 10px;
            transition: .2s ease;
            color: inherit;
            text-decoration: none;
            overflow-wrap: anywhere;
            word-break: break-word;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        .notif-dismiss-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 1px solid rgba(0,0,0,.08);
            background: rgba(255,255,255,.86);
            color: #6b7280;
            transition: .18s ease;
        }

        .notif-dismiss-btn:hover {
            background: #fee2e2;
            color: #dc2626;
            border-color: rgba(220,38,38,.18);
        }

        .dark .notif-dismiss-btn {
            background: rgba(15,23,42,.84);
            border-color: rgba(255,255,255,.08);
            color: #cbd5e1;
        }

        .dark .notif-item {
            background: rgba(255,255,255,.04);
            border-color: rgba(255,255,255,.05);
        }

        .notif-item:hover {
            background: rgba(255,255,255,1);
            transform: translateY(-1px);
        }

        .dark .notif-item:hover {
            background: rgba(255,255,255,.06);
        }

        .notif-item:last-child {
            margin-bottom: 0;
        }

        .notif-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
        }

        .notif-pill.auto {
            background: rgba(22,101,52,.10);
            color: #166534;
        }

        .notif-pill.manual {
            background: rgba(37,99,235,.10);
            color: #1d4ed8;
        }

        .dark .notif-pill.auto {
            background: rgba(34,197,94,.12);
            color: #86efac;
        }

        .dark .notif-pill.manual {
            background: rgba(59,130,246,.14);
            color: #93c5fd;
        }

        .notif-level {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 800;
        }

        .notif-level.high {
            background: rgba(220,38,38,.10);
            color: #dc2626;
        }

        .notif-level.medium {
            background: rgba(245,158,11,.12);
            color: #b45309;
        }

        .notif-level.low {
            background: rgba(22,101,52,.10);
            color: #166534;
        }

        .dark .notif-level.high {
            background: rgba(239,68,68,.12);
            color: #fca5a5;
        }

        .dark .notif-level.medium {
            background: rgba(245,158,11,.14);
            color: #fcd34d;
        }

        .dark .notif-level.low {
            background: rgba(34,197,94,.12);
            color: #86efac;
        }

        .notif-empty {
            padding: 28px 18px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        .dark .notif-empty {
            color: #9ca3af;
        }

        .notif-close-btn {
            width: 38px;
            height: 38px;
            border-radius: 14px;
            border: 1px solid rgba(0,0,0,.08);
            background: rgba(255,255,255,.76);
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: transform .18s ease, background .18s ease;
        }

        .notif-close-btn:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,.96);
        }

        .dark .notif-close-btn {
            border-color: rgba(255,255,255,.08);
            background: rgba(255,255,255,.06);
            color: #e2e8f0;
        }

        .notif-actions {
            padding: 10px;
            border-top: 1px solid rgba(0,0,0,.06);
            background: rgba(255,255,255,.82);
        }

        .dark .notif-actions {
            border-top-color: rgba(255,255,255,.08);
            background: rgba(15,23,42,.86);
        }

        body.pwa-standalone a {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
            user-select: none;
        }

        .offline-sync-toast,
        .toast-notification {
            position: fixed;
            right: 18px;
            bottom: 96px;
            z-index: 260;
            max-width: min(360px, calc(100vw - 36px));
            border: 1px solid rgba(22,101,52,.16);
            border-radius: 18px;
            background: rgba(15,23,42,.94);
            color: #f8fafc;
            padding: 13px 16px;
            font-size: 13px;
            font-weight: 850;
            box-shadow: 0 18px 42px rgba(2,6,23,.26);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }

        .offline-queue-badge {
            position: fixed;
            right: 18px;
            bottom: 154px;
            z-index: 250;
            border: 1px solid rgba(22,101,52,.18);
            border-radius: 999px;
            background: linear-gradient(135deg, #166534, #22c55e);
            color: #fff;
            padding: 11px 15px;
            font-size: 12px;
            font-weight: 900;
            box-shadow: 0 18px 38px rgba(22,101,52,.24);
            cursor: pointer;
        }

        .dark .offline-sync-toast,
        .dark .toast-notification {
            border-color: rgba(134,239,172,.18);
            background: rgba(2,6,23,.96);
        }

        .app-preloader {
            position: fixed;
            inset: 0;
            z-index: 999999;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(760px 460px at 18% 12%, rgba(34,197,94,.18), transparent 58%),
                linear-gradient(135deg, rgba(246,248,251,.98), rgba(238,244,239,.98));
            transition: opacity .28s ease, visibility .28s ease;
        }

        .dark .app-preloader {
            background:
                radial-gradient(760px 460px at 18% 12%, rgba(34,197,94,.14), transparent 58%),
                linear-gradient(135deg, rgba(2,6,23,.98), rgba(15,23,42,.98));
        }

        .app-preloader.is-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .app-preloader-card {
            width: min(100%, 320px);
            border: 1px solid rgba(22,101,52,.14);
            border-radius: 30px;
            padding: 26px;
            text-align: center;
            background: rgba(255,255,255,.76);
            box-shadow: 0 24px 60px rgba(15,23,42,.14);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .dark .app-preloader-card {
            border-color: rgba(134,239,172,.18);
            background: rgba(15,23,42,.82);
            box-shadow: 0 24px 60px rgba(0,0,0,.34);
        }

        .app-preloader-icon {
            width: 74px;
            height: 74px;
            object-fit: cover;
            border-radius: 22px;
            margin: 0 auto 16px;
            box-shadow: 0 16px 34px rgba(22,101,52,.18);
        }

        .app-preloader-title {
            font-size: 20px;
            line-height: 1.15;
            font-weight: 950;
            color: #111827;
        }

        .dark .app-preloader-title {
            color: #f8fafc;
        }

        .app-preloader-text {
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
            font-weight: 750;
        }

        .dark .app-preloader-text {
            color: #cbd5e1;
        }

        .app-preloader-bar {
            position: relative;
            height: 7px;
            margin-top: 20px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(22,101,52,.10);
        }

        .app-preloader-bar::after {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 42%;
            border-radius: inherit;
            background: linear-gradient(90deg, #166534, #22c55e, #a3e635);
            animation: appPreloaderSlide 1.05s ease-in-out infinite;
        }

        @keyframes appPreloaderSlide {
            0% {
                transform: translateX(-115%);
            }

            100% {
                transform: translateX(260%);
            }
        }

        .profile-menu-shell {
            position: relative;
            z-index: 140;
        }

        .profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            height: 46px;
            padding: 0 12px 0 8px;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,.06);
            background: rgba(255,255,255,.74);
            transition: .18s ease;
            cursor: pointer;
        }

        .dark .profile-btn {
            background: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.05);
        }

        .profile-btn:hover {
            background: rgba(255,255,255,.96);
            transform: translateY(-1px);
        }

        .dark .profile-btn:hover {
            background: rgba(255,255,255,.08);
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: linear-gradient(135deg, #166534, #22c55e);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 13px;
            box-shadow: 0 10px 22px rgba(22,101,52,.18);
            flex-shrink: 0;
        }

        .profile-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 260px;
            border-radius: 22px;
            border: 1px solid rgba(0,0,0,.08);
            background: rgba(255,255,255,.98);
            box-shadow: 0 20px 44px rgba(0,0,0,.12);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            overflow: hidden;
            display: none;
            z-index: 160;
        }

        .dark .profile-menu {
            background: rgba(15,23,42,.98);
            border-color: rgba(255,255,255,.06);
        }

        .profile-menu.is-open {
            display: block;
        }

        .profile-menu-header {
            padding: 14px 14px 12px;
            border-bottom: 1px solid rgba(0,0,0,.06);
            background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.92));
        }

        .dark .profile-menu-header {
            border-bottom-color: rgba(255,255,255,.06);
            background: linear-gradient(180deg, rgba(15,23,42,.98), rgba(15,23,42,.92));
        }

        .profile-menu-list {
            padding: 8px;
        }

        .profile-menu-item {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
            padding: 12px 12px;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            background: transparent;
            border: 0;
            border-radius: 14px;
            transition: .18s ease;
            text-decoration: none;
        }

        .dark .profile-menu-item {
            color: #e5e7eb;
        }

        .profile-menu-item:hover {
            background: rgba(0,0,0,.04);
        }

        .dark .profile-menu-item:hover {
            background: rgba(255,255,255,.05);
        }

        .profile-menu-item-danger {
            color: #b91c1c;
        }

        .dark .profile-menu-item-danger {
            color: #fca5a5;
        }

        .profile-menu-divider {
            height: 1px;
            margin: 8px 0;
            background: rgba(0,0,0,.06);
        }

        .dark .profile-menu-divider {
            background: rgba(255,255,255,.06);
        }

        .profile-menu-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            opacity: .9;
        }

        .ts-wrapper {
            width: 100%;
            max-width: 100%;
            position: relative;
            z-index: 1;
            box-sizing: border-box;
        }

        .ts-wrapper.select-search,
        .ts-wrapper[class*="border"],
        .ts-wrapper[class*="bg-white"] {
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        .ts-wrapper.dropdown-active {
            z-index: 9999 !important;
        }

        .ts-control {
            width: 100% !important;
            min-height: 46px;
            height: 46px;
            box-sizing: border-box;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,.10);
            background: rgba(255,255,255,.70);
            padding: 10px 42px 10px 14px;
            color: #111827;
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 6px;
            overflow: hidden;
        }

        .ts-wrapper.single .ts-control {
            background-image: none;
        }

        .ts-wrapper.single .ts-control::after {
            right: 14px;
            border-color: #64748b transparent transparent transparent;
        }

        .ts-wrapper.single.dropdown-active .ts-control::after {
            border-color: transparent transparent #64748b transparent;
        }

        .dark .ts-wrapper.single .ts-control::after {
            border-color: #cbd5e1 transparent transparent transparent;
        }

        .dark .ts-wrapper.single.dropdown-active .ts-control::after {
            border-color: transparent transparent #cbd5e1 transparent;
        }

        .ts-control > input,
        .ts-control .item {
            min-width: 0 !important;
            max-width: 100%;
        }

        .ts-control .item {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.35;
        }

        .ts-control > input {
            flex: 1 1 auto;
            width: 1px !important;
        }

        .dark .ts-control {
            border-color: rgba(148,163,184,.28) !important;
            background: rgba(15,23,42,.88) !important;
            color: #f8fafc !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
        }

        .dark .ts-control,
        .dark .ts-control input,
        .dark .ts-control .item,
        .dark .ts-wrapper.single .ts-control .item {
            color: #f8fafc !important;
        }

        .dark .ts-control input::placeholder {
            color: #94a3b8 !important;
        }

        .ts-dropdown {
            margin-top: 8px;
            border-radius: 14px;
            border: 1px solid rgba(0,0,0,.08);
            background: white;
            box-shadow: 0 20px 40px rgba(0,0,0,.12);
            overflow: hidden;
            z-index: 999999 !important;
        }

        .dark .ts-dropdown {
            background: #0f172a !important;
            border-color: rgba(148,163,184,.28) !important;
            color: #f8fafc !important;
        }

        .ts-dropdown .option {
            padding: 10px 14px;
        }

        .dark .ts-dropdown .option {
            color: #e5e7eb !important;
        }

        .ts-dropdown .active {
            background: rgba(22,101,52,.08);
            color: #166534;
        }

        .dark .ts-dropdown .active,
        .dark .ts-dropdown .selected {
            background: rgba(34,197,94,.18) !important;
            color: #bbf7d0 !important;
        }

        .toast-notification {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 99999;
            max-width: 320px;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,.08);
            background: rgba(255,255,255,.96);
            box-shadow: 0 18px 45px rgba(0,0,0,.14);
            padding: 14px 16px;
            color: #111827;
            font-size: 14px;
            font-weight: 700;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            animation: toastIn .22s ease;
            pointer-events: none;
        }

        .dark .toast-notification {
            background: rgba(15,23,42,.96);
            border-color: rgba(255,255,255,.06);
            color: #f3f4f6;
        }

        input,
        select,
        textarea {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
            color: #111827;
            background: rgba(255,255,255,.88);
            border: 1px solid rgba(0,0,0,.10);
        }

        input[type="date"],
        input[type="datetime-local"],
        input[type="time"],
        .flatpickr-input {
            display: block;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
            appearance: none;
            -webkit-appearance: none;
        }

        input[type="date"]::-webkit-date-and-time-value,
        input[type="datetime-local"]::-webkit-date-and-time-value,
        input[type="time"]::-webkit-date-and-time-value {
            min-width: 0;
            text-align: left;
        }

        .animal-section,
        .animal-section * {
            box-sizing: border-box;
        }

        .animal-section .grid > *,
        .animal-section .animal-select-wrap,
        .animal-section label,
        .animal-section p,
        .animal-section input,
        .animal-section select,
        .animal-section textarea,
        .animal-section .ts-wrapper,
        .animal-section .ts-control {
            min-width: 0;
            max-width: 100%;
        }

        .animal-section label,
        .animal-section p,
        .animal-section .text-sm,
        .animal-section .text-xs {
            overflow-wrap: anywhere;
        }

        @supports (-webkit-touch-callout: none) {
            input,
            select,
            textarea,
            .ts-control,
            .ts-control input {
                font-size: 16px !important;
            }
        }

        input::placeholder,
        textarea::placeholder {
            color: #6b7280;
        }

        .dark input,
        .dark select,
        .dark textarea {
            color: #f8fafc !important;
            background: rgba(15,23,42,.86) !important;
            border-color: rgba(148,163,184,.26) !important;
            caret-color: #f8fafc;
        }

        .dark input::placeholder,
        .dark textarea::placeholder {
            color: #94a3b8 !important;
        }

        .dark option {
            background: #0f172a !important;
            color: #f8fafc !important;
        }

        .dark option:checked,
        .dark option:hover,
        .dark select option:checked {
            background: #166534 !important;
            color: #ffffff !important;
        }

        .dark select:disabled,
        .dark input:disabled,
        .dark textarea:disabled,
        .dark .ts-wrapper.disabled .ts-control {
            background: rgba(30,41,59,.70) !important;
            color: #94a3b8 !important;
            opacity: 1 !important;
        }

        .dark input[type="date"]::-webkit-calendar-picker-indicator,
        .dark input[type="datetime-local"]::-webkit-calendar-picker-indicator,
        .dark input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            opacity: .78;
        }

        .dark select:focus,
        .dark input:focus,
        .dark textarea:focus,
        .dark .ts-wrapper.focus .ts-control {
            border-color: rgba(34,197,94,.62) !important;
            box-shadow: 0 0 0 4px rgba(34,197,94,.16), inset 0 1px 0 rgba(255,255,255,.05) !important;
        }

        .dark .upload-dropzone {
            border-color: rgba(34,197,94,.34) !important;
            background: linear-gradient(135deg, rgba(15,23,42,.92), rgba(30,41,59,.84)) !important;
            color: #e5e7eb !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.05);
        }

        .dark .upload-dropzone.dragover {
            border-color: rgba(74,222,128,.76) !important;
            background: rgba(22,101,52,.24) !important;
        }

        .dark .upload-dropzone h3,
        .dark .animal-section h1,
        .dark .animal-section h2,
        .dark .animal-section h3,
        .dark .animal-section label {
            color: #f8fafc !important;
        }

        .dark .upload-dropzone p,
        .dark #photos-help,
        .dark .animal-section p,
        .dark .animal-section .text-gray-500,
        .dark .animal-section .text-gray-600,
        .dark .animal-section .text-gray-700 {
            color: #cbd5e1 !important;
        }

        .dark #preview-grid > div,
        .dark .repro-card,
        .dark .lot-helper-card {
            background: rgba(15,23,42,.88) !important;
            border-color: rgba(148,163,184,.24) !important;
            color: #f8fafc !important;
        }

        .dark #preview-grid img {
            background: #0f172a;
        }

        .dark #preview-grid [class*="bg-white"],
        .dark #preview-grid [class*="bg-brand"] {
            background-color: rgba(22,101,52,.22) !important;
            border-color: rgba(148,163,184,.18) !important;
            color: #bbf7d0 !important;
        }

        .dark #preview-grid button {
            background: rgba(15,23,42,.94) !important;
            color: #e5e7eb !important;
        }

        .dark #preview-grid button:hover {
            background: rgba(127,29,29,.36) !important;
            color: #fecaca !important;
        }

        .animals-tabs,
        .billing-tabs,
        div:has(> .settings-tab) {
            position: relative !important;
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 8px !important;
            max-width: 100% !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            padding-bottom: 0;
            padding-right: 28px;
            scrollbar-width: none;
            overscroll-behavior-x: contain;
            -webkit-overflow-scrolling: touch;
        }

        .animals-tabs::-webkit-scrollbar,
        .billing-tabs::-webkit-scrollbar,
        div:has(> .settings-tab)::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .animals-tab,
        .billing-tab,
        .settings-tab {
            flex: 0 0 auto !important;
            min-height: 36px !important;
            border-radius: 999px !important;
            padding: 7px 10px !important;
            gap: 7px !important;
            font-size: 12px !important;
            line-height: 1 !important;
            white-space: nowrap !important;
        }

        .animals-tab-count {
            min-width: 20px !important;
            height: 20px !important;
            padding: 0 6px !important;
            font-size: 10px !important;
        }

        .billing-tabs {
            border-radius: 16px !important;
            padding: 5px !important;
        }

        .tab-scroll-hint {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 34px;
            z-index: 4;
            display: none;
            align-items: center;
            justify-content: flex-end;
            pointer-events: none;
            border-top-right-radius: inherit;
            border-bottom-right-radius: inherit;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.94) 58%, rgba(255,255,255,.98));
        }

        .dark .tab-scroll-hint {
            background: linear-gradient(90deg, transparent, rgba(15,23,42,.94) 58%, rgba(15,23,42,.98));
        }

        .tab-scroll-hint::after {
            content: "";
            width: 8px;
            height: 8px;
            margin-right: 8px;
            border-top: 2px solid rgba(22,101,52,.86);
            border-right: 2px solid rgba(22,101,52,.86);
            transform: rotate(45deg);
            animation: tabHintNudge 1.25s ease-in-out infinite;
        }

        .dark .tab-scroll-hint::after {
            border-color: #86efac;
        }

        .tab-scroll-hint.is-visible {
            display: flex;
        }

        @keyframes tabHintNudge {
            0%, 100% {
                transform: translateX(-2px) rotate(45deg);
                opacity: .72;
            }

            50% {
                transform: translateX(2px) rotate(45deg);
                opacity: 1;
            }
        }

        @media (max-width: 640px) {
            .animals-tabs,
            .billing-tabs,
            div:has(> .settings-tab) {
                width: calc(100vw - 48px) !important;
                margin-right: -2px;
            }

            .animals-tab,
            .billing-tab,
            .settings-tab {
                min-height: 34px !important;
                padding: 7px 9px !important;
                font-size: 11px !important;
            }
        }

        .dark label,
        .dark p,
        .dark span,
        .dark td,
        .dark th,
        .dark li,
        .dark dt,
        .dark dd {
            border-color: rgba(255,255,255,.08);
        }

        .dark .bg-white {
            background-color: rgb(15 23 42) !important;
        }

        .dark .bg-white\/30,
        .dark .bg-white\/40,
        .dark .bg-white\/50,
        .dark .bg-white\/60,
        .dark .bg-white\/70,
        .dark .bg-white\/80,
        .dark .bg-white\/90 {
            background-color: rgba(30,41,59,.78) !important;
        }

        .dark .bg-gray-50,
        .dark .bg-gray-100 {
            background-color: rgba(30,41,59,.86) !important;
        }

        .dark .bg-green-50,
        .dark .bg-emerald-50,
        .dark .bg-lime-50 {
            background-color: rgba(20,83,45,.34) !important;
        }

        .dark .bg-amber-50,
        .dark .bg-yellow-50 {
            background-color: rgba(120,53,15,.34) !important;
        }

        .dark .bg-red-50,
        .dark .bg-rose-50 {
            background-color: rgba(127,29,29,.36) !important;
        }

        .dark .bg-sky-50,
        .dark .bg-blue-50 {
            background-color: rgba(12,74,110,.34) !important;
        }

        .dark .text-gray-900 {
            color: #f9fafb !important;
        }

        .dark .text-gray-800 {
            color: #f3f4f6 !important;
        }

        .dark .text-gray-700 {
            color: #e5e7eb !important;
        }

        .dark .text-gray-600 {
            color: #e2e8f0 !important;
        }

        .dark .text-gray-500 {
            color: #cbd5e1 !important;
        }

        .dark .text-gray-400 {
            color: #cbd5e1 !important;
        }

        .dark .text-gray-300 {
            color: #e2e8f0 !important;
        }

        .dark .text-green-700,
        .dark .text-green-800,
        .dark .text-emerald-700,
        .dark .text-emerald-800,
        .dark .text-lime-700,
        .dark .text-lime-800 {
            color: #86efac !important;
        }

        .dark .text-amber-700,
        .dark .text-amber-800,
        .dark .text-yellow-700,
        .dark .text-yellow-800 {
            color: #fde68a !important;
        }

        .dark .text-red-600,
        .dark .text-red-700,
        .dark .text-red-800,
        .dark .text-rose-700,
        .dark .text-rose-800 {
            color: #fca5a5 !important;
        }

        .dark .text-sky-700,
        .dark .text-sky-800,
        .dark .text-blue-700,
        .dark .text-blue-800 {
            color: #bae6fd !important;
        }

        .dark .border-gray-200,
        .dark .border-gray-300 {
            border-color: rgba(255,255,255,.10) !important;
        }

        .dark .border-black\/5,
        .dark .border-black\/10 {
            border-color: rgba(148,163,184,.22) !important;
        }

        .dark .border-green-100,
        .dark .border-green-200,
        .dark .border-emerald-100,
        .dark .border-emerald-200,
        .dark .border-lime-100,
        .dark .border-lime-200 {
            border-color: rgba(134,239,172,.28) !important;
        }

        .dark .border-amber-100,
        .dark .border-amber-200,
        .dark .border-yellow-100,
        .dark .border-yellow-200 {
            border-color: rgba(253,230,138,.30) !important;
        }

        .dark .border-red-100,
        .dark .border-red-200,
        .dark .border-rose-100,
        .dark .border-rose-200 {
            border-color: rgba(252,165,165,.30) !important;
        }

        .dark .divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: rgba(255,255,255,.08) !important;
        }

        .dark .glass-soft,
        .dark .card,
        .dark .dashboard-mini-stat,
        .dark .dashboard-kpi-card,
        .dark .settings-card,
        .dark .settings-role-card,
        .dark .settings-table-wrap,
        .dark .finance-card,
        .dark .finance-table-wrap,
        .dark .finance-chart-wrap,
        .dark .prod-hero,
        .dark .prod-kpi-card,
        .dark .prod-filter-card,
        .dark .prod-chart-card,
        .dark .prod-table-card,
        .dark .prod-mini-kpi,
        .dark .prod-chart-shell,
        .dark .prod-table-wrap,
        .dark .prod-empty,
        .dark .prod-modal-panel,
        .dark .prod-modal-header,
        .dark .animal-row,
        .dark .animal-empty-state,
        .dark .animal-modal-panel,
        .dark .animal-modal-section,
        .dark .lot-card,
        .dark .lot-card-premium,
        .dark .lot-metric-card,
        .dark .lot-insight,
        .dark .lot-empty,
        .dark .events-card,
        .dark .events-mini-card,
        .dark .events-filter-popover,
        .dark .events-year-month,
        .dark .event-quick-panel,
        .dark .event-quick-header,
        .dark .event-detail-popover,
        .dark .event-detail-block,
        .dark .event-detail-description,
        .dark .event-card {
            background: rgba(15,23,42,.88) !important;
            border-color: rgba(148,163,184,.22) !important;
            color: #f8fafc;
        }

        .dark .animals-filter-shell,
        .dark .animals-table-wrap,
        .dark .animals-tab,
        .dark .animals-tab-count,
        .dark .animal-avatar,
        .dark .animal-modal-header {
            background: rgba(15,23,42,.88) !important;
            border-color: rgba(148,163,184,.22) !important;
            color: #f8fafc !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.05) !important;
        }

        .dark .animal-row:hover,
        .dark .events-mini-card.clickable:hover {
            background: rgba(51,65,85,.72) !important;
        }

        .dark .animal-row.is-deceased {
            background: rgba(71,85,105,.40) !important;
        }

        .dark .animal-row.is-sold {
            background: rgba(120,53,15,.30) !important;
        }

        .dark .settings-input,
        .dark .settings-select,
        .dark .settings-textarea,
        .dark .finance-form-input,
        .dark .finance-form-select,
        .dark .finance-form-textarea,
        .dark .prod-input,
        .dark .prod-select,
        .dark .prod-textarea,
        .dark .animal-modal-select,
        .dark .animal-modal-textarea,
        .dark .lot-filter-select,
        .dark .event-form-input,
        .dark .event-form-select,
        .dark .event-form-textarea,
        .dark .events-filter-input,
        .dark .animals-filter-input,
        .dark .animals-filter-select {
            background: rgba(15,23,42,.86) !important;
            border-color: rgba(148,163,184,.24) !important;
            color: #f8fafc !important;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.04) !important;
        }

        .dark .settings-btn-secondary,
        .dark .finance-btn-secondary,
        .dark .prod-btn-secondary,
        .dark .lot-action-btn,
        .dark .animal-quick-btn,
        .dark .event-detail-popover button,
        .dark .event-quick-panel button,
        .dark .events-nav-btn,
        .dark .events-view-btn,
        .dark .events-filter-btn,
        .dark .animals-tab,
        .dark .animals-filter-toggle {
            background: rgba(30,41,59,.82) !important;
            border-color: rgba(148,163,184,.24) !important;
            color: #f8fafc !important;
        }

        .dark .settings-permission-pill,
        .dark .finance-pill,
        .dark .prod-badge,
        .dark .prod-chip,
        .dark .lot-pill,
        .dark .animal-stage-pill,
        .dark .animal-sex-pill,
        .dark .animal-status-pill,
        .dark .animal-life-pill,
        .dark .events-badge,
        .dark .animals-filter-count {
            background: rgba(34,197,94,.14) !important;
            border-color: rgba(134,239,172,.24) !important;
            color: #bbf7d0 !important;
        }

        .dark .animals-tab.active,
        .dark .events-view-btn.is-active {
            background: rgba(34,197,94,.20) !important;
            border-color: rgba(134,239,172,.34) !important;
            color: #bbf7d0 !important;
        }

        .dark .animal-sex-pill.male,
        .dark .animal-status-pill.na,
        .dark .animal-status-pill.not-pregnant,
        .dark .events-badge.cancelled {
            background: rgba(148,163,184,.16) !important;
            border-color: rgba(203,213,225,.22) !important;
            color: #e2e8f0 !important;
        }

        .dark .animal-status-pill.pregnant {
            background: rgba(236,72,153,.18) !important;
            border-color: rgba(244,114,182,.30) !important;
            color: #fbcfe8 !important;
        }

        .dark .animal-status-pill.not-milking,
        .dark .animal-life-pill.sold,
        .dark .events-badge.pending {
            background: rgba(245,158,11,.18) !important;
            border-color: rgba(251,191,36,.30) !important;
            color: #fde68a !important;
        }

        .dark .animal-quick-btn.health {
            background: rgba(239,68,68,.16) !important;
            border-color: rgba(248,113,113,.30) !important;
            color: #fecaca !important;
        }

        .dark .fc {
            --fc-border-color: rgba(148,163,184,.22) !important;
            --fc-page-bg-color: transparent !important;
            --fc-neutral-bg-color: rgba(30,41,59,.70) !important;
            --fc-list-event-hover-bg-color: rgba(51,65,85,.82) !important;
            --fc-today-bg-color: rgba(34,197,94,.18) !important;
            color: #f8fafc;
        }

        .dark .fc .fc-scrollgrid,
        .dark .fc .fc-timegrid-slot,
        .dark .fc .fc-timegrid-axis,
        .dark .fc .fc-col-header-cell,
        .dark .fc .fc-daygrid-day,
        .dark .fc .fc-list,
        .dark .fc .fc-list-table td,
        .dark .fc .fc-list-day-cushion {
            background: rgba(15,23,42,.42) !important;
            border-color: rgba(148,163,184,.22) !important;
        }

        .dark .fc .fc-col-header-cell-cushion,
        .dark .fc .fc-daygrid-day-number,
        .dark .fc .fc-timegrid-axis-cushion,
        .dark .events-toolbar-title,
        .dark .events-year-month-title,
        .dark .events-year-day-number,
        .dark .event-quick-title,
        .dark .event-detail-value {
            color: #f8fafc !important;
        }

        .dark .events-year-weekday,
        .dark .event-quick-subtitle,
        .dark .events-filter-label,
        .dark .event-form-label,
        .dark .event-detail-label {
            color: #cbd5e1 !important;
        }

        .dark .lot-name,
        .dark .lot-metric-value,
        .dark .lot-insight-value,
        .dark .lot-empty-title,
        .dark .lot-card-premium .text-gray-900,
        .dark .lot-metric-card .text-gray-900,
        .dark .lot-empty .text-gray-900 {
            color: #f8fafc !important;
        }

        .dark .lot-metric-label,
        .dark .lot-code,
        .dark .lot-preview-empty,
        .dark .lot-insight-label,
        .dark .lot-description,
        .dark .lot-empty,
        .dark .lot-empty-text {
            color: #cbd5e1 !important;
        }

        .dark .lot-preview {
            background:
                radial-gradient(circle at 20% 20%, rgba(34,197,94,.16), transparent 34%),
                linear-gradient(135deg, rgba(30,41,59,.94), rgba(15,23,42,.92)) !important;
            border-color: rgba(148,163,184,.22) !important;
        }

        .dark .lot-preview::after {
            border-color: rgba(134,239,172,.18) !important;
        }

        .dark .lot-code {
            background: rgba(30,41,59,.82) !important;
            border-color: rgba(148,163,184,.22) !important;
        }

        .dark .lot-pill.type {
            background: rgba(59,130,246,.18) !important;
            color: #bfdbfe !important;
        }

        .dark .lot-pill.animals {
            background: rgba(139,92,246,.18) !important;
            color: #ddd6fe !important;
        }

        .dark .lot-pill.inactive {
            background: rgba(148,163,184,.16) !important;
            color: #e2e8f0 !important;
        }

        .dark .lot-filter-input,
        .dark .lot-filter-select {
            background: rgba(15,23,42,.86) !important;
            border-color: rgba(148,163,184,.24) !important;
            color: #f8fafc !important;
        }

        .dark .prod-hero,
        .dark .prod-filter-card,
        .dark .prod-chart-card,
        .dark .prod-table-card,
        .dark .prod-kpi-card,
        .dark .prod-mini-kpi,
        .dark .prod-chart-shell,
        .dark .prod-table-wrap,
        .dark .prod-modal-panel,
        .dark .prod-modal-header {
            background: rgba(15,23,42,.90) !important;
            border-color: rgba(148,163,184,.24) !important;
            color: #f8fafc !important;
            box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.05) !important;
        }

        .dark .prod-section-title,
        .dark .prod-table td,
        .dark .prod-table .text-gray-900,
        .dark .prod-kpi-card .text-gray-900,
        .dark .prod-mini-kpi .text-gray-900,
        .dark .prod-modal-panel .text-gray-900 {
            color: #f8fafc !important;
        }

        .dark .prod-section-subtitle,
        .dark .prod-chart-legend-item,
        .dark .prod-field label,
        .dark .prod-table th,
        .dark .prod-empty,
        .dark .prod-kpi-card .text-gray-500,
        .dark .prod-mini-kpi .text-gray-500,
        .dark .prod-modal-panel .text-gray-500 {
            color: #cbd5e1 !important;
        }

        .dark .prod-table thead,
        .dark .prod-table thead tr,
        .dark .prod-table thead th {
            background: rgba(15,23,42,.96) !important;
            color: #f8fafc !important;
            border-color: rgba(148,163,184,.24) !important;
        }

        .dark .prod-table tbody tr {
            border-color: rgba(148,163,184,.16) !important;
        }

        .dark .prod-table tbody tr:hover {
            background: rgba(51,65,85,.72) !important;
        }

        .dark .prod-chip,
        .dark .prod-badge.filter {
            background: rgba(30,41,59,.82) !important;
            border-color: rgba(148,163,184,.24) !important;
            color: #f8fafc !important;
        }

        .dark .prod-badge.milk,
        .dark .prod-kpi-icon.milk {
            background: rgba(34,197,94,.16) !important;
            color: #bbf7d0 !important;
        }

        .dark .prod-badge.meat,
        .dark .prod-kpi-icon.meat {
            background: rgba(239,68,68,.16) !important;
            color: #fecaca !important;
        }

        .dark .prod-kpi-icon.money {
            background: rgba(245,158,11,.18) !important;
            color: #fde68a !important;
        }

        .dark .prod-kpi-icon.filter {
            background: rgba(59,130,246,.18) !important;
            color: #bfdbfe !important;
        }

        .dark .prod-delete-btn {
            background: rgba(239,68,68,.16) !important;
            border-color: rgba(248,113,113,.30) !important;
            color: #fecaca !important;
        }

        .dark .animal-table-head,
        .dark .animal-table-head tr,
        .dark .animal-table-head th {
            background: rgba(15,23,42,.96) !important;
            color: #f8fafc !important;
            border-color: rgba(148,163,184,.24) !important;
        }

        .dark .animals-table-wrap table,
        .dark .animals-table-wrap tbody,
        .dark .animals-table-wrap td {
            color: #e2e8f0 !important;
            border-color: rgba(148,163,184,.16) !important;
        }

        .dark .animal-row td,
        .dark .animal-row .text-gray-600,
        .dark .animal-row .text-gray-500,
        .dark .animal-row .text-gray-900 {
            color: #e2e8f0 !important;
        }

        .dark .animal-row .font-extrabold,
        .dark .animal-row .font-bold {
            color: #f8fafc !important;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes navCascade {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 1024px) {
            .sidebar-shell {
                display: none;
            }

            .topbar-shell {
                left: 0;
            }

            .notif-panel {
                position: fixed;
                top: calc(var(--topbar-h) + 12px);
                right: 14px;
                left: auto;
                width: min(430px, calc(100vw - 28px));
                max-height: calc(100dvh - var(--topbar-h) - 28px);
                z-index: 240;
            }

            .notif-list {
                max-height: calc(100dvh - var(--topbar-h) - 166px);
            }

            .content-shell {
                margin-left: 0;
            }

            .topbar-btn.mobile-menu-btn {
                display: inline-flex;
            }

            .mobile-brand {
                display: inline-flex;
            }

            .mobile-drawer {
                display: block;
            }

            .status-pill {
                display: none;
            }

            .profile-btn .hidden.md\:block {
                display: none !important;
            }
        }

        @media (max-width: 640px) {
            :root {
                --topbar-h: 76px;
            }

            .admin-viewing-client .topbar-shell {
                top: 60px;
            }

            .admin-viewing-client .content-shell {
                padding-top: calc(var(--topbar-h) + 76px);
            }

            .topbar-shell {
                padding: calc(6px + env(safe-area-inset-top, 0px)) 12px 0 12px;
            }

            .topbar-card {
                height: 60px;
                padding: 0 10px;
                border-radius: 18px;
            }

            .mobile-brand img {
                height: 30px;
            }

            .topbar-btn {
                width: 38px;
                height: 38px;
                border-radius: 13px;
            }

            .profile-btn {
                height: 40px;
                gap: 8px;
                padding: 0 10px 0 7px;
                border-radius: 14px;
            }

            .profile-avatar {
                width: 30px;
                height: 30px;
            }

            .content-inner {
                padding: 0 12px 24px;
            }

            body.pwa-standalone .content-inner {
                padding-bottom: 112px;
            }

            body.pwa-standalone {
                --topbar-h: 78px;
            }

            body.pwa-standalone .topbar-shell {
                padding: max(20px, calc(env(safe-area-inset-top, 0px) - 6px)) 12px 0 12px;
            }

            body.pwa-standalone .topbar-card {
                height: 58px;
                border-radius: 19px;
            }

            body.pwa-standalone .content-shell {
                padding-top: calc(var(--topbar-h) + 8px + max(20px, calc(env(safe-area-inset-top, 0px) - 6px)));
            }

            body.pwa-standalone .content-inner {
                padding-bottom: 102px;
            }

            body:not(.pwa-standalone) .notif-panel {
                top: calc(92px + env(safe-area-inset-top, 0px));
                left: 12px;
                right: 12px;
                width: auto;
                max-width: none;
                max-height: calc(100dvh - 116px - env(safe-area-inset-top, 0px));
                border-radius: 22px;
                z-index: 240;
            }

            body:not(.pwa-standalone).admin-viewing-client .notif-panel {
                top: calc(160px + env(safe-area-inset-top, 0px));
                max-height: calc(100dvh - 184px - env(safe-area-inset-top, 0px));
            }

            body:not(.pwa-standalone) .notif-list {
                max-height: calc(100dvh - 244px - env(safe-area-inset-top, 0px));
            }

            body.pwa-standalone .notif-panel {
                position: fixed;
                top: calc(84px + max(20px, calc(env(safe-area-inset-top, 0px) - 6px)));
                left: 14px;
                right: 14px;
                bottom: calc(18px + env(safe-area-inset-bottom, 0px));
                width: auto;
                max-width: none;
                max-height: none;
                border: 0;
                border-radius: 0;
                background: transparent;
                box-shadow: none;
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
                overflow: visible;
                z-index: 10060;
            }

            body.pwa-standalone.mobile-notifications-open::before {
                content: '';
                position: fixed;
                inset: 0;
                z-index: 10020;
                background:
                    radial-gradient(circle at 50% 100%, rgba(34,197,94,.18), transparent 36%),
                    rgba(2,6,23,.28);
                backdrop-filter: blur(6px);
                -webkit-backdrop-filter: blur(6px);
            }

            body.pwa-standalone.mobile-notifications-open {
                overflow: hidden;
                overscroll-behavior: none;
            }

            body.pwa-standalone.mobile-notifications-open .topbar-shell {
                z-index: 10050;
            }

            body.pwa-standalone.mobile-notifications-open .mobile-app-nav-wrap {
                pointer-events: none;
                opacity: .18;
            }

            body.pwa-standalone .notif-panel.is-open {
                display: flex;
                flex-direction: column;
                animation: mobileNotifSheetIn .2s ease;
            }

            body.pwa-standalone.dark .notif-panel {
                background: transparent;
                box-shadow: none;
            }

            body.pwa-standalone.dark.mobile-notifications-open::before {
                background:
                    radial-gradient(circle at 50% 100%, rgba(34,197,94,.12), transparent 38%),
                    rgba(2,6,23,.82);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
            }

            body.pwa-standalone .notif-panel::before {
                display: none;
            }

            body.pwa-standalone.admin-viewing-client .notif-panel {
                top: calc(172px + env(safe-area-inset-top, 0px));
                max-height: none;
            }

            body.pwa-standalone .notif-header {
                flex-shrink: 0;
                padding: 0 4px 14px;
                border: 0;
                background: transparent;
            }

            body.pwa-standalone .notif-header h3 {
                color: #f8fafc !important;
                text-shadow: 0 2px 14px rgba(2,6,23,.30);
            }

            body.pwa-standalone .notif-header p {
                color: rgba(248,250,252,.86) !important;
                text-shadow: 0 2px 12px rgba(2,6,23,.24);
            }

            body.pwa-standalone.dark .notif-header h3 {
                color: #f0fdf4 !important;
                text-shadow: 0 2px 18px rgba(0,0,0,.62);
            }

            body.pwa-standalone.dark .notif-header p {
                color: rgba(220,252,231,.88) !important;
                text-shadow: 0 2px 16px rgba(0,0,0,.56);
            }

            body.pwa-standalone #markAllNotificationsBtn {
                width: 100%;
                justify-content: center;
                border-color: rgba(34,197,94,.20);
                background: linear-gradient(135deg, rgba(22,101,52,.96), rgba(34,197,94,.88));
                color: #fff;
                box-shadow: 0 18px 34px rgba(22,101,52,.24);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
            }

            body.pwa-standalone.dark .notif-header {
                background: transparent;
            }

            .notif-header .flex {
                align-items: flex-start;
            }

            body.pwa-standalone .notif-list {
                flex: 1 1 auto;
                min-height: 0;
                max-height: none;
                padding: 0 2px 12px;
            }

            body.pwa-standalone.admin-viewing-client .notif-list {
                max-height: none;
            }

            body.pwa-standalone .notif-empty {
                flex: 1 1 auto;
                display: flex;
                align-items: center;
                justify-content: center;
                color: rgba(248,250,252,.88);
                text-shadow: 0 2px 12px rgba(2,6,23,.24);
            }

            body.pwa-standalone .notif-actions {
                flex-shrink: 0;
                padding: 8px 2px 0;
                border: 0;
                background: transparent;
            }

            body.pwa-standalone .notif-close-btn {
                border-color: rgba(255,255,255,.28);
                background: rgba(255,255,255,.18);
                color: #fff;
                box-shadow: 0 14px 28px rgba(2,6,23,.16);
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
            }

            body.pwa-standalone .notif-item {
                padding: 14px;
                border-radius: 20px;
                background: rgba(255,255,255,.88);
                border-color: rgba(255,255,255,.52);
                border-left: 4px solid rgba(22,163,74,.78);
                box-shadow: 0 18px 42px rgba(2,6,23,.16);
                backdrop-filter: blur(18px);
                -webkit-backdrop-filter: blur(18px);
            }

            body.pwa-standalone.dark .notif-item {
                background: linear-gradient(135deg, rgba(2,6,23,.96), rgba(15,23,42,.90));
                border-color: rgba(74,222,128,.24);
                border-left-color: rgba(74,222,128,.82);
                box-shadow: 0 18px 42px rgba(0,0,0,.58);
            }

            body.pwa-standalone.dark .notif-item .text-gray-900,
            body.pwa-standalone.dark .notif-item .dark\:text-white {
                color: #f8fafc !important;
            }

            body.pwa-standalone.dark .notif-item .text-gray-500,
            body.pwa-standalone.dark .notif-item .dark\:text-gray-400 {
                color: #cbd5e1 !important;
            }

            body.pwa-standalone.dark .notif-close-btn {
                border-color: rgba(74,222,128,.28);
                background: rgba(2,6,23,.78);
                color: #dcfce7;
                box-shadow: 0 14px 28px rgba(0,0,0,.36);
            }

            body.pwa-standalone.dark #markAllNotificationsBtn {
                border-color: rgba(74,222,128,.24);
                background: linear-gradient(135deg, rgba(20,83,45,.98), rgba(22,163,74,.90));
            }

            .dark body.pwa-standalone.dark .notif-item,
            .dark body.pwa-standalone .notif-item {
                background: linear-gradient(135deg, rgba(2,6,23,.96), rgba(15,23,42,.90)) !important;
                border-color: rgba(74,222,128,.24) !important;
                border-left-color: rgba(74,222,128,.82) !important;
                box-shadow: 0 18px 42px rgba(0,0,0,.58) !important;
            }

            .dark body.pwa-standalone .notif-item:hover {
                background: linear-gradient(135deg, rgba(2,6,23,.98), rgba(15,23,42,.94)) !important;
            }

            .dark body.pwa-standalone .notif-item .text-gray-900,
            .dark body.pwa-standalone .notif-item .dark\:text-white {
                color: #f8fafc !important;
            }

            .dark body.pwa-standalone .notif-item .text-gray-500,
            .dark body.pwa-standalone .notif-item .dark\:text-gray-400 {
                color: #cbd5e1 !important;
            }

            .dark body.pwa-standalone.mobile-notifications-open::before {
                background:
                    radial-gradient(circle at 50% 100%, rgba(34,197,94,.12), transparent 38%),
                    rgba(2,6,23,.82) !important;
            }

            .notif-item .flex.items-start.justify-between {
                gap: 10px;
            }

            .notif-pill,
            .notif-level {
                padding: 4px 8px;
                font-size: 10px;
            }

            body.pwa-standalone #notifToggleBtn {
                display: none;
            }

            .profile-menu {
                right: -10px;
                width: min(260px, calc(100vw - 20px));
            }
        }

        @media (max-width: 380px) {
            body.pwa-standalone .notif-panel {
                left: 12px;
                right: 12px;
                top: calc(100px + env(safe-area-inset-top, 0px));
                bottom: calc(12px + env(safe-area-inset-bottom, 0px));
            }

            .notif-header .flex.items-center.justify-between {
                flex-direction: column;
            }

            #markAllNotificationsBtn {
                width: 100%;
                justify-content: center;
            }

            .notif-item .flex.flex-wrap.items-center {
                gap: 6px;
            }
        }

        [x-cloak] {
            display: none !important;
        }

        .mobile-app-nav-wrap {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 230;
            display: none;
            justify-content: center;
            padding: 0 14px calc(5px + env(safe-area-inset-bottom, 0px));
            pointer-events: none;
        }

        .mobile-app-nav {
            position: relative;
            width: min(100%, 430px);
            min-height: 68px;
            display: grid;
            grid-template-columns: 1fr 1fr 76px 1fr 1fr;
            align-items: center;
            gap: 4px;
            border-radius: 999px;
            padding: 8px 10px;
            border: 1px solid rgba(255,255,255,.62);
            background:
                linear-gradient(135deg, rgba(255,255,255,.74), rgba(255,255,255,.48));
            box-shadow:
                0 24px 58px rgba(15,23,42,.18),
                inset 0 1px 0 rgba(255,255,255,.74);
            backdrop-filter: blur(24px) saturate(1.35);
            -webkit-backdrop-filter: blur(24px) saturate(1.35);
            pointer-events: auto;
        }

        .dark .mobile-app-nav {
            border-color: rgba(255,255,255,.10);
            background:
                linear-gradient(135deg, rgba(15,23,42,.72), rgba(2,6,23,.54));
            box-shadow:
                0 24px 58px rgba(0,0,0,.40),
                inset 0 1px 0 rgba(255,255,255,.08);
        }

        .mobile-nav-item,
        .mobile-nav-action {
            position: relative;
            min-width: 0;
            border: 0;
            background: transparent;
            color: #64748b;
            text-decoration: none;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            border-radius: 20px;
            padding: 8px 2px;
            font-size: 9px;
            line-height: 1;
            font-weight: 850;
            transition: transform .18s ease, color .18s ease, background .18s ease;
            cursor: pointer;
        }

        .mobile-nav-item svg,
        .mobile-nav-action svg {
            width: 21px;
            height: 21px;
        }

        .mobile-nav-item > span,
        .mobile-nav-action > span:not(.mobile-nav-badge) {
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            letter-spacing: 0;
        }

        .mobile-nav-item.active,
        .mobile-nav-item:hover,
        .mobile-nav-action:hover {
            color: #166534;
            background: rgba(22,101,52,.08);
        }

        .dark .mobile-nav-item,
        .dark .mobile-nav-action {
            color: #cbd5e1;
        }

        .dark .mobile-nav-item.active,
        .dark .mobile-nav-item:hover,
        .dark .mobile-nav-action:hover {
            color: #bbf7d0;
            background: rgba(34,197,94,.12);
        }

        .mobile-nav-plus {
            width: 52px;
            height: 52px;
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,.70);
            border-radius: 20px;
            background: linear-gradient(135deg, #22c55e, #166534);
            color: #fff;
            box-shadow:
                0 14px 26px rgba(22,101,52,.28),
                inset 0 1px 0 rgba(255,255,255,.34);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform .18s ease, filter .18s ease;
        }

        .mobile-nav-plus:hover,
        .mobile-nav-plus.is-open {
            transform: scale(1.02);
            filter: brightness(1.04);
        }

        .mobile-nav-plus svg {
            width: 28px;
            height: 28px;
            transition: transform .18s ease;
        }

        .mobile-nav-plus.is-open svg {
            transform: rotate(45deg);
        }

        .mobile-nav-badge {
            position: absolute;
            top: 4px;
            right: calc(50% - 20px);
            min-width: 17px;
            height: 17px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            border: 2px solid rgba(255,255,255,.92);
            font-size: 9px;
            font-weight: 950;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(239,68,68,.24);
        }

        .dark .mobile-nav-badge {
            border-color: rgba(2,6,23,.96);
        }

        .mobile-quick-menu {
            position: fixed;
            left: 18px;
            right: 18px;
            bottom: calc(98px + env(safe-area-inset-bottom, 0px));
            z-index: 229;
            display: none;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            width: min(394px, calc(100vw - 36px));
            margin: 0 auto;
            border: 1px solid rgba(255,255,255,.62);
            border-radius: 28px;
            padding: 12px;
            background: linear-gradient(135deg, rgba(255,255,255,.78), rgba(255,255,255,.52));
            box-shadow: 0 24px 58px rgba(15,23,42,.20), inset 0 1px 0 rgba(255,255,255,.70);
            backdrop-filter: blur(24px) saturate(1.35);
            -webkit-backdrop-filter: blur(24px) saturate(1.35);
        }

        .mobile-quick-menu::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -9px;
            width: 18px;
            height: 18px;
            border-right: 1px solid rgba(255,255,255,.62);
            border-bottom: 1px solid rgba(255,255,255,.62);
            background: rgba(255,255,255,.58);
            transform: translateX(-50%) rotate(45deg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        .mobile-quick-menu.is-open {
            display: grid;
            animation: mobileQuickIn .18s ease;
        }

        .dark .mobile-quick-menu {
            border-color: rgba(255,255,255,.10);
            background: linear-gradient(135deg, rgba(15,23,42,.82), rgba(2,6,23,.62));
            box-shadow: 0 24px 58px rgba(0,0,0,.42), inset 0 1px 0 rgba(255,255,255,.08);
        }

        .dark .mobile-quick-menu::after {
            border-color: rgba(255,255,255,.10);
            background: rgba(15,23,42,.70);
        }

        .mobile-quick-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            border-radius: 18px;
            padding: 12px;
            color: #0f172a;
            background: rgba(255,255,255,.62);
            border: 1px solid rgba(15,23,42,.06);
            text-decoration: none;
            font-size: 12px;
            font-weight: 950;
            transition: transform .18s ease, background .18s ease;
        }

        .mobile-quick-link:hover {
            transform: translateY(-1px);
            background: rgba(255,255,255,.88);
        }

        .dark .mobile-quick-link {
            color: #f8fafc;
            background: rgba(255,255,255,.06);
            border-color: rgba(255,255,255,.08);
        }

        .mobile-quick-icon {
            width: 36px;
            height: 36px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #166534;
            background: rgba(34,197,94,.12);
        }

        .dark .mobile-quick-icon {
            color: #bbf7d0;
            background: rgba(34,197,94,.14);
        }

        .mobile-quick-icon svg {
            width: 19px;
            height: 19px;
        }

        @keyframes mobileQuickIn {
            from {
                opacity: 0;
                transform: translateY(10px) scale(.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes mobileNotifSheetIn {
            from {
                opacity: 0;
                transform: translateY(18px) scale(.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 640px) {
            body.pwa-standalone .mobile-app-nav-wrap {
                display: flex;
                z-index: 10080;
                padding: 0 12px max(14px, calc(env(safe-area-inset-bottom, 0px) - 14px));
            }

            body.pwa-standalone .mobile-quick-menu {
                z-index: 10079;
            }

            body:not(.pwa-standalone) .mobile-quick-menu.is-open {
                display: none;
            }

            body.pwa-standalone .mobile-app-nav {
                min-height: 64px;
                padding: 8px 10px;
                border-radius: 999px;
            }

            body.pwa-standalone .mobile-nav-plus {
                width: 52px;
                height: 52px;
                margin-top: 0;
            }
        }

        @media (pointer: coarse) {
            body.pwa-standalone {
                -webkit-text-size-adjust: 100%;
                text-size-adjust: 100%;
            }

            body.pwa-standalone .mobile-app-nav-wrap {
                display: flex;
                z-index: 10080;
                padding: 0 12px max(14px, calc(env(safe-area-inset-bottom, 0px) - 14px));
            }

            body.pwa-standalone .mobile-quick-menu {
                z-index: 10079;
            }

            body.pwa-standalone .content-inner {
                padding-bottom: 112px;
            }
        }
    </style>
</head>

@php
    $hasFarmNotificationModel = class_exists(\App\Models\FarmNotification::class);
    $hasFarmNotificationService = class_exists(\App\Services\FarmNotificationService::class);

    $notificationsSyncUrl = Route::has('notifications.sync') ? route('notifications.sync') : '';
    $notificationsMarkAllUrl = Route::has('notifications.mark-all-read') ? route('notifications.mark-all-read') : '';
    $notificationsPushSubscribeUrl = Route::has('notifications.push.subscribe') ? route('notifications.push.subscribe') : '';
    $notificationsPushUnsubscribeUrl = Route::has('notifications.push.unsubscribe') ? route('notifications.push.unsubscribe') : '';
    $webPushPublicKey = (string) config('services.webpush.public_key', '');

    $user = auth()->user();
    $adminViewingClient = null;

    if ($user?->canAccessAdminPanel() && session('admin_view_client_id')) {
        $adminViewingClient = \App\Models\User::find(session('admin_view_client_id'));
    }

    $farm = $user?->currentFarm();
    $farmsOwner = $adminViewingClient ?: $user;
    $notificationsUser = $adminViewingClient ?: $user;
    $farms = $farmsOwner ? $farmsOwner->farms()->orderBy('name')->get() : collect();
    $isAdminMode = $user?->canAccessAdminPanel() && ! $adminViewingClient;
    $supportWhatsappUrl = null;
    $supportWhatsappMessage = 'Ayuda por WhatsApp';

    if (class_exists(\App\Models\PlatformSetting::class)
        && \Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
        $supportWhatsappUrl = \App\Models\PlatformSetting::where('key', 'support_whatsapp_url')->value('value');
        $supportWhatsappMessage = \App\Models\PlatformSetting::where('key', 'support_whatsapp_message')->value('value')
            ?: $supportWhatsappMessage;
    }

    if (! $supportWhatsappUrl && $farm && class_exists(\App\Models\FarmSetting::class)) {
        $supportWhatsappUrl = \App\Models\FarmSetting::where('farm_id', $farm->id)
            ->where('key', 'support_whatsapp_url')
            ->value('value');
    }

    $supportWhatsappUrl = $supportWhatsappUrl
        ?: config('services.interfarm_support_whatsapp_url')
        ?: env('INTERFARM_SUPPORT_WHATSAPP_URL')
        ?: 'https://wa.me/';

    if (! $isAdminMode && $farm && $notificationsUser && $hasFarmNotificationService) {
        try { app(\App\Services\FarmNotificationService::class)->syncForFarmAndUser($farm, $notificationsUser); } catch (\Throwable $e) { \Log::warning('Notif sync skipped: '.$e->getMessage()); }
    }

    $notificationsQuery = null;

    if ($notificationsUser && $hasFarmNotificationModel) {
        $notificationsQuery = \App\Models\FarmNotification::where('user_id', $notificationsUser->id)
            ->where(function ($query) {
                $query->whereNull('dismissed_at')
                    ->orWhere(function ($subQuery) {
                        $subQuery->whereNotNull('dismissed_until')
                            ->where('dismissed_until', '<=', now());
                    });
            });

        if ($isAdminMode) {
            $notificationsQuery->whereIn('source_type', ['admin_payment_received']);
        } else {
            $notificationsQuery->where(function ($query) use ($farm) {
                $query->whereNull('farm_id');

                if ($farm) {
                    $query->orWhere('farm_id', $farm->id);
                }
            });
        }
    }

    $notifications = $notificationsQuery
        ? (clone $notificationsQuery)->latest('scheduled_for')->take(20)->get()
        : collect();

    $unreadNotificationCount = $notificationsQuery
        ? (clone $notificationsQuery)->whereNull('read_at')->count()
        : 0;

    if ($isAdminMode) {
        $nav = [
            ['section' => 'administracion', 'label' => 'Centro SaaS', 'route' => Route::has('admin.saas.index') ? 'admin.saas.index' : 'admin.dashboard', 'icon' => 'settings'],
            ['section' => 'administracion', 'label' => 'Clientes', 'route' => 'admin.users.index', 'icon' => 'animals'],
            ['section' => 'administracion', 'label' => 'Administradores', 'route' => 'admin.staff.index', 'icon' => 'users'],
            [
                'section' => 'administracion',
                'label' => 'Facturación',
                'route' => 'admin.saas.billing.index',
                'icon' => 'money',
                'children' => [
                    ['label' => 'Facturas', 'route' => Route::has('admin.saas.billing.invoices') ? 'admin.saas.billing.invoices' : 'admin.saas.billing.index'],
                    ['label' => 'Pagos', 'route' => Route::has('admin.saas.billing.payments') ? 'admin.saas.billing.payments' : 'admin.saas.billing.index'],
                    ['label' => 'Formas de pago', 'route' => Route::has('admin.saas.billing.payment-methods') ? 'admin.saas.billing.payment-methods' : 'admin.saas.billing.index'],
                ],
            ],
            ['section' => 'administracion', 'label' => 'Planes', 'route' => 'admin.saas.plans.index', 'icon' => 'reports'],
            ['section' => 'administracion', 'label' => 'Notificaciones', 'route' => Route::has('admin.saas.notifications.index') ? 'admin.saas.notifications.index' : 'admin.saas.index', 'icon' => 'calendar'],
            ['section' => 'administracion', 'label' => 'Auditoría', 'route' => 'admin.saas.audit.index', 'icon' => 'reports', 'super_admin_only' => true],
            ['section' => 'administracion', 'label' => 'Configuración', 'route' => 'admin.saas.settings.index', 'icon' => 'settings'],
        ];

        $nav = collect($nav)
            ->filter(function ($item) use ($user) {
                if (($item['super_admin_only'] ?? false) && ! $user?->isSuperAdmin()) {
                    return false;
                }

                $route = $item['route'] ?? null;
                $permission = \App\Models\User::adminPermissionForRoute($route);

                return ! $permission || $user?->hasAdminPermission($permission);
            })
            ->map(function ($item) use ($user) {
                if (! empty($item['children'])) {
                    $item['children'] = collect($item['children'])
                        ->filter(function ($child) use ($user) {
                            $route = $child['route'] ?? null;
                            $permission = \App\Models\User::adminPermissionForRoute($route);

                            return ! $permission || $user?->hasAdminPermission($permission);
                        })
                        ->values()
                        ->all();
                }

                return $item;
            })
            ->values()
            ->all();
    } elseif ($user?->isSuspended()) {
        $nav = [
            [
                'section' => 'sistema',
                'label' => 'Facturación',
                'route' => Route::has('client.billing.invoices') ? 'client.billing.invoices' : 'client.suspended',
                'icon' => 'money',
                'children' => [
                    ['label' => 'Aviso de pago', 'route' => Route::has('client.suspended') ? 'client.suspended' : null],
                    ['label' => 'Facturas', 'route' => Route::has('client.billing.invoices') ? 'client.billing.invoices' : null],
                    ['label' => 'Pagos', 'route' => Route::has('client.billing.payments') ? 'client.billing.payments' : null],
                    ['label' => 'Forma de pago', 'route' => Route::has('client.billing.payment-methods') ? 'client.billing.payment-methods' : null],
                ],
            ],
        ];
    } else {
        $nav = [
            ['section' => 'principal', 'label' => 'Panel', 'route' => 'dashboard', 'icon' => 'panel'],
            ['section' => 'principal', 'label' => 'Animales', 'route' => 'animals.index', 'icon' => 'animals'],
        ['section' => 'principal', 'label' => 'Genealogía', 'route' => Route::has('genealogy.index') ? 'genealogy.index' : null, 'icon' => 'animals'],
            ['section' => 'principal', 'label' => 'Lotes y praderas', 'route' => 'lots.index', 'icon' => 'fields'],
            ['section' => 'principal', 'label' => 'Eventos', 'route' => 'events.index', 'icon' => 'calendar'],
            ['section' => 'operacion', 'label' => 'Producción', 'route' => Route::has('production.index') ? 'production.index' : null, 'icon' => 'milk'],
        ['section' => 'operacion', 'label' => 'Lactancia', 'route' => Route::has('lactation.index') ? 'lactation.index' : null, 'icon' => 'milk'],
            ['section' => 'operacion', 'label' => 'Reportes', 'route' => Route::has('reports.index') ? 'reports.index' : null, 'icon' => 'reports'],
            ['section' => 'operacion', 'label' => 'Gastos e ingresos', 'route' => Route::has('finances.index') ? 'finances.index' : null, 'icon' => 'money'],
            [
                'section' => 'sistema',
                'label' => 'Facturación',
                'route' => Route::has('client.billing.invoices') ? 'client.billing.invoices' : null,
                'icon' => 'money',
                'children' => [
                    ['label' => 'Facturas', 'route' => Route::has('client.billing.invoices') ? 'client.billing.invoices' : null],
                    ['label' => 'Pagos', 'route' => Route::has('client.billing.payments') ? 'client.billing.payments' : null],
                    ['label' => 'Forma de pago', 'route' => Route::has('client.billing.payment-methods') ? 'client.billing.payment-methods' : null],
                ],
            ],
            ['section' => 'sistema', 'label' => 'Configuración', 'route' => 'settings.index', 'icon' => 'settings'],
        ];

        if ($user?->canAccessAdminPanel()) {
            $nav[] = ['section' => 'sistema', 'label' => 'Volver a Administración SaaS', 'route' => Route::has('admin.saas.index') ? 'admin.saas.index' : 'admin.dashboard', 'icon' => 'settings'];
        }
    }

    $navGroups = collect($nav)->groupBy('section');

    $icons = [
        'panel' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4zm10 0h6v4h-6V4zM14 12h6v8h-6v-8zM4 14h6v6H4v-6z"/>',
        'animals' => '
            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="
                M6 8
                C8.2 4.8 10.2 4 12 4
                C13.8 4 15.8 4.8 18 8

                M6 8
                C4.8 7.1 3.8 6.1 3.2 4.8
                C4.9 5.4 6.2 6.1 7.2 7.1

                M18 8
                C19.2 7.1 20.2 6.1 20.8 4.8
                C19.1 5.4 17.8 6.1 16.8 7.1

                M7.2 8.2
                L8.8 16

                M16.8 8.2
                L15.2 16

                M8.8 16
                C10 14.8 11 14.3 12 14.3
                C13 14.3 14 14.8 15.2 16

                M9 16
                C8.3 16.5 8 17.2 8 18
                C8 19.7 9.6 21 12 21
                C14.4 21 16 19.7 16 18
                C16 17.2 15.7 16.5 15 16

                M10.3 20
                L12 20.7
                L13.7 20
            "/>
        ',
        'fields' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10Z"/><circle cx="12" cy="11" r="2.2" stroke-width="1.8"/>',
        'calendar' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M7 2v4M17 2v4M3 10h18M5 6h14a2 2 0 0 1 2 2v12H3V8a2 2 0 0 1 2-2z"/>',
        'milk' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 3h6l1.3 3.5H7.7L9 3zm-2 5h10l-1.2 12H8.2L7 8z"/>',
        'reports' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M5 19V9m7 10V5m7 14v-7"/>',
        'money' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m-6.5-4c.9 1.6 3 2.5 6.5 2.5 3.9 0 6.5-1.5 6.5-4s-2.4-3.7-6.5-4.4c-3.8-.6-6.5-1.6-6.5-4.1S8.2 3.5 12 3.5c3.2 0 5.4 1 6.2 2.8"/>',
        'users' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M16 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M8 13a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M2.5 21a5.5 5.5 0 0 1 11 0"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M13.5 17.5A5.5 5.5 0 0 1 21.5 21"/>',
        'settings' => '<path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 8.5A3.5 3.5 0 1 0 12 15.5A3.5 3.5 0 1 0 12 8.5z"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.4a1.7 1.7 0 0 0-.34-1.87l-.06-.06A2 2 0 1 1 7.08 3.64l.06.06A1.7 1.7 0 0 0 9 4.04a1.7 1.7 0 0 0 1.04-1.56V2.4a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.08 4a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.44 8.4A1.7 1.7 0 0 0 21 9.44H21.1a2 2 0 1 1 0 4H21a1.7 1.7 0 0 0-1.56 1.04z"/>',
    ];

    $sectionNames = [
        'principal' => 'Principal',
        'operacion' => 'Operación',
        'sistema' => 'Sistema',
        'administracion' => 'Administración SaaS',
    ];

    $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    $displayName = $fullName !== '' ? $fullName : ($user->name ?? 'Usuario');
    $initials = collect(explode(' ', $displayName))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<body class="min-h-screen text-gray-900 dark:text-gray-100 {{ $adminViewingClient ? 'admin-viewing-client' : '' }}"
      data-notifications-sync-url="{{ $notificationsSyncUrl }}"
      data-notifications-mark-all-url="{{ $notificationsMarkAllUrl }}"
      data-notifications-push-subscribe-url="{{ $notificationsPushSubscribeUrl }}"
      data-notifications-push-unsubscribe-url="{{ $notificationsPushUnsubscribeUrl }}"
      data-web-push-public-key="{{ $webPushPublicKey }}"
      data-current-farm-id="{{ session('current_farm_id') }}"
      data-finance-cache-refresh="{{ session('finance_cache_refresh') ? '1' : '0' }}">
    <div id="appPreloader" class="app-preloader" aria-live="polite" aria-label="Cargando InterFarm">
        <div class="app-preloader-card">
            <img src="/images/apple-touch-icon.png?v=3" alt="InterFarm" class="app-preloader-icon">
            <div class="app-preloader-title">InterFarm</div>
            <div id="appPreloaderText" class="app-preloader-text">Preparando tu finca...</div>
            <div class="app-preloader-bar" aria-hidden="true"></div>
        </div>
    </div>

    @if($adminViewingClient)
        <div class="fixed left-0 right-0 top-0 z-[100] bg-amber-500 text-amber-950 shadow-lg md:left-[var(--sidebar-w)]">
            <div class="flex flex-col gap-2 px-4 py-2 text-sm font-black md:flex-row md:items-center md:justify-between">
                <div>
                    Modo cliente: estás viendo la plataforma como {{ $adminViewingClient->full_name ?: $adminViewingClient->email }}.
                </div>
                <form method="POST" action="{{ route('admin.saas.clients.stop-viewing') }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg bg-amber-950 px-3 py-1.5 text-xs font-black text-white">
                        Volver al modo administrador
                    </button>
                </form>
            </div>
        </div>
    @endif

    <aside class="sidebar-shell">
        <div class="sidebar-inner">
            <div class="sidebar-top">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
                    <x-brand-logo class="h-11 w-auto" />
                </a>
            </div>

            <div class="sidebar-scroll">
                @if($isAdminMode)
                    <div class="farm-card">
                        <div class="font-bold text-[22px] text-gray-900 dark:text-white leading-tight">
                            Modo administrador
                        </div>

                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                            Gestiona clientes, planes, pagos y configuración global de la plataforma.
                        </div>

                        <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-green-500/20 bg-green-50 px-3 py-1.5 text-xs font-black text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                            <span class="h-2 w-2 rounded-full bg-green-500"></span>
                            SaaS activo
                        </div>

                        <div class="mt-4 grid gap-2">
                            <a href="{{ route('admin.saas.index') }}"
                               class="inline-flex w-full items-center justify-center rounded-xl bg-green-600 px-3 py-2 text-sm font-extrabold text-white transition hover:bg-green-700">
                                Centro SaaS
                            </a>
                            <a href="{{ route('admin.users.index') }}"
                               class="inline-flex w-full items-center justify-center rounded-xl border border-green-500/25 bg-green-50 px-3 py-2 text-sm font-extrabold text-green-700 transition hover:bg-green-100 dark:border-green-400/25 dark:bg-green-500/10 dark:text-green-200">
                                Ver clientes
                            </a>
                        </div>
                    </div>
                @else
                <div class="farm-card">
                    <div class="font-bold text-[22px] text-gray-900 dark:text-white leading-tight">
                        {{ $farm?->name ?? 'Tu finca' }}
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                        {{ $farm?->location ?? 'Ubicación no definida' }}
                    </div>

                    <div class="mt-4 inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-black/5 dark:border-white/8 bg-white/50 dark:bg-white/5 text-gray-700 dark:text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                        En operación
                    </div>

                    <div class="mt-4 space-y-2">
                        @if($farms->count() > 1 && Route::has('farms.switch'))
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button"
                                        @click="open = !open"
                                        class="flex w-full items-center gap-3 rounded-2xl border border-black/10 bg-white/80 px-3 py-3 text-left shadow-sm transition hover:border-green-400 hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:hover:border-green-400/40">
                                    <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-200">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 10.5 12 4l8 6.5V20H4v-9.5Z"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 20v-6h6v6"/>
                                        </svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[11px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Finca activa</span>
                                        <span class="block truncate text-sm font-black text-gray-900 dark:text-white">{{ $farm?->name ?? 'Tu finca' }}</span>
                                    </span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4 text-gray-400 transition" :class="{ 'rotate-180': open }">
                                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                                    </svg>
                                </button>

                                <div x-cloak
                                     x-show="open"
                                     x-transition
                                     class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-black/10 bg-white p-2 shadow-xl dark:border-white/10 dark:bg-slate-950">
                                    <div class="max-h-64 space-y-1 overflow-y-auto pr-1">
                                        @foreach($farms as $availableFarm)
                                            @php $isCurrentFarm = $farm && $availableFarm->id === $farm->id; @endphp
                                            <form method="POST" action="{{ route('farms.switch') }}">
                                                @csrf
                                                <input type="hidden" name="farm_id" value="{{ $availableFarm->id }}">
                                                <button type="submit"
                                                        @disabled($isCurrentFarm)
                                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition {{ $isCurrentFarm ? 'bg-green-50 text-green-800 dark:bg-green-500/10 dark:text-green-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5' }}">
                                                    <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $isCurrentFarm ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300' }}">
                                                        {{ mb_strtoupper(mb_substr($availableFarm->name, 0, 1)) }}
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm font-black">{{ $availableFarm->name }}</span>
                                                        <span class="block truncate text-xs font-semibold opacity-70">{{ $availableFarm->location ?: 'Sin ubicación' }}</span>
                                                    </span>
                                                    @if($isCurrentFarm)
                                                        <span class="text-xs font-black">Activa</span>
                                                    @endif
                                                </button>
                                            </form>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(Route::has('farms.create'))
                            <a href="{{ route('farms.create') }}"
                               class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-green-500/25 bg-green-50 px-3 py-2 text-sm font-extrabold text-green-700 transition hover:bg-green-100 dark:border-green-400/25 dark:bg-green-500/10 dark:text-green-200 dark:hover:bg-green-500/15">
                                <span class="text-base leading-none">+</span>
                                Agregar finca
                            </a>
                        @endif
                    </div>
                </div>
                @endif

                @foreach($navGroups as $group => $items)
                    <div class="nav-section-title">
                        {{ $sectionNames[$group] ?? ucfirst($group) }}
                    </div>

                    <nav class="nav-list">
                        @foreach($items as $item)
                            @php
                                $childRoutes = collect($item['children'] ?? [])->pluck('route')->filter()->all();
                                $isActive = ($item['route'] ? (request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*')) : false)
                                    || collect($childRoutes)->contains(fn ($route) => request()->routeIs($route) || request()->routeIs($route . '.*'));
                                $href = '#';

                                if ($item['route'] && Route::has($item['route'])) {
                                    $href = route($item['route']);
                                }

                                $delay = ($loop->parent->index * 220) + ($loop->index * 55);
                            @endphp

                            <a href="{{ $href }}"
                               class="nav-item {{ $isActive ? 'active' : '' }} {{ $item['route'] ? '' : 'opacity-55 cursor-not-allowed' }}"
                               style="--nav-delay: {{ $delay }}ms;">
                                <span class="nav-icon-wrap">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                                        {!! $icons[$item['icon']] ?? '' !!}
                                    </svg>
                                </span>

                                <span class="flex-1">{{ $item['label'] }}</span>

                                @if($isActive)
                                    <span class="nav-dot"></span>
                                @endif
                            </a>

                            @if(! empty($item['children']) && $isActive)
                                <div class="nav-sublist">
                                    @foreach($item['children'] as $child)
                                        @php
                                            $childActive = $child['route'] ? (request()->routeIs($child['route']) || request()->routeIs($child['route'] . '.*')) : false;
                                            $childHref = ($child['route'] && Route::has($child['route'])) ? route($child['route']) : '#';
                                        @endphp

                                        <a href="{{ $childHref }}"
                                           class="nav-subitem {{ $childActive ? 'active' : '' }} {{ $child['route'] ? '' : 'opacity-55 cursor-not-allowed' }}">
                                            <span>{{ $child['label'] }}</span>
                                            @if($childActive)
                                                <span class="h-1.5 w-1.5 rounded-full bg-green-600 dark:bg-green-300"></span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </nav>
                @endforeach

                @if($supportWhatsappUrl)
                    <nav class="nav-list nav-support-list" aria-label="Soporte">
                        <a href="{{ $supportWhatsappUrl }}"
                           class="nav-item whatsapp-help-nav"
                           target="_blank"
                           rel="noopener noreferrer"
                           aria-label="Abrir ayuda por WhatsApp"
                           style="--nav-delay: {{ count($nav) * 55 }}ms;">
                            <span class="nav-icon-wrap">
                                <svg viewBox="0 0 32 32" fill="none" class="w-5 h-5" aria-hidden="true">
                                    <path fill="currentColor" d="M16 4.2c-6.42 0-11.64 5.08-11.64 11.34 0 2.2.65 4.25 1.78 5.99L4.7 27.8l6.5-1.55A11.9 11.9 0 0 0 16 26.88c6.42 0 11.64-5.08 11.64-11.34S22.42 4.2 16 4.2Zm0 20.55c-1.52 0-3.02-.37-4.34-1.07l-.43-.23-3.52.84.78-3.36-.28-.45a8.86 8.86 0 0 1-1.45-4.94c0-5.08 4.14-9.22 9.24-9.22s9.24 4.14 9.24 9.22-4.14 9.21-9.24 9.21Zm5.08-6.9c-.28-.14-1.64-.79-1.89-.88-.25-.1-.43-.14-.61.14-.18.27-.7.88-.86 1.06-.16.19-.32.2-.6.07-.28-.14-1.18-.42-2.25-1.35-.83-.72-1.39-1.61-1.56-1.89-.16-.27-.02-.42.12-.56.13-.13.28-.32.42-.48.14-.16.18-.27.28-.46.09-.18.04-.35-.02-.49-.07-.14-.61-1.44-.84-1.98-.22-.52-.45-.45-.61-.46h-.52c-.18 0-.49.07-.74.35-.25.27-.97.92-.97 2.25 0 1.32.99 2.6 1.13 2.78.14.18 1.95 2.9 4.72 4.07.66.27 1.17.44 1.57.56.66.2 1.26.17 1.74.1.53-.08 1.64-.65 1.87-1.28.23-.63.23-1.17.16-1.28-.07-.12-.25-.19-.53-.33Z"/>
                                </svg>
                            </span>

                            <span class="flex-1">{{ $supportWhatsappMessage }}</span>
                        </a>
                    </nav>
                @endif
            </div>
        </div>
    </aside>

    <div id="mobileDrawerBackdrop" class="mobile-drawer-backdrop"></div>

    <aside id="mobileDrawer" class="mobile-drawer">
        <div class="mobile-drawer-top">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 min-w-0">
                <x-brand-logo class="h-10 w-auto" />
            </a>

            <button id="mobileDrawerCloseBtn" type="button" class="mobile-drawer-close" aria-label="Cerrar menú">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/>
                </svg>
            </button>
        </div>

        <div class="mobile-drawer-scroll">
            @if($isAdminMode)
                <div class="farm-card">
                    <div class="font-bold text-[22px] text-gray-900 dark:text-white leading-tight">
                        Modo administrador
                    </div>

                    <div class="mt-2 text-sm text-gray-500 dark:text-gray-300">
                        Gestiona clientes, planes, pagos y configuración global de la plataforma.
                    </div>

                    <div class="mt-4 inline-flex items-center gap-2 rounded-full border border-green-500/20 bg-green-50 px-3 py-1.5 text-xs font-black text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        SaaS activo
                    </div>

                    <div class="mt-4 grid gap-2">
                        <a href="{{ route('admin.saas.index') }}"
                           class="inline-flex w-full items-center justify-center rounded-xl bg-green-600 px-3 py-2 text-sm font-extrabold text-white transition hover:bg-green-700">
                            Centro SaaS
                        </a>
                        <a href="{{ route('admin.users.index') }}"
                           class="inline-flex w-full items-center justify-center rounded-xl border border-green-500/25 bg-green-50 px-3 py-2 text-sm font-extrabold text-green-700 transition hover:bg-green-100 dark:border-green-400/25 dark:bg-green-500/10 dark:text-green-200">
                            Ver clientes
                        </a>
                    </div>
                </div>
            @else
            <div class="farm-card">
                <div class="font-bold text-[22px] text-gray-900 dark:text-white leading-tight">
                    {{ $farm?->name ?? 'Tu finca' }}
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                    {{ $farm?->location ?? 'Ubicación no definida' }}
                </div>

                <div class="mt-4 inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-full border border-black/5 dark:border-white/8 bg-white/50 dark:bg-white/5 text-gray-700 dark:text-gray-300">
                    <span class="w-2 h-2 rounded-full bg-green-500"></span>
                    En operación
                </div>

                <div class="mt-4 space-y-2">
                    @if($farms->count() > 1 && Route::has('farms.switch'))
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button"
                                    @click="open = !open"
                                    class="flex w-full items-center gap-3 rounded-2xl border border-black/10 bg-white/80 px-3 py-3 text-left shadow-sm transition hover:border-green-400 hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:hover:border-green-400/40">
                                <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-200">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 10.5 12 4l8 6.5V20H4v-9.5Z"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 20v-6h6v6"/>
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[11px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Finca activa</span>
                                    <span class="block truncate text-sm font-black text-gray-900 dark:text-white">{{ $farm?->name ?? 'Tu finca' }}</span>
                                </span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4 text-gray-400 transition" :class="{ 'rotate-180': open }">
                                    <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                                </svg>
                            </button>

                            <div x-cloak
                                 x-show="open"
                                 x-transition
                                 class="mt-2 overflow-hidden rounded-2xl border border-black/10 bg-white p-2 shadow-xl dark:border-white/10 dark:bg-slate-950">
                                <div class="max-h-64 space-y-1 overflow-y-auto pr-1">
                                    @foreach($farms as $availableFarm)
                                        @php $isCurrentFarm = $farm && $availableFarm->id === $farm->id; @endphp
                                        <form method="POST" action="{{ route('farms.switch') }}">
                                            @csrf
                                            <input type="hidden" name="farm_id" value="{{ $availableFarm->id }}">
                                            <button type="submit"
                                                    @disabled($isCurrentFarm)
                                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition {{ $isCurrentFarm ? 'bg-green-50 text-green-800 dark:bg-green-500/10 dark:text-green-100' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5' }}">
                                                <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $isCurrentFarm ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300' }}">
                                                    {{ mb_strtoupper(mb_substr($availableFarm->name, 0, 1)) }}
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-black">{{ $availableFarm->name }}</span>
                                                    <span class="block truncate text-xs font-semibold opacity-70">{{ $availableFarm->location ?: 'Sin ubicación' }}</span>
                                                </span>
                                                @if($isCurrentFarm)
                                                    <span class="text-xs font-black">Activa</span>
                                                @endif
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(Route::has('farms.create'))
                        <a href="{{ route('farms.create') }}"
                           class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-green-500/25 bg-green-50 px-3 py-2 text-sm font-extrabold text-green-700 transition hover:bg-green-100 dark:border-green-400/25 dark:bg-green-500/10 dark:text-green-200 dark:hover:bg-green-500/15">
                            <span class="text-base leading-none">+</span>
                            Agregar finca
                        </a>
                    @endif
                </div>
            </div>
            @endif

            @foreach($navGroups as $group => $items)
                <div class="nav-section-title">
                    {{ $sectionNames[$group] ?? ucfirst($group) }}
                </div>

                <nav class="nav-list">
                    @foreach($items as $item)
                        @php
                            $childRoutes = collect($item['children'] ?? [])->pluck('route')->filter()->all();
                            $isActive = ($item['route'] ? (request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*')) : false)
                                || collect($childRoutes)->contains(fn ($route) => request()->routeIs($route) || request()->routeIs($route . '.*'));
                            $href = '#';

                            if ($item['route'] && Route::has($item['route'])) {
                                $href = route($item['route']);
                            }

                            $delay = ($loop->parent->index * 220) + ($loop->index * 55);
                        @endphp

                        <a href="{{ $href }}"
                           class="nav-item {{ $isActive ? 'active' : '' }} {{ $item['route'] ? '' : 'opacity-55 cursor-not-allowed' }}"
                           style="--nav-delay: {{ $delay }}ms;">
                            <span class="nav-icon-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                                    {!! $icons[$item['icon']] ?? '' !!}
                                </svg>
                            </span>

                            <span class="flex-1">{{ $item['label'] }}</span>

                            @if($isActive)
                                <span class="nav-dot"></span>
                            @endif
                        </a>

                        @if(! empty($item['children']) && $isActive)
                            <div class="nav-sublist">
                                @foreach($item['children'] as $child)
                                    @php
                                        $childActive = $child['route'] ? (request()->routeIs($child['route']) || request()->routeIs($child['route'] . '.*')) : false;
                                        $childHref = ($child['route'] && Route::has($child['route'])) ? route($child['route']) : '#';
                                    @endphp

                                    <a href="{{ $childHref }}"
                                       class="nav-subitem {{ $childActive ? 'active' : '' }} {{ $child['route'] ? '' : 'opacity-55 cursor-not-allowed' }}">
                                        <span>{{ $child['label'] }}</span>
                                        @if($childActive)
                                            <span class="h-1.5 w-1.5 rounded-full bg-green-600 dark:bg-green-300"></span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </nav>
            @endforeach

            @if($supportWhatsappUrl)
                <nav class="nav-list nav-support-list" aria-label="Soporte">
                    <a href="{{ $supportWhatsappUrl }}"
                       class="nav-item whatsapp-help-nav"
                       target="_blank"
                       rel="noopener noreferrer"
                       aria-label="Abrir ayuda por WhatsApp"
                       style="--nav-delay: {{ count($nav) * 55 }}ms;">
                        <span class="nav-icon-wrap">
                            <svg viewBox="0 0 32 32" fill="none" class="w-5 h-5" aria-hidden="true">
                                <path fill="currentColor" d="M16 4.2c-6.42 0-11.64 5.08-11.64 11.34 0 2.2.65 4.25 1.78 5.99L4.7 27.8l6.5-1.55A11.9 11.9 0 0 0 16 26.88c6.42 0 11.64-5.08 11.64-11.34S22.42 4.2 16 4.2Zm0 20.55c-1.52 0-3.02-.37-4.34-1.07l-.43-.23-3.52.84.78-3.36-.28-.45a8.86 8.86 0 0 1-1.45-4.94c0-5.08 4.14-9.22 9.24-9.22s9.24 4.14 9.24 9.22-4.14 9.21-9.24 9.21Zm5.08-6.9c-.28-.14-1.64-.79-1.89-.88-.25-.1-.43-.14-.61.14-.18.27-.7.88-.86 1.06-.16.19-.32.2-.6.07-.28-.14-1.18-.42-2.25-1.35-.83-.72-1.39-1.61-1.56-1.89-.16-.27-.02-.42.12-.56.13-.13.28-.32.42-.48.14-.16.18-.27.28-.46.09-.18.04-.35-.02-.49-.07-.14-.61-1.44-.84-1.98-.22-.52-.45-.45-.61-.46h-.52c-.18 0-.49.07-.74.35-.25.27-.97.92-.97 2.25 0 1.32.99 2.6 1.13 2.78.14.18 1.95 2.9 4.72 4.07.66.27 1.17.44 1.57.56.66.2 1.26.17 1.74.1.53-.08 1.64-.65 1.87-1.28.23-.63.23-1.17.16-1.28-.07-.12-.25-.19-.53-.33Z"/>
                            </svg>
                        </span>

                        <span class="flex-1">{{ $supportWhatsappMessage }}</span>
                    </a>
                </nav>
            @endif
        </div>
    </aside>

    <style>
        .topbar-farm-shell{position:relative;min-width:0;flex-shrink:1;display:flex;}
        .topbar-farm-badge{display:inline-flex;align-items:center;gap:8px;max-width:100%;min-width:0;padding:8px 12px;border-radius:999px;background:var(--fb);color:var(--fc);border:0;font-weight:900;font-size:13px;line-height:1;box-shadow:0 1px 2px rgba(0,0,0,.06);cursor:default;}
        .topbar-farm-badge.is-switcher{cursor:pointer;}
        .topbar-farm-dot{width:9px;height:9px;border-radius:50%;background:var(--fc);flex:none;}
        .topbar-farm-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;letter-spacing:.2px;min-width:0;}
        .topbar-farm-chevron{width:14px;height:14px;opacity:.7;flex:none;transition:transform .18s ease;}
        .topbar-farm-menu{position:absolute;left:0;top:calc(100% + 8px);z-index:70;width:min(280px,80vw);background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;box-shadow:0 18px 45px rgba(0,0,0,.16);padding:6px;}
        .dark .topbar-farm-menu{background:#0b1220;border-color:rgba(255,255,255,.08);}
        .topbar-farm-menu-scroll{max-height:60vh;overflow-y:auto;display:flex;flex-direction:column;gap:2px;}
        .topbar-farm-option{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:9px 10px;border-radius:12px;transition:background .15s ease;}
        .topbar-farm-option:hover{background:rgba(0,0,0,.04);}
        .dark .topbar-farm-option:hover{background:rgba(255,255,255,.06);}
        .topbar-farm-option.is-current{background:rgba(22,101,52,.08);}
        .dark .topbar-farm-option.is-current{background:rgba(34,197,94,.12);}
        .topbar-farm-option-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;flex:none;border-radius:9px;background:#f1f5f9;color:#334155;font-weight:900;font-size:13px;}
        .dark .topbar-farm-option-icon{background:rgba(255,255,255,.08);color:#e2e8f0;}
        .topbar-farm-option.is-current .topbar-farm-option-icon{background:#16a34a;color:#fff;}
        .topbar-farm-option-text{min-width:0;flex:1;}
        .topbar-farm-option-name{display:block;font-weight:800;font-size:13px;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .dark .topbar-farm-option-name{color:#f1f5f9;}
        .topbar-farm-option-sub{display:block;font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .topbar-farm-option-check{color:#16a34a;font-weight:900;flex:none;}
        .dark .topbar-farm-badge{filter:saturate(1.08);}
        @media (max-width:640px){
            .mobile-brand{display:none !important;}
            .topbar-left{flex:1 1 auto;min-width:0;}
            .topbar-farm-shell{flex:1 1 auto;min-width:0;}
            .topbar-farm-badge{gap:6px;padding:8px 12px;}
            .topbar-farm-name{max-width:none;font-size:13px;}
            .topbar-farm-chevron,.topbar-farm-dot{display:none;}
            .topbar-dark-btn{order:6;}
            .profile-menu-shell{order:7;}
            .toast-notification{bottom:calc(92px + env(safe-area-inset-bottom)) !important;}
        }
    </style>
    <header class="topbar-shell">
        <div class="topbar-card">
            <div class="topbar-left">
                <button id="mobileMenuBtn" type="button" class="topbar-btn mobile-menu-btn" aria-label="Abrir menú">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <a href="{{ route('dashboard') }}" class="mobile-brand">
                    <x-brand-logo />
                </a>

                @php
                    $ifFarmPalette = [['#166534','#dcfce7'],['#1e40af','#dbeafe'],['#9a3412','#ffedd5'],['#6b21a8','#f3e8ff'],['#0f766e','#ccfbf1'],['#b91c1c','#fee2e2'],['#a16207','#fef9c3'],['#be185d','#fce7f3']];
                    $ifFarmColor = $farm ? $ifFarmPalette[$farm->id % count($ifFarmPalette)] : ['#334155','#e2e8f0'];
                    $ifCanSwitch = $farm && $farms->count() > 1 && Route::has('farms.switch');
                @endphp
                @if($farm)
                    <div class="topbar-farm-shell" @if($ifCanSwitch) x-data="{ farmOpen: false }" @click.outside="farmOpen = false" @endif>
                        <button type="button" class="topbar-farm-badge {{ $ifCanSwitch ? 'is-switcher' : '' }}" style="--fc:{{ $ifFarmColor[0] }};--fb:{{ $ifFarmColor[1] }};"
                                @if($ifCanSwitch) @click="farmOpen = !farmOpen" title="Cambiar de finca" :aria-expanded="farmOpen" @endif
                                aria-label="Finca actual: {{ $farm->name }}">
                            <span class="topbar-farm-dot"></span>
                            <span class="topbar-farm-name">{{ $farm->name }}</span>
                            @if($ifCanSwitch)
                                <svg class="topbar-farm-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" :class="{ 'rotate-180': farmOpen }"><path stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                            @endif
                        </button>
                        @if($ifCanSwitch)
                            <div x-cloak x-show="farmOpen" x-transition class="topbar-farm-menu">
                                <div class="topbar-farm-menu-scroll">
                                    @foreach($farms as $availableFarm)
                                        @php $ifIsCurrent = $farm && $availableFarm->id === $farm->id; @endphp
                                        <form method="POST" action="{{ route('farms.switch') }}">
                                            @csrf
                                            <input type="hidden" name="farm_id" value="{{ $availableFarm->id }}">
                                            <button type="submit" @disabled($ifIsCurrent) class="topbar-farm-option {{ $ifIsCurrent ? 'is-current' : '' }}">
                                                <span class="topbar-farm-option-icon">{{ mb_strtoupper(mb_substr($availableFarm->name, 0, 1)) }}</span>
                                                <span class="topbar-farm-option-text">
                                                    <span class="topbar-farm-option-name">{{ $availableFarm->name }}</span>
                                                    <span class="topbar-farm-option-sub">{{ $availableFarm->location ?: 'Sin ubicación' }}</span>
                                                </span>
                                                @if($ifIsCurrent)<span class="topbar-farm-option-check">&check;</span>@endif
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2 md:gap-3">
                <div id="connectionStatusPill" class="status-pill">
                    <span class="status-dot"></span>
                    <span id="connectionStatusText">En línea</span>
                </div>

                <button type="button"
                        @click="dark = !dark"
                        class="topbar-btn topbar-dark-btn"
                        aria-label="Cambiar tema">
                    <svg x-show="!dark" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.5M12 18.5V21M4.93 4.93l1.77 1.77M17.3 17.3l1.77 1.77M3 12h2.5M18.5 12H21M4.93 19.07l1.77-1.77M17.3 6.7l1.77-1.77M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/>
                    </svg>

                    <svg x-show="dark" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
                    </svg>
                </button>

                <div class="notif-shell">
                    <button id="notifToggleBtn" type="button" class="topbar-btn" aria-label="Notificaciones">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9"/>
                        </svg>

                        <span id="notifCountBadge"
                              data-count="{{ $unreadNotificationCount }}"
                              class="notif-badge {{ $unreadNotificationCount > 0 ? '' : 'hidden' }}">
                            {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                        </span>
                    </button>

                    <div id="notifPanel" class="notif-panel">
                        <div class="notif-header">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Notificaciones</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Alertas de finca, facturación y actividad importante
                                    </p>
                                </div>

                                <button id="closeNotificationsBtn" type="button" class="notif-close-btn" aria-label="Cerrar notificaciones">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        @if($notifications->count())
                            <div class="notif-list">
                                @foreach($notifications as $notification)
                                    @php
                                        $animalName = $notification->meta['animal_name'] ?? null;
                                        $typeLabel = $notification->meta['type_label'] ?? 'Evento';
                                        $notificationUrl = route('events.index');

                                        $notificationReadUrl = Route::has('notifications.read')
                                            ? route('notifications.read', $notification)
                                            : '';
                                        $notificationDismissUrl = Route::has('notifications.dismiss')
                                            ? route('notifications.dismiss', $notification)
                                            : '';

                                        if ($notification->source_type === 'admin_payment_received' && Route::has('admin.saas.billing.payments')) {
                                            $notificationUrl = route('admin.saas.billing.payments');
                                        } elseif ($notification->source_type === 'subscription_invoice' && !empty($notification->meta['invoice_id'])) {
                                            $notificationUrl = route('client.billing.invoices.show', $notification->meta['invoice_id']);
                                        } elseif ($notification->source_type === 'admin_broadcast' && Route::has('dashboard')) {
                                            $notificationUrl = route('dashboard');
                                        } elseif ($notification->source_type === 'daily_production_reminder' && Route::has('production.index')) {
                                            $notificationUrl = route('production.index');
                                        } elseif ($notification->event_id) {
                                            $notificationUrl = route('events.index', ['event' => $notification->event_id]);
                                        } elseif (!empty($notification->source_key) && $notification->source_type === 'automatic_event') {
                                            $notificationUrl = route('events.index', [
                                                'event' => $notification->source_key,
                                                'focus_date' => $notification->event_date?->format('Y-m-d'),
                                                'automatic' => 1,
                                            ]);
                                        }
                                    @endphp

                                    <div class="notif-item notification-row {{ $notification->read_at ? 'opacity-70' : '' }}">
                                        <button type="button"
                                                class="notif-dismiss-btn"
                                                data-dismiss-url="{{ $notificationDismissUrl }}"
                                                aria-label="Eliminar notificación">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                                                <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/>
                                            </svg>
                                        </button>

                                        <a href="{{ $notificationUrl }}"
                                           data-read-url="{{ $notificationReadUrl }}"
                                           draggable="false"
                                           class="notification-link block">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                                    <span class="notif-pill {{ ($notification->meta['automatic'] ?? false) ? 'auto' : 'manual' }}">
                                                        {{ ($notification->meta['automatic'] ?? false) ? 'Automática' : 'Manual' }}
                                                    </span>

                                                    <span class="notif-level {{ $notification->level }}">
                                                        {{ match($notification->level) {
                                                            'high' => 'Alta',
                                                            'medium' => 'Media',
                                                            default => 'Baja',
                                                        } }}
                                                    </span>
                                                </div>

                                                <div class="font-bold text-sm text-gray-900 dark:text-white leading-5">
                                                    {{ $notification->title }}
                                                </div>

                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-2 space-y-1">
                                                    <div>{{ $notification->message }}</div>

                                                    <div>
                                                        Fecha:
                                                        {{ $notification->event_date ? $notification->event_date->format('d/m/Y') : '—' }}
                                                    </div>

                                                    <div>
                                                        Tipo:
                                                        {{ $typeLabel }}
                                                    </div>

                                                    @if($animalName)
                                                        <div>Animal: {{ $animalName }}</div>
                                                    @endif

                                                    @if($notification->lot_name)
                                                        <div>Lote: {{ $notification->lot_name }}</div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if(! $notification->read_at)
                                                <span class="notification-unread-dot mt-1 w-2.5 h-2.5 rounded-full bg-brand flex-shrink-0"></span>
                                            @endif
                                        </div>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="notif-empty">
                                No tienes alertas internas en este momento.
                            </div>
                        @endif

                        @if($notificationsMarkAllUrl)
                            <div class="notif-actions">
                                <button id="markAllNotificationsBtn"
                                        type="button"
                                        class="inline-flex rounded-xl border border-black/10 dark:border-white/8 bg-white/80 dark:bg-white/5 px-3 py-2.5 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-white dark:hover:bg-white/10 transition">
                                    Cerrar todas
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="profile-menu-shell">
                    <button id="profileMenuBtn" type="button" class="profile-btn">
                        <span class="profile-avatar">{{ $initials !== '' ? $initials : 'U' }}</span>

                        <div class="hidden md:block text-left min-w-0">
                            <div class="text-[13px] font-extrabold text-gray-900 dark:text-white leading-tight truncate">
                                {{ $displayName }}
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400 leading-tight truncate">
                                {{ $farm?->name ?? 'InterFarm' }}
                            </div>
                        </div>

                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-4 h-4 text-gray-500 dark:text-gray-400">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>

                    <div id="profileMenu" class="profile-menu">
                        <div class="profile-menu-header">
                            <div class="text-sm font-extrabold text-gray-900 dark:text-white truncate">
                                {{ $displayName }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-1">
                                {{ $farm?->name ?? 'InterFarm' }}
                            </div>
                        </div>

                        <div class="profile-menu-list">
                            @if(Route::has('profile.edit') && ! ($user?->isSuspended()))
                                <a href="{{ route('profile.edit') }}" class="profile-menu-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="profile-menu-icon">
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0"/>
                                    </svg>
                                    <span>Mi perfil</span>
                                </a>
                            @endif

                            @if(Route::has('settings.index') && ! ($user?->isSuspended()))
                                <a href="{{ route('settings.index') }}" class="profile-menu-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="profile-menu-icon">
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 8.5A3.5 3.5 0 1 0 12 15.5A3.5 3.5 0 1 0 12 8.5z"/>
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.04-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.4a1.7 1.7 0 0 0-.34-1.87l-.06-.06A2 2 0 1 1 7.08 3.64l.06.06A1.7 1.7 0 0 0 9 4.04a1.7 1.7 0 0 0 1.04-1.56V2.4a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.08 4a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.44 8.4A1.7 1.7 0 0 0 21 9.44H21.1a2 2 0 1 1 0 4H21a1.7 1.7 0 0 0-1.56 1.04z"/>
                                    </svg>
                                    <span>Configuración</span>
                                </a>
                            @endif

                            <div class="profile-menu-divider"></div>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="profile-menu-item profile-menu-item-danger">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="profile-menu-icon">
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M15 17l5-5-5-5"/>
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M20 12H9"/>
                                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/>
                                    </svg>
                                    <span>Cerrar sesión</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="content-shell">
        <div class="content-inner">
            <div data-reveal>
                @yield('content')
            </div>
        </div>
    </main>

    @if(! $isAdminMode && ! ($user?->isSuspended()))
        <div id="mobileQuickMenu" class="mobile-quick-menu" aria-hidden="true">
            @if(Route::has('animals.create'))
                <a href="{{ route('animals.create') }}" class="mobile-quick-link">
                    <span class="mobile-quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                        </svg>
                    </span>
                    <span>Nuevo animal</span>
                </a>
            @endif

            @if(Route::has('production.index'))
                <a href="{{ route('production.index') }}" class="mobile-quick-link">
                    <span class="mobile-quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M7 4h10v4l-2 3v7a3 3 0 0 1-6 0v-7L7 8V4Z"/>
                        </svg>
                    </span>
                    <span>Agregar producción</span>
                </a>
            @endif

            @if(Route::has('lots.create'))
                <a href="{{ route('lots.create') }}" class="mobile-quick-link">
                    <span class="mobile-quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 17h16M7 4v16M17 4v16"/>
                        </svg>
                    </span>
                    <span>Nuevo lote</span>
                </a>
            @endif

            @if(Route::has('events.index'))
                <a href="{{ route('events.index') }}" class="mobile-quick-link">
                    <span class="mobile-quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/>
                        </svg>
                    </span>
                    <span>Nuevo evento</span>
                </a>
            @endif

            @if(Route::has('finances.index'))
                <a href="{{ route('finances.index') }}" class="mobile-quick-link">
                    <span class="mobile-quick-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M17 7.5c0-1.7-1.7-3-4.1-3H10a3 3 0 0 0 0 6h4a3 3 0 0 1 0 6h-3.2c-2.3 0-4.1-1.3-4.1-3"/>
                        </svg>
                    </span>
                    <span>Gasto / ingreso</span>
                </a>
            @endif
        </div>

        <div class="mobile-app-nav-wrap">
            <nav class="mobile-app-nav" aria-label="Navegación rápida móvil">
                @if(Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}" class="mobile-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 10.5 12 4l8 6.5V20H4v-9.5Z"/>
                        </svg>
                        <span>Panel</span>
                    </a>
                @endif

                @if(Route::has('animals.index'))
                    <a href="{{ route('animals.index') }}" class="mobile-nav-item {{ request()->routeIs('animals.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M5 13c1.7-4.2 5.5-6 10.4-4.8 2.6.6 4.1 2.3 4.6 4.8M7 13v5M17 13v5M9 18h6M8 9 6 6M16 8l2-2"/>
                        </svg>
                        <span>Animales</span>
                    </a>
                @endif

                <button id="mobileQuickAddBtn" type="button" class="mobile-nav-plus" aria-label="Acciones rápidas" aria-expanded="false" aria-controls="mobileQuickMenu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
                    </svg>
                </button>

                @if(Route::has('events.index'))
                    <a href="{{ route('events.index') }}" class="mobile-nav-item {{ request()->routeIs('events.*') ? 'active' : '' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/>
                        </svg>
                        <span>Eventos</span>
                    </a>
                @endif

                <button id="mobileNotifBtn" type="button" class="mobile-nav-action" aria-label="Notificaciones">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0m6 0H9"/>
                    </svg>
                    <span>Notificaciones</span>
                    <span id="mobileNotifCountBadge"
                          data-count="{{ $unreadNotificationCount }}"
                          class="mobile-nav-badge {{ $unreadNotificationCount > 0 ? '' : 'hidden' }}">
                        {{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}
                    </span>
                </button>
            </nav>
        </div>
    @endif

    <script src="/assets/interfarm-offline.js?v=22" defer></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pwaDisplayModes = [
                '(display-mode: standalone)',
                '(display-mode: fullscreen)',
                '(display-mode: minimal-ui)',
                '(display-mode: window-controls-overlay)',
            ];
            const launchSource = new URLSearchParams(window.location.search).get('source') || '';
            if (launchSource.startsWith('pwa')) {
                localStorage.setItem('interfarm_pwa_launch', '1');
            }

            const isStandalonePwa = () => pwaDisplayModes.some((query) => window.matchMedia(query).matches)
                || window.navigator.standalone === true
                || localStorage.getItem('interfarm_pwa_launch') === '1';
            const applyPwaMode = () => {
                document.body.classList.toggle('pwa-standalone', isStandalonePwa());
            };

            applyPwaMode();
            window.addEventListener('load', applyPwaMode);
            pwaDisplayModes.forEach((query) => {
                const media = window.matchMedia(query);
                media.addEventListener?.('change', applyPwaMode);
            });
            document.body.classList.toggle('dark', document.documentElement.classList.contains('dark'));

            const themeColorMeta = document.querySelector('meta[name="theme-color"]:not([media])')
                || document.querySelector('meta[name="theme-color"]');
            const appleStatusMeta = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');

            const applySystemBars = (isDark) => {
                if (themeColorMeta) {
                    themeColorMeta.setAttribute('content', isDark ? '#020617' : '#f6f8fb');
                }

                if (appleStatusMeta) {
                    appleStatusMeta.setAttribute('content', 'black-translucent');
                }
            };

            applySystemBars(document.documentElement.classList.contains('dark'));

            window.addEventListener('interfarm-theme-change', (event) => {
                const isDark = Boolean(event.detail?.dark);

                document.body.classList.toggle('dark', isDark);
                applySystemBars(isDark);
            });

            const appPreloader = document.getElementById('appPreloader');
            const appPreloaderText = document.getElementById('appPreloaderText');
            const shouldRefreshFinanceCache = document.body.dataset.financeCacheRefresh === '1';
            const currentFarmId = document.body.dataset.currentFarmId || '';

            const clearStaleModuleCaches = async () => {
                if (!('caches' in window)) return;

                const staleUrls = [
                    '/lotes',
                    `${window.location.origin}/lotes`,
                    '/lotes/crear',
                    `${window.location.origin}/lotes/crear`,
                    '/assets/interfarm-offline.js?v=20',
                    `${window.location.origin}/assets/interfarm-offline.js?v=20`,
                    '/assets/interfarm-offline.js?v=21',
                    `${window.location.origin}/assets/interfarm-offline.js?v=21`,
                ];

                try {
                    const cacheNames = await caches.keys();

                    await Promise.all(cacheNames.map(async (cacheName) => {
                        const cache = await caches.open(cacheName);
                        await Promise.all(staleUrls.map((url) => cache.delete(url)));
                        const requests = await cache.keys();
                        await Promise.all(requests.map((request) => {
                            const url = new URL(request.url);

                            if (url.pathname.startsWith('/lotes/buscar-ubicacion')) {
                                return cache.delete(request);
                            }

                            return Promise.resolve(false);
                        }));
                    }));
                } catch (error) {
                    console.error('No se pudo limpiar la caché de lotes:', error);
                }
            };

            const clearFarmScopedCachesWhenFarmChanges = async () => {
                if (!currentFarmId || !('caches' in window)) return;

                const storageKey = 'interfarm.currentFarmId';
                const previousFarmId = window.localStorage.getItem(storageKey);

                window.localStorage.setItem(storageKey, currentFarmId);

                if (!previousFarmId || previousFarmId === currentFarmId) return;

                const farmScopedUrls = [
                    '/dashboard',
                    `${window.location.origin}/dashboard`,
                    '/finanzas',
                    `${window.location.origin}/finanzas`,
                    '/lotes',
                    `${window.location.origin}/lotes`,
                    '/lotes/crear',
                    `${window.location.origin}/lotes/crear`,
                    '/produccion',
                    `${window.location.origin}/produccion`,
                    '/reportes',
                    `${window.location.origin}/reportes`,
                    '/',
                    `${window.location.origin}/`,
                ];

                try {
                    const cacheNames = await caches.keys();

                    await Promise.all(cacheNames.map(async (cacheName) => {
                        const cache = await caches.open(cacheName);
                        await Promise.all(farmScopedUrls.map((url) => cache.delete(url)));
                    }));
                } catch (error) {
                    console.error('No se pudo limpiar la caché de finca:', error);
                }
            };

            const refreshDashboardCacheAfterFinanceChange = async () => {
                if (!shouldRefreshFinanceCache || !('caches' in window)) return;

                const dashboardUrls = [
                    '/dashboard',
                    `${window.location.origin}/dashboard`,
                    '/',
                    `${window.location.origin}/`,
                ];

                try {
                    const cacheNames = await caches.keys();

                    await Promise.all(cacheNames.map(async (cacheName) => {
                        const cache = await caches.open(cacheName);
                        await Promise.all(dashboardUrls.map((url) => cache.delete(url)));
                    }));

                    if (navigator.onLine) {
                        const response = await fetch('/dashboard', {
                            credentials: 'same-origin',
                            cache: 'reload',
                        });

                        if (response.ok) {
                            const runtimeCacheName = cacheNames.find((name) => name.includes('-runtime')) || 'interfarm-v22-runtime';
                            const cache = await caches.open(runtimeCacheName);
                            await cache.put('/dashboard', response.clone());
                        }
                    }
                } catch (error) {
                    console.error('No se pudo refrescar el panel financiero:', error);
                }
            };

            clearStaleModuleCaches();
            clearFarmScopedCachesWhenFarmChanges();
            refreshDashboardCacheAfterFinanceChange();

            const hidePreloader = () => {
                if (!appPreloader) return;

                appPreloader.classList.add('is-hidden');

                setTimeout(() => {
                    appPreloader.remove();
                }, 360);
            };

            if (appPreloaderText && !navigator.onLine) {
                appPreloaderText.textContent = 'Cargando datos guardados...';
            }

            window.addEventListener('load', () => {
                setTimeout(hidePreloader, 260);
            });

            setTimeout(hidePreloader, 1800);

            const items = document.querySelectorAll('[data-reveal]');

            items.forEach((el, i) => {
                el.classList.add('reveal');
                el.style.setProperty('--d', `${i * 60}ms`);
            });

            requestAnimationFrame(() => items.forEach(el => el.classList.add('is-in')));

            const setupTabScrollHints = () => {
                const tabBars = document.querySelectorAll('.animals-tabs, .billing-tabs, div:has(> .settings-tab)');

                tabBars.forEach((bar) => {
                    if (bar.dataset.scrollHintReady === 'true') return;

                    bar.dataset.scrollHintReady = 'true';

                    const hint = document.createElement('span');
                    hint.className = 'tab-scroll-hint';
                    hint.setAttribute('aria-hidden', 'true');
                    bar.appendChild(hint);

                    const updateHint = () => {
                        const hasOverflow = bar.scrollWidth > bar.clientWidth + 4;
                        const isAtEnd = bar.scrollLeft + bar.clientWidth >= bar.scrollWidth - 8;
                        hint.classList.toggle('is-visible', hasOverflow && !isAtEnd);
                    };

                    bar.addEventListener('scroll', updateHint, { passive: true });
                    window.addEventListener('resize', updateHint);
                    requestAnimationFrame(updateHint);
                    setTimeout(updateHint, 500);
                });
            };

            setupTabScrollHints();

            document.querySelectorAll('.select-search').forEach((select) => {
                if (!select.tomselect) {
                    new TomSelect(select, {
                        create: false,
                        sortField: {
                            field: 'text',
                            direction: 'asc'
                        },
                        placeholder: 'Buscar...',
                        dropdownParent: 'body'
                    });
                }
            });

            document.querySelectorAll('form[data-auto-filter]').forEach((form) => {
                let autoFilterTimer = null;

                const submitFilter = (delay = 300) => {
                    clearTimeout(autoFilterTimer);

                    autoFilterTimer = setTimeout(() => {
                        if (form.dataset.filterSubmitting === 'true') return;

                        if (form.hasAttribute('data-ajax-filter')) {
                            form.dispatchEvent(new CustomEvent('auto-filter:submit', {
                                bubbles: true,
                                cancelable: true,
                            }));
                            return;
                        }

                        form.dataset.filterSubmitting = 'true';

                        HTMLFormElement.prototype.submit.call(form);
                    }, delay);
                };

                form.querySelectorAll('[data-live-search]').forEach((input) => {
                    input.addEventListener('input', () => {
                        const value = input.value.trim();

                        if (value.length === 0 || value.length >= 2) {
                            submitFilter(450);
                        }
                    });

                    input.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            submitFilter(0);
                        }
                    });
                });

                form.querySelectorAll('select, input[type="date"]').forEach((field) => {
                    field.addEventListener('change', () => {
                        if (field.name === 'range' && field.value === 'custom') {
                            return;
                        }

                        submitFilter(120);
                    });
                });
            });

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const notifBtn = document.getElementById('notifToggleBtn');
            const mobileNotifBtn = document.getElementById('mobileNotifBtn');
            const notifPanel = document.getElementById('notifPanel');
            const notifCountBadge = document.getElementById('notifCountBadge');
            const mobileNotifCountBadge = document.getElementById('mobileNotifCountBadge');
            const markAllBtn = document.getElementById('markAllNotificationsBtn');
            const closeNotificationsBtn = document.getElementById('closeNotificationsBtn');
            const notificationsSyncUrl = document.body.dataset.notificationsSyncUrl || '';
            const notificationsMarkAllUrl = document.body.dataset.notificationsMarkAllUrl || '';
            const notificationsPushSubscribeUrl = document.body.dataset.notificationsPushSubscribeUrl || '';
            const webPushPublicKey = document.body.dataset.webPushPublicKey || '';
            const mobileQuickAddBtn = document.getElementById('mobileQuickAddBtn');
            const mobileQuickMenu = document.getElementById('mobileQuickMenu');

            const profileBtn = document.getElementById('profileMenuBtn');
            const profileMenu = document.getElementById('profileMenu');

            const statusPill = document.getElementById('connectionStatusPill');
            const statusText = document.getElementById('connectionStatusText');

            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mobileDrawer = document.getElementById('mobileDrawer');
            const mobileDrawerBackdrop = document.getElementById('mobileDrawerBackdrop');
            const mobileDrawerCloseBtn = document.getElementById('mobileDrawerCloseBtn');

            const mountNotificationPanelForPwa = () => {
                if (!notifPanel || !isStandalonePwa || window.innerWidth > 640) return;

                if (notifPanel.parentElement !== document.body) {
                    document.body.appendChild(notifPanel);
                }
            };

            mountNotificationPanelForPwa();

            let lastCount = parseInt(notifCountBadge?.dataset.count || '0', 10) || 0;

            const urlBase64ToUint8Array = (base64String) => {
                const padding = '='.repeat((4 - base64String.length % 4) % 4);
                const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                const rawData = window.atob(base64);
                const outputArray = new Uint8Array(rawData.length);

                for (let i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }

                return outputArray;
            };

            const updateNativeAppBadge = async (count) => {
                try {
                    if ('setAppBadge' in navigator && count > 0) {
                        await navigator.setAppBadge(count);
                    } else if ('clearAppBadge' in navigator) {
                        await navigator.clearAppBadge();
                    }
                } catch (error) {
                    console.debug('Badge nativo no disponible:', error);
                }
            };

            const showSystemNotification = async (title, options = {}) => {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;

                try {
                    const registration = await navigator.serviceWorker?.ready;

                    if (registration?.showNotification) {
                        await registration.showNotification(title, {
                            badge: '/images/pwa-icon-192.png?v=3',
                            icon: '/images/pwa-icon-192.png?v=3',
                            tag: 'interfarm-notifications',
                            renotify: true,
                            ...options,
                        });
                    }
                } catch (error) {
                    console.debug('No se pudo mostrar notificación del sistema:', error);
                }
            };

            const ensureNotificationPermission = async () => {
                if (!isStandalonePwa || !('Notification' in window)) {
                    return false;
                }

                if (Notification.permission === 'granted') {
                    return true;
                }

                if (Notification.permission === 'default') {
                    const permission = await Notification.requestPermission();

                    return permission === 'granted';
                }

                return false;
            };

            const ensurePushSubscription = async () => {
                if (!isStandalonePwa
                    || !notificationsPushSubscribeUrl
                    || !webPushPublicKey
                    || !('Notification' in window)
                    || !('serviceWorker' in navigator)
                    || !('PushManager' in window)) {
                    return;
                }

                try {
                    const hasPermission = await ensureNotificationPermission();

                    if (!hasPermission) {
                        return;
                    }

                    const registration = await navigator.serviceWorker.ready;
                    let subscription = await registration.pushManager.getSubscription();

                    if (!subscription) {
                        subscription = await registration.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlBase64ToUint8Array(webPushPublicKey),
                        });
                    }

                    await fetch(notificationsPushSubscribeUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(subscription.toJSON()),
                    });
                } catch (error) {
                    console.debug('Suscripción push no disponible todavía:', error);
                }
            };

            const setNotificationsOpen = (open) => {
                if (!notifPanel) return;

                notifPanel.classList.toggle('is-open', open);
                const isMobilePwaOpen = open && isStandalonePwa && window.innerWidth <= 640;

                document.body.classList.toggle('mobile-notifications-open', isMobilePwaOpen);

                if (isMobilePwaOpen) {
                    document.body.dataset.previousOverflow = '';
                    document.documentElement.dataset.previousOverflow = '';
                    document.body.style.overflow = 'hidden';
                    document.documentElement.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = document.body.dataset.previousOverflow || '';
                    document.documentElement.style.overflow = document.documentElement.dataset.previousOverflow || '';
                    delete document.body.dataset.previousOverflow;
                    delete document.documentElement.dataset.previousOverflow;
                }
            };

            const closeNotifications = () => {
                setNotificationsOpen(false);
            };

            const closeMobileQuickMenu = () => {
                if (!mobileQuickAddBtn || !mobileQuickMenu) return;

                mobileQuickAddBtn.classList.remove('is-open');
                mobileQuickAddBtn.setAttribute('aria-expanded', 'false');
                mobileQuickMenu.classList.remove('is-open');
                mobileQuickMenu.setAttribute('aria-hidden', 'true');
            };

            const closeMobileDrawer = () => {
                if (!mobileDrawer || !mobileDrawerBackdrop) return;

                mobileDrawer.classList.remove('is-open');
                mobileDrawerBackdrop.classList.remove('is-open');
                document.body.style.overflow = '';
            };

            const toggleMobileQuickMenu = () => {
                if (!mobileQuickAddBtn || !mobileQuickMenu) return;

                const willOpen = !mobileQuickMenu.classList.contains('is-open');

                closeNotifications();
                if (profileMenu) profileMenu.classList.remove('is-open');
                closeMobileDrawer();

                mobileQuickAddBtn.classList.toggle('is-open', willOpen);
                mobileQuickAddBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                mobileQuickMenu.classList.toggle('is-open', willOpen);
                mobileQuickMenu.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
            };

            const openMobileDrawer = () => {
                if (!mobileDrawer || !mobileDrawerBackdrop) return;

                closeNotifications();
                if (profileMenu) profileMenu.classList.remove('is-open');
                closeMobileQuickMenu();

                mobileDrawer.classList.add('is-open');
                mobileDrawerBackdrop.classList.add('is-open');
                document.body.style.overflow = 'hidden';
            };

            const updateConnectionStatus = () => {
                if (!statusPill || !statusText) return;

                if (navigator.onLine) {
                    statusPill.classList.remove('offline');
                    statusText.textContent = 'En línea';
                } else {
                    statusPill.classList.add('offline');
                    statusText.textContent = 'Sin conexión';
                }
            };

            updateConnectionStatus();
            window.addEventListener('online', updateConnectionStatus);
            window.addEventListener('offline', updateConnectionStatus);

            const updateBadge = (count) => {
                [notifCountBadge, mobileNotifCountBadge].forEach((badge) => {
                    if (!badge) return;

                    badge.dataset.count = count;

                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                });

                updateNativeAppBadge(count);
            };

            const showToast = (message) => {
                const existing = document.querySelector('.toast-notification');
                if (existing) existing.remove();

                const toast = document.createElement('div');
                toast.className = 'toast-notification';
                toast.textContent = message;

                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 2500);
            };

            const syncNotifications = async () => {
                if (!notificationsSyncUrl) return;

                try {
                    const response = await fetch(notificationsSyncUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const data = await response.json();

                    if (data.success) {
                        const newCount = parseInt(data.unread_count || 0, 10);

                        if (newCount > lastCount) {
                            showToast('Tienes nuevas alertas en la plataforma');
                            showSystemNotification('InterFarm', {
                                body: 'Tienes nuevas notificaciones de tu finca.',
                                data: { url: '{{ route('events.index') }}' },
                            });
                        }

                        lastCount = newCount;
                        updateBadge(newCount);
                    }
                } catch (error) {
                    console.error('Error sincronizando notificaciones:', error);
                }
            };

            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openMobileDrawer();
                });
            }

            if (mobileDrawerCloseBtn) {
                mobileDrawerCloseBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeMobileDrawer();
                });
            }

            if (mobileDrawerBackdrop) {
                mobileDrawerBackdrop.addEventListener('click', closeMobileDrawer);
            }

            if (mobileDrawer) {
                mobileDrawer.querySelectorAll('a[href]').forEach((link) => {
                    link.addEventListener('click', () => {
                        closeMobileDrawer();
                    });
                });
            }

            if (notifBtn && notifPanel) {
                notifBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const willOpen = !notifPanel.classList.contains('is-open');

                    setNotificationsOpen(willOpen);

                    if (profileMenu) {
                        profileMenu.classList.remove('is-open');
                    }

                    closeMobileDrawer();
                    closeMobileQuickMenu();

                    if (willOpen && notificationsSyncUrl) {
                        await ensureNotificationPermission();
                        await ensurePushSubscription();
                        await syncNotifications();
                    }
                });
            }

            if (mobileNotifBtn && notifPanel) {
                mobileNotifBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const willOpen = !notifPanel.classList.contains('is-open');

                    setNotificationsOpen(willOpen);

                    if (profileMenu) {
                        profileMenu.classList.remove('is-open');
                    }

                    closeMobileDrawer();
                    closeMobileQuickMenu();

                    if (willOpen && notificationsSyncUrl) {
                        await ensureNotificationPermission();
                        await ensurePushSubscription();
                        await syncNotifications();
                    }
                });
            }

            if (mobileQuickAddBtn && mobileQuickMenu) {
                mobileQuickAddBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleMobileQuickMenu();
                });

                mobileQuickMenu.querySelectorAll('a[href]').forEach((link) => {
                    link.addEventListener('click', closeMobileQuickMenu);
                });
            }

            if (profileBtn && profileMenu) {
                profileBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    profileMenu.classList.toggle('is-open');

                    closeNotifications();

                    closeMobileDrawer();
                    closeMobileQuickMenu();
                });
            }

            document.addEventListener('click', (e) => {
                if (document.body.classList.contains('mobile-notifications-open') && notifPanel) {
                    const protectedNotificationArea = e.target.closest('.notif-item, .notif-header, .notif-actions');

                    if (!protectedNotificationArea) {
                        closeNotifications();
                        return;
                    }
                }

                if (notifPanel && notifBtn && !notifPanel.contains(e.target) && !notifBtn.contains(e.target)) {
                    const clickedMobileNotif = mobileNotifBtn && mobileNotifBtn.contains(e.target);

                    if (!clickedMobileNotif) {
                        closeNotifications();
                    }
                }

                if (profileMenu && profileBtn && !profileMenu.contains(e.target) && !profileBtn.contains(e.target)) {
                    profileMenu.classList.remove('is-open');
                }

                if (mobileQuickMenu
                    && mobileQuickAddBtn
                    && !mobileQuickMenu.contains(e.target)
                    && !mobileQuickAddBtn.contains(e.target)) {
                    closeMobileQuickMenu();
                }
            });

            document.addEventListener('touchmove', (e) => {
                if (!document.body.classList.contains('mobile-notifications-open')) return;

                if (!notifPanel || !notifPanel.contains(e.target)) {
                    e.preventDefault();
                }
            }, { passive: false });

            document.addEventListener('contextmenu', (e) => {
                if (isStandalonePwa && e.target.closest('a')) {
                    e.preventDefault();
                }
            });

            document.addEventListener('dragstart', (e) => {
                if (isStandalonePwa && e.target.closest('a')) {
                    e.preventDefault();
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeNotifications();
                    if (profileMenu) profileMenu.classList.remove('is-open');
                    closeMobileDrawer();
                    closeMobileQuickMenu();
                }
            });

            if (markAllBtn && notificationsMarkAllUrl) {
                markAllBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    try {
                        const response = await fetch(notificationsMarkAllUrl, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        const data = await response.json();

                        if (data.success) {
                            updateBadge(0);
                            lastCount = 0;

                            document.querySelectorAll('.notification-row').forEach((row) => {
                                row.classList.add('opacity-70');
                            });

                            document.querySelectorAll('.notification-unread-dot').forEach((dot) => {
                                dot.remove();
                            });
                        }
                    } catch (error) {
                        console.error('Error marcando notificaciones:', error);
                    }
                });
            }

            if (closeNotificationsBtn) {
                closeNotificationsBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeNotifications();
                });
            }

            document.querySelectorAll('.notification-link').forEach((link) => {
                link.addEventListener('click', async () => {
                    const readUrl = link.getAttribute('data-read-url');

                    if (!readUrl) return;

                    try {
                        await fetch(readUrl, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                    } catch (error) {
                        console.error('Error marcando notificación individual:', error);
                    }
                });
            });

            document.querySelectorAll('.notif-dismiss-btn').forEach((button) => {
                button.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const dismissUrl = button.getAttribute('data-dismiss-url');
                    const row = button.closest('.notification-row');

                    if (!dismissUrl || !row) return;

                    try {
                        const response = await fetch(dismissUrl, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        const data = await response.json();

                        if (data.success) {
                            row.remove();
                            const newCount = parseInt(data.unread_count || 0, 10);
                            lastCount = newCount;
                            updateBadge(newCount);

                            if (!document.querySelector('.notification-row')) {
                                const list = document.querySelector('.notif-list');

                                if (list) {
                                    list.outerHTML = '<div class="notif-empty">No tienes alertas internas en este momento.</div>';
                                }
                            }
                        }
                    } catch (error) {
                        console.error('Error eliminando notificación:', error);
                    }
                });
            });

            if (notificationsSyncUrl) {
                setInterval(() => {
                    syncNotifications();
                }, 30000);

                syncNotifications();
                updateNativeAppBadge(lastCount);

                if ('Notification' in window && Notification.permission === 'granted') {
                    ensurePushSubscription();
                }
            }

            window.addEventListener('resize', () => {
                mountNotificationPanelForPwa();

                if (window.innerWidth > 1024) {
                    closeMobileDrawer();
                }

                if (window.innerWidth > 640) {
                    closeMobileQuickMenu();
                    closeNotifications();
                }
            });
        });
    </script>
</body>
</html>
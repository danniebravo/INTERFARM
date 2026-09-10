@extends('layouts.app')

@section('title', 'Configuración SaaS')

@section('content')
@php
    $billingUrl = Route::has('admin.saas.billing.index') ? route('admin.saas.billing.index') : route('admin.saas.index');
    $supportUrl = Route::has('admin.saas.settings.index') ? route('admin.saas.settings.index') : route('admin.saas.index');
    $generatedWompiWebhookUrl = Route::has('wompi.webhook') ? route('wompi.webhook') : url('/webhooks/wompi');
    $wompiWebhookUrl = old('wompi_webhook_url', $generatedWompiWebhookUrl);
@endphp

<style>
    .settings-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.68);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .settings-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.9);
        padding: 11px 13px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        outline: none;
    }

    .settings-input:focus {
        border-color: rgba(var(--brand), .55);
        box-shadow: 0 0 0 4px rgba(var(--brand), .12);
    }

    .settings-select-wrap {
        position: relative;
    }

    .settings-select-wrap select {
        appearance: none;
        padding-right: 38px;
    }

    .settings-select-wrap::after {
        content: "";
        position: absolute;
        right: 14px;
        top: 50%;
        width: 9px;
        height: 9px;
        border-right: 2px solid #64748b;
        border-bottom: 2px solid #64748b;
        transform: translateY(-70%) rotate(45deg);
        pointer-events: none;
    }

    .settings-tab {
        display: inline-flex;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        border: 1px solid rgba(148,163,184,.32);
        background: rgba(255,255,255,.7);
        color: #475569;
        padding: 0 18px;
        font-size: 12px;
        line-height: 1;
        font-weight: 900;
        text-align: center;
        white-space: nowrap;
        transition: .18s ease;
    }

    .settings-tab:hover {
        border-color: rgba(148,163,184,.52);
        background: rgba(255,255,255,.92);
        color: #334155;
    }

    .settings-tab.active {
        border-color: rgba(var(--brand), .42);
        background: rgba(var(--brand), .12);
        color: var(--brand-hex);
    }

    .settings-icon {
        display: inline-flex;
        height: 44px;
        width: 44px;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        background: rgba(var(--brand), .12);
        color: var(--brand-hex);
    }

    .settings-action-row {
        margin-top: auto;
        display: flex;
        justify-content: flex-end;
        padding-top: 8px;
    }

    .settings-primary-btn {
        display: inline-flex;
        min-height: 46px;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: var(--brand-hex);
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 900;
        color: #fff;
        box-shadow: 0 14px 24px rgba(var(--brand), .20);
        transition: .18s ease;
    }

    .settings-primary-btn:hover {
        background: var(--brand-dark-hex);
    }

    .settings-color-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }

    @media (min-width: 768px) {
        .settings-color-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .settings-color-field {
        display: grid;
        grid-template-columns: 52px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        border-radius: 16px;
        border: 1px solid rgba(148,163,184,.24);
        background: rgba(255,255,255,.54);
        padding: 12px;
    }

    .settings-color-input {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.35);
        background: transparent;
        padding: 3px;
        cursor: pointer;
    }

    .settings-color-input::-webkit-color-swatch-wrapper {
        padding: 0;
    }

    .settings-color-input::-webkit-color-swatch {
        border: 0;
        border-radius: 10px;
    }

    .settings-appearance-preview {
        position: relative;
        overflow: hidden;
        border-radius: 22px;
        border: 1px solid rgba(148,163,184,.24);
        background:
            radial-gradient(560px 180px at 0% 0%, color-mix(in srgb, var(--preview-primary) 18%, transparent), transparent 60%),
            linear-gradient(135deg, var(--preview-bg), #ffffff);
        padding: 16px;
    }

    .settings-appearance-preview .preview-sidebar {
        border-radius: 18px;
        border: 1px solid rgba(15,23,42,.08);
        background: rgba(255,255,255,.72);
        padding: 12px;
    }

    .settings-appearance-preview .preview-pill {
        display: inline-flex;
        border-radius: 999px;
        background: color-mix(in srgb, var(--preview-primary) 14%, transparent);
        color: var(--preview-dark);
        padding: 7px 10px;
        font-size: 11px;
        font-weight: 900;
    }

    .settings-appearance-preview .preview-button {
        display: inline-flex;
        border-radius: 12px;
        background: var(--preview-primary);
        color: #fff;
        padding: 9px 12px;
        font-size: 12px;
        font-weight: 900;
        box-shadow: 0 12px 22px color-mix(in srgb, var(--preview-primary) 24%, transparent);
    }

    .settings-logo-field {
        display: grid;
        gap: 14px;
        border-radius: 18px;
        border: 1px solid rgba(148,163,184,.24);
        background: rgba(255,255,255,.54);
        padding: 14px;
    }

    .settings-logo-preview {
        display: flex;
        min-height: 92px;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        border: 1px dashed rgba(22,101,52,.32);
        background: rgba(255,255,255,.7);
        padding: 18px;
    }

    .settings-file-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.9);
        padding: 10px;
        font-size: 13px;
        font-weight: 800;
        color: #334155;
    }

    .settings-copy-field {
        display: grid;
        gap: 10px;
    }

    .settings-copy-input {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        letter-spacing: 0;
    }

    .settings-copy-input[readonly] {
        cursor: text;
        background: rgba(248,250,252,.92);
    }

    @media (min-width: 768px) {
        .settings-copy-field {
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: end;
        }
    }

    .settings-copy-btn {
        display: inline-flex;
        min-height: 43px;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        border: 1px solid rgba(22,101,52,.20);
        background: rgba(22,101,52,.08);
        color: #166534;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 900;
        transition: .18s ease;
        white-space: nowrap;
    }

    .settings-copy-btn:hover {
        background: rgba(22,101,52,.13);
    }

    .settings-copy-btn.is-copied {
        border-color: rgba(22,163,74,.35);
        background: rgba(22,163,74,.18);
        color: #15803d;
    }

    @media (max-width: 640px) {
        .settings-action-row {
            justify-content: stretch;
        }

        .settings-primary-btn {
            width: 100%;
        }
    }

    .dark .settings-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .settings-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .settings-select-wrap::after {
        border-color: #cbd5e1;
    }

    .dark .settings-tab {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.82);
        color: #cbd5e1;
    }

    .dark .settings-tab:hover {
        border-color: rgba(148,163,184,.35);
        background: rgba(30,41,59,.88);
        color: #f8fafc;
    }

    .dark .settings-tab.active {
        border-color: rgba(74,222,128,.35);
        background: rgba(34,197,94,.14);
        color: #bbf7d0;
    }

    .dark .settings-icon {
        background: rgba(34,197,94,.14);
        color: #bbf7d0;
    }

    .dark .settings-primary-btn {
        box-shadow: 0 14px 24px rgba(34,197,94,.14);
    }

    .dark .settings-color-field {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.38);
    }

    .dark .settings-appearance-preview {
        border-color: rgba(148,163,184,.22);
        background:
            radial-gradient(560px 180px at 0% 0%, color-mix(in srgb, var(--preview-primary) 18%, transparent), transparent 60%),
            linear-gradient(135deg, var(--preview-dark-bg), #0f172a);
    }

    .dark .settings-appearance-preview .preview-sidebar {
        border-color: rgba(148,163,184,.18);
        background: rgba(15,23,42,.72);
    }

    .dark .settings-logo-field {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.34);
    }

    .dark .settings-logo-preview {
        border-color: rgba(74,222,128,.25);
        background: rgba(15,23,42,.7);
    }

    .dark .settings-file-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .settings-copy-btn {
        border-color: rgba(74,222,128,.25);
        background: rgba(34,197,94,.12);
        color: #bbf7d0;
    }

    .dark .settings-copy-btn.is-copied {
        border-color: rgba(134,239,172,.32);
        background: rgba(34,197,94,.20);
        color: #dcfce7;
    }

    .dark .settings-copy-input[readonly] {
        background: rgba(15,23,42,.78);
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Configuración SaaS
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Ajustes de la plataforma</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Administra el canal de ayuda global y la cuenta de Wompi que usará toda la plataforma para cobrar a los clientes.
            </p>
        </div>

        <a href="{{ route('admin.saas.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Volver al Centro SaaS
        </a>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.saas.index') }}" class="settings-tab">Resumen</a>
        <a href="{{ route('admin.users.index') }}" class="settings-tab">Clientes</a>
        <a href="{{ $billingUrl }}" class="settings-tab {{ $activeConfigSection === 'billing' ? 'active' : '' }}">Facturación</a>
        <a href="{{ route('admin.saas.plans.index') }}" class="settings-tab">Planes</a>
        <a href="{{ $supportUrl }}" class="settings-tab {{ $activeConfigSection === 'support' ? 'active' : '' }}">Configuración</a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if(! $settingsReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            Falta la tabla de configuración SaaS. Ejecuta las migraciones para guardar estos ajustes.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[.9fr_1.25fr]">
        <section id="support" class="settings-card flex flex-col p-5">
            <div class="mb-5 flex items-start gap-4">
                <span class="settings-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H9l-5 4V5.5Z"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">WhatsApp y soporte</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Controla el acceso de ayuda por WhatsApp que se muestra a los clientes en el menú lateral.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.saas.settings.update') }}" class="flex flex-1 flex-col gap-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">URL WhatsApp soporte</label>
                    <input name="support_whatsapp_url" value="{{ old('support_whatsapp_url', $settings['support_whatsapp_url'] ?? '') }}" class="settings-input mt-1" placeholder="https://wa.me/57...">
                    @error('support_whatsapp_url')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Texto del botón</label>
                    <input name="support_whatsapp_message" value="{{ old('support_whatsapp_message', $settings['support_whatsapp_message'] ?? 'Ayuda por WhatsApp') }}" class="settings-input mt-1">
                    @error('support_whatsapp_message')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-2xl border border-green-500/15 bg-green-50/70 p-4 text-sm font-bold text-green-800 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-100">
                    Este enlace se aplica globalmente en el acceso de soporte.
                </div>

                <div class="settings-action-row">
                    <button class="settings-primary-btn">
                        Guardar WhatsApp
                    </button>
                </div>
            </form>
        </section>

        <section id="map-settings" class="settings-card flex flex-col p-5">
            <div class="mb-5 flex items-start gap-4">
                <span class="settings-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 18 3.8 20.2V6.2L9 4m0 14 6 2.2m-6-2.2V4m6 16.2 5.2-2.2V4L15 6.2m0 14V6.2M15 6.2 9 4"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Google Maps</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Define la API key usada por mapas, búsqueda de direcciones, edificios y lugares en lotes/praderas.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.saas.settings.update') }}" class="flex flex-1 flex-col gap-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">API key Google Maps</label>
                    <input name="google_maps_api_key"
                           value="{{ old('google_maps_api_key', $settings['google_maps_api_key'] ?? '') }}"
                           class="settings-input mt-1"
                           placeholder="AIza...">
                    @error('google_maps_api_key')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-2xl border border-blue-500/15 bg-blue-50/70 p-4 text-sm font-bold text-blue-800 dark:border-blue-400/20 dark:bg-blue-500/10 dark:text-blue-100">
                    En Google Cloud deben estar activas Maps JavaScript API, Places API (New) y Geocoding API. La key debe permitir el dominio app.somosinterfarm.com.
                </div>

                <div class="settings-action-row">
                    <button class="settings-primary-btn">
                        Guardar Google Maps
                    </button>
                </div>
            </form>
        </section>

        <section id="appearance-settings" class="settings-card flex flex-col p-5">
            <div class="mb-5 flex items-start gap-4">
                <span class="settings-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3a9 9 0 0 0 0 18h1.5a1.8 1.8 0 0 0 .6-3.5 1.6 1.6 0 0 1 .5-3.1H16a5 5 0 0 0 0-10H12Z"/>
                        <circle cx="7.5" cy="11" r="1" fill="currentColor" stroke="none"/>
                        <circle cx="10" cy="7.5" r="1" fill="currentColor" stroke="none"/>
                        <circle cx="14.5" cy="7.5" r="1" fill="currentColor" stroke="none"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Apariencia y colores</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Personaliza la paleta principal que verán los clientes en la aplicación.</p>
                </div>
            </div>

            @php
                $appearanceDefaults = [
                    'app_primary_color' => '#166534',
                    'app_primary_dark_color' => '#14532d',
                    'app_accent_color' => '#a3e635',
                    'app_background_color' => '#f6f8fb',
                    'app_dark_background_color' => '#020617',
                ];

                $appearanceValues = collect($appearanceDefaults)
                    ->mapWithKeys(fn ($default, $key) => [$key => old($key, $settings[$key] ?? $default)])
                    ->all();

                $appLogoPath = $settings['app_logo_path'] ?? null;
                $appLogoUrl = $appLogoPath ? url('/storage/' . ltrim($appLogoPath, '/')) : asset('images/logo.png');
            @endphp

            <form method="POST" action="{{ route('admin.saas.settings.update') }}" enctype="multipart/form-data" class="flex flex-1 flex-col gap-4">
                @csrf
                @method('PATCH')

                <div class="settings-logo-field">
                    <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)] md:items-center">
                        <div class="settings-logo-preview">
                            <img src="{{ $appLogoUrl }}" alt="Logo actual de InterFarm" class="max-h-16 max-w-full">
                        </div>

                        <div>
                            <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Logo de la plataforma</label>
                            <input type="file" name="app_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="settings-file-input mt-2">
                            <p class="mt-2 text-xs font-bold text-gray-500 dark:text-gray-300">
                                Usa PNG, JPG, WEBP o SVG. Máximo 2 MB.
                            </p>

                            @if($appLogoPath)
                                <label class="mt-3 flex items-center gap-2 text-sm font-bold text-gray-600 dark:text-gray-300">
                                    <input type="checkbox" name="remove_app_logo" value="1" class="rounded border-gray-300 text-brand focus:ring-brand">
                                    Restaurar logo original
                                </label>
                            @endif

                            @error('app_logo')
                                <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="settings-color-grid">
                    <label class="settings-color-field">
                        <input type="color" name="app_primary_color" value="{{ $appearanceValues['app_primary_color'] }}" class="settings-color-input">
                        <span>
                            <span class="block text-xs font-black uppercase text-gray-500 dark:text-gray-400">Color principal</span>
                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $appearanceValues['app_primary_color'] }}</span>
                        </span>
                    </label>

                    <label class="settings-color-field">
                        <input type="color" name="app_primary_dark_color" value="{{ $appearanceValues['app_primary_dark_color'] }}" class="settings-color-input">
                        <span>
                            <span class="block text-xs font-black uppercase text-gray-500 dark:text-gray-400">Principal oscuro</span>
                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $appearanceValues['app_primary_dark_color'] }}</span>
                        </span>
                    </label>

                    <label class="settings-color-field">
                        <input type="color" name="app_accent_color" value="{{ $appearanceValues['app_accent_color'] }}" class="settings-color-input">
                        <span>
                            <span class="block text-xs font-black uppercase text-gray-500 dark:text-gray-400">Acento</span>
                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $appearanceValues['app_accent_color'] }}</span>
                        </span>
                    </label>

                    <label class="settings-color-field">
                        <input type="color" name="app_background_color" value="{{ $appearanceValues['app_background_color'] }}" class="settings-color-input">
                        <span>
                            <span class="block text-xs font-black uppercase text-gray-500 dark:text-gray-400">Fondo claro</span>
                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $appearanceValues['app_background_color'] }}</span>
                        </span>
                    </label>

                    <label class="settings-color-field md:col-span-2">
                        <input type="color" name="app_dark_background_color" value="{{ $appearanceValues['app_dark_background_color'] }}" class="settings-color-input">
                        <span>
                            <span class="block text-xs font-black uppercase text-gray-500 dark:text-gray-400">Fondo oscuro</span>
                            <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $appearanceValues['app_dark_background_color'] }}</span>
                        </span>
                    </label>
                </div>

                <div class="settings-appearance-preview"
                     style="--preview-primary: {{ $appearanceValues['app_primary_color'] }}; --preview-dark: {{ $appearanceValues['app_primary_dark_color'] }}; --preview-bg: {{ $appearanceValues['app_background_color'] }}; --preview-dark-bg: {{ $appearanceValues['app_dark_background_color'] }};">
                    <div class="preview-sidebar">
                        <div class="preview-pill">Vista previa</div>
                        <div class="mt-4 flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-black text-gray-900 dark:text-white">InterFarm</div>
                                <div class="mt-1 text-xs font-bold text-gray-500 dark:text-gray-300">Menú, botones y fondos principales</div>
                            </div>
                            <span class="preview-button">Guardar</span>
                        </div>
                    </div>
                </div>

                <div class="settings-action-row">
                    <button class="settings-primary-btn">
                        Guardar colores
                    </button>
                </div>
            </form>
        </section>

        <section id="mail-settings" class="settings-card flex flex-col p-5">
            <div class="mb-5 flex items-start gap-4">
                <span class="settings-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4 6.5h16v11H4v-11Zm.5.5 7.5 5.5L19.5 7"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Correo y SMTP</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Configura el envío de restablecimiento de contraseña, verificación y correos transaccionales.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.saas.settings.update') }}" class="grid flex-1 gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Método de envío</label>
                    <div class="settings-select-wrap mt-1">
                        <select name="mail_mailer" class="settings-input">
                            @foreach(['smtp' => 'SMTP', 'sendmail' => 'Sendmail del servidor', 'log' => 'Log / pruebas'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('mail_mailer', $settings['mail_mailer'] ?? 'smtp') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('mail_mailer')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Seguridad SMTP</label>
                    <div class="settings-select-wrap mt-1">
                        <select name="mail_scheme" class="settings-input">
                            <option value="none" @selected(old('mail_scheme', $settings['mail_scheme'] ?? 'tls') === 'none')>Sin cifrado</option>
                            <option value="tls" @selected(old('mail_scheme', $settings['mail_scheme'] ?? 'tls') === 'tls')>TLS</option>
                            <option value="ssl" @selected(old('mail_scheme', $settings['mail_scheme'] ?? '') === 'ssl')>SSL</option>
                        </select>
                    </div>
                    @error('mail_scheme')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Servidor SMTP</label>
                    <input name="mail_host" value="{{ old('mail_host', $settings['mail_host'] ?? '') }}" class="settings-input mt-1" placeholder="mail.somosinterfarm.com">
                    @error('mail_host')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Puerto</label>
                    <input type="number" min="1" max="65535" name="mail_port" value="{{ old('mail_port', $settings['mail_port'] ?? 587) }}" class="settings-input mt-1" placeholder="587">
                    @error('mail_port')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Usuario SMTP</label>
                    <input name="mail_username" value="{{ old('mail_username', $settings['mail_username'] ?? '') }}" class="settings-input mt-1" placeholder="no-reply@somosinterfarm.com">
                    @error('mail_username')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Contraseña SMTP</label>
                    <input type="password" name="mail_password" value="{{ old('mail_password', $settings['mail_password'] ?? '') }}" class="settings-input mt-1" autocomplete="new-password">
                    @error('mail_password')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Correo remitente</label>
                    <input type="email" name="mail_from_address" value="{{ old('mail_from_address', $settings['mail_from_address'] ?? '') }}" class="settings-input mt-1" placeholder="no-reply@somosinterfarm.com">
                    @error('mail_from_address')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Nombre remitente</label>
                    <input name="mail_from_name" value="{{ old('mail_from_name', $settings['mail_from_name'] ?? 'InterFarm') }}" class="settings-input mt-1" placeholder="InterFarm">
                    @error('mail_from_name')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Dominio EHLO</label>
                    <input name="mail_ehlo_domain" value="{{ old('mail_ehlo_domain', $settings['mail_ehlo_domain'] ?? '') }}" class="settings-input mt-1" placeholder="somosinterfarm.com">
                    @error('mail_ehlo_domain')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Ruta sendmail</label>
                    <input name="mail_sendmail_path" value="{{ old('mail_sendmail_path', $settings['mail_sendmail_path'] ?? '/usr/sbin/sendmail -bs -i') }}" class="settings-input mt-1">
                    @error('mail_sendmail_path')
                        <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-2xl border border-amber-500/15 bg-amber-50/70 p-4 text-sm font-bold text-amber-800 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-100 md:col-span-2">
                    Para que los correos lleguen bien, verifica que el dominio tenga SPF, DKIM y DMARC activos. Estos datos conectan la plataforma con el servicio de correo; la entrega final depende del proveedor y de la configuración del dominio.
                </div>

                <div class="settings-action-row md:col-span-2">
                    <button class="settings-primary-btn">
                        Guardar correo
                    </button>
                </div>
            </form>
        </section>

        <section id="payment-settings" class="settings-card flex flex-col p-5">
            <div class="mb-5 flex items-start gap-4">
                <span class="settings-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5">
                        <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v10H3V7Zm3 4h4m8 3h.01"/>
                    </svg>
                </span>
                <div>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Wompi y pagos del sistema</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Define la pasarela que se usará para todos los pagos automáticos mensuales o anuales de los clientes.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.saas.settings.update') }}" class="grid flex-1 gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Pasarela</label>
                    <div class="settings-select-wrap mt-1">
                        <select name="payment_gateway" class="settings-input">
                            @foreach(['' => 'Sin seleccionar', 'wompi' => 'Wompi', 'mercadopago' => 'Mercado Pago', 'stripe' => 'Stripe', 'payu' => 'PayU'] as $value => $label)
                                <option value="{{ $value }}" @selected(($settings['payment_gateway'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Ambiente Wompi</label>
                    <div class="settings-select-wrap mt-1">
                        <select name="wompi_environment" class="settings-input">
                            <option value="sandbox" @selected(($settings['wompi_environment'] ?? 'sandbox') === 'sandbox')>Pruebas / sandbox</option>
                            <option value="production" @selected(($settings['wompi_environment'] ?? '') === 'production')>Producción</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Wompi llave pública</label>
                    <input name="wompi_public_key" value="{{ old('wompi_public_key', $settings['wompi_public_key'] ?? $settings['payment_public_key'] ?? '') }}" class="settings-input mt-1" placeholder="pub_prod_...">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Wompi llave privada</label>
                    <input type="password" name="wompi_private_key" value="{{ old('wompi_private_key', $settings['wompi_private_key'] ?? $settings['payment_secret_key'] ?? '') }}" class="settings-input mt-1" placeholder="prv_prod_...">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Wompi events key</label>
                    <input name="wompi_events_key" value="{{ old('wompi_events_key', $settings['wompi_events_key'] ?? $settings['payment_webhook_secret'] ?? '') }}" class="settings-input mt-1" placeholder="events_prod_...">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Secreto de integridad</label>
                    <input name="wompi_integrity_secret" value="{{ old('wompi_integrity_secret', $settings['wompi_integrity_secret'] ?? '') }}" class="settings-input mt-1">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Moneda</label>
                    <input name="billing_currency" value="{{ old('billing_currency', $settings['billing_currency'] ?? 'COP') }}" class="settings-input mt-1">
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Días de periodo de prueba</label>
                    <input type="number" min="0" max="365" name="trial_days" value="{{ old('trial_days', $settings['trial_days'] ?? 15) }}" class="settings-input mt-1">
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Correo facturación</label>
                    <input type="email" name="billing_email" value="{{ old('billing_email', $settings['billing_email'] ?? '') }}" class="settings-input mt-1">
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">URL webhook Wompi</label>
                    <input type="hidden" name="wompi_webhook_url" value="{{ $wompiWebhookUrl }}">
                    <div class="settings-copy-field mt-1">
                        <input id="wompiWebhookUrl"
                               value="{{ $wompiWebhookUrl }}"
                               class="settings-input settings-copy-input"
                               readonly>
                        <button type="button" class="settings-copy-btn" data-copy-target="wompiWebhookUrl">
                            Copiar URL
                        </button>
                    </div>
                    <p class="mt-2 text-xs font-bold text-gray-500 dark:text-gray-300">
                        Pégala en Wompi como URL de eventos/webhook. El endpoint recibe eventos por POST y no usa CSRF.
                    </p>
                </div>

                <div class="settings-action-row md:col-span-2">
                    <button class="settings-primary-btn">
                        Guardar Wompi
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const input = document.getElementById(button.dataset.copyTarget);

            if (! input) {
                return;
            }

            const originalText = button.textContent.trim();
            const value = input.value;

            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                } else {
                    input.select();
                    document.execCommand('copy');
                    input.blur();
                }

                button.textContent = 'Copiada';
                button.classList.add('is-copied');

                window.setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('is-copied');
                }, 1800);
            } catch (error) {
                input.focus();
                input.select();
            }
        });
    });
</script>

@if(in_array($activeConfigSection, ['billing', 'support'], true))
    <script>
        window.addEventListener('load', () => {
            if (window.location.hash) return;

            const target = document.getElementById(@json($activeConfigSection));

            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    </script>
@endif
@endsection
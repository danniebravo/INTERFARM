@props([
    'alt' => config('app.name', 'InterFarm'),
])

@php
    $logoPath = null;

    if (class_exists(\App\Models\PlatformSetting::class)
        && \Illuminate\Support\Facades\Schema::hasTable('platform_settings')) {
        $logoPath = \App\Models\PlatformSetting::where('key', 'app_logo_path')->value('value');
    }

    $logoSrc = $logoPath
        ? url('/storage/' . ltrim($logoPath, '/'))
        : asset('images/logo.png');
@endphp

<img src="{{ $logoSrc }}" alt="{{ $alt }}" {{ $attributes }}>

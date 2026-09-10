@extends('layouts.app')

@section('title', $animal->name ?: 'Animal')

@section('content')

@php
    use Carbon\Carbon;

    $developmentStage = $animal->developmentStage();
    $photos = $animal->photos ?? collect();

    $animalName = $animal->name ?: 'Animal sin nombre';
    $animalBreed = $animal->breed ?: 'Raza no registrada';
    $animalStage = $developmentStage ?: 'Etapa no definida';
    $animalAge = $animal->ageHuman() ?? 'Edad no definida';
    $photoCount = $photos->count();
    $isFemaleAnimal = $animal->isFemale();
    $canRegisterProduction = $animal->canRegisterProduction();
    $productionBlockedReason = $animal->productionBlockedReason();
    $canRegisterMilkProduction = $animal->canRegisterMilkProduction();
    $milkProductionBlockedReason = $animal->milkProductionBlockedReason();
    $eligibleSires = collect($eligibleSires ?? []);
    $pregnancySireLabel = $animal->pregnancySire
        ? (($animal->pregnancySire->ear_tag ?: 'Sin arete') . ' - ' . ($animal->pregnancySire->name ?: 'Animal '.$animal->pregnancySire->id))
        : ($animal->pregnancy_sire_name_manual ?: null);

    $plainNotes = method_exists($animal, 'cleanNotes')
        ? $animal->cleanNotes()
        : trim(preg_replace('/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s', '', $animal->notes ?? ''));

    $productionRecords = collect($productionRecords ?? [])->sortBy('date')->values();
    $healthRecords = collect($healthRecords ?? [])->sortByDesc('date')->values();
    $milkProductionRecords = $productionRecords->filter(fn ($record) => ($record['type'] ?? 'Leche') === 'Leche')->values();
    $meatProductionRecords = $productionRecords->filter(fn ($record) => ($record['type'] ?? null) === 'Carne')->values();

    $latestProduction = $productionRecords->last();
    $latestHealth = $healthRecords->first();

    $productionChart = $productionRecords->take(-7)->values();

    $productionAverage = $milkProductionRecords->whereNotNull('liters')->count() > 0
        ? round($milkProductionRecords->whereNotNull('liters')->avg('liters'), 1)
        : null;

    $bestProduction = $milkProductionRecords->whereNotNull('liters')->count() > 0
        ? round($milkProductionRecords->whereNotNull('liters')->max('liters'), 1)
        : null;

    $latestWeight = $meatProductionRecords->whereNotNull('weight')->sortByDesc('date')->first();

    $chartValues = $productionChart->map(function ($item) {
        return (float) ($item['liters'] ?? $item['weight'] ?? 0);
    })->values();

    $chartLabels = $productionChart->map(function ($item) {
        return !empty($item['date']) ? Carbon::parse($item['date'])->translatedFormat('d M') : '—';
    })->values();

    $maxChartValue = max(1, (float) ($chartValues->max() ?? 0));

    $pointCount = $chartValues->count();
    $chartW = 720;
    $chartH = 260;
    $padX = 22;
    $padTop = 24;
    $padBottom = 26;
    $usableW = $chartW - ($padX * 2);
    $usableH = $chartH - $padTop - $padBottom;

    $points = [];

    foreach ($chartValues as $i => $value) {
        $x = $padX + ($pointCount > 1 ? ($usableW / ($pointCount - 1)) * $i : $usableW / 2);
        $y = $padTop + $usableH - (($value / $maxChartValue) * $usableH);

        $points[] = [
            'x' => round($x, 2),
            'y' => round($y, 2),
            'value' => $value,
            'label' => $chartLabels[$i] ?? '—',
        ];
    }

    $linePath = '';
    $areaPath = '';

    if (count($points) === 1) {
        $p = $points[0];
        $linePath = "M {$p['x']} {$p['y']}";
        $areaPath = "M {$p['x']} {$p['y']} L {$p['x']} " . ($chartH - 10) . " L {$p['x']} " . ($chartH - 10) . " Z";
    } elseif (count($points) > 1) {
        $linePath = "M {$points[0]['x']} {$points[0]['y']} ";

        for ($i = 0; $i < count($points) - 1; $i++) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $cx = ($p1['x'] + $p2['x']) / 2;
            $linePath .= "C {$cx} {$p1['y']}, {$cx} {$p2['y']}, {$p2['x']} {$p2['y']} ";
        }

        $areaPath = $linePath
            . "L {$points[count($points) - 1]['x']} " . ($chartH - 10) . " "
            . "L {$points[0]['x']} " . ($chartH - 10) . " Z";
    }

    $productionErrorFields = ['date', 'period', 'liters', 'weight', 'feeding_type', 'notes'];
    $healthErrorFields = ['treatment_type', 'disease', 'diagnosis', 'medication', 'days'];
    $reproductiveErrorFields = ['is_pregnant', 'pregnancy_date', 'pregnancy_sire_id', 'pregnancy_sire_name_manual', 'last_calving_date', 'calving_count', 'status', 'status_date', 'status_notes'];

    $hasProductionErrors = false;
    foreach ($productionErrorFields as $field) {
        if ($errors->has($field)) {
            $hasProductionErrors = true;
            break;
        }
    }

    $hasHealthErrors = false;
    foreach ($healthErrorFields as $field) {
        if ($errors->has($field)) {
            $hasHealthErrors = true;
            break;
        }
    }

    $hasReproductiveErrors = false;
    foreach ($reproductiveErrorFields as $field) {
        if ($errors->has($field)) {
            $hasReproductiveErrors = true;
            break;
        }
    }
@endphp

<style>
    .animal-avatar {
        width: 68px;
        height: 68px;
        border-radius: 999px;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .68);
        box-shadow: 0 10px 24px rgba(0, 0, 0, .08), inset 0 1px 0 rgba(255,255,255,.7);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .animal-avatar-icon {
        font-size: 28px;
        line-height: 1;
    }

    .animal-action-card {
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .62);
        border-radius: 28px;
        padding: 24px;
        transition: all .22s ease;
        box-shadow: 0 10px 28px rgba(0, 0, 0, .06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        cursor: pointer;
    }

    .animal-action-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(0, 0, 0, .10);
    }

    .animal-action-card:disabled {
        cursor: not-allowed;
        opacity: .68;
        transform: none;
    }

    .animal-action-card:disabled:hover {
        transform: none;
        box-shadow: 0 10px 28px rgba(0, 0, 0, .06);
    }

    .animal-action-icon {
        width: 68px;
        height: 68px;
        border-radius: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
    }

    .animal-action-card.production .animal-action-icon {
        background: rgba(22, 101, 52, .12);
        color: #166534;
    }

    .animal-action-card.health .animal-action-icon {
        background: rgba(239, 68, 68, .12);
        color: #dc2626;
    }

    .animal-action-card.reproduction .animal-action-icon {
        background: rgba(34, 197, 94, .14);
        color: #15803d;
    }

    .animal-carousel-shell {
        position: relative;
        overflow: hidden;
        border-radius: 28px;
    }

    .animal-carousel-track {
        display: flex;
        gap: 16px;
        transition: transform .35s ease;
        will-change: transform;
    }

    .animal-carousel-item {
        flex: 0 0 calc(33.333% - 10.7px);
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .70);
        box-shadow: 0 12px 24px rgba(0, 0, 0, .08);
        position: relative;
    }

    .animal-carousel-item::after {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: 24px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.7);
        pointer-events: none;
    }

    .animal-carousel-image {
        width: 100%;
        height: 260px;
        object-fit: cover;
        display: block;
    }

    .animal-carousel-label {
        position: absolute;
        left: 14px;
        top: 14px;
        z-index: 10;
        border-radius: 999px;
        background: rgba(22, 101, 52, .95);
        color: white;
        font-size: 11px;
        font-weight: 800;
        padding: 7px 12px;
        box-shadow: 0 8px 18px rgba(22, 101, 52, .20);
    }

    .animal-carousel-btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 20;
        width: 44px;
        height: 44px;
        border-radius: 999px;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .92);
        box-shadow: 0 10px 22px rgba(0, 0, 0, .12);
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .2s ease;
    }

    .animal-carousel-btn:hover {
        background: white;
        transform: translateY(-50%) scale(1.03);
    }

    .animal-carousel-btn:disabled {
        opacity: .45;
        cursor: not-allowed;
    }

    .animal-carousel-btn.prev {
        left: 10px;
    }

    .animal-carousel-btn.next {
        right: 10px;
    }

    .animal-carousel-dots {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 18px;
    }

    .animal-carousel-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        background: rgba(0, 0, 0, .14);
        transition: all .2s ease;
    }

    .animal-carousel-dot.active {
        width: 24px;
        background: rgba(22, 101, 52, 1);
    }

    .animal-stat-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .60);
        border-radius: 20px;
        padding: 16px;
    }

    .animal-chart-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background:
            radial-gradient(1200px 400px at 0% 0%, rgba(34,197,94,.08), transparent 45%),
            linear-gradient(180deg, rgba(255,255,255,.76), rgba(255,255,255,.60));
        border-radius: 28px;
        padding: 22px;
        position: relative;
        overflow: hidden;
    }

    .animal-chart-wave-labels {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
        position: relative;
        z-index: 2;
    }

    .animal-chart-wave-labels span {
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: #6b7280;
        text-transform: capitalize;
    }

    .animal-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: none;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, .34);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        opacity: 0;
        transition: opacity .22s ease;
    }

    .animal-modal-backdrop.is-open {
        display: block;
        opacity: 1;
    }

    .animal-modal-frame {
        width: 100vw;
        min-height: 100vh;
        overflow-y: auto;
        padding: 28px 18px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .animal-modal-panel {
        width: min(760px, 100%);
        max-height: calc(100vh - 56px);
        overflow-y: auto;
        min-height: auto;
        margin: 0 auto;
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,.55);
        background: rgba(255,255,255,.90);
        box-shadow: 0 24px 60px rgba(0,0,0,.16), inset 0 1px 0 rgba(255,255,255,.72);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 22px;
        transform: translateY(20px) scale(.97);
        opacity: 0;
        transition: transform .24s cubic-bezier(.22,.9,.2,1), opacity .24s ease;
    }

    .animal-modal-backdrop.is-open .animal-modal-panel {
        transform: translateY(0) scale(1);
        opacity: 1;
    }

    .animal-modal-header {
        margin: -22px -22px 20px -22px;
        padding: 20px 22px 16px 22px;
        background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.90));
        border-bottom: 1px solid rgba(0, 0, 0, .06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-top-left-radius: 28px;
        border-top-right-radius: 28px;
    }

    .animal-modal-field label {
        display: block;
        margin-bottom: 6px;
        font-size: 14px;
        font-weight: 700;
        color: #374151;
    }

    .animal-modal-input,
    .animal-modal-select,
    .animal-modal-textarea {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, .10);
        background: rgba(255,255,255,.82);
        padding: 12px 16px;
        outline: none;
        transition: .2s ease;
        color: #111827;
    }

    .animal-modal-input:focus,
    .animal-modal-select:focus,
    .animal-modal-textarea:focus {
        border-color: rgba(22, 101, 52, .35);
        box-shadow: 0 0 0 4px rgba(22, 101, 52, .08);
    }

    .animal-modal-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image:
            linear-gradient(45deg, transparent 50%, #6b7280 50%),
            linear-gradient(135deg, #6b7280 50%, transparent 50%);
        background-position:
            calc(100% - 18px) calc(50% - 3px),
            calc(100% - 12px) calc(50% - 3px);
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
        padding-right: 42px;
    }

    .animal-modal-section {
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.52);
        border-radius: 24px;
        padding: 18px;
    }

    .animal-history-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255,255,255,.66);
        border-radius: 20px;
        padding: 16px;
    }

    .animal-field-error {
        display: block;
        margin-top: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #dc2626;
    }

    .animal-modal-error-box {
        margin-bottom: 16px;
        border-radius: 16px;
        border: 1px solid rgba(239,68,68,.16);
        background: rgba(254,242,242,.9);
        padding: 12px 14px;
        font-size: 13px;
        color: #b91c1c;
    }

    .dark .animal-avatar,
    .dark .animal-action-card,
    .dark .animal-stat-card,
    .dark .animal-history-card,
    .dark .animal-carousel-item,
    .dark .animal-chart-card,
    .dark .animal-modal-section,
    .dark .animal-modal-panel {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.86);
        color: #f8fafc;
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .animal-chart-card {
        background:
            radial-gradient(900px 360px at 0% 0%, rgba(34,197,94,.14), transparent 45%),
            rgba(15,23,42,.88);
    }

    .dark .animal-action-card:hover,
    .dark .animal-history-card:hover {
        background: rgba(30,41,59,.92);
    }

    .dark .animal-carousel-btn,
    .dark a[class*="bg-white"],
    .dark button[class*="bg-white"],
    .dark div[class*="bg-white"] {
        border-color: rgba(148,163,184,.24) !important;
        background-color: rgba(30,41,59,.82) !important;
        color: #e2e8f0 !important;
    }

    .dark .animal-modal-header {
        border-bottom-color: rgba(148,163,184,.18);
        background: linear-gradient(180deg, rgba(15,23,42,.96), rgba(15,23,42,.88));
    }

    .dark .animal-modal-input,
    .dark .animal-modal-select,
    .dark .animal-modal-textarea {
        border-color: rgba(148,163,184,.24);
        background-color: rgba(2,6,23,.70);
        color: #f8fafc;
    }

    .dark .animal-modal-input::placeholder,
    .dark .animal-modal-textarea::placeholder {
        color: #94a3b8;
    }

    .dark .animal-modal-field label,
    .dark .text-gray-900,
    .dark .text-gray-800,
    .dark .font-semibold,
    .dark .font-bold,
    .dark .font-extrabold {
        color: #f8fafc !important;
    }

    .dark .text-gray-500,
    .dark .text-gray-600,
    .dark .text-gray-700 {
        color: #cbd5e1 !important;
    }

    .dark .border-black\/10,
    .dark .border-dashed {
        border-color: rgba(148,163,184,.24) !important;
    }

    .dark .bg-brand\/5 {
        background-color: rgba(34,197,94,.12) !important;
    }

    .dark .bg-brand\/10 {
        background-color: rgba(34,197,94,.16) !important;
    }

    .dark .text-brand {
        color: #86efac !important;
    }

    .dark .animal-chart-wave-labels span {
        color: #cbd5e1;
    }

    .dark .animal-modal-error-box {
        border-color: rgba(248,113,113,.28);
        background: rgba(127,29,29,.28);
        color: #fecaca;
    }

    .dark .bg-amber-50 {
        border-color: rgba(251,191,36,.28) !important;
        background-color: rgba(120,53,15,.28) !important;
        color: #fde68a !important;
    }

    .dark .bg-green-50 {
        border-color: rgba(74,222,128,.28) !important;
        background-color: rgba(20,83,45,.36) !important;
        color: #bbf7d0 !important;
    }

    @media (max-width: 1024px) {
        .animal-carousel-item {
            flex: 0 0 calc(50% - 8px);
        }
    }

    @media (max-width: 640px) {
        .animal-action-card {
            border-radius: 22px;
            padding: 16px;
            min-height: 190px;
        }

        .animal-action-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            margin-bottom: 12px;
        }

        .animal-action-icon svg {
            width: 24px;
            height: 24px;
        }

        .animal-action-card h2 {
            font-size: 18px;
            line-height: 1.18;
        }

        .animal-action-card p {
            font-size: 12px;
            line-height: 1.35;
            margin-top: 8px;
        }

        .animal-action-card .inline-flex {
            margin-top: 14px;
            padding: 7px 10px;
            font-size: 11px;
        }

        .animal-action-card.reproduction {
            min-height: auto;
        }

        .animal-carousel-item {
            flex: 0 0 100%;
        }

        .animal-carousel-image {
            height: 240px;
        }

        .animal-modal-frame {
            padding: 12px;
        }

        .animal-modal-panel {
            width: 100%;
            padding: 16px;
            border-radius: 22px;
        }

        .animal-modal-header {
            margin: -16px -16px 18px -16px;
            padding: 18px 16px 14px 16px;
            border-top-left-radius: 22px;
            border-top-right-radius: 22px;
        }
    }
</style>

<div class="space-y-6">

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <div class="animal-avatar">
                <span class="animal-avatar-icon">{{ $animal->isFemale() ? '🐄' : '🐂' }}</span>
            </div>

            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">
                    {{ $animalName }}
                </h1>

                <p class="text-sm text-gray-500 mt-1">
                    {{ $animalBreed }} · {{ $animalStage }} · {{ $animalAge }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('animals.edit', $animal) }}"
               class="rounded-xl bg-brand/10 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand/15 transition">
                Editar
            </a>

            <a href="{{ route('animals.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-2 xl:grid-cols-3 gap-4 md:gap-6">
        <button type="button"
                class="animal-action-card production text-left"
                @if($canRegisterProduction)
                    data-open-modal="productionModal"
                @else
                    title="{{ $productionBlockedReason }}"
                    disabled
                @endif>
            <div class="animal-action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 3h6v3l2 2v9a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2V8l2-2V3Z"/>
                </svg>
            </div>

            <h2 class="text-2xl font-extrabold text-gray-900">Producción</h2>
            <p class="text-sm text-gray-500 mt-2">
                {{ $canRegisterProduction ? ('Registra rápido ' . ($canRegisterMilkProduction ? 'litros, peso' : 'peso') . ', alimentación y observaciones.') : $productionBlockedReason }}
            </p>

            <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-brand/10 px-4 py-2 text-sm font-bold text-brand">
                {{ $canRegisterProduction ? 'Nuevo registro' : 'Producción bloqueada' }}
            </div>
        </button>

        <button type="button" class="animal-action-card health text-left" data-open-modal="healthModal">
            <div class="animal-action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6.7-4.35-9.2-8.1C.92 10.08 2.07 6.5 5.5 5.4c2.06-.66 4.11.1 5.28 1.75C11.95 5.5 14 4.74 16.06 5.4c3.43 1.1 4.58 4.68 2.7 7.5C18.7 16.65 12 21 12 21Z"/>
                </svg>
            </div>

            <h2 class="text-2xl font-extrabold text-gray-900">Salud</h2>
            <p class="text-sm text-gray-500 mt-2">
                Registra rápido vacunas, diagnósticos, tratamientos y medicamentos.
            </p>

            <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-red-50 px-4 py-2 text-sm font-bold text-red-600">
                Nuevo registro
            </div>
        </button>

        @if($animal->sex === 'hembra')
        <button type="button" class="animal-action-card reproduction text-left col-span-2 xl:col-span-1" data-open-modal="reproductiveModal">
            <div class="animal-action-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 21c4.4-2.7 7-6 7-10.1A6.9 6.9 0 0 0 12 4a6.9 6.9 0 0 0-7 6.9C5 15 7.6 18.3 12 21Z"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9.6 11.3h4.8M12 8.9v4.8"/>
                </svg>
            </div>

            <h2 class="text-2xl font-extrabold text-gray-900">Reproducción</h2>
            <p class="text-sm text-gray-500 mt-2">
                Marca preñez, fecha de servicio, toro usado y estado actual del animal.
            </p>

            <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-brand/10 px-4 py-2 text-sm font-bold text-brand">
                Actualizar estado
            </div>
        </button>
        @endif
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Galería del animal</h2>
                <p class="text-sm text-gray-500 mt-1">Registro visual organizado del animal.</p>
            </div>

            @if($photoCount)
                <div class="inline-flex items-center gap-2 rounded-full border border-black/10 bg-white/70 px-3 py-1.5 text-xs font-semibold text-gray-600">
                    {{ $photoCount }} foto(s)
                </div>
            @endif
        </div>

        @if($photoCount)
            <div class="animal-carousel-shell">
                <div class="animal-carousel-track" id="animalCarouselTrack">
                    @foreach($photos as $photo)
                        <div class="animal-carousel-item">
                            @if($photo->is_main)
                                <div class="animal-carousel-label">Principal</div>
                            @endif
                            <img src="{{ asset('storage/' . $photo->path) }}" alt="Foto del animal" class="animal-carousel-image">
                        </div>
                    @endforeach
                </div>

                @if($photoCount > 3)
                    <button type="button" class="animal-carousel-btn prev" id="animalCarouselPrev" aria-label="Anterior">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5 text-gray-700">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/>
                        </svg>
                    </button>

                    <button type="button" class="animal-carousel-btn next" id="animalCarouselNext" aria-label="Siguiente">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5 text-gray-700">
                            <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                @endif
            </div>

            @if($photoCount > 3)
                <div class="animal-carousel-dots" id="animalCarouselDots"></div>
            @endif
        @else
            <div class="rounded-[24px] border border-dashed border-black/10 bg-white/40 px-6 py-16 text-center">
                <div class="text-5xl mb-3">📷</div>
                <div class="text-lg font-extrabold text-gray-900">Aún no hay fotos registradas</div>
                <p class="text-sm text-gray-500 mt-2">Cuando subas imágenes del animal, aparecerán aquí en carrusel.</p>
            </div>
        @endif
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Producción del animal</h2>
                <p class="text-sm text-gray-500 mt-1">Vista rápida del rendimiento individual.</p>
            </div>

            <a href="{{ route('animals.production', $animal) }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Ver historial completo
            </a>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="animal-chart-card xl:col-span-2">
                <div class="flex items-center justify-between relative z-[2]">
                    <div>
                        <div class="text-sm font-bold text-gray-900">Comportamiento reciente</div>
                        <div class="text-xs text-gray-500 mt-1">
                            {{ $productionChart->count() ? 'Basado en los últimos registros guardados.' : 'Aún no hay datos suficientes para graficar.' }}
                        </div>
                    </div>

                    <div class="text-sm font-extrabold text-brand">Producción</div>
                </div>

                @if($productionChart->count() > 1)
                    <svg viewBox="0 0 720 260" class="w-full h-[260px] mt-4 relative z-[2]">
                        <defs>
                            <linearGradient id="animalProdFill" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(34,197,94,0.22)" />
                                <stop offset="100%" stop-color="rgba(34,197,94,0.02)" />
                            </linearGradient>

                            <linearGradient id="animalProdLine" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0%" stop-color="#22c55e" />
                                <stop offset="100%" stop-color="#a3e635" />
                            </linearGradient>

                            <filter id="animalProdGlow">
                                <feGaussianBlur stdDeviation="4" result="coloredBlur"/>
                                <feMerge>
                                    <feMergeNode in="coloredBlur"/>
                                    <feMergeNode in="SourceGraphic"/>
                                </feMerge>
                            </filter>
                        </defs>

                        <path d="{{ $areaPath }}" fill="url(#animalProdFill)"></path>

                        <path d="{{ $linePath }}"
                              fill="none"
                              stroke="url(#animalProdLine)"
                              stroke-width="4"
                              stroke-linecap="round"
                              filter="url(#animalProdGlow)"
                              opacity=".94"
                              pathLength="100"
                              stroke-dasharray="100"
                              stroke-dashoffset="100">
                            <animate attributeName="stroke-dashoffset" from="100" to="0" dur="1.8s" fill="freeze" />
                        </path>

                        @foreach($points as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#ffffff" opacity=".96"></circle>
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.2" fill="#22c55e"></circle>
                        @endforeach
                    </svg>

                    <div class="animal-chart-wave-labels">
                        @foreach($chartLabels as $label)
                            <span>{{ $label }}</span>
                        @endforeach
                    </div>
                @elseif($productionChart->count() === 1)
                    <div class="mt-6 rounded-2xl border border-black/10 bg-white/55 px-6 py-8 text-center">
                        <div class="text-sm font-bold text-gray-900">Tienes 1 registro disponible</div>
                        <div class="text-sm text-gray-500 mt-2">
                            Agrega más registros para ver la gráfica en ondas.
                        </div>
                    </div>
                @else
                    <div class="mt-6 rounded-2xl border border-dashed border-black/10 bg-white/40 px-6 py-10 text-center text-sm text-gray-500">
                        Cuando registres producción, aquí verás la gráfica del animal.
                    </div>
                @endif
            </div>

            <div class="space-y-4">
                @if($animal->sex === 'hembra')
                <div class="animal-stat-card">
                    <div class="text-gray-500 text-sm">Promedio de litros</div>
                    <div class="font-extrabold text-2xl text-gray-900 mt-1">
                        {{ $productionAverage !== null ? $productionAverage : '—' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $productionAverage !== null ? 'Calculado con registros de producción.' : 'Sin datos suficientes.' }}
                    </div>
                </div>

                <div class="animal-stat-card">
                    <div class="text-gray-500 text-sm">Mejor registro de leche</div>
                    <div class="font-extrabold text-2xl text-gray-900 mt-1">
                        {{ $bestProduction !== null ? $bestProduction.' L' : '—' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $bestProduction !== null ? 'Mayor volumen registrado.' : 'Sin datos suficientes.' }}
                    </div>
                </div>
                @endif

                <div class="animal-stat-card">
                    <div class="text-gray-500 text-sm">Último peso en carne</div>
                    <div class="font-extrabold text-2xl text-gray-900 mt-1">
                        {{ $latestWeight && !empty($latestWeight['weight']) ? $latestWeight['weight'].' kg' : '—' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $latestWeight ? 'Registro de carne más reciente.' : 'Sin registros de carne.' }}
                    </div>
                </div>

                <div class="animal-stat-card">
                    <div class="text-gray-500 text-sm">Último registro</div>
                    <div class="font-extrabold text-lg text-brand mt-1">
                        @if($latestProduction)
                            {{ $latestProduction['date'] ?? '—' }}
                        @else
                            Sin registros
                        @endif
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        @if($latestProduction)
                            {{ !empty($latestProduction['liters']) ? $latestProduction['liters'].' L' : (!empty($latestProduction['weight']) ? $latestProduction['weight'].' kg' : 'Registro guardado') }}
                        @else
                            Agrega producción para empezar el seguimiento.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="glass rounded-[28px] p-6">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900">Últimos registros de producción</h2>
                    <p class="text-sm text-gray-500 mt-1">Vista rápida del animal.</p>
                </div>

                <a href="{{ route('animals.production', $animal) }}"
                   class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                    Ver historial completo
                </a>
            </div>

            <div class="space-y-3">
                @forelse($productionRecords->sortByDesc('date')->take(3) as $record)
                    <div class="animal-history-card">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-gray-900">{{ $record['date'] ?? '—' }}</div>
                            <div class="text-xs font-semibold text-brand text-right">
                                <span class="block">{{ $record['type'] ?? 'Producción' }}</span>
                                {{ !empty($record['liters']) ? $record['liters'].' L' : (!empty($record['weight']) ? $record['weight'].' kg' : 'Registro') }}
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            @if(($record['type'] ?? null) === 'Carne' && !empty($record['weight_gain']))
                                Ganancia: {{ $record['weight_gain'] }} kg
                            @else
                                {{ !empty($record['period']) ? ucfirst($record['period']) : 'Sin período' }}
                            @endif
                        </div>
                        <div class="text-sm text-gray-500 mt-2">
                            {{ $record['notes'] ?? 'Sin observaciones.' }}
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-5 text-sm text-gray-500">
                        Aún no hay registros de producción.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="glass rounded-[28px] p-6">
            <div class="flex items-center justify-between gap-3 mb-5">
                <div>
                    <h2 class="text-lg font-extrabold text-gray-900">Últimos registros de salud</h2>
                    <p class="text-sm text-gray-500 mt-1">Resumen sanitario reciente.</p>
                </div>

                <a href="{{ route('animals.health', $animal) }}"
                   class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                    Ver historial completo
                </a>
            </div>

            <div class="space-y-3">
                @forelse($healthRecords->take(3) as $record)
                    <div class="animal-history-card">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-semibold text-gray-900">{{ $record['date'] ?? '—' }}</div>
                            <div class="text-xs font-semibold text-red-600">
                                {{ $record['treatment_type'] ?? 'Salud' }}
                            </div>
                        </div>
                        <div class="text-sm text-gray-500 mt-2">
                            {{ $record['diagnosis'] ?? $record['notes'] ?? 'Sin observaciones.' }}
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-5 text-sm text-gray-500">
                        Aún no hay registros de salud.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="glass rounded-[28px] p-6 xl:col-span-2">
            <h2 class="text-lg font-extrabold text-gray-900 mb-5">Información general</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 text-sm">
                <div class="animal-stat-card">
                    <div class="text-gray-500">Arete</div>
                    <div class="font-semibold text-gray-900 mt-1">{{ $animal->ear_tag ?: '—' }}</div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Código interno</div>
                    <div class="font-semibold text-gray-900 mt-1">{{ $animal->internal_code ?: '—' }}</div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Sexo</div>
                    <div class="font-semibold text-gray-900 mt-1 capitalize">{{ $animal->sex ?: '—' }}</div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Raza</div>
                    <div class="font-semibold text-gray-900 mt-1">{{ $animal->breed ?: '—' }}</div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Propósito</div>
                    <div class="font-semibold text-gray-900 mt-1 capitalize">
                        {{ $animal->purpose ? str_replace('_', ' ', $animal->purpose) : '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Etapa de desarrollo</div>
                    <div class="font-semibold text-gray-900 mt-1">{{ $developmentStage ?: '—' }}</div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Fecha de nacimiento</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->birth_date ? $animal->birth_date->format('d/m/Y') : '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Edad</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->ageHuman() ?: '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Peso actual</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->weight_current ? $animal->weight_current . ' kg' : '—' }}
                    </div>
                </div>
                @if($animal->sex === 'hembra')
                <div class="animal-stat-card">
                    <div class="text-gray-500">¿Está preñada?</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->is_pregnant ? ucfirst($animal->is_pregnant) : '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Fecha de preñez / servicio</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->pregnancy_date ? $animal->pregnancy_date->format('d/m/Y') : '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Toro del servicio</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        @if($animal->pregnancySire)
                            <a href="{{ route('animals.show', $animal->pregnancySire) }}" class="text-brand hover:underline">
                                {{ $pregnancySireLabel }}
                            </a>
                        @else
                            {{ $pregnancySireLabel ?: '—' }}
                        @endif
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">¿Ha tenido partos?</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->has_calved_before ? ucfirst($animal->has_calved_before) : '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Cantidad de partos</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->calving_count !== null ? $animal->calving_count : '—' }}
                    </div>
                </div>
                <div class="animal-stat-card">
                    <div class="text-gray-500">Último parto</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $animal->last_calving_date ? $animal->last_calving_date->format('d/m/Y') : '—' }}
                    </div>
                </div>
                @endif
                <div class="animal-stat-card">
                    <div class="text-gray-500">Último registro de salud</div>
                    <div class="font-semibold text-gray-900 mt-1">
                        {{ $latestHealth['date'] ?? '—' }}
                    </div>
                </div>
                <div class="animal-stat-card md:col-span-2">
                    <div class="text-gray-500">Ubicación / lote</div>
                    <div class="font-semibold text-gray-900 mt-1">{{ ($animal->isSold() || $animal->isDeceased()) ? 'Fuera de la finca' : ($animal->lot?->name ?: ($animal->location ?: '—')) }}</div>
                </div>
            </div>

            <div class="mt-5">
                <div class="rounded-2xl border border-black/10 bg-white/50 p-4">
                    <div class="text-sm font-semibold text-gray-700 mb-2">Observaciones</div>
                    <div class="text-sm text-gray-600">
                        {{ $plainNotes ?: 'Sin observaciones registradas.' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="glass rounded-[28px] p-6">
            <h2 class="text-lg font-extrabold text-gray-900 mb-5">Resumen rápido</h2>

            <div class="space-y-4 text-sm">
                <div class="rounded-2xl border border-brand/10 bg-brand/5 px-4 py-4">
                    <div class="text-gray-500">Nombre visible</div>
                    <div class="font-bold text-gray-900 mt-1">{{ $animal->name ?: 'Sin nombre' }}</div>
                </div>

                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-4">
                    <div class="text-gray-500">Identificador principal</div>
                    <div class="font-bold text-gray-900 mt-1">
                        {{ $animal->ear_tag ?: ($animal->internal_code ?: 'Sin identificador') }}
                    </div>
                </div>

                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-4">
                    <div class="text-gray-500">Tipo</div>
                    <div class="font-bold text-gray-900 mt-1">
                        {{ $animal->isFemale() ? 'Hembra' : 'Macho' }}
                    </div>
                </div>

                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-4">
                    <div class="text-gray-500">Última producción</div>
                    <div class="font-bold text-gray-900 mt-1">
                        @if($latestProduction)
                            {{ !empty($latestProduction['liters']) ? $latestProduction['liters'].' L' : (!empty($latestProduction['weight']) ? $latestProduction['weight'].' kg' : 'Registro') }}
                        @else
                            Sin registros
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-black/10 bg-white/50 px-4 py-4">
                    <div class="text-gray-500">Última novedad de salud</div>
                    <div class="font-bold text-gray-900 mt-1">
                        {{ $latestHealth['treatment_type'] ?? 'Sin registros' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Genealogía</h2>
            @if(Route::has('genealogy.index'))
                <a href="{{ route('genealogy.index', ['animal' => $animal->id]) }}"
                   class="inline-flex items-center gap-1 rounded-xl bg-green-700 text-white text-sm font-semibold px-4 py-2 hover:bg-green-800 transition">
                    Ver árbol genealógico
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
            <div class="rounded-2xl border border-black/10 bg-white/50 p-4">
                <div class="text-gray-500 mb-1">Madre</div>

                @if($animal->dam)
                    <a href="{{ route('animals.show', $animal->dam) }}" class="font-semibold text-brand hover:underline">
                        {{ $animal->dam->name ?: 'Animal '.$animal->dam->id }}
                    </a>
                @else
                    <div class="font-semibold text-gray-900">{{ $animal->dam_name_manual ?: 'No registrada' }}</div>
                @endif
            </div>

            <div class="rounded-2xl border border-black/10 bg-white/50 p-4">
                <div class="text-gray-500 mb-1">Padre</div>

                @if($animal->sire)
                    <a href="{{ route('animals.show', $animal->sire) }}" class="font-semibold text-brand hover:underline">
                        {{ $animal->sire->name ?: 'Animal '.$animal->sire->id }}
                    </a>
                @else
                    <div class="font-semibold text-gray-900">{{ $animal->sire_name_manual ?: 'No registrado' }}</div>
                @endif
            </div>

            <div class="rounded-2xl border border-black/10 bg-white/50 p-4 md:col-span-2">
                <div class="text-gray-500 mb-2">Crías registradas ({{ $offspring->count() }})</div>
                @if($offspring->isEmpty())
                    <div class="text-sm text-gray-500">Aún no hay crías registradas. Registra cada cría como animal y selecciona a este animal como Madre o Padre para vincularla.</div>
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach($offspring as $cria)
                            <a href="{{ route('animals.show', $cria) }}" class="inline-flex items-center gap-1 rounded-xl border border-black/10 bg-white px-3 py-1.5 text-sm font-semibold text-brand hover:bg-white/80">{{ $cria->name ?: 'Animal '.$cria->id }}@if($cria->ear_tag ?: $cria->internal_code)<span class="text-xs text-gray-400">({{ $cria->ear_tag ?: $cria->internal_code }})</span>@endif</a>
                        @endforeach
                    </div>
                @endif

                @if(($eligibleOffspring ?? collect())->isNotEmpty())
                    <form method="POST" action="{{ route('animals.offspring.attach', $animal) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <select name="child_id" required class="select-search flex-1 rounded-xl border border-black/10 bg-white px-3 py-2 text-sm">
                            <option value="">Selecciona la cría (hijo/a) a vincular</option>
                            @foreach($eligibleOffspring as $cand)
                                <option value="{{ $cand->id }}">{{ $cand->ear_tag ?: ($cand->internal_code ?: 'Sin arete') }} - {{ $cand->name ?: 'Animal '.$cand->id }}@if($cand->farm) · 🏠 {{ $cand->farm->name }}@endif</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand/90">Vincular cría</button>
                    </form>
                    <p class="text-[11px] text-gray-400 mt-1">Se asignará este animal como {{ $animal->sex === 'hembra' ? 'madre' : 'padre' }} de la cría elegida. Puedes elegir crías de cualquiera de tus fincas.</p>
                @endif
            </div>

            <div class="rounded-2xl border border-brand/10 bg-brand/5 p-4 md:col-span-2">
                <div class="text-gray-500 mb-1">Servicio actual / futura cría</div>

                @if($animal->is_pregnant === 'si')
                    <div class="font-semibold text-gray-900">
                        Madre: {{ $animal->name ?: 'este animal' }}
                        @if($pregnancySireLabel)
                            · Toro: {{ $pregnancySireLabel }}
                        @endif
                    </div>
                    <div class="text-xs font-semibold text-gray-500 mt-1">
                        Cuando registres la cría, estos datos sirven como base para conectar su árbol genealógico.
                    </div>
                @else
                    <div class="font-semibold text-gray-900">No hay una preñez activa registrada.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div id="reproductiveModal" class="animal-modal-backdrop">
    <div class="animal-modal-frame">
        <div class="animal-modal-panel">
            <div class="animal-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Seguimiento reproductivo</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $animalName }}</p>
                </div>

                <button type="button"
                        class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700"
                        data-close-modal="reproductiveModal">
                    Cerrar
                </button>
            </div>

            <form method="POST" action="{{ route('animals.reproductive.update', $animal) }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="animal-modal-section">
                    <div class="mb-5">
                        <h4 class="text-lg font-extrabold text-gray-900">Preñez y servicio</h4>
                        <p class="text-sm text-gray-500 mt-1">
                            Estos datos quedan guardados para conectar la futura cría con su madre y con el toro usado.
                        </p>
                    </div>

                    @if($hasReproductiveErrors)
                        <div class="animal-modal-error-box">
                            Revisa los campos del seguimiento reproductivo.
                        </div>
                    @endif

                    @if(! $isFemaleAnimal)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                            Este seguimiento de preñez solo aplica para hembras.
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                        <div class="animal-modal-field">
                            <label>¿Actualmente está preñada?</label>
                            <select name="is_pregnant" id="reproIsPregnant" class="animal-modal-select">
                                <option value="no" {{ old('is_pregnant', $animal->is_pregnant ?: 'no') === 'no' ? 'selected' : '' }}>No</option>
                                <option value="si" {{ old('is_pregnant', $animal->is_pregnant) === 'si' ? 'selected' : '' }}>Sí</option>
                            </select>
                            @error('is_pregnant')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field" data-pregnancy-field>
                            <label>Fecha de preñez / servicio</label>
                            <input type="date" name="pregnancy_date" value="{{ old('pregnancy_date', optional($animal->pregnancy_date)->format('Y-m-d')) }}" class="animal-modal-input" {{ ! $isFemaleAnimal ? 'disabled' : '' }}>
                            @error('pregnancy_date')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field md:col-span-2" data-pregnancy-field>
                            <label>¿Fue preñada por un toro registrado en la plataforma?</label>
                            <select name="pregnancy_sire_id" class="animal-modal-select" {{ ! $isFemaleAnimal ? 'disabled' : '' }}>
                                <option value="">No / no lo sé todavía</option>
                                @foreach($eligibleSires as $sire)
                                    <option value="{{ $sire->id }}" {{ (int) old('pregnancy_sire_id', $animal->pregnancy_sire_id) === (int) $sire->id ? 'selected' : '' }}>
                                        {{ $sire->ear_tag ?: 'Sin arete' }} - {{ $sire->name ?: 'Animal '.$sire->id }}@if($sire->farm) · 🏠 {{ $sire->farm->name }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('pregnancy_sire_id')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field md:col-span-2" data-pregnancy-field>
                            <label>Toro manual / pajilla / referencia externa</label>
                            <input type="text" name="pregnancy_sire_name_manual" value="{{ old('pregnancy_sire_name_manual', $animal->pregnancy_sire_name_manual) }}" class="animal-modal-input" placeholder="Ej: Toro Brahman 421 / Pajilla GYR-009" {{ ! $isFemaleAnimal ? 'disabled' : '' }}>
                            @error('pregnancy_sire_name_manual')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field" data-calving-field>
                            <label>Fecha del parto / último parto</label>
                            <input type="date" name="last_calving_date" value="{{ old('last_calving_date', $animal->is_pregnant === 'si' ? null : optional($animal->last_calving_date)->format('Y-m-d')) }}" class="animal-modal-input" {{ ! $isFemaleAnimal ? 'disabled' : '' }}>
                            @error('last_calving_date')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field" data-calving-field>
                            <label>Cantidad de partos</label>
                            <input type="number" min="0" max="25" step="1" name="calving_count" value="{{ old('calving_count', $animal->calving_count) }}" class="animal-modal-input" {{ ! $isFemaleAnimal ? 'disabled' : '' }}>
                            @error('calving_count')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="animal-modal-section">
                    <div class="mb-5">
                        <h4 class="text-lg font-extrabold text-gray-900">Estado del animal</h4>
                        <p class="text-sm text-gray-500 mt-1">Úsalo cuando necesites pasar el animal a activo, vendido o fallecido.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="animal-modal-field">
                            <label>Estado</label>
                            <select name="status" class="animal-modal-select">
                                @foreach(\App\Models\Animal::statusOptions() as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $animal->status ?: \App\Models\Animal::STATUS_ACTIVE) === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field">
                            <label>Fecha del estado</label>
                            <input type="date" name="status_date" value="{{ old('status_date', optional($animal->status_date)->format('Y-m-d')) }}" class="animal-modal-input">
                            @error('status_date')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field md:col-span-2">
                            <label>Notas del estado</label>
                            <textarea name="status_notes" rows="3" class="animal-modal-textarea" placeholder="Ej: vendido a proveedor, baja sanitaria, traslado interno...">{{ old('status_notes', $animal->status_notes) }}</textarea>
                            @error('status_notes')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                            class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                        Guardar seguimiento
                    </button>

                    <button type="button"
                            class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition"
                            data-close-modal="reproductiveModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="productionModal" class="animal-modal-backdrop">
    <div class="animal-modal-frame">
        <div class="animal-modal-panel">
            <div class="animal-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Nuevo registro de producción</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $animalName }}</p>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('animals.production', $animal) }}"
                       class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                        Ver historial completo
                    </a>

                    <button type="button"
                            class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700"
                            data-close-modal="productionModal">
                        Cerrar
                    </button>
                </div>
            </div>

            @if($canRegisterProduction)
                <form method="POST" action="{{ route('animals.production.store', $animal) }}" class="space-y-6">
                    @csrf

                    <div class="animal-modal-section">
                        <h4 class="text-lg font-extrabold text-gray-900 mb-5">Registro rápido</h4>

                        @if($hasProductionErrors)
                            <div class="animal-modal-error-box">
                                Revisa los campos del registro de producción.
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="animal-modal-field">
                                <label>Fecha</label>
                                <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="animal-modal-input">
                                @error('date')
                                    <span class="animal-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            @if($canRegisterMilkProduction)
                                <div class="animal-modal-field">
                                    <label>Periodo del día *</label>
                                    <select name="period" class="animal-modal-select" data-production-period-select>
                                        <option value="">Selecciona</option>
                                        <option value="mañana" {{ old('period') === 'mañana' ? 'selected' : '' }}>Mañana</option>
                                        <option value="tarde" {{ old('period') === 'tarde' ? 'selected' : '' }}>Tarde</option>
                                        <option value="mañana_tarde" {{ old('period') === 'mañana_tarde' ? 'selected' : '' }}>Mañana y Tarde</option>
                                    </select>
                                    @error('period')
                                        <span class="animal-field-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="animal-modal-field" data-single-liters-field>
                                    <label>Litros de leche</label>
                                    <input type="number" step="0.01" min="0" name="liters" value="{{ old('liters') }}" class="animal-modal-input">
                                    @error('liters')
                                        <span class="animal-field-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="animal-modal-field hidden" data-dual-liters-field>
                                    <label>Litros mañana</label>
                                    <input type="number" step="0.01" min="0" name="liters_morning" value="{{ old('liters_morning') }}" class="animal-modal-input">
                                    @error('liters_morning')
                                        <span class="animal-field-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="animal-modal-field hidden" data-dual-liters-field>
                                    <label>Litros tarde</label>
                                    <input type="number" step="0.01" min="0" name="liters_afternoon" value="{{ old('liters_afternoon') }}" class="animal-modal-input">
                                    @error('liters_afternoon')
                                        <span class="animal-field-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            @endif

                            <div class="animal-modal-field">
                                <label>Peso</label>
                                <input type="number" step="0.01" min="0" max="2000" name="weight" value="{{ old('weight') }}" class="animal-modal-input">
                                @error('weight')
                                    <span class="animal-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="animal-modal-field md:col-span-2">
                                <label>Tipo de alimentación</label>
                                <input type="text" name="feeding_type" value="{{ old('feeding_type') }}" class="animal-modal-input">
                                @error('feeding_type')
                                    <span class="animal-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="animal-modal-field md:col-span-2">
                                <label>Notas</label>
                                <textarea name="notes" rows="2" class="animal-modal-textarea">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <span class="animal-field-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit"
                                class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                            Guardar registro
                        </button>

                        <button type="button"
                                class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition"
                                data-close-modal="productionModal">
                            Cancelar
                        </button>
                    </div>
                </form>
            @else
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-800">
                    {{ $productionBlockedReason }}
                </div>
            @endif
        </div>
    </div>
</div>

<div id="healthModal" class="animal-modal-backdrop">
    <div class="animal-modal-frame">
        <div class="animal-modal-panel">
            <div class="animal-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold text-gray-900">Nuevo registro de salud</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $animalName }}</p>
                </div>

                <div class="flex gap-2">
                    <a href="{{ route('animals.health', $animal) }}"
                       class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                        Ver historial completo
                    </a>

                    <button type="button"
                            class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700"
                            data-close-modal="healthModal">
                        Cerrar
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('animals.health.store', $animal) }}" class="space-y-6">
                @csrf

                <div class="animal-modal-section">
                    <h4 class="text-lg font-extrabold text-gray-900 mb-5">Registro rápido</h4>

                    @if($hasHealthErrors)
                        <div class="animal-modal-error-box">
                            Revisa los campos del registro de salud.
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="animal-modal-field">
                            <label>Fecha</label>
                            <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" class="animal-modal-input">
                            @error('date')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field">
                            <label>Días de tratamiento</label>
                            <input type="number" min="1" name="days" value="{{ old('days') }}" class="animal-modal-input">
                            @error('days')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field">
                            <label>Tipo de tratamiento</label>
                            <select name="treatment_type" class="animal-modal-select">
                                <option value="">Selecciona</option>
                                <option value="Vacuna" {{ old('treatment_type') === 'Vacuna' ? 'selected' : '' }}>Vacuna</option>
                                <option value="Desparasitación" {{ old('treatment_type') === 'Desparasitación' ? 'selected' : '' }}>Desparasitación</option>
                                <option value="Antibiótico" {{ old('treatment_type') === 'Antibiótico' ? 'selected' : '' }}>Antibiótico</option>
                                <option value="Vitaminización" {{ old('treatment_type') === 'Vitaminización' ? 'selected' : '' }}>Vitaminización</option>
                                <option value="Curación" {{ old('treatment_type') === 'Curación' ? 'selected' : '' }}>Curación</option>
                                <option value="Otro" {{ old('treatment_type') === 'Otro' ? 'selected' : '' }}>Otro</option>
                            </select>
                            @error('treatment_type')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field">
                            <label>Enfermedad</label>
                            <select name="disease" class="animal-modal-select">
                                <option value="">Selecciona</option>
                                <option value="Mastitis" {{ old('disease') === 'Mastitis' ? 'selected' : '' }}>Mastitis</option>
                                <option value="Fiebre aftosa" {{ old('disease') === 'Fiebre aftosa' ? 'selected' : '' }}>Fiebre aftosa</option>
                                <option value="Parasitismo" {{ old('disease') === 'Parasitismo' ? 'selected' : '' }}>Parasitismo</option>
                                <option value="Diarrea" {{ old('disease') === 'Diarrea' ? 'selected' : '' }}>Diarrea</option>
                                <option value="Problema respiratorio" {{ old('disease') === 'Problema respiratorio' ? 'selected' : '' }}>Problema respiratorio</option>
                                <option value="Otra" {{ old('disease') === 'Otra' ? 'selected' : '' }}>Otra</option>
                            </select>
                            @error('disease')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field">
                            <label>Diagnóstico</label>
                            <input type="text" name="diagnosis" value="{{ old('diagnosis') }}" class="animal-modal-input">
                            @error('diagnosis')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field">
                            <label>Medicamento</label>
                            <input type="text" name="medication" value="{{ old('medication') }}" class="animal-modal-input">
                            @error('medication')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="animal-modal-field md:col-span-2">
                            <label>Notas y observaciones</label>
                            <textarea name="notes" rows="4" class="animal-modal-textarea">{{ old('notes') }}</textarea>
                            @error('notes')
                                <span class="animal-field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit"
                            class="rounded-xl bg-red-600 hover:bg-red-700 transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                        Guardar registro
                    </button>

                    <button type="button"
                            class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition"
                            data-close-modal="healthModal">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const track = document.getElementById('animalCarouselTrack');
    const prevBtn = document.getElementById('animalCarouselPrev');
    const nextBtn = document.getElementById('animalCarouselNext');
    const dotsWrap = document.getElementById('animalCarouselDots');

    if (track) {
        const items = Array.from(track.children);
        let currentPage = 0;

        const getVisibleItems = () => {
            if (window.innerWidth <= 640) return 1;
            if (window.innerWidth <= 1024) return 2;
            return 3;
        };

        const getTotalPages = () => {
            const visible = getVisibleItems();
            return Math.max(1, Math.ceil(items.length / visible));
        };

        const buildDots = () => {
            if (!dotsWrap) return;
            dotsWrap.innerHTML = '';

            const totalPages = getTotalPages();

            for (let i = 0; i < totalPages; i++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'animal-carousel-dot' + (i === currentPage ? ' active' : '');
                dot.addEventListener('click', () => {
                    currentPage = i;
                    updateCarousel();
                });
                dotsWrap.appendChild(dot);
            }
        };

        const updateButtons = () => {
            if (!prevBtn || !nextBtn) return;
            const totalPages = getTotalPages();
            prevBtn.disabled = currentPage <= 0;
            nextBtn.disabled = currentPage >= totalPages - 1;
        };

        const updateCarousel = () => {
            const visible = getVisibleItems();
            const gap = 16;
            const shellWidth = track.parentElement.clientWidth;
            const itemWidth = (shellWidth - (gap * (visible - 1))) / visible;
            const moveBy = currentPage * visible;
            const offset = moveBy * (itemWidth + gap);

            track.style.transform = `translateX(-${offset}px)`;

            const dots = dotsWrap ? Array.from(dotsWrap.children) : [];
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentPage);
            });

            updateButtons();
        };

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentPage > 0) {
                    currentPage--;
                    updateCarousel();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const totalPages = getTotalPages();
                if (currentPage < totalPages - 1) {
                    currentPage++;
                    updateCarousel();
                }
            });
        }

        window.addEventListener('resize', () => {
            const totalPages = getTotalPages();
            if (currentPage > totalPages - 1) {
                currentPage = totalPages - 1;
            }
            buildDots();
            updateCarousel();
        });

        buildDots();
        updateCarousel();
    }

    const modalIds = ['reproductiveModal', 'productionModal', 'healthModal'];

    modalIds.forEach((id) => {
        const modal = document.getElementById(id);
        if (modal) {
            document.body.appendChild(modal);
        }
    });

    const openButtons = document.querySelectorAll('[data-open-modal]');
    const closeButtons = document.querySelectorAll('[data-close-modal]');

    const openModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.style.display = 'block';
        requestAnimationFrame(() => {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        });
    };

    const closeModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (!modal) return;

        modal.classList.remove('is-open');

        setTimeout(() => {
            modal.style.display = 'none';
            if (!document.querySelector('.animal-modal-backdrop.is-open')) {
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            }
        }, 240);
    };

    const syncProductionPeriodFields = (select) => {
        const scope = select.closest('.animal-modal-panel') || select.form || select.closest('form');
        if (!scope) return;

        const isDual = select.value === 'mañana_tarde';
        scope.querySelectorAll('[data-single-liters-field]').forEach((field) => {
            field.classList.toggle('hidden', isDual);
            field.style.display = isDual ? 'none' : '';
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = isDual;
            });
        });
        scope.querySelectorAll('[data-dual-liters-field]').forEach((field) => {
            field.classList.toggle('hidden', !isDual);
            field.style.display = isDual ? '' : 'none';
            field.querySelectorAll('input, select, textarea').forEach((input) => {
                input.disabled = !isDual;
            });
        });
    };

    document.addEventListener('change', (e) => {
        const select = e.target.closest('[data-production-period-select]');

        if (select) {
            syncProductionPeriodFields(select);
        }
    });

    document.querySelectorAll('[data-production-period-select]').forEach((select) => {
        syncProductionPeriodFields(select);
    });

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.getAttribute('data-open-modal'));
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('.animal-modal-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop || e.target.classList.contains('animal-modal-frame')) {
                closeModal(backdrop.id);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.animal-modal-backdrop.is-open').forEach((modal) => {
                closeModal(modal.id);
            });
        }
    });

    const reproIsPregnant = document.getElementById('reproIsPregnant');
    const pregnancyFields = document.querySelectorAll('[data-pregnancy-field]');
    const calvingFields = document.querySelectorAll('[data-calving-field]');
    const syncPregnancyFields = () => {
        const isPregnant = reproIsPregnant && reproIsPregnant.value === 'si';
        const shouldShowCalving = reproIsPregnant && reproIsPregnant.value === 'no';

        pregnancyFields.forEach((field) => {
            field.style.display = isPregnant ? '' : 'none';
        });

        calvingFields.forEach((field) => {
            field.style.display = shouldShowCalving ? '' : 'none';
        });
    };

    if (reproIsPregnant) {
        reproIsPregnant.addEventListener('change', syncPregnancyFields);
        syncPregnancyFields();
    }

    @if($hasReproductiveErrors)
        openModal('reproductiveModal');
    @elseif($hasProductionErrors)
        openModal('productionModal');
    @elseif($hasHealthErrors)
        openModal('healthModal');
    @endif
});
</script>

@endsection
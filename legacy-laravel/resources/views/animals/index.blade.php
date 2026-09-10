@extends('layouts.app')

@section('title', 'Animales')

@section('content')

@php
    $statusFilter = $status ?? request('status', 'activos');
    $filteredAnimals = $animals;

    $tabs = [
        'activos' => ['label' => 'Activos', 'count' => $activeAnimalsCount ?? 0],
        'fallecidos' => ['label' => 'Fallecidos', 'count' => $deceasedAnimalsCount ?? 0],
        'vendidos' => ['label' => 'Vendidos', 'count' => $soldAnimalsCount ?? 0],
        'todos' => ['label' => 'Todos', 'count' => ($activeAnimalsCount ?? 0) + ($soldAnimalsCount ?? 0) + ($deceasedAnimalsCount ?? 0)],
    ];

    $activeFilterCount = collect([
        $search ?? null,
        $sex ?? null,
        $purpose ?? null,
        $reproduction ?? null,
        $productionStatus ?? null,
        $lotId ?? null,
        (($sort ?? 'latest') !== 'latest') ? ($sort ?? null) : null,
    ])->filter(fn ($value) => filled($value))->count();

    $filtersOpen = $activeFilterCount > 0;
    $transferFarms = $transferFarms ?? collect();
    $transferPanelOpen = $errors->has('animal_ids') || $errors->has('target_farm_id') || $errors->has('target_lot_id');
    $productionErrorFields = ['date', 'period', 'liters', 'weight', 'feeding_type', 'notes'];
    $productionErrorAnimalId = old('_production_animal_id');
    $productionErrorMessage = collect($productionErrorFields)
        ->first(fn ($field) => $errors->has($field));
    $productionErrorMessage = $productionErrorMessage ? $errors->first($productionErrorMessage) : null;
    $transferFarmsForJson = $transferFarms->map(fn ($farm) => [
        'id' => $farm->id,
        'name' => $farm->name,
        'lots' => $farm->lots->map(fn ($lot) => [
            'id' => $lot->id,
            'name' => $lot->name,
        ])->values(),
    ])->values();
@endphp

<style>
    .animals-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .animals-filter-input,
    .animals-filter-select {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,.10);
        background:
            linear-gradient(135deg, rgba(255,255,255,.92), rgba(255,255,255,.72));
        padding: 12px 16px;
        outline: none;
        transition: .18s ease;
        color: #111827;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72), 0 8px 18px rgba(15,23,42,.04);
    }

    .animals-filter-input:focus,
    .animals-filter-select:focus {
        border-color: rgba(22,101,52,.25);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .animals-select-shell {
        position: relative;
    }

    .animals-select-shell::after {
        content: "";
        position: absolute;
        top: 50%;
        right: 16px;
        width: 9px;
        height: 9px;
        border-right: 2px solid rgba(22,101,52,.76);
        border-bottom: 2px solid rgba(22,101,52,.76);
        transform: translateY(-66%) rotate(45deg);
        pointer-events: none;
    }

    .animals-filter-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 44px;
        cursor: pointer;
    }

    .animals-filter-select:hover,
    .animals-filter-input:hover {
        border-color: rgba(22,101,52,.18);
        background:
            linear-gradient(135deg, rgba(255,255,255,.98), rgba(255,255,255,.78));
    }

    .animals-filter-shell {
        overflow: hidden;
        border: 1px solid rgba(0,0,0,.06);
        background:
            radial-gradient(700px 220px at 0% 0%, rgba(34,197,94,.08), transparent 42%),
            rgba(255,255,255,.46);
        border-radius: 24px;
        max-height: 0;
        opacity: 0;
        transform: translateY(-6px);
        transition: max-height .28s ease, opacity .22s ease, transform .22s ease, margin .22s ease;
        margin-bottom: 0;
    }

    .animals-filter-shell.is-open {
        max-height: 620px;
        opacity: 1;
        transform: translateY(0);
        margin-bottom: 24px;
    }

    .animals-filter-inner {
        padding: 18px;
    }

    .animals-filter-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        border-radius: 16px;
        border: 1px solid rgba(22,101,52,.14);
        background: rgba(22,101,52,.08);
        color: #166534;
        padding: 11px 14px;
        font-size: 13px;
        font-weight: 900;
        transition: .18s ease;
    }

    .animals-filter-toggle:hover {
        background: rgba(22,101,52,.12);
        transform: translateY(-1px);
    }

    .animals-filter-toggle-icon {
        width: 18px;
        height: 18px;
        transition: transform .18s ease;
    }

    .animals-filter-toggle[aria-expanded="true"] .animals-filter-toggle-icon {
        transform: rotate(180deg);
    }

    .animals-filter-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 8px;
        border-radius: 999px;
        background: rgba(22,101,52,.12);
        color: #166534;
        font-size: 11px;
        font-weight: 900;
    }

    .animals-tab {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255,255,255,.68);
        color: #374151;
        font-size: 13px;
        font-weight: 800;
        transition: all .18s ease;
        text-decoration: none;
    }

    .animals-tab:hover {
        transform: translateY(-1px);
        background: rgba(255,255,255,.9);
    }

    .animals-tab.active {
        background: rgba(22,101,52,.10);
        color: #166534;
        border-color: rgba(22,101,52,.14);
    }

    .animals-tab-count {
        min-width: 24px;
        height: 24px;
        padding: 0 8px;
        border-radius: 999px;
        background: rgba(17,24,39,.08);
        color: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 900;
    }

    .animals-tab.active .animals-tab-count {
        background: rgba(22,101,52,.14);
        color: #166534;
    }

    .animals-table-wrap {
        position: relative;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: none;
        border-radius: 24px;
        border: 1px solid rgba(0, 0, 0, .06);
        background: rgba(255,255,255,.42);
    }

    .animals-table-wrap::-webkit-scrollbar {
        display: none;
    }

    .animals-table-shell {
        position: relative;
    }

    .animals-table-shell::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: 72px;
        border-top-right-radius: 24px;
        border-bottom-right-radius: 24px;
        background: linear-gradient(90deg, rgba(255,255,255,0), rgba(255,255,255,.86));
        pointer-events: none;
        opacity: .9;
    }

    .animals-scroll-hint {
        display: none;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
        border: 1px solid rgba(22,101,52,.12);
        background: rgba(22,101,52,.07);
        color: #166534;
        border-radius: 999px;
        padding: 9px 12px;
        font-size: 12px;
        font-weight: 900;
    }

    .animals-scroll-hint-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 999px;
        background: rgba(22,101,52,.12);
        flex-shrink: 0;
    }

    .animals-table {
        min-width: 1120px;
    }

    .dark .animals-table-wrap {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.78);
    }

    .dark .animals-table-shell::after {
        background: linear-gradient(90deg, rgba(15,23,42,0), rgba(15,23,42,.92));
    }

    .dark .animals-scroll-hint {
        border-color: rgba(134,239,172,.20);
        background: rgba(34,197,94,.12);
        color: #bbf7d0;
    }

    .dark .animals-scroll-hint-icon {
        background: rgba(134,239,172,.14);
    }

    .dark .animal-table-head tr {
        background: rgba(30,41,59,.72) !important;
        border-color: rgba(148,163,184,.18) !important;
    }

    .dark .animal-table-head th {
        color: #cbd5e1;
    }

    .dark .animal-row {
        border-color: rgba(148,163,184,.14) !important;
    }

    .dark .animal-row:hover {
        background: rgba(30,41,59,.72);
    }

    .dark .animal-row td,
    .dark .animal-row .text-gray-600,
    .dark .animal-row .text-gray-500 {
        color: #cbd5e1;
    }

    .dark .animal-row.is-deceased {
        background: rgba(71,85,105,.24);
    }

    .dark .animal-row.is-sold {
        background: rgba(146,64,14,.18);
    }

    .dark .animal-avatar,
    .dark .animal-stage-pill,
    .dark .animal-quick-btn {
        border-color: rgba(148,163,184,.20);
        background: rgba(30,41,59,.78);
        color: #e2e8f0;
    }

    .dark .animal-sex-pill.male,
    .dark .animal-status-pill.na {
        background: rgba(148,163,184,.16);
        color: #e2e8f0;
    }

    .dark .animal-sex-pill.female,
    .dark .animal-status-pill.milking,
    .dark .animal-status-pill.condition.producing,
    .dark .animal-life-pill.active {
        background: rgba(34,197,94,.16);
        color: #86efac;
        border-color: rgba(134,239,172,.22);
    }

    .dark .animal-status-pill.pregnant {
        background: rgba(236,72,153,.16);
        color: #f9a8d4;
        border-color: rgba(249,168,212,.22);
    }

    .dark .animal-status-pill.not-pregnant {
        background: rgba(148,163,184,.14);
        color: #cbd5e1;
        border-color: rgba(203,213,225,.16);
    }

    .dark .animal-status-pill.not-milking,
    .dark .animal-status-pill.condition.not-producing,
    .dark .animal-life-pill.sold {
        background: rgba(245,158,11,.16);
        color: #fcd34d;
        border-color: rgba(252,211,77,.20);
    }

    .dark .animal-status-pill.condition.producing {
        background: rgba(34,197,94,.16);
        color: #86efac;
        border-color: rgba(134,239,172,.22);
    }

    .dark .animal-life-pill.deceased {
        background: rgba(148,163,184,.14);
        color: #cbd5e1;
        border-color: rgba(203,213,225,.16);
    }

    .dark .animal-production-name {
        color: #f8fafc;
    }

    .dark .animal-production-name.has-production,
    .dark .animal-production-hint {
        color: #86efac !important;
    }

    .dark .animal-row .animal-production-name.has-production,
    .dark .animal-row .animal-production-hint {
        color: #86efac !important;
    }

    .dark .animal-quick-btn.production {
        color: #86efac;
        background: rgba(34,197,94,.14);
        border-color: rgba(134,239,172,.22);
    }

    .dark .animal-quick-btn.health {
        color: #fca5a5;
        background: rgba(239,68,68,.14);
        border-color: rgba(252,165,165,.22);
    }

    .dark .animal-quick-btn.edit {
        color: #e2e8f0;
    }

    .dark .animal-lot-pill {
        background: rgba(34,197,94,.14);
        color: #86efac;
        border-color: rgba(134,239,172,.22);
    }

    .animal-row {
        position: relative;
        transition: background .18s ease, transform .18s ease;
        cursor: pointer;
    }

    .animal-row:hover {
        background: rgba(255,255,255,.72);
    }

    .animal-row td {
        position: relative;
        z-index: 2;
    }

    .animal-row-link {
        position: absolute;
        inset: 0;
        z-index: 1;
        border-radius: 0;
    }

    .animal-row.is-deceased {
        background: rgba(107,114,128,.08);
    }

    .animal-row.is-deceased:hover {
        background: rgba(107,114,128,.14);
    }

    .animal-row.is-sold {
        background: rgba(245,158,11,.07);
    }

    .animal-row.is-sold:hover {
        background: rgba(245,158,11,.12);
    }

    .animal-production-name {
        color: #111827;
    }

    .animal-production-name.has-production {
        color: #166534;
    }

    .animal-production-hint {
        color: #16a34a;
    }

    .animal-avatar {
        width: 52px;
        height: 52px;
        border-radius: 999px;
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .72);
        box-shadow: 0 10px 24px rgba(0, 0, 0, .08), inset 0 1px 0 rgba(255,255,255,.7);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .animal-avatar-icon {
        font-size: 22px;
        line-height: 1;
    }

    .animal-stage-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.68);
        color: #374151;
        white-space: nowrap;
        text-align: center;
    }

    .animal-sex-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
        text-align: center;
    }

    .animal-sex-pill.female {
        background: rgba(22, 101, 52, .10);
        color: #166534;
    }

    .animal-sex-pill.male {
        background: rgba(17, 24, 39, .08);
        color: #111827;
    }

    .animal-status-stack {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px;
    }

    .animal-status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
        border: 1px solid transparent;
        text-align: center;
    }

    .animal-status-pill.pregnant {
        background: rgba(236, 72, 153, .10);
        color: #be185d;
        border-color: rgba(236, 72, 153, .16);
    }

    .animal-status-pill.not-pregnant {
        background: rgba(107, 114, 128, .10);
        color: #4b5563;
        border-color: rgba(107, 114, 128, .14);
    }

    .animal-status-pill.milking {
        background: rgba(22, 101, 52, .10);
        color: #166534;
        border-color: rgba(22, 101, 52, .14);
    }

    .animal-status-pill.condition {
        min-width: 178px;
        justify-content: center;
        font-weight: 900;
    }

    .animal-status-pill.condition.producing {
        background: rgba(22, 101, 52, .10);
        color: #166534;
        border-color: rgba(22, 101, 52, .14);
    }

    .animal-status-pill.condition.not-producing {
        background: rgba(245, 158, 11, .12);
        color: #b45309;
        border-color: rgba(245, 158, 11, .16);
    }

    .animal-status-pill.not-milking {
        background: rgba(245, 158, 11, .12);
        color: #b45309;
        border-color: rgba(245, 158, 11, .16);
    }

    .animal-status-pill.na {
        background: rgba(17, 24, 39, .08);
        color: #374151;
        border-color: rgba(17, 24, 39, .10);
    }

    .animal-life-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 900;
        white-space: nowrap;
        border: 1px solid transparent;
        text-align: center;
    }

    .animal-life-pill.active {
        background: rgba(22,101,52,.10);
        color: #166534;
        border-color: rgba(22,101,52,.14);
    }

    .animal-life-pill.deceased {
        background: rgba(107,114,128,.14);
        color: #4b5563;
        border-color: rgba(107,114,128,.16);
    }

    .animal-life-pill.sold {
        background: rgba(245,158,11,.12);
        color: #b45309;
        border-color: rgba(245,158,11,.16);
    }

    .animal-quick-btn {
        position: relative;
        z-index: 3;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 13px;
        font-weight: 700;
        transition: all .18s ease;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.74);
        color: #374151;
        white-space: nowrap;
        min-height: 38px;
    }

    .animal-quick-btn:hover {
        transform: translateY(-1px);
        background: rgba(255,255,255,.95);
    }

    .animal-quick-btn:disabled {
        cursor: not-allowed;
        opacity: .58;
        transform: none;
    }

    .animal-quick-btn:disabled:hover {
        transform: none;
        background: rgba(255,255,255,.74);
    }

    .animal-quick-btn.production {
        color: #166534;
        background: rgba(22,101,52,.08);
        border-color: rgba(22,101,52,.12);
    }

    .animal-quick-btn.health {
        color: #dc2626;
        background: rgba(239,68,68,.08);
        border-color: rgba(239,68,68,.12);
    }

    .animal-quick-btn.edit {
        color: #374151;
    }

    .animal-col-main {
        min-width: 260px;
    }

    .animal-col-actions {
        min-width: 260px;
    }

    .animal-table-head th {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .animal-empty-state {
        border: 1px dashed rgba(0,0,0,.10);
        background: rgba(255,255,255,.40);
        border-radius: 24px;
    }

    .animal-bulk-transfer {
        border: 1px solid rgba(22,101,52,.12);
        background: rgba(22,101,52,.06);
        border-radius: 22px;
    }

    .animal-bulk-transfer.is-hidden {
        display: none;
    }

    .animal-row-select {
        position: relative;
        z-index: 4;
        width: 18px;
        height: 18px;
        accent-color: #166534;
    }

    .animal-modal-row {
        display: none;
    }

    #animalsResults:not(.is-transfer-mode) .animal-transfer-column {
        display: none;
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
        margin: 0 auto;
        border-radius: 28px;
        border: 1px solid rgba(255,255,255,.55);
        background: rgba(255,255,255,.92);
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

    .animal-modal-form {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .animal-modal-actions {
        position: sticky;
        bottom: -22px;
        z-index: 5;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin: 0 -22px -22px;
        padding: 14px 22px max(14px, env(safe-area-inset-bottom, 0px));
        border-top: 1px solid rgba(15, 23, 42, .08);
        border-bottom-left-radius: 28px;
        border-bottom-right-radius: 28px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 -14px 32px rgba(15, 23, 42, .08);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }

    .animal-modal-action-btn {
        min-height: 46px;
        border-radius: 14px;
        padding: 12px 18px;
        font-size: 14px;
        font-weight: 850;
        transition: transform .18s ease, opacity .18s ease, background .18s ease;
    }

    .animal-modal-action-btn:active {
        transform: scale(.98);
    }

    .animal-modal-error-box {
        border-radius: 18px;
        border: 1px solid rgba(220, 38, 38, .28);
        background: rgba(254, 226, 226, .96);
        color: #991b1b;
        padding: 14px 16px;
        font-size: 14px;
        font-weight: 800;
        line-height: 1.45;
        box-shadow: 0 12px 24px rgba(220, 38, 38, .10);
    }

    @media (max-width: 1024px) {
        .animal-col-actions {
            min-width: 320px;
        }
    }

    @media (max-width: 640px) {
        .animals-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 2px;
            scrollbar-width: none;
        }

        .animals-tabs::-webkit-scrollbar {
            display: none;
        }

        .animals-tab {
            flex: 0 0 auto;
            padding: 9px 12px;
            font-size: 12px;
        }

        .animals-scroll-hint {
            display: flex;
        }

        .animals-table-wrap {
            margin-inline: -2px;
            border-radius: 20px;
        }

        .animals-table-shell::after {
            width: 54px;
            border-top-right-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .animals-table {
            min-width: 1020px;
        }

        .animal-table-head th {
            font-size: 11px;
        }

        .animal-row td {
            padding-top: 12px;
            padding-bottom: 12px;
        }

        .animal-col-main {
            min-width: 225px;
        }

        .animal-col-actions {
            min-width: 260px;
        }

        .animal-avatar {
            width: 44px;
            height: 44px;
        }

        .animal-avatar-icon {
            font-size: 20px;
        }

        .animal-stage-pill,
        .animal-sex-pill,
        .animal-status-pill,
        .animal-life-pill {
            padding: 6px 9px;
            font-size: 11px;
        }

        .animal-status-stack {
            gap: 6px;
            flex-wrap: nowrap;
        }

        .animal-quick-btn {
            padding: 8px 10px;
            border-radius: 12px;
            font-size: 12px;
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

        .animal-modal-actions {
            bottom: -16px;
            margin: 0 -16px -16px;
            padding: 12px 16px max(12px, env(safe-area-inset-bottom, 0px));
            border-bottom-left-radius: 22px;
            border-bottom-right-radius: 22px;
        }

        .animal-modal-action-btn {
            flex: 1;
            padding: 12px 14px;
        }
    }
</style>

<div class="space-y-6">

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any() && ! $productionErrorMessage)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            Revisa los campos del formulario. Hay información pendiente o inválida.
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Animales</h1>
            <p class="text-sm text-gray-500 mt-1">Inventario general de la finca.</p>
        </div>

        <div class="flex flex-wrap gap-3">
            @if($transferFarms->count())
                <button type="button"
                        class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition"
                        data-toggle-bulk-transfer
                        aria-expanded="{{ $transferPanelOpen ? 'true' : 'false' }}"
                        aria-controls="animalBulkTransferPanel">
                    Trasladar animales
                </button>
            @endif

            <a href="{{ route('animals.create') }}"
               class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                + Nuevo animal
            </a>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Estados del inventario</h2>
                <p class="text-sm text-gray-500 mt-1">Filtra rápido por el estado de vida del animal.</p>
            </div>

            <div class="flex flex-col gap-3 lg:items-end">
                <div class="animals-tabs animals-tabs-with-filter">
                    @foreach($tabs as $key => $tab)
                        <a href="{{ route('animals.index', array_filter([
                            'status' => $key,
                            'search' => $search ?? null,
                            'sex' => $sex ?? null,
                            'purpose' => $purpose ?? null,
                            'reproduction' => $reproduction ?? null,
                            'production_status' => $productionStatus ?? null,
                            'lot_id' => $lotId ?? null,
                            'sort' => $sort ?? null,
                        ])) }}"
                           class="animals-tab {{ $statusFilter === $key ? 'active' : '' }}">
                            <span>{{ $tab['label'] }}</span>
                            <span class="animals-tab-count">{{ $tab['count'] }}</span>
                        </a>
                    @endforeach

                    <button type="button"
                            id="animalsFilterToggle"
                            class="animals-filter-toggle"
                            aria-controls="animalsFilterPanel"
                            aria-expanded="{{ $filtersOpen ? 'true' : 'false' }}">
                        <svg class="animals-filter-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                        </svg>
                        <span data-filter-toggle-label>{{ $filtersOpen ? 'Ocultar filtros' : 'Mostrar filtros' }}</span>
                        @if($activeFilterCount > 0)
                            <span class="animals-filter-count">{{ $activeFilterCount }}</span>
                        @endif
                    </button>
                </div>
            </div>
        </div>

        <div id="animalsFilterPanel" class="animals-filter-shell {{ $filtersOpen ? 'is-open' : '' }}">
            <form method="GET" action="{{ route('animals.index') }}" class="animals-filter-inner grid grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-4" data-auto-filter data-ajax-filter data-ajax-target="#animalsResults">
                <input type="hidden" name="status" value="{{ $statusFilter }}">

                <div class="md:col-span-2 xl:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar</label>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Nombre, código, arete o raza"
                        class="animals-filter-input"
                        data-live-search>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Sexo</label>
                    <div class="animals-select-shell">
                        <select name="sex" class="animals-filter-select">
                            <option value="">Cualquiera</option>
                            <option value="hembra" {{ ($sex ?? '') === 'hembra' ? 'selected' : '' }}>Hembras</option>
                            <option value="macho" {{ ($sex ?? '') === 'macho' ? 'selected' : '' }}>Machos</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Propósito</label>
                    <div class="animals-select-shell">
                        <select name="purpose" class="animals-filter-select">
                            <option value="">Cualquiera</option>
                            <option value="leche" {{ ($purpose ?? '') === 'leche' ? 'selected' : '' }}>Leche</option>
                            <option value="carne" {{ ($purpose ?? '') === 'carne' ? 'selected' : '' }}>Carne</option>
                            <option value="doble_proposito" {{ ($purpose ?? '') === 'doble_proposito' ? 'selected' : '' }}>Doble propósito</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Reproducción</label>
                    <div class="animals-select-shell">
                        <select name="reproduction" class="animals-filter-select">
                            <option value="">Cualquiera</option>
                            <option value="pregnant" {{ ($reproduction ?? '') === 'pregnant' ? 'selected' : '' }}>Preñada</option>
                            <option value="not_pregnant" {{ ($reproduction ?? '') === 'not_pregnant' ? 'selected' : '' }}>Sin preñar</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Condición productiva</label>
                    <div class="animals-select-shell">
                        <select name="production_status" class="animals-filter-select">
                            <option value="">Cualquiera</option>
                            <option value="producing" {{ ($productionStatus ?? '') === 'producing' ? 'selected' : '' }}>En producción</option>
                            <option value="not_producing" {{ ($productionStatus ?? '') === 'not_producing' ? 'selected' : '' }}>Sin producción</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Lote</label>
                    <div class="animals-select-shell">
                        <select name="lot_id" class="animals-filter-select">
                            <option value="">Cualquiera</option>
                            @foreach($lots ?? [] as $lot)
                                <option value="{{ $lot->id }}" {{ (string) ($lotId ?? '') === (string) $lot->id ? 'selected' : '' }}>
                                    {{ $lot->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ordenar</label>
                    <div class="animals-select-shell">
                        <select name="sort" class="animals-filter-select">
                            @foreach($sortOptions ?? ['latest' => 'Más recientes'] as $sortValue => $sortLabel)
                                <option value="{{ $sortValue }}" {{ ($sort ?? 'latest') === $sortValue ? 'selected' : '' }}>
                                    {{ $sortLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="md:col-span-2 xl:col-span-7 flex flex-wrap gap-3">
                    <a href="{{ route('animals.index', ['status' => $statusFilter]) }}"
                       class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                        Limpiar
                    </a>

                    <noscript>
                        <button type="submit"
                                class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                            Aplicar filtros
                        </button>
                    </noscript>
                </div>
            </form>
        </div>

        <div id="animalsResults" class="{{ $transferPanelOpen ? 'is-transfer-mode' : '' }}">
        @if($filteredAnimals->count())
            @if($transferFarms->count())
                <div id="animalBulkTransferPanel" class="animal-bulk-transfer {{ $transferPanelOpen ? '' : 'is-hidden' }} mb-5 p-4">
                    <form id="bulkAnimalTransferForm"
                          method="POST"
                          action="{{ route('animals.bulk-transfer') }}"
                          class="grid grid-cols-1 gap-3 lg:grid-cols-[minmax(190px,1fr)_minmax(190px,1fr)_auto] lg:items-end"
                          data-bulk-transfer-form>
                        @csrf

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Finca destino</label>
                            <div class="animals-select-shell">
                                <select name="target_farm_id" class="animals-filter-select" data-transfer-farm-select required>
                                    <option value="">Selecciona finca</option>
                                    @foreach($transferFarms as $farmOption)
                                        <option value="{{ $farmOption->id }}">{{ $farmOption->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Lote destino</label>
                            <div class="animals-select-shell">
                                <select name="target_lot_id" class="animals-filter-select" data-transfer-lot-select disabled>
                                    <option value="">Primero selecciona una finca</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit"
                                class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow"
                                data-transfer-submit>
                            Trasladar seleccionados
                        </button>
                    </form>

                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm font-semibold text-gray-600">
                        <span data-selected-animals-count>0 animales seleccionados</span>
                        <button type="button" class="text-brand font-extrabold" data-clear-animal-selection>Limpiar selección</button>
                    </div>
                </div>
            @endif

            <div class="animals-scroll-hint" aria-hidden="true">
                <span>Desliza la lista para ver más información</span>
                <span class="animals-scroll-hint-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-4 w-4">
                        <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 7l-5 5 5 5M16 7l5 5-5 5M3 12h18"/>
                    </svg>
                </span>
            </div>

            <div class="animals-table-shell">
            <div class="animals-table-wrap">
                <table class="animals-table text-sm">
                    <thead class="animal-table-head">
                        <tr class="border-b border-black/10 text-left text-gray-500 bg-white/35">
                            @if($transferFarms->count())
                                <th class="py-4 pl-5 pr-3 animal-transfer-column">
                                    <input type="checkbox" class="animal-row-select" data-select-all-animals aria-label="Seleccionar todos los animales visibles">
                                </th>
                            @endif
                            <th class="py-4 px-5 animal-col-main">Animal</th>
                            <th class="py-4 pr-5">Arete</th>
                            <th class="py-4 pr-5">Raza</th>
                            <th class="py-4 pr-5">Etapa</th>
                            <th class="py-4 pr-5">Sexo</th>
                            <th class="py-4 pr-5">Estado</th>
                            <th class="py-4 pr-5">Condición</th>
                            <th class="py-4 pr-5">Lote / ubicación</th>
                            <th class="py-4 px-5 animal-col-actions">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($filteredAnimals as $animal)
                            @php
                                $stage = $animal->developmentStage();
                                $isFemale = $animal->isFemale();
                                $canRegisterProduction = $animal->canRegisterProduction();
                                $productionBlockedReason = $animal->productionBlockedReason();
                                $canRegisterMilkProduction = $animal->canRegisterMilkProduction();
                                $milkProductionBlockedReason = $animal->milkProductionBlockedReason();
                                $avatarIcon = $isFemale ? '🐄' : '🐂';
                                    $mainPhoto = $animal->photos->first();
                                    $avatarPhotoUrl = $mainPhoto ? asset('storage/' . $mainPhoto->path) : null;
                                $displayName = $animal->name ?: 'Animal sin nombre';

                                $lifeStatus = $animal->status ?: 'activo';
                                $rowClass = $lifeStatus === 'fallecido'
                                    ? 'is-deceased'
                                    : ($lifeStatus === 'vendido' ? 'is-sold' : '');

                                $isPregnant = ($animal->is_pregnant ?? null) === 'si';

                                $notes = $animal->notes ?? '';
                                $meta = [];

                                if ($notes && preg_match('/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s', $notes, $matches)) {
                                    $decoded = json_decode(trim($matches[1]), true);
                                    $meta = is_array($decoded) ? $decoded : [];
                                }

                                $today = now()->toDateString();
                                $productionRecords = collect($meta['productions'] ?? []);
                                $hasTodayTableProduction = ((int) ($animal->today_milk_productions_count ?? 0) > 0)
                                    || ((int) ($animal->today_meat_productions_count ?? 0) > 0);
                                $hasTodayLegacyProduction = $productionRecords->contains(function ($record) use ($today) {
                                    return ($record['date'] ?? null) === $today;
                                });
                                $hasProductionToday = $hasTodayTableProduction || $hasTodayLegacyProduction;

                                $isProducingMilk = $isFemale
                                    && (((int) ($animal->milk_productions_count ?? 0) > 0)
                                        || $productionRecords->contains(function ($record) {
                                            return isset($record['liters'])
                                                && (float) $record['liters'] > 0;
                                        })
                                        || (in_array($animal->purpose, ['leche', 'doble_proposito'], true)
                                            && (($animal->has_calved_before ?? null) === 'si' || (int) ($animal->calving_count ?? 0) > 0)));

                                $conditionLabel = 'No aplica';
                                $conditionClass = 'na';

                                if ($isFemale && $lifeStatus === 'activo') {
                                    $conditionLabel = ($isPregnant ? 'Preñada' : 'Sin preñar')
                                        . ' · '
                                        . ($isProducingMilk ? 'En producción' : 'Sin producción');
                                    $conditionClass = ($isPregnant ? 'pregnant' : 'not-pregnant')
                                        . ($isProducingMilk ? ' producing' : ' not-producing');
                                }

                                $lifeStatusLabel = match ($lifeStatus) {
                                    'fallecido' => 'Fallecido',
                                    'vendido' => 'Vendido',
                                    default => 'Activo',
                                };

                                $lifeStatusClass = match ($lifeStatus) {
                                    'fallecido' => 'deceased',
                                    'vendido' => 'sold',
                                    default => 'active',
                                };
                            @endphp

                            <tr class="animal-row {{ $rowClass }} border-b border-black/5 last:border-b-0">
                                @if($transferFarms->count())
                                    <td class="py-4 pl-5 pr-3 animal-transfer-column">
                                        <input type="checkbox"
                                               name="animal_ids[]"
                                               value="{{ $animal->id }}"
                                               form="bulkAnimalTransferForm"
                                               class="animal-row-select"
                                               data-animal-select
                                               aria-label="Seleccionar {{ $displayName }}">
                                    </td>
                                @endif

                                <td class="py-4 px-5 animal-col-main">
                                    <a href="{{ route('animals.show', $animal) }}" class="animal-row-link" aria-label="Ver {{ $displayName }}"></a>

                                    <div class="flex items-center gap-4">
                                        <div class="animal-avatar">
                                            @if($avatarPhotoUrl)<img src="{{ $avatarPhotoUrl }}" alt="Foto de {{ $displayName }}" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:999px;display:block;">@else<span class="animal-avatar-icon">{{ $avatarIcon }}</span>@endif
                                        </div>

                                        <div class="min-w-0">
                                            <div class="animal-production-name {{ $hasProductionToday ? 'has-production' : '' }} font-extrabold truncate">
                                                {{ $displayName }}
                                            </div>

                                            <div class="text-xs text-gray-500 mt-1 truncate">
                                                @if($hasProductionToday)
                                                    <span class="animal-production-hint font-bold">Producción registrada hoy</span>
                                                    <span class="text-gray-400">·</span>
                                                @endif
                                                {{ $animal->internal_code ?: 'Sin código interno' }}
                                            </div>
                                            @if($animal->ageHuman())
                                                <div class="text-xs text-gray-400 mt-0.5 truncate">Edad: {{ $animal->ageHuman() }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $animal->ear_tag ?: '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $animal->breed ?: '—' }}
                                </td>

                                <td class="py-4 pr-5">
                                    <span class="animal-stage-pill">
                                        {{ $stage ?: '—' }}
                                    </span>
                                </td>

                                <td class="py-4 pr-5">
                                    <span class="animal-sex-pill {{ $isFemale ? 'female' : 'male' }}">
                                        {{ $isFemale ? 'Hembra' : 'Macho' }}
                                    </span>
                                </td>

                                <td class="py-4 pr-5">
                                    <div class="flex flex-col items-center gap-2 text-center">
                                        <span class="animal-life-pill {{ $lifeStatusClass }}">
                                            {{ $lifeStatusLabel }}
                                        </span>

                                        @if(!empty($animal->status_date))
                                            <span class="text-xs text-gray-500">
                                                {{ \Carbon\Carbon::parse($animal->status_date)->format('d/m/Y') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>

                                <td class="py-4 pr-5">
                                    <div class="animal-status-stack">
                                        <span class="animal-status-pill condition {{ $conditionClass }}">
                                            {{ $conditionLabel }}
                                        </span>
                                    </div>
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    @if($lifeStatus !== 'activo')
                                        <span class="text-xs text-gray-400">Fuera de la finca</span>
                                    @elseif($animal->lot)
                                        <span class="animal-lot-pill inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-3 py-1 text-xs font-bold border border-emerald-100">
                                            {{ $animal->lot->name }}
                                        </span>
                                    @elseif($animal->location)
                                        {{ $animal->location }}
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="py-4 px-5 animal-col-actions">
                                    <div class="flex flex-nowrap gap-2">
                                        <a href="{{ route('animals.edit', $animal) }}"
                                           class="animal-quick-btn edit">
                                            Editar
                                        </a>

                                        @if($canRegisterProduction)
                                            <button type="button"
                                                    class="animal-quick-btn production"
                                                    data-open-modal="productionModal-{{ $animal->id }}">
                                                Producción
                                            </button>
                                        @else
                                            <button type="button"
                                                    class="animal-quick-btn production"
                                                    title="{{ $productionBlockedReason }}"
                                                    disabled>
                                                Producción
                                            </button>
                                        @endif

                                        <button type="button"
                                                class="animal-quick-btn health"
                                                data-open-modal="healthModal-{{ $animal->id }}">
                                            Salud
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr class="animal-modal-row" aria-hidden="true">
                                <td colspan="{{ $transferFarms->count() ? 10 : 9 }}">
                            <div id="productionModal-{{ $animal->id }}" class="animal-modal-backdrop">
                                <div class="animal-modal-frame">
                                    <div class="animal-modal-panel">
                                        <div class="animal-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <h3 class="text-2xl font-extrabold text-gray-900">Nuevo registro de producción</h3>
                                                <p class="text-sm text-gray-500 mt-1">{{ $displayName }}</p>
                                            </div>

                                            <div class="flex gap-2">
                                                <a href="{{ route('animals.production', $animal) }}"
                                                   class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition relative z-[100000]">
                                                    Ver historial completo
                                                </a>

                                                <button type="button"
                                                        class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700"
                                                        data-close-modal="productionModal-{{ $animal->id }}">
                                                    Cerrar
                                                </button>
                                            </div>
                                        </div>

                                        @if($canRegisterProduction)
                                            <form method="POST" action="{{ route('animals.production.store', $animal) }}" class="animal-modal-form" data-animal-production-form>
                                                @csrf
                                                <input type="hidden" name="_production_animal_id" value="{{ $animal->id }}">
                                                <input type="hidden" name="_return_to" value="{{ request()->fullUrl() }}">

                                                <div class="animal-modal-section">
                                                    <h4 class="text-lg font-extrabold text-gray-900 mb-5">Registro rápido</h4>

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                                        @if((string) $productionErrorAnimalId === (string) $animal->id && $productionErrorMessage)
                                                            <div class="animal-modal-error-box md:col-span-2">
                                                                {{ $productionErrorMessage }}
                                                            </div>
                                                        @endif

                                                        <div class="animal-modal-field">
                                                            <label>Fecha</label>
                                                            <input type="date" name="date" value="{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('date', now()->format('Y-m-d')) : now()->format('Y-m-d') }}" class="animal-modal-input" required>
                                                            @if((string) $productionErrorAnimalId === (string) $animal->id)
                                                                @error('date')
                                                                    <span class="text-sm text-red-600">{{ $message }}</span>
                                                                @enderror
                                                            @endif
                                                        </div>

                                                        @if($canRegisterMilkProduction)
                                                            <div class="animal-modal-field">
                                                                <label>Periodo del día</label>
                                                                <select name="period" class="animal-modal-select" data-production-period-select>
                                                                    <option value="">Selecciona</option>
                                                                    <option value="mañana" @selected((string) $productionErrorAnimalId === (string) $animal->id && old('period') === 'mañana')>Mañana</option>
                                                                    <option value="tarde" @selected((string) $productionErrorAnimalId === (string) $animal->id && old('period') === 'tarde')>Tarde</option>
                                                                    <option value="mañana_tarde" @selected((string) $productionErrorAnimalId === (string) $animal->id && old('period') === 'mañana_tarde')>Mañana y Tarde</option>
                                                                </select>
                                                            </div>

                                                            <div class="animal-modal-field" data-single-liters-field>
                                                                <label>Litros de leche</label>
                                                                <input type="number" step="0.01" min="0" name="liters" value="{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('liters') : '' }}" class="animal-modal-input">
                                                                @if((string) $productionErrorAnimalId === (string) $animal->id)
                                                                    @error('liters')
                                                                        <span class="text-sm text-red-600">{{ $message }}</span>
                                                                    @enderror
                                                                @endif
                                                            </div>

                                                            <div class="animal-modal-field hidden" data-dual-liters-field>
                                                                <label>Litros mañana</label>
                                                                <input type="number" step="0.01" min="0" name="liters_morning" value="{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('liters_morning') : '' }}" class="animal-modal-input">
                                                            </div>

                                                            <div class="animal-modal-field hidden" data-dual-liters-field>
                                                                <label>Litros tarde</label>
                                                                <input type="number" step="0.01" min="0" name="liters_afternoon" value="{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('liters_afternoon') : '' }}" class="animal-modal-input">
                                                            </div>
                                                        @endif

                                                        <div class="animal-modal-field">
                                                            <label>Peso</label>
                                                            <input type="number" step="0.01" min="0" max="2000" name="weight" value="{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('weight') : '' }}" class="animal-modal-input">
                                                            @if((string) $productionErrorAnimalId === (string) $animal->id)
                                                                @error('weight')
                                                                    <span class="text-sm text-red-600">{{ $message }}</span>
                                                                @enderror
                                                            @endif
                                                        </div>

                                                        <div class="animal-modal-field md:col-span-2">
                                                            <label>Tipo de alimentación</label>
                                                            <input type="text" name="feeding_type" value="{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('feeding_type') : '' }}" class="animal-modal-input">
                                                        </div>

                                                        <div class="animal-modal-field md:col-span-2">
                                                            <label>Notas</label>
                                                            <textarea name="notes" rows="2" class="animal-modal-textarea">{{ (string) $productionErrorAnimalId === (string) $animal->id ? old('notes') : '' }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="animal-modal-actions">
                                                    <button type="submit"
                                                            class="animal-modal-action-btn bg-brand hover:bg-brand-dark text-white shadow-glow"
                                                            data-submit-label="Guardar registro"
                                                            data-animal-production-submit>
                                                        Guardar registro
                                                    </button>

                                                    <button type="button"
                                                            class="animal-modal-action-btn border border-black/10 bg-white/80 text-gray-700 hover:bg-white"
                                                            data-close-modal="productionModal-{{ $animal->id }}">
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

                            <div id="healthModal-{{ $animal->id }}" class="animal-modal-backdrop">
                                <div class="animal-modal-frame">
                                    <div class="animal-modal-panel">
                                        <div class="animal-modal-header flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                            <div>
                                                <h3 class="text-2xl font-extrabold text-gray-900">Nuevo registro de salud</h3>
                                                <p class="text-sm text-gray-500 mt-1">{{ $displayName }}</p>
                                            </div>

                                            <div class="flex gap-2">
                                                <a href="{{ route('animals.health', $animal) }}"
                                                   class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition relative z-[100000]">
                                                    Ver historial completo
                                                </a>

                                                <button type="button"
                                                        class="rounded-xl border border-black/10 bg-white/80 px-4 py-2 text-sm font-semibold text-gray-700"
                                                        data-close-modal="healthModal-{{ $animal->id }}">
                                                    Cerrar
                                                </button>
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('animals.health.store', $animal) }}" class="animal-modal-form">
                                            @csrf

                                            <div class="animal-modal-section">
                                                <h4 class="text-lg font-extrabold text-gray-900 mb-5">Registro rápido</h4>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                                    <div class="animal-modal-field">
                                                        <label>Fecha</label>
                                                        <input type="date" name="date" value="{{ now()->format('Y-m-d') }}" class="animal-modal-input">
                                                    </div>

                                                    <div class="animal-modal-field">
                                                        <label>Días de tratamiento</label>
                                                        <input type="number" min="1" name="days" class="animal-modal-input">
                                                    </div>

                                                    <div class="animal-modal-field">
                                                        <label>Tipo de tratamiento</label>
                                                        <select name="treatment_type" class="animal-modal-select">
                                                            <option value="">Selecciona</option>
                                                            <option value="Vacuna">Vacuna</option>
                                                            <option value="Desparasitación">Desparasitación</option>
                                                            <option value="Antibiótico">Antibiótico</option>
                                                            <option value="Vitaminización">Vitaminización</option>
                                                            <option value="Curación">Curación</option>
                                                            <option value="Otro">Otro</option>
                                                        </select>
                                                    </div>

                                                    <div class="animal-modal-field">
                                                        <label>Enfermedad</label>
                                                        <select name="disease" class="animal-modal-select">
                                                            <option value="">Selecciona</option>
                                                            <option value="Mastitis">Mastitis</option>
                                                            <option value="Fiebre aftosa">Fiebre aftosa</option>
                                                            <option value="Parasitismo">Parasitismo</option>
                                                            <option value="Diarrea">Diarrea</option>
                                                            <option value="Problema respiratorio">Problema respiratorio</option>
                                                            <option value="Otra">Otra</option>
                                                        </select>
                                                    </div>

                                                    <div class="animal-modal-field">
                                                        <label>Diagnóstico</label>
                                                        <input type="text" name="diagnosis" class="animal-modal-input">
                                                    </div>

                                                    <div class="animal-modal-field">
                                                        <label>Medicamento</label>
                                                        <input type="text" name="medication" class="animal-modal-input">
                                                    </div>

                                                    <div class="animal-modal-field md:col-span-2">
                                                        <label>Notas y observaciones</label>
                                                        <textarea name="notes" rows="4" class="animal-modal-textarea"></textarea>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="animal-modal-actions">
                                                <button type="submit"
                                                        class="animal-modal-action-btn bg-red-600 hover:bg-red-700 text-white shadow-glow">
                                                    Guardar registro
                                                </button>

                                                <button type="button"
                                                        class="animal-modal-action-btn border border-black/10 bg-white/80 text-gray-700 hover:bg-white"
                                                        data-close-modal="healthModal-{{ $animal->id }}">
                                                    Cancelar
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="text-sm font-semibold text-gray-500">
                    Mostrando {{ $filteredAnimals->firstItem() }}-{{ $filteredAnimals->lastItem() }} de {{ $filteredAnimals->total() }} animales
                </div>

                <div>
                    {{ $filteredAnimals->links() }}
                </div>
            </div>
        @else
            <div class="animal-empty-state px-6 py-10 text-center">
                <div class="text-lg font-extrabold text-gray-900">
                    @if($activeFilterCount > 0)
                        No encontramos animales con esos filtros
                    @elseif($statusFilter === 'fallecidos')
                        No hay animales fallecidos registrados
                    @elseif($statusFilter === 'vendidos')
                        No hay animales vendidos registrados
                    @elseif($statusFilter === 'todos')
                        Aún no hay animales registrados
                    @else
                        No hay animales activos registrados
                    @endif
                </div>

                <p class="text-sm text-gray-500 mt-2">
                    @if($activeFilterCount > 0)
                        Revisa el texto de búsqueda o cambia los filtros aplicados.
                    @elseif($statusFilter === 'todos')
                        Empieza agregando el primer animal a tu finca.
                    @else
                        Cambia de pestaña o registra un nuevo animal.
                    @endif
                </p>

                @if($activeFilterCount > 0)
                    <a href="{{ route('animals.index', ['status' => $statusFilter]) }}"
                       class="mt-5 inline-flex rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                        Limpiar búsqueda
                    </a>
                @else
                    <a href="{{ route('animals.create') }}"
                       class="mt-5 inline-flex rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                        + Crear animal
                    </a>
                @endif
            </div>
        @endif
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const transferFarms = @json($transferFarmsForJson);
    const animalsFilterToggle = document.getElementById('animalsFilterToggle');
    const animalsFilterPanel = document.getElementById('animalsFilterPanel');
    const animalsFilterToggleLabel = animalsFilterToggle?.querySelector('[data-filter-toggle-label]');
    const animalsFilterForm = document.querySelector('form[data-ajax-filter][data-ajax-target="#animalsResults"]');
    const bulkTransferToggle = document.querySelector('[data-toggle-bulk-transfer]');

    bulkTransferToggle?.addEventListener('click', () => {
        const panel = document.getElementById('animalBulkTransferPanel');
        const results = document.getElementById('animalsResults');
        if (!panel) return;

        const isHidden = panel.classList.toggle('is-hidden');
        results?.classList.toggle('is-transfer-mode', !isHidden);
        bulkTransferToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
    });

    if (animalsFilterToggle && animalsFilterPanel && animalsFilterToggleLabel) {
        animalsFilterToggle.addEventListener('click', () => {
            const isOpen = animalsFilterPanel.classList.toggle('is-open');

            animalsFilterToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            animalsFilterToggleLabel.textContent = isOpen ? 'Ocultar filtros' : 'Mostrar filtros';
        });
    }

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

    const syncAllProductionPeriodFields = () => {
        document.querySelectorAll('[data-production-period-select]').forEach((select) => {
            syncProductionPeriodFields(select);
        });
    };

    document.addEventListener('change', (e) => {
        const select = e.target.closest('[data-production-period-select]');

        if (select) {
            syncProductionPeriodFields(select);
        }
    });

    const bindAnimalModals = (root = document) => {
        root.querySelectorAll('.animal-modal-backdrop').forEach((modal) => {
            document.body.appendChild(modal);
        });

        root.querySelectorAll('[data-open-modal]').forEach((button) => {
            if (button.dataset.modalBound === 'true') return;

            button.dataset.modalBound = 'true';
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openModal(button.getAttribute('data-open-modal'));
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            if (button.dataset.modalBound === 'true') return;

            button.dataset.modalBound = 'true';
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeModal(button.getAttribute('data-close-modal'));
            });
        });

        document.querySelectorAll('.animal-modal-backdrop').forEach((backdrop) => {
            if (backdrop.dataset.modalBackdropBound === 'true') return;

            backdrop.dataset.modalBackdropBound = 'true';
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop || e.target.classList.contains('animal-modal-frame')) {
                    closeModal(backdrop.id);
                }
            });
        });

        syncAllProductionPeriodFields();
    };

    const markProductionFormSubmitting = (form) => {
        if (!form || form.dataset.submitting === 'true') return false;

        form.dataset.submitting = 'true';
        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton) {
            submitButton.textContent = 'Guardando...';
            submitButton.style.opacity = '.78';
        }

        return true;
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-animal-production-submit]');
        if (!button) return;

        const form = button.form;
        if (!form) return;

        event.preventDefault();
        event.stopPropagation();

        if (form.dataset.submitting === 'true') return;

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (!navigator.onLine && typeof form.requestSubmit === 'function') {
            form.requestSubmit(button);
            return;
        }

        markProductionFormSubmitting(form);
        HTMLFormElement.prototype.submit.call(form);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-animal-production-form]');
        if (!form) return;

        markProductionFormSubmitting(form);
    });

    const bindBulkTransfer = (root = document) => {
        const form = root.querySelector('[data-bulk-transfer-form]');
        const farmSelect = root.querySelector('[data-transfer-farm-select]');
        const lotSelect = root.querySelector('[data-transfer-lot-select]');
        const selectAll = root.querySelector('[data-select-all-animals]');
        const checkboxes = Array.from(root.querySelectorAll('[data-animal-select]'));
        const selectedCount = root.querySelector('[data-selected-animals-count]');
        const clearSelection = root.querySelector('[data-clear-animal-selection]');

        if (!form || form.dataset.bulkBound === 'true') return;

        form.dataset.bulkBound = 'true';

        const updateSelectedCount = () => {
            const count = checkboxes.filter((checkbox) => checkbox.checked).length;

            if (selectedCount) {
                selectedCount.textContent = `${count} ${count === 1 ? 'animal seleccionado' : 'animales seleccionados'}`;
            }

            if (selectAll) {
                selectAll.checked = count > 0 && count === checkboxes.length;
                selectAll.indeterminate = count > 0 && count < checkboxes.length;
            }
        };

        const rebuildLots = () => {
            if (!farmSelect || !lotSelect) return;

            const farm = transferFarms.find((item) => String(item.id) === String(farmSelect.value));
            lotSelect.innerHTML = '';

            if (!farm) {
                lotSelect.disabled = true;
                lotSelect.append(new Option('Primero selecciona una finca', ''));
                return;
            }

            lotSelect.disabled = false;
            lotSelect.append(new Option('Sin lote asignado', ''));
            farm.lots.forEach((lot) => lotSelect.append(new Option(lot.name, lot.id)));
        };

        farmSelect?.addEventListener('change', rebuildLots);

        selectAll?.addEventListener('change', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAll.checked;
            });
            updateSelectedCount();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateSelectedCount);
        });

        clearSelection?.addEventListener('click', () => {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = false;
            });
            updateSelectedCount();
        });

        form.addEventListener('submit', (event) => {
            if (!checkboxes.some((checkbox) => checkbox.checked)) {
                event.preventDefault();
                alert('Selecciona al menos un animal para trasladar.');
            }
        });

        rebuildLots();
        updateSelectedCount();
    };

    const buildFilterUrl = (form) => {
        const url = new URL(form.action, window.location.origin);
        const params = new URLSearchParams(new FormData(form));

        Array.from(params.keys()).forEach((key) => {
            const value = params.get(key);

            if (value === null || value.trim() === '') {
                params.delete(key);
            }
        });

        url.search = params.toString();

        return url;
    };

    const fetchAnimalsResults = async () => {
        if (!animalsFilterForm) return;

        const targetSelector = animalsFilterForm.dataset.ajaxTarget;
        const target = document.querySelector(targetSelector);

        if (!target) return;

        const url = buildFilterUrl(animalsFilterForm);

        target.style.opacity = '.55';
        target.style.pointerEvents = 'none';

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nextTarget = doc.querySelector(targetSelector);

            if (!nextTarget) {
                window.location.href = url.toString();
                return;
            }

            document.querySelectorAll('.animal-modal-backdrop').forEach((modal) => modal.remove());

            target.replaceWith(nextTarget);
            window.history.replaceState({}, '', url.toString());

            bindAnimalModals(nextTarget);
            bindBulkTransfer(nextTarget);
        } catch (error) {
            console.error('Error filtrando animales:', error);
            window.location.href = url.toString();
        } finally {
            const updatedTarget = document.querySelector(targetSelector);

            if (updatedTarget) {
                updatedTarget.style.opacity = '';
                updatedTarget.style.pointerEvents = '';
            }
        }
    };

    if (animalsFilterForm) {
        animalsFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            fetchAnimalsResults();
        });

        animalsFilterForm.addEventListener('auto-filter:submit', (event) => {
            event.preventDefault();
            fetchAnimalsResults();
        });
    }

    bindAnimalModals(document);
    bindBulkTransfer(document);

    @if($productionErrorAnimalId)
        openModal('productionModal-{{ $productionErrorAnimalId }}');
    @endif

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.animal-modal-backdrop.is-open').forEach((modal) => {
                closeModal(modal.id);
            });
        }
    });
});
</script>

@endsection
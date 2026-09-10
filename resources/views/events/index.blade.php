@extends('layouts.app')

@section('title', 'Eventos')

@section('content')

@php
    $animals = $animals ?? collect();
    $upcomingEvents = $upcomingEvents ?? collect();
    $eventsListInitialLimit = 8;
    $eventsListTotal = $upcomingEvents->count();
    $eventsListVisibleInitial = min($eventsListInitialLimit, $eventsListTotal);
@endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">

<style>
    .events-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.64);
        border-radius: 28px;
        padding: 24px;
        box-shadow: 0 14px 32px rgba(0,0,0,.08);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
    }

    .events-mini-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.56);
        border-radius: 20px;
        padding: 16px;
        box-shadow: 0 10px 24px rgba(0,0,0,.06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        transition: .18s ease;
    }

    .events-mini-card.clickable {
        cursor: pointer;
    }

    .events-mini-card.clickable:hover {
        transform: translateY(-2px);
        background: rgba(255,255,255,.82);
        box-shadow: 0 14px 28px rgba(0,0,0,.09);
    }

    .events-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        padding: 6px 11px;
        font-size: 11px;
        font-weight: 800;
    }

    .events-badge.pending {
        background: rgba(245, 158, 11, .12);
        color: #b45309;
    }

    .events-badge.completed {
        background: rgba(22, 101, 52, .12);
        color: #166534;
    }

    .events-badge.cancelled {
        background: rgba(107, 114, 128, .14);
        color: #4b5563;
    }

    .events-badge.automatic {
        background: rgba(22, 101, 52, .10);
        color: #166534;
    }

    .events-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        flex-shrink: 0;
    }

    .events-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 18px;
    }

    .events-toolbar-left,
    .events-toolbar-right {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }

    .events-nav-btn,
    .events-view-btn,
    .events-filter-btn {
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.86);
        color: #374151;
        box-shadow: 0 8px 18px rgba(0,0,0,.06);
        padding: 10px 14px;
        font-size: 14px;
        font-weight: 700;
        line-height: 1;
        transition: .18s ease;
    }

    .events-nav-btn:hover,
    .events-view-btn:hover,
    .events-filter-btn:hover {
        background: rgba(255,255,255,1);
        color: #111827;
    }

    .events-view-btn.is-active {
        background: rgba(22,101,52,.12);
        color: #166534;
        border-color: rgba(22,101,52,.15);
    }

    .events-toolbar-title {
        font-size: 1.3rem;
        font-weight: 800;
        color: #111827;
        min-width: 180px;
    }

    .events-filter-wrap {
        position: relative;
    }

    .events-filter-popover {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: min(340px, calc(100vw - 32px));
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.96);
        border-radius: 22px;
        padding: 18px;
        box-shadow: 0 18px 45px rgba(0,0,0,.12);
        z-index: 60;
        display: none;
    }

    .events-filter-popover.is-open {
        display: block;
    }

    .events-filter-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .events-filter-label {
        display: block;
        margin-bottom: 6px;
        font-size: 12px;
        font-weight: 700;
        color: #4b5563;
    }

    .events-filter-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.90);
        padding: 10px 12px;
        outline: none;
        transition: .2s ease;
        color: #111827;
        font-size: 13px;
    }

    .events-filter-input:focus {
        border-color: rgba(22,101,52,.35);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .events-scroll {
        max-height: 650px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .events-scroll::-webkit-scrollbar {
        width: 8px;
    }

    .events-scroll::-webkit-scrollbar-track {
        background: rgba(0,0,0,.04);
        border-radius: 999px;
    }

    .events-scroll::-webkit-scrollbar-thumb {
        background: rgba(22,101,52,.28);
        border-radius: 999px;
    }

    .events-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(22,101,52,.40);
    }

    .events-load-more {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(22,101,52,.16);
        background: rgba(22,101,52,.10);
        color: #166534;
        padding: 11px 14px;
        font-size: 13px;
        font-weight: 800;
        transition: .18s ease;
    }

    .events-load-more:hover {
        background: rgba(22,101,52,.16);
    }

    .event-detail-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }

    .event-detail-actions.hidden {
        display: none;
    }

    .event-detail-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.86);
        color: #374151;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 800;
        transition: .18s ease;
    }

    .event-detail-action-btn:hover {
        background: #fff;
        color: #111827;
    }

    .event-detail-action-btn.primary {
        border-color: rgba(22,101,52,.22);
        background: rgba(22,101,52,.12);
        color: #166534;
    }

    .event-detail-action-btn.danger {
        border-color: rgba(220,38,38,.20);
        background: rgba(220,38,38,.10);
        color: #dc2626;
    }

    .event-detail-edit-form {
        display: none;
        margin-top: 18px;
        border-top: 1px solid rgba(0,0,0,.08);
        padding-top: 18px;
    }

    .event-detail-edit-form.is-open {
        display: block;
    }

    .fc {
        --fc-border-color: rgba(0,0,0,.08);
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: rgba(255,255,255,.42);
        --fc-list-event-hover-bg-color: rgba(22,101,52,.06);
        --fc-today-bg-color: rgba(22,101,52,.10);
        --fc-now-indicator-color: #dc2626;
    }

    .fc .fc-header-toolbar {
        display: none !important;
    }

    .fc .fc-scrollgrid,
    .fc .fc-timegrid-slot,
    .fc .fc-timegrid-axis,
    .fc .fc-col-header-cell,
    .fc .fc-daygrid-day {
        background: transparent !important;
    }

    .fc .fc-daygrid-day-frame,
    .fc .fc-timegrid-col-frame {
        min-height: 108px;
    }

    .fc .fc-col-header-cell-cushion,
    .fc .fc-daygrid-day-number,
    .fc .fc-timegrid-axis-cushion {
        color: #374151;
        font-weight: 700;
        text-decoration: none !important;
        font-size: 13px;
    }

    .fc .fc-daygrid-day.fc-day-today {
        background: rgba(22,101,52,.08) !important;
        box-shadow: inset 0 0 0 2px rgba(22,101,52,.14);
        border-radius: 16px;
    }

    .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 28px;
        height: 28px;
        margin: 6px;
        border-radius: 999px;
        background: #166534;
        color: #fff !important;
        font-weight: 800;
        font-size: 12px;
    }

    .fc .fc-event {
        border: none !important;
        border-radius: 10px !important;
        padding: 2px 6px !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        box-shadow: 0 8px 18px rgba(0,0,0,.10);
        cursor: pointer;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .fc .fc-event:hover {
        transform: translateY(-1px);
    }

    .fc-event-highlight {
        box-shadow: 0 0 0 3px rgba(34,197,94,.35), 0 12px 24px rgba(0,0,0,.16) !important;
        transform: scale(1.02);
    }

    .events-year-wrap {
        display: none;
    }

    .events-year-wrap.is-active {
        display: block;
    }

    .events-year-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .events-year-month {
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.44);
        border-radius: 22px;
        padding: 14px;
    }

    .events-year-month-title {
        font-size: 15px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 10px;
    }

    .events-year-weekdays,
    .events-year-days {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 4px;
    }

    .events-year-weekday {
        font-size: 10px;
        font-weight: 700;
        color: #6b7280;
        text-align: center;
        padding-bottom: 4px;
    }

    .events-year-day {
        min-height: 38px;
        border-radius: 12px;
        padding: 4px 2px;
        text-align: center;
        position: relative;
        cursor: pointer;
        transition: .18s ease;
    }

    .events-year-day:hover {
        background: rgba(22,101,52,.06);
    }

    .events-year-day.is-empty {
        cursor: default;
        opacity: 0;
        pointer-events: none;
    }

    .events-year-day-number {
        font-size: 11px;
        font-weight: 700;
        color: #374151;
        line-height: 1;
    }

    .events-year-day.is-today .events-year-day-number {
        width: 22px;
        height: 22px;
        margin: 0 auto;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #166534;
        color: white;
    }

    .events-year-dots {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        margin-top: 5px;
        min-height: 10px;
    }

    .events-year-dot {
        width: 7px;
        height: 7px;
        border-radius: 999px;
        flex-shrink: 0;
        border: 1px solid rgba(255,255,255,.85);
        box-shadow: 0 1px 3px rgba(15,23,42,.22);
    }

    .event-quick-wrap {
        position: fixed;
        inset: 0;
        z-index: 9998;
        display: none;
        pointer-events: none;
    }

    .event-quick-wrap.is-open {
        display: block;
    }

    .event-quick-panel {
        position: absolute;
        top: 112px;
        left: 50%;
        transform: translateX(-50%) translateY(12px) scale(.98);
        width: min(520px, calc(100vw - 36px));
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,.58);
        background: rgba(255,255,255,.88);
        box-shadow: 0 24px 60px rgba(0,0,0,.14), inset 0 1px 0 rgba(255,255,255,.72);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        overflow: hidden;
        opacity: 0;
        transition: transform .22s ease, opacity .22s ease;
        pointer-events: auto;
    }

    .event-quick-wrap.is-open .event-quick-panel {
        transform: translateX(-50%) translateY(0) scale(1);
        opacity: 1;
    }

    .event-quick-header {
        padding: 16px 18px 12px;
        border-bottom: 1px solid rgba(0,0,0,.06);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
    }

    .event-quick-title {
        font-size: 14px;
        font-weight: 800;
        color: #111827;
    }

    .event-quick-subtitle {
        font-size: 12px;
        color: #6b7280;
        margin-top: 2px;
    }

    .event-quick-body {
        padding: 16px 18px 18px;
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }

    .event-quick-body::-webkit-scrollbar {
        width: 8px;
    }

    .event-quick-body::-webkit-scrollbar-thumb {
        background: rgba(22,101,52,.18);
        border-radius: 999px;
    }

    .event-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .event-form-full {
        grid-column: 1 / -1;
    }

    .event-form-label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        font-weight: 700;
        color: #374151;
    }

    .event-form-input,
    .event-form-select,
    .event-form-textarea {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.94);
        padding: 10px 12px;
        outline: none;
        transition: .2s ease;
        color: #111827;
        font-size: 13px;
    }

    .event-form-textarea {
        min-height: 84px;
        resize: vertical;
    }

    .event-form-input:focus,
    .event-form-select:focus,
    .event-form-textarea:focus {
        border-color: rgba(22,101,52,.35);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .event-form-select {
        display: block;
        min-height: 42px;
        line-height: 1.25;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image:
            linear-gradient(45deg, transparent 50%, #6b7280 50%),
            linear-gradient(135deg, #6b7280 50%, transparent 50%);
        background-position:
            calc(100% - 16px) calc(50% - 3px),
            calc(100% - 10px) calc(50% - 3px);
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
        padding-right: 38px;
    }

    .event-form-select option {
        background: #ffffff;
        color: #111827;
        font-weight: 600;
    }

    .event-quick-panel .ts-wrapper.event-form-select,
    .event-quick-panel .ts-wrapper.select-search {
        width: 100%;
        border: 0 !important;
        background: transparent !important;
        padding: 0 !important;
        box-shadow: none !important;
    }

    .event-quick-panel .ts-wrapper.event-form-select .ts-control,
    .event-quick-panel .ts-wrapper.select-search .ts-control {
        min-height: 42px;
        height: 42px;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background:
            linear-gradient(45deg, transparent 50%, #6b7280 50%) calc(100% - 16px) calc(50% - 3px) / 6px 6px no-repeat,
            linear-gradient(135deg, #6b7280 50%, transparent 50%) calc(100% - 10px) calc(50% - 3px) / 6px 6px no-repeat,
            rgba(255,255,255,.94);
        color: #111827;
        padding: 10px 38px 10px 12px;
        font-size: 13px;
        font-weight: 600;
        box-shadow: none;
        transition: .2s ease;
    }

    .event-quick-panel .ts-wrapper.focus .ts-control,
    .event-quick-panel .ts-wrapper.dropdown-active .ts-control {
        border-color: rgba(22,101,52,.35);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .event-quick-panel .ts-dropdown {
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        overflow: hidden;
        box-shadow: 0 16px 32px rgba(15,23,42,.14);
        font-size: 13px;
        font-weight: 600;
        z-index: 10020;
    }

    .event-quick-panel .ts-dropdown .option {
        padding: 10px 12px;
    }

    .event-quick-panel .ts-dropdown .active {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .dark .event-form-select option {
        background: #0f172a;
        color: #f8fafc;
    }

    .dark .event-quick-panel .ts-wrapper.event-form-select .ts-control,
    .dark .event-quick-panel .ts-wrapper.select-search .ts-control {
        border-color: rgba(148,163,184,.24);
        background:
            linear-gradient(45deg, transparent 50%, #cbd5e1 50%) calc(100% - 16px) calc(50% - 3px) / 6px 6px no-repeat,
            linear-gradient(135deg, #cbd5e1 50%, transparent 50%) calc(100% - 10px) calc(50% - 3px) / 6px 6px no-repeat,
            rgba(15,23,42,.86);
        color: #f8fafc;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
    }

    .dark .event-quick-panel .ts-wrapper.focus .ts-control,
    .dark .event-quick-panel .ts-wrapper.dropdown-active .ts-control {
        border-color: rgba(74,222,128,.35);
        box-shadow: 0 0 0 4px rgba(34,197,94,.12), inset 0 1px 0 rgba(255,255,255,.04);
    }

    .dark .event-quick-panel .ts-dropdown {
        border-color: rgba(148,163,184,.24);
        background: #0f172a;
        color: #f8fafc;
        box-shadow: 0 18px 36px rgba(0,0,0,.42);
    }

    .dark .event-quick-panel .ts-dropdown .active {
        background: rgba(34,197,94,.16);
        color: #bbf7d0;
    }

    .event-quick-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 14px;
    }

    .event-detail-popover {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, calc(-50% + 12px)) scale(.98);
        width: min(520px, calc(100vw - 36px));
        max-height: min(78vh, 620px);
        overflow-y: auto;
        z-index: 9999;
        border-radius: 24px;
        border: 1px solid rgba(255,255,255,.58);
        background: rgba(255,255,255,.90);
        box-shadow: 0 24px 60px rgba(0,0,0,.14), inset 0 1px 0 rgba(255,255,255,.72);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        padding: 18px;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: transform .22s ease, opacity .22s ease, visibility .22s ease;
    }

    .event-detail-popover.is-open {
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translate(-50%, -50%) scale(1);
    }

    .event-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .event-detail-block {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.90);
        border-radius: 16px;
        padding: 14px;
        min-height: 84px;
    }

    .event-detail-label {
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
        margin-bottom: 7px;
    }

    .event-detail-value {
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        line-height: 1.45;
        word-break: break-word;
    }

    .event-detail-description {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.90);
        border-radius: 16px;
        padding: 14px;
    }

    @media (max-width: 1100px) {
        .events-year-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .events-year-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .events-toolbar {
            align-items: flex-start;
        }

        .events-toolbar-left,
        .events-toolbar-right {
            width: 100%;
        }

        .events-toolbar-title {
            font-size: 1.15rem;
            min-width: auto;
        }

        .events-filter-popover {
            right: 0;
            left: 0;
            width: 100%;
        }

        .event-form-grid,
        .event-detail-grid {
            grid-template-columns: 1fr;
        }

        .events-year-grid {
            grid-template-columns: 1fr;
        }

        .event-quick-panel {
            top: 94px;
            width: calc(100vw - 24px);
        }

        .event-detail-popover {
            width: calc(100vw - 24px);
            max-height: min(76vh, 620px);
        }
    }
</style>

<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            Revisa los campos del formulario del evento.
        </div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Eventos y alertas</h1>
            <p class="text-sm text-gray-500 mt-1">
                Gestiona recordatorios, próximos partos, tratamientos, vacunas y eventos de la finca.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button"
                    id="openCreateEventBtn"
                    class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                Crear evento
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        <div class="xl:col-span-3 events-card">
            <div class="events-toolbar">
                <div class="events-toolbar-left">
                    <button type="button" id="calendarPrevBtn" class="events-nav-btn" aria-label="Anterior">‹</button>
                    <button type="button" id="calendarNextBtn" class="events-nav-btn" aria-label="Siguiente">›</button>
                    <button type="button" id="calendarTodayBtn" class="events-nav-btn">Hoy</button>
                    <div id="calendarToolbarTitle" class="events-toolbar-title">—</div>
                </div>

                <div class="events-toolbar-right">
                    <div class="events-filter-wrap">
                        <button type="button"
                                id="toggleDateFilterBtn"
                                class="events-filter-btn">
                            Filtrar fechas
                        </button>

                        <div id="dateFilterPopover" class="events-filter-popover">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-sm font-extrabold text-gray-900">Filtrar por fechas</h3>
                                    <p class="text-xs text-gray-500 mt-1">Muestra solo los eventos dentro del rango elegido.</p>
                                </div>

                                <button type="button"
                                        id="closeDateFilterBtn"
                                        class="rounded-xl border border-black/10 bg-white/80 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-white transition">
                                    Cerrar
                                </button>
                            </div>

                            <div class="events-filter-grid">
                                <div>
                                    <label class="events-filter-label">Desde</label>
                                    <input type="date" id="filterStartDate" class="events-filter-input">
                                </div>

                                <div>
                                    <label class="events-filter-label">Hasta</label>
                                    <input type="date" id="filterEndDate" class="events-filter-input">
                                </div>

                                <div class="flex flex-wrap gap-2 pt-1">
                                    <button type="button"
                                            id="applyDateFilterBtn"
                                            class="rounded-xl bg-brand hover:bg-brand-dark transition px-4 py-3 text-sm font-semibold text-white shadow-glow">
                                        Aplicar
                                    </button>

                                    <button type="button"
                                            id="clearDateFilterBtn"
                                            class="rounded-xl border border-black/10 bg-white/70 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                                        Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="events-view-btn" data-view-mode="year">Año</button>
                    <button type="button" class="events-view-btn is-active" data-view-mode="month">Mes</button>
                    <button type="button" class="events-view-btn" data-view-mode="week">Semana</button>
                    <button type="button" class="events-view-btn" data-view-mode="day">Día</button>
                    <button type="button" class="events-view-btn" data-view-mode="list">Lista</button>
                </div>
            </div>

            <div id="calendarWrap">
                <div id="calendar"></div>
            </div>

            <div id="yearViewWrap" class="events-year-wrap">
                <div id="yearViewGrid" class="events-year-grid"></div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="events-card">
                <h2 class="text-lg font-extrabold text-gray-900">Leyenda</h2>
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="events-dot" style="background:#dc2626;"></span>
                        <span class="font-semibold text-gray-700">Partos</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="events-dot" style="background:#2563eb;"></span>
                        <span class="font-semibold text-gray-700">Vacunas</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="events-dot" style="background:#7c3aed;"></span>
                        <span class="font-semibold text-gray-700">Tratamientos</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="events-dot" style="background:#d97706;"></span>
                        <span class="font-semibold text-gray-700">Inseminación</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="events-dot" style="background:#db2777;"></span>
                        <span class="font-semibold text-gray-700">Celo / fertilidad</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="events-dot" style="background:#0891b2;"></span>
                        <span class="font-semibold text-gray-700">Revisión</span>
                    </div>
                </div>
            </div>

            <div class="events-card">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-900">Eventos del calendario</h2>
                        @if($eventsListTotal > 0)
                            <p id="calendarEventListCount" class="mt-1 text-xs font-semibold text-gray-500">
                                Mostrando {{ $eventsListVisibleInitial }} de {{ $eventsListTotal }} eventos
                            </p>
                        @endif
                    </div>
                </div>

                <div id="calendarEventList" class="mt-4 space-y-3 events-scroll">
                    @forelse($upcomingEvents as $event)
                        @php
                            $eventStatusLabel = match($event->status ?? 'pending') {
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                                default => 'Pendiente',
                            };

                            $eventId = (string) ($event->id ?? '');
                            $eventFocusDate = $event->event_date?->format('Y-m-d')
                                ?? $event->start_datetime?->format('Y-m-d')
                                ?? $event->sort_date?->format('Y-m-d')
                                ?? '';
                        @endphp

                        <div class="events-mini-card clickable upcoming-event-trigger {{ $loop->index >= $eventsListInitialLimit ? 'hidden' : '' }}"
                             data-calendar-list-item
                             data-event-id="{{ $eventId }}"
                             data-focus-date="{{ $eventFocusDate }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-bold text-gray-900">{{ $event->title }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $event->event_date?->format('d/m/Y') ?? $event->start_datetime?->format('d/m/Y H:i') ?? ($event->sort_date?->format('d/m/Y') ?? 'Sin fecha') }}
                                    </div>
                                </div>

                                <span class="events-badge {{ $event->status ?? 'pending' }}">
                                    {{ $eventStatusLabel }}
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                @if(!empty($event->is_automatic))
                                    <span class="events-badge automatic">Automático</span>
                                @endif
                            </div>

                            <div class="mt-3 text-xs text-gray-500 space-y-1">
                                <div>
                                    Tipo:
                                    {{ $event->type_label ?? ucfirst(str_replace('_', ' ', $event->type ?? 'general')) }}
                                </div>

                                @if(!empty($event->animal))
                                    <div>Animal: {{ $event->animal->name ?: ($event->animal->ear_tag ?: 'Animal') }}</div>
                                @elseif(!empty($event->animal_name))
                                    <div>Animal: {{ $event->animal_name }}</div>
                                @endif

                                @if(!empty($event->lot_name))
                                    <div>Lote: {{ $event->lot_name }}</div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-5 text-sm text-gray-500">
                            Aún no hay eventos registrados.
                        </div>
                    @endforelse
                </div>

                @if($eventsListTotal > $eventsListInitialLimit)
                    <button type="button"
                            id="loadMoreCalendarEvents"
                            class="events-load-more mt-4"
                            data-step="{{ $eventsListInitialLimit }}"
                            data-visible="{{ $eventsListVisibleInitial }}">
                        Cargar más
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

<div id="eventQuickWrap" class="event-quick-wrap">
    <div class="event-quick-panel">
        <div class="event-quick-header">
            <div>
                <div class="event-quick-title">Crear evento</div>
                <div class="event-quick-subtitle">Registro rápido del evento</div>
            </div>

            <button type="button"
                    class="rounded-xl border border-black/10 bg-white/80 px-3 py-2 text-xs font-semibold text-gray-700"
                    data-close-quick-popup>
                Cerrar
            </button>
        </div>

        <div class="event-quick-body">
            <form method="POST" action="{{ route('events.store') }}" class="space-y-4">
                @csrf

                <div class="event-form-grid">
                    <div class="event-form-full">
                        <label class="event-form-label">Título *</label>
                        <input type="text"
                               name="title"
                               value="{{ old('title') }}"
                               class="event-form-input"
                               placeholder="Ej: Próximo parto de Estrella">
                        @error('title')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">Tipo *</label>
                        <select name="type" class="event-form-select">
                            <option value="general" {{ old('type') === 'general' ? 'selected' : '' }}>General</option>
                            <option value="parto" {{ old('type') === 'parto' ? 'selected' : '' }}>Parto</option>
                            <option value="vacuna" {{ old('type') === 'vacuna' ? 'selected' : '' }}>Vacuna</option>
                            <option value="tratamiento" {{ old('type') === 'tratamiento' ? 'selected' : '' }}>Tratamiento</option>
                            <option value="inseminacion" {{ old('type') === 'inseminacion' ? 'selected' : '' }}>Inseminación</option>
                            <option value="celo" {{ old('type') === 'celo' ? 'selected' : '' }}>Celo</option>
                            <option value="revision" {{ old('type') === 'revision' ? 'selected' : '' }}>Revisión</option>
                        </select>
                        @error('type')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">Animal</label>
                        <select name="animal_id" class="event-form-select select-search">
                            <option value="">Sin animal</option>
                            @foreach($animals as $animal)
                                <option value="{{ $animal->id }}" {{ (string) old('animal_id') === (string) $animal->id ? 'selected' : '' }}>
                                    {{ $animal->ear_tag ?: 'Sin arete' }} - {{ $animal->name ?: 'Sin nombre' }}
                                </option>
                            @endforeach
                        </select>
                        @error('animal_id')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">Lote</label>
                        <input type="text"
                               name="lot_name"
                               value="{{ old('lot_name') }}"
                               class="event-form-input"
                               placeholder="Ej: Lote 3">
                        @error('lot_name')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">Estado *</label>
                        <select name="status" class="event-form-select">
                            <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>Pendiente</option>
                            <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completado</option>
                            <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                        </select>
                        @error('status')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">Prioridad *</label>
                        <select name="priority" class="event-form-select">
                            <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Baja</option>
                            <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>Media</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>Alta</option>
                        </select>
                        @error('priority')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">¿Todo el día?</label>
                        <select name="all_day" id="eventAllDaySelect" class="event-form-select">
                            <option value="1" {{ old('all_day', '1') === '1' ? 'selected' : '' }}>Sí</option>
                            <option value="0" {{ old('all_day') === '0' ? 'selected' : '' }}>No</option>
                        </select>
                    </div>

                    <div id="eventDateBlock">
                        <label class="event-form-label">Fecha</label>
                        <input type="date"
                               name="event_date"
                               value="{{ old('event_date') }}"
                               class="event-form-input">
                        @error('event_date')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="eventStartBlock" class="hidden">
                        <label class="event-form-label">Inicio</label>
                        <input type="datetime-local"
                               name="start_datetime"
                               value="{{ old('start_datetime') }}"
                               class="event-form-input">
                        @error('start_datetime')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="eventEndBlock" class="hidden">
                        <label class="event-form-label">Fin</label>
                        <input type="datetime-local"
                               name="end_datetime"
                               value="{{ old('end_datetime') }}"
                               class="event-form-input">
                        @error('end_datetime')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="event-form-label">Color</label>
                        <input type="color"
                               name="color"
                               value="{{ old('color', '#166534') }}"
                               class="event-form-input h-[42px] p-1.5">
                        @error('color')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="event-form-full">
                        <label class="event-form-label">Descripción</label>
                        <textarea name="description"
                                  rows="3"
                                  class="event-form-textarea"
                                  placeholder="Detalles del evento...">{{ old('description') }}</textarea>
                        @error('description')
                            <span class="mt-2 block text-xs text-red-600">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="event-quick-actions">
                    <button type="button"
                            class="rounded-xl border border-black/10 bg-white/70 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-white transition"
                            data-close-quick-popup>
                        Cancelar
                    </button>

                    <button type="submit"
                            class="rounded-xl bg-brand hover:bg-brand-dark transition px-4 py-2.5 text-sm font-semibold text-white shadow-glow">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="eventDetailPopover" class="event-detail-popover">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-4">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h3 id="detailTitle" class="text-xl font-extrabold text-gray-900">Detalle del evento</h3>
                <span id="detailAutomaticBadge" class="events-badge automatic hidden">Automático</span>
            </div>
            <p id="detailDate" class="text-xs text-gray-500 mt-1">—</p>
        </div>

        <button type="button"
                id="closeDetailPopoverBtn"
                class="rounded-xl border border-black/10 bg-white/80 px-3 py-2 text-xs font-semibold text-gray-700">
            Cerrar
        </button>
    </div>

    <div class="space-y-3">
        <div class="event-detail-grid">
            <div class="event-detail-block">
                <div class="event-detail-label">Tipo</div>
                <div id="detailType" class="event-detail-value">—</div>
            </div>

            <div class="event-detail-block">
                <div class="event-detail-label">Estado</div>
                <div id="detailStatus" class="event-detail-value">—</div>
            </div>

            <div class="event-detail-block">
                <div class="event-detail-label">Prioridad</div>
                <div id="detailPriority" class="event-detail-value">—</div>
            </div>

            <div class="event-detail-block">
                <div class="event-detail-label">Animal / lote</div>
                <div id="detailRelation" class="event-detail-value">—</div>
            </div>
        </div>

        <div class="event-detail-description">
            <div class="event-detail-label">Descripción</div>
            <div id="detailDescription" class="event-detail-value font-medium">Sin descripción.</div>
        </div>

        <div id="detailManualActions" class="event-detail-actions">
            <button type="button" id="detailEditBtn" class="event-detail-action-btn primary">
                Editar evento
            </button>

            <form id="detailDeleteForm" method="POST">
                @csrf
                @method('DELETE')
                <button type="submit" class="event-detail-action-btn danger">
                    Eliminar
                </button>
            </form>
        </div>

        <div id="detailAutomaticNotice" class="rounded-2xl border border-green-500/15 bg-green-50/70 p-4 text-sm font-bold text-green-800 hidden">
            Este evento es automático. Se actualiza desde la información del animal y no se edita manualmente en el calendario.
        </div>

        <form id="eventEditForm" method="POST" class="event-detail-edit-form">
            @csrf
            @method('PUT')

            <div class="event-form-grid">
                <div class="event-form-full">
                    <label class="event-form-label">Título *</label>
                    <input type="text" name="title" id="editEventTitle" class="event-form-input" required>
                </div>

                <div>
                    <label class="event-form-label">Tipo *</label>
                    <select name="type" id="editEventType" class="event-form-select" required>
                        <option value="general">General</option>
                        <option value="parto">Parto</option>
                        <option value="vacuna">Vacuna</option>
                        <option value="tratamiento">Tratamiento</option>
                        <option value="inseminacion">Inseminación</option>
                        <option value="celo">Celo</option>
                        <option value="revision">Revisión</option>
                    </select>
                </div>

                <div>
                    <label class="event-form-label">Animal</label>
                    <select name="animal_id" id="editEventAnimal" class="event-form-select">
                        <option value="">Sin animal</option>
                        @foreach($animals as $animal)
                            <option value="{{ $animal->id }}">
                                {{ $animal->ear_tag ?: 'Sin arete' }} - {{ $animal->name ?: 'Sin nombre' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="event-form-label">Lote</label>
                    <input type="text" name="lot_name" id="editEventLotName" class="event-form-input">
                </div>

                <div>
                    <label class="event-form-label">Estado *</label>
                    <select name="status" id="editEventStatus" class="event-form-select" required>
                        <option value="pending">Pendiente</option>
                        <option value="completed">Completado</option>
                        <option value="cancelled">Cancelado</option>
                    </select>
                </div>

                <div>
                    <label class="event-form-label">Prioridad *</label>
                    <select name="priority" id="editEventPriority" class="event-form-select" required>
                        <option value="low">Baja</option>
                        <option value="medium">Media</option>
                        <option value="high">Alta</option>
                    </select>
                </div>

                <div>
                    <label class="event-form-label">¿Todo el día?</label>
                    <select name="all_day" id="editEventAllDay" class="event-form-select">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>

                <div id="editEventDateBlock">
                    <label class="event-form-label">Fecha</label>
                    <input type="date" name="event_date" id="editEventDate" class="event-form-input">
                </div>

                <div id="editEventStartBlock" class="hidden">
                    <label class="event-form-label">Inicio</label>
                    <input type="datetime-local" name="start_datetime" id="editEventStart" class="event-form-input">
                </div>

                <div id="editEventEndBlock" class="hidden">
                    <label class="event-form-label">Fin</label>
                    <input type="datetime-local" name="end_datetime" id="editEventEnd" class="event-form-input">
                </div>

                <div>
                    <label class="event-form-label">Color</label>
                    <input type="color" name="color" id="editEventColor" class="event-form-input h-[42px] p-1.5">
                </div>

                <div class="event-form-full">
                    <label class="event-form-label">Descripción</label>
                    <textarea name="description" id="editEventDescription" rows="3" class="event-form-textarea"></textarea>
                </div>
            </div>

            <div class="event-quick-actions">
                <button type="button" id="cancelEditEventBtn" class="event-detail-action-btn">
                    Cancelar edición
                </button>

                <button type="submit" class="event-detail-action-btn primary">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const quickWrap = document.getElementById('eventQuickWrap');
    const quickPanel = quickWrap?.querySelector('.event-quick-panel');
    const detailPopover = document.getElementById('eventDetailPopover');
    const openCreateBtn = document.getElementById('openCreateEventBtn');
    const allDaySelect = document.getElementById('eventAllDaySelect');

    const eventDateBlock = document.getElementById('eventDateBlock');
    const eventStartBlock = document.getElementById('eventStartBlock');
    const eventEndBlock = document.getElementById('eventEndBlock');

    const toggleDateFilterBtn = document.getElementById('toggleDateFilterBtn');
    const closeDateFilterBtn = document.getElementById('closeDateFilterBtn');
    const dateFilterPopover = document.getElementById('dateFilterPopover');
    const filterStartDate = document.getElementById('filterStartDate');
    const filterEndDate = document.getElementById('filterEndDate');
    const applyDateFilterBtn = document.getElementById('applyDateFilterBtn');
    const clearDateFilterBtn = document.getElementById('clearDateFilterBtn');

    const prevBtn = document.getElementById('calendarPrevBtn');
    const nextBtn = document.getElementById('calendarNextBtn');
    const todayBtn = document.getElementById('calendarTodayBtn');
    const titleEl = document.getElementById('calendarToolbarTitle');
    const viewButtons = document.querySelectorAll('[data-view-mode]');
    const calendarWrap = document.getElementById('calendarWrap');
    const yearViewWrap = document.getElementById('yearViewWrap');
    const yearViewGrid = document.getElementById('yearViewGrid');

    const detailTitle = document.getElementById('detailTitle');
    const detailDate = document.getElementById('detailDate');
    const detailType = document.getElementById('detailType');
    const detailStatus = document.getElementById('detailStatus');
    const detailPriority = document.getElementById('detailPriority');
    const detailRelation = document.getElementById('detailRelation');
    const detailDescription = document.getElementById('detailDescription');
    const detailAutomaticBadge = document.getElementById('detailAutomaticBadge');
    const closeDetailPopoverBtn = document.getElementById('closeDetailPopoverBtn');
    const detailManualActions = document.getElementById('detailManualActions');
    const detailAutomaticNotice = document.getElementById('detailAutomaticNotice');
    const detailEditBtn = document.getElementById('detailEditBtn');
    const detailDeleteForm = document.getElementById('detailDeleteForm');
    const eventEditForm = document.getElementById('eventEditForm');
    const cancelEditEventBtn = document.getElementById('cancelEditEventBtn');
    const editEventAllDay = document.getElementById('editEventAllDay');
    const editEventDateBlock = document.getElementById('editEventDateBlock');
    const editEventStartBlock = document.getElementById('editEventStartBlock');
    const editEventEndBlock = document.getElementById('editEventEndBlock');

    if (detailPopover && detailPopover.parentElement !== document.body) {
        document.body.appendChild(detailPopover);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const eventIdFromUrl = urlParams.get('event');
    const focusDateFromUrl = urlParams.get('focus_date');
    const isAutomaticFromUrl = urlParams.get('automatic') === '1';

    const weekdayShort = ['D', 'L', 'M', 'X', 'J', 'V', 'S'];
    const monthNames = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    const statusLabels = {
        pending: 'Pendiente',
        completed: 'Completado',
        cancelled: 'Cancelado'
    };

    const priorityLabels = {
        low: 'Baja',
        medium: 'Media',
        high: 'Alta'
    };

    const viewModeMap = {
        month: 'dayGridMonth',
        week: 'timeGridWeek',
        day: 'timeGridDay',
        list: 'listYear'
    };

    let calendar = null;
    let highlightedElement = null;
    let activeMode = 'month';
    let currentYearView = new Date().getFullYear();
    const yearEventsCache = {};

    const feedUrl = '{{ route('events.feed') }}';
    const eventUpdateUrlTemplate = '{{ route('events.update', ['event' => '__EVENT_ID__']) }}';
    const eventDeleteUrlTemplate = '{{ route('events.destroy', ['event' => '__EVENT_ID__']) }}';

    const openQuickPopup = () => {
        if (!quickWrap) return;
        quickWrap.classList.add('is-open');
    };

    const closeQuickPopup = () => {
        if (!quickWrap) return;
        quickWrap.classList.remove('is-open');
    };

    const openDetailPopover = () => {
        if (!detailPopover) return;
        detailPopover.classList.add('is-open');
    };

    const closeDetailPopover = () => {
        if (!detailPopover) return;
        detailPopover.classList.remove('is-open');
    };

    const closeAllOverlays = () => {
        closeQuickPopup();
        closeDetailPopover();
        closeFilterPopover();
    };

    const setActiveModeButton = (mode) => {
        viewButtons.forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.viewMode === mode);
        });
    };

    const updateToolbarTitle = () => {
        if (!titleEl) return;

        if (activeMode === 'year') {
            titleEl.textContent = String(currentYearView);
            return;
        }

        if (calendar) {
            titleEl.textContent = calendar.view.title;
        }
    };

    const setMode = async (mode) => {
        activeMode = mode;
        setActiveModeButton(mode);

        if (mode === 'year') {
            calendarWrap.style.display = 'none';
            yearViewWrap.classList.add('is-active');

            if (calendar) {
                currentYearView = calendar.getDate().getFullYear();
            }

            await renderYearView(currentYearView);
            updateToolbarTitle();
            return;
        }

        yearViewWrap.classList.remove('is-active');
        calendarWrap.style.display = '';

        if (calendar && viewModeMap[mode]) {
            calendar.changeView(viewModeMap[mode]);
        }

        updateToolbarTitle();
    };

    const openFilterPopover = () => {
        if (!dateFilterPopover) return;
        dateFilterPopover.classList.add('is-open');
    };

    const closeFilterPopover = () => {
        if (!dateFilterPopover) return;
        dateFilterPopover.classList.remove('is-open');
    };

    const toggleEventDateFields = () => {
        if (!allDaySelect) return;

        const isAllDay = allDaySelect.value === '1';

        eventDateBlock.classList.toggle('hidden', !isAllDay);
        eventStartBlock.classList.toggle('hidden', isAllDay);
        eventEndBlock.classList.toggle('hidden', isAllDay);
    };

    const toggleEditEventDateFields = () => {
        if (!editEventAllDay) return;

        const isAllDay = editEventAllDay.value === '1';

        editEventDateBlock?.classList.toggle('hidden', !isAllDay);
        editEventStartBlock?.classList.toggle('hidden', isAllDay);
        editEventEndBlock?.classList.toggle('hidden', isAllDay);
    };

    const setFormValue = (id, value) => {
        const field = document.getElementById(id);
        if (!field) return;
        field.value = value ?? '';
    };

    const setEventFormAction = (form, template, eventId) => {
        if (!form || !eventId) return;
        form.action = template.replace('__EVENT_ID__', encodeURIComponent(eventId));
    };

    const openEditEventForm = () => {
        eventEditForm?.classList.add('is-open');
    };

    const closeEditEventForm = () => {
        eventEditForm?.classList.remove('is-open');
    };

    const fillDetailPopover = (event) => {
        const props = event.extendedProps || {};
        const isAutomatic = !!props.automatic;

        detailTitle.textContent = event.title || 'Evento';
        detailDate.textContent = event.start
            ? new Intl.DateTimeFormat('es-CO', {
                dateStyle: 'full',
                timeStyle: event.allDay ? undefined : 'short'
            }).format(event.start)
            : 'Sin fecha';

        detailType.textContent = props.type_label || props.type || 'General';
        detailStatus.textContent = statusLabels[props.status] || 'Pendiente';
        detailPriority.textContent = priorityLabels[props.priority] || 'Media';

        const relationParts = [];
        if (props.animal_name) relationParts.push('Animal: ' + props.animal_name);
        if (props.lot_name) relationParts.push('Lote: ' + props.lot_name);
        detailRelation.textContent = relationParts.length ? relationParts.join(' · ') : 'Sin relación';

        detailDescription.textContent = props.description || 'Sin descripción.';
        detailAutomaticBadge.classList.toggle('hidden', !isAutomatic);
        detailManualActions?.classList.toggle('hidden', isAutomatic);
        detailAutomaticNotice?.classList.toggle('hidden', !isAutomatic);
        closeEditEventForm();

        if (!isAutomatic) {
            setEventFormAction(eventEditForm, eventUpdateUrlTemplate, event.id);
            setEventFormAction(detailDeleteForm, eventDeleteUrlTemplate, event.id);

            setFormValue('editEventTitle', event.title || '');
            setFormValue('editEventType', props.type || 'general');
            setFormValue('editEventAnimal', props.animal_id || '');
            setFormValue('editEventLotName', props.lot_name || '');
            setFormValue('editEventStatus', props.status || 'pending');
            setFormValue('editEventPriority', props.priority || 'medium');
            setFormValue('editEventAllDay', event.allDay ? '1' : '0');
            setFormValue('editEventDate', props.event_date || (event.start ? eventDateKey(event) : ''));
            setFormValue('editEventStart', props.start_datetime || '');
            setFormValue('editEventEnd', props.end_datetime || '');
            setFormValue('editEventColor', props.color || event.backgroundColor || '#166534');
            setFormValue('editEventDescription', props.description || '');
            toggleEditEventDateFields();
        }

        closeQuickPopup();
        openDetailPopover();
    };

    const fetchYearEvents = async (year) => {
        if (yearEventsCache[year]) {
            return yearEventsCache[year];
        }

        const params = new URLSearchParams({
            filter_start: `${year}-01-01`,
            filter_end: `${year}-12-31`
        });

        const response = await fetch(`${feedUrl}?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const data = await response.json();
        yearEventsCache[year] = Array.isArray(data) ? data : [];

        return yearEventsCache[year];
    };

    const eventDateKey = (event) => {
        const props = event.extendedProps || {};
        const rawDate = event.start || props.event_date || props.start_datetime || event.event_date || event.start_datetime || '';

        if (!rawDate) return '';

        if (typeof rawDate === 'string') {
            return rawDate.slice(0, 10);
        }

        if (rawDate instanceof Date && !Number.isNaN(rawDate.getTime())) {
            const year = rawDate.getFullYear();
            const month = String(rawDate.getMonth() + 1).padStart(2, '0');
            const day = String(rawDate.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        return '';
    };

    const eventDotColor = (event) => {
        const props = event.extendedProps || {};

        return event.backgroundColor
            || event.borderColor
            || event.color
            || props.color
            || '#166534';
    };

    const groupEventsByDate = (events) => {
        const grouped = {};

        events.forEach((event) => {
            const dateKey = eventDateKey(event);

            if (!dateKey) return;

            if (!grouped[dateKey]) {
                grouped[dateKey] = [];
            }

            grouped[dateKey].push(event);
        });

        return grouped;
    };

    const renderYearView = async (year) => {
        if (!yearViewGrid) return;

        yearViewGrid.innerHTML = '<div class="text-sm text-gray-500">Cargando año...</div>';

        try {
            const events = await fetchYearEvents(year);
            const grouped = groupEventsByDate(events);
            const today = new Date();
            const todayKey = today.toISOString().slice(0, 10);

            let html = '';

            for (let month = 0; month < 12; month++) {
                const firstDay = new Date(year, month, 1);
                const lastDate = new Date(year, month + 1, 0).getDate();
                const startWeekday = firstDay.getDay();

                html += `<div class="events-year-month">`;
                html += `<div class="events-year-month-title">${monthNames[month]}</div>`;
                html += `<div class="events-year-weekdays">`;

                weekdayShort.forEach((day) => {
                    html += `<div class="events-year-weekday">${day}</div>`;
                });

                html += `</div>`;
                html += `<div class="events-year-days">`;

                for (let i = 0; i < startWeekday; i++) {
                    html += `<div class="events-year-day is-empty"></div>`;
                }

                for (let day = 1; day <= lastDate; day++) {
                    const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const dayEvents = grouped[dateKey] || [];
                    const dots = dayEvents.slice(0, 3).map((event) => {
                        const color = eventDotColor(event);
                        return `<span class="events-year-dot" style="background:${color};"></span>`;
                    }).join('');

                    const isToday = dateKey === todayKey;

                    html += `
                        <div class="events-year-day ${isToday ? 'is-today' : ''}" data-year-date="${dateKey}">
                            <div class="events-year-day-number">${day}</div>
                            <div class="events-year-dots">${dots}</div>
                        </div>
                    `;
                }

                html += `</div>`;
                html += `</div>`;
            }

            yearViewGrid.innerHTML = html;

            yearViewGrid.querySelectorAll('[data-year-date]').forEach((dayEl) => {
                dayEl.addEventListener('click', () => {
                    const selectedDate = dayEl.getAttribute('data-year-date');
                    if (!selectedDate || !calendar) return;

                    calendar.gotoDate(selectedDate);
                    setMode('month');
                });
            });
        } catch (error) {
            console.error('Error cargando vista año:', error);
            yearViewGrid.innerHTML = '<div class="text-sm text-red-600">No se pudo cargar la vista anual.</div>';
        }
    };

    const openEventById = (eventId, focusDate = null) => {
        if (!calendar || !eventId) return;

        if (focusDate) {
            calendar.gotoDate(focusDate);
        }

        setTimeout(() => {
            const event = calendar.getEventById(String(eventId));

            if (event) {
                if (event.start) {
                    calendar.gotoDate(event.start);
                }

                fillDetailPopover(event);
            }
        }, 320);
    };

    if (toggleDateFilterBtn) {
        toggleDateFilterBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dateFilterPopover.classList.contains('is-open')) {
                closeFilterPopover();
            } else {
                openFilterPopover();
            }
        });
    }

    if (closeDateFilterBtn) {
        closeDateFilterBtn.addEventListener('click', closeFilterPopover);
    }

    viewButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.viewMode;
            setMode(mode);
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', async () => {
            if (activeMode === 'year') {
                currentYearView -= 1;
                await renderYearView(currentYearView);
                updateToolbarTitle();
                return;
            }

            calendar?.prev();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', async () => {
            if (activeMode === 'year') {
                currentYearView += 1;
                await renderYearView(currentYearView);
                updateToolbarTitle();
                return;
            }

            calendar?.next();
        });
    }

    if (todayBtn) {
        todayBtn.addEventListener('click', async () => {
            if (activeMode === 'year') {
                currentYearView = new Date().getFullYear();
                await renderYearView(currentYearView);
                updateToolbarTitle();
                return;
            }

            calendar?.today();
        });
    }

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeDetailPopover();
            openQuickPopup();
        });
    }

    document.querySelectorAll('[data-close-quick-popup]').forEach((button) => {
        button.addEventListener('click', closeQuickPopup);
    });

    if (closeDetailPopoverBtn) {
        closeDetailPopoverBtn.addEventListener('click', closeDetailPopover);
    }

    if (detailEditBtn) {
        detailEditBtn.addEventListener('click', openEditEventForm);
    }

    if (cancelEditEventBtn) {
        cancelEditEventBtn.addEventListener('click', closeEditEventForm);
    }

    if (editEventAllDay) {
        editEventAllDay.addEventListener('change', toggleEditEventDateFields);
    }

    if (detailDeleteForm) {
        detailDeleteForm.addEventListener('submit', (event) => {
            if (!confirm('¿Seguro que quieres eliminar este evento? Esta acción no se puede deshacer.')) {
                event.preventDefault();
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (
            dateFilterPopover &&
            toggleDateFilterBtn &&
            !dateFilterPopover.contains(e.target) &&
            !toggleDateFilterBtn.contains(e.target)
        ) {
            closeFilterPopover();
        }

        if (quickWrap.classList.contains('is-open')) {
            const clickedInsideQuick = quickPanel && quickPanel.contains(e.target);
            const clickedOpenBtn = openCreateBtn && openCreateBtn.contains(e.target);

            if (!clickedInsideQuick && !clickedOpenBtn) {
                closeQuickPopup();
            }
        }

        if (detailPopover.classList.contains('is-open')) {
            const clickedInsideDetail = detailPopover.contains(e.target);
            const clickedUpcoming = e.target.closest('.upcoming-event-trigger');
            const clickedCalendarEvent = e.target.closest('.fc-event');

            if (!clickedInsideDetail && !clickedUpcoming && !clickedCalendarEvent) {
                closeDetailPopover();
            }
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllOverlays();
        }
    });

    if (allDaySelect) {
        allDaySelect.addEventListener('change', toggleEventDateFields);
        toggleEventDateFields();
    }

    document.querySelectorAll('.upcoming-event-trigger').forEach((item) => {
        item.addEventListener('click', () => {
            const eventId = item.getAttribute('data-event-id');
            const focusDate = item.getAttribute('data-focus-date') || null;
            openEventById(eventId, focusDate);
        });
    });

    const loadMoreCalendarEventsBtn = document.getElementById('loadMoreCalendarEvents');
    const calendarEventListCount = document.getElementById('calendarEventListCount');
    const calendarEventListItems = Array.from(document.querySelectorAll('[data-calendar-list-item]'));

    if (loadMoreCalendarEventsBtn && calendarEventListItems.length > 0) {
        const step = Number(loadMoreCalendarEventsBtn.dataset.step || 8);

        loadMoreCalendarEventsBtn.addEventListener('click', () => {
            const currentVisible = Number(loadMoreCalendarEventsBtn.dataset.visible || step);
            const nextVisible = Math.min(currentVisible + step, calendarEventListItems.length);

            calendarEventListItems.forEach((item, index) => {
                item.classList.toggle('hidden', index >= nextVisible);
            });

            loadMoreCalendarEventsBtn.dataset.visible = String(nextVisible);

            if (calendarEventListCount) {
                calendarEventListCount.textContent = `Mostrando ${nextVisible} de ${calendarEventListItems.length} eventos`;
            }

            if (nextVisible >= calendarEventListItems.length) {
                loadMoreCalendarEventsBtn.classList.add('hidden');
            }
        });
    }

    const calendarEl = document.getElementById('calendar');
    let currentViewDate = null;

    if (calendarEl) {
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'es',
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: false,
            events: {
                url: feedUrl,
                extraParams: function () {
                    return {
                        filter_start: filterStartDate?.value || '',
                        filter_end: filterEndDate?.value || ''
                    };
                }
            },
            editable: false,
            selectable: true,
            dayMaxEvents: true,
            navLinks: true,
            datesSet: function(info) {
                currentViewDate = info.start;
                currentYearView = calendar.getDate().getFullYear();
                if (activeMode !== 'year') {
                    updateToolbarTitle();
                }
            },
            select: function(info) {
                const dateInput = document.querySelector('input[name="event_date"]');
                const startInput = document.querySelector('input[name="start_datetime"]');
                const endInput = document.querySelector('input[name="end_datetime"]');

                if (allDaySelect) {
                    allDaySelect.value = info.allDay ? '1' : '0';
                    toggleEventDateFields();
                }

                if (info.allDay && dateInput) {
                    dateInput.value = info.startStr;
                }

                if (!info.allDay && startInput) {
                    startInput.value = info.startStr.slice(0, 16);
                }

                if (!info.allDay && endInput && info.endStr) {
                    endInput.value = info.endStr.slice(0, 16);
                }

                closeDetailPopover();
                openQuickPopup();
            },
            eventClick: function(info) {
                fillDetailPopover(info.event);
            },
            eventDidMount: function(info) {
                if (eventIdFromUrl && String(info.event.id) === String(eventIdFromUrl)) {
                    setTimeout(() => {
                        if (highlightedElement) {
                            highlightedElement.classList.remove('fc-event-highlight');
                        }

                        highlightedElement = info.el;
                        info.el.classList.add('fc-event-highlight');

                        info.el.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                            inline: 'center'
                        });
                    }, 350);
                }
            }
        });

        calendar.render();
        updateToolbarTitle();

        if (eventIdFromUrl || focusDateFromUrl) {
            setTimeout(() => {
                if (focusDateFromUrl) {
                    calendar.gotoDate(focusDateFromUrl);
                }

                setTimeout(() => {
                    const targetEvent = calendar.getEventById(eventIdFromUrl);

                    if (targetEvent) {
                        fillDetailPopover(targetEvent);
                    } else if (isAutomaticFromUrl && focusDateFromUrl) {
                        const sameDayEvent = calendar.getEvents().find((event) => {
                            if (!event.start) return false;

                            const startDate = event.start.toISOString().slice(0, 10);
                            return startDate === focusDateFromUrl && String(event.id) === String(eventIdFromUrl);
                        });

                        if (sameDayEvent) {
                            fillDetailPopover(sameDayEvent);
                        }
                    }

                    const cleanUrl = new URL(window.location.href);
                    cleanUrl.searchParams.delete('event');
                    cleanUrl.searchParams.delete('focus_date');
                    cleanUrl.searchParams.delete('automatic');
                    window.history.replaceState({}, '', cleanUrl.toString());
                }, 500);
            }, 350);
        }
    }

    if (applyDateFilterBtn) {
        applyDateFilterBtn.addEventListener('click', async () => {
            if (calendar) {
                calendar.refetchEvents();
            }

            if (activeMode === 'year') {
                yearEventsCache[currentYearView] = null;
                await renderYearView(currentYearView);
            }

            closeFilterPopover();
        });
    }

    if (clearDateFilterBtn) {
        clearDateFilterBtn.addEventListener('click', async () => {
            if (filterStartDate) filterStartDate.value = '';
            if (filterEndDate) filterEndDate.value = '';

            if (calendar) {
                calendar.refetchEvents();
            }

            if (activeMode === 'year') {
                yearEventsCache[currentYearView] = null;
                await renderYearView(currentYearView);
            }

            closeFilterPopover();
        });
    }

    @if ($errors->any())
        openQuickPopup();
    @endif
});
</script>

@endsection

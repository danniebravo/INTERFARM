@extends('layouts.app')

@section('title', 'Notificaciones')

@section('content')
@php
    $targets = [
        'all' => 'Todos los clientes',
        'active' => 'Clientes activos',
        'trial' => 'Periodo de prueba',
        'past_due' => 'Clientes en mora',
    ];

    $levels = [
        'low' => 'Baja',
        'medium' => 'Media',
        'high' => 'Alta',
    ];
@endphp

<style>
    .broadcast-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.70);
        box-shadow: 0 14px 34px rgba(15,23,42,.06);
    }

    .broadcast-input,
    .broadcast-textarea {
        width: 100%;
        border-radius: 15px;
        border: 1px solid rgba(148,163,184,.42);
        background: rgba(255,255,255,.92);
        padding: 12px 14px;
        font-size: 13px;
        font-weight: 750;
        color: #111827;
        outline: none;
    }

    .broadcast-textarea {
        min-height: 150px;
        resize: vertical;
    }

    .broadcast-input:focus,
    .broadcast-textarea:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 4px rgba(34,197,94,.12);
    }

    .broadcast-select-wrap {
        position: relative;
    }

    .broadcast-select-wrap select {
        appearance: none;
        padding-right: 42px;
    }

    .broadcast-select-wrap::after {
        content: "";
        position: absolute;
        right: 15px;
        top: 50%;
        width: 9px;
        height: 9px;
        border-right: 2px solid #64748b;
        border-bottom: 2px solid #64748b;
        transform: translateY(-70%) rotate(45deg);
        pointer-events: none;
    }

    .broadcast-level {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 950;
    }

    .broadcast-level.high {
        background: rgba(239,68,68,.12);
        color: #b91c1c;
    }

    .broadcast-level.medium {
        background: rgba(245,158,11,.14);
        color: #92400e;
    }

    .broadcast-level.low {
        background: rgba(34,197,94,.12);
        color: #15803d;
    }

    .dark .broadcast-card {
        border-color: rgba(148,163,184,.20);
        background: rgba(15,23,42,.88);
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .broadcast-input,
    .dark .broadcast-textarea {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .broadcast-select-wrap::after {
        border-color: #cbd5e1;
    }

    .dark .broadcast-level.high {
        background: rgba(239,68,68,.18);
        color: #fecaca;
    }

    .dark .broadcast-level.medium {
        background: rgba(245,158,11,.18);
        color: #fde68a;
    }

    .dark .broadcast-level.low {
        background: rgba(34,197,94,.16);
        color: #bbf7d0;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Comunicación
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Notificaciones a clientes</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Envía comunicados importantes a los usuarios de la plataforma. Se verán en la campanita, en la app instalada y en el contador móvil.
            </p>
        </div>

        <a href="{{ route('admin.saas.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Volver al Centro SaaS
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
            Revisa el título, mensaje y destino de la notificación.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[.95fr_1.1fr]">
        <section class="broadcast-card p-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Crear notificación</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">
                Úsalo para anuncios de mantenimiento, novedades, cobros o información importante de la plataforma.
            </p>

            <form method="POST" action="{{ route('admin.saas.notifications.send') }}" class="mt-5 space-y-4">
                @csrf

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Título</label>
                    <input name="title" value="{{ old('title') }}" class="broadcast-input mt-1" maxlength="140" placeholder="Ej: Actualización importante de InterFarm">
                    @error('title')
                        <div class="mt-1 text-xs font-bold text-red-600 dark:text-red-300">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Mensaje</label>
                    <textarea name="message" class="broadcast-textarea mt-1" maxlength="1000" placeholder="Escribe el comunicado que verán tus clientes...">{{ old('message') }}</textarea>
                    @error('message')
                        <div class="mt-1 text-xs font-bold text-red-600 dark:text-red-300">{{ $message }}</div>
                    @enderror
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Destino</label>
                        <div class="broadcast-select-wrap mt-1">
                            <select name="target" class="broadcast-input">
                                @foreach($targets as $value => $label)
                                    <option value="{{ $value }}" @selected(old('target', 'all') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Prioridad</label>
                        <div class="broadcast-select-wrap mt-1">
                            <select name="level" class="broadcast-input">
                                @foreach($levels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('level', 'medium') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-800 dark:border-amber-400/25 dark:bg-amber-500/10 dark:text-amber-100">
                    La notificación queda disponible de inmediato. Si el cliente tiene la app abierta o instalada, el contador se actualizará en la siguiente sincronización.
                </div>

                <div class="flex justify-end">
                    <button class="rounded-xl bg-green-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                        Enviar notificación
                    </button>
                </div>
            </form>
        </section>

        <section class="broadcast-card overflow-hidden">
            <div class="border-b border-black/5 p-5 dark:border-white/10">
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Últimos envíos</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Historial reciente de comunicados enviados desde administración.</p>
            </div>

            <div class="divide-y divide-black/5 dark:divide-white/10">
                @forelse($recentNotifications as $broadcast)
                    <article class="p-5">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-black text-gray-900 dark:text-white">{{ $broadcast['title'] }}</h3>
                                    <span class="broadcast-level {{ $broadcast['level'] }}">
                                        {{ $levels[$broadcast['level']] ?? 'Media' }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm font-medium text-gray-500 dark:text-gray-300">{{ $broadcast['message'] }}</p>
                            </div>

                            <div class="shrink-0 rounded-2xl border border-black/5 bg-white/60 px-4 py-3 text-right dark:border-white/10 dark:bg-white/5">
                                <div class="text-lg font-black text-gray-900 dark:text-white">{{ $broadcast['recipients'] }}</div>
                                <div class="text-[11px] font-bold uppercase text-gray-500 dark:text-gray-400">Destinatarios</div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-3 text-xs font-bold text-gray-500 dark:text-gray-400">
                            <span>Leídas: {{ $broadcast['read_count'] }}</span>
                            <span>Enviado: {{ optional($broadcast['scheduled_for'])->format('d/m/Y H:i') ?? '—' }}</span>
                        </div>
                    </article>
                @empty
                    <div class="p-8 text-center text-sm font-bold text-gray-500 dark:text-gray-300">
                        Aún no has enviado notificaciones globales.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection

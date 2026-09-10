@extends('layouts.app')

@section('title', 'Planes')

@section('content')
@php
    $billingLabels = [
        'monthly' => 'Mensual',
        'yearly' => 'Anual',
        'one_time' => 'Pago único',
    ];
@endphp

<style>
    .plans-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.68);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .plans-input {
        width: 100%;
        min-width: 0;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.9);
        padding: 11px 13px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        outline: none;
    }

    .plans-price-input {
        font-variant-numeric: tabular-nums;
        letter-spacing: 0;
    }

    .plans-select-wrap {
        position: relative;
    }

    .plans-select-wrap select {
        appearance: none;
        padding-right: 38px;
    }

    .plans-select-wrap::after {
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

    .dark .plans-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
    }

    .dark .plans-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .plans-select-wrap::after {
        border-color: #cbd5e1;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Planes
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Oferta comercial</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Crea, edita y organiza los planes que luego asignas a clientes desde su detalle.
            </p>
        </div>

        <a href="{{ route('admin.saas.index') }}"
           class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Volver al Centro SaaS
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if(! $plansReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            Falta la tabla de planes. Ejecuta las migraciones para crear y editar planes.
        </div>
    @endif

    <section class="plans-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Crear plan</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Define precio, límites y beneficios del plan.</p>
        </div>

        <form method="POST" action="{{ route('admin.saas.plans.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Nombre</label>
                <input name="name" value="{{ old('name') }}" class="plans-input mt-1" placeholder="Ej: Profesional">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Precio</label>
                <input type="number" step="0.01" min="0" name="price" value="{{ old('price', 0) }}" class="plans-input plans-price-input mt-1">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Cobro</label>
                <div class="plans-select-wrap mt-1">
                    <select name="billing_period" class="plans-input">
                        <option value="monthly">Mensual</option>
                        <option value="yearly">Anual</option>
                        <option value="one_time">Pago único</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <input type="number" min="1" name="max_farms" class="plans-input" placeholder="Fincas">
                <input type="number" min="1" name="max_users" class="plans-input" placeholder="Usuarios">
                <input type="number" min="1" name="max_animals" class="plans-input" placeholder="Animales">
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Descripción</label>
                <textarea name="description" rows="2" class="plans-input mt-1">{{ old('description') }}</textarea>
            </div>

            <div class="md:col-span-2">
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Beneficios, uno por línea</label>
                <textarea name="features" rows="4" class="plans-input mt-1">{{ old('features') }}</textarea>
            </div>

            <div class="md:col-span-2 flex items-center justify-between gap-3">
                <label class="inline-flex items-center gap-2 text-sm font-black text-gray-700 dark:text-gray-200">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                    Paquete activo
                </label>

                <button class="rounded-xl bg-green-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                    Crear plan
                </button>
            </div>
        </form>
    </section>

    <section class="plans-card p-5">
        <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Planes existentes</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Edita límites, precio y beneficios.</p>
            </div>
            <span class="text-xs font-black uppercase text-gray-400">{{ $plans->count() }} plan(es)</span>
        </div>

        <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
            @forelse($plans as $plan)
                <div class="rounded-2xl border border-gray-200 bg-white/70 p-5 dark:border-white/10 dark:bg-slate-950/50">
                    <form method="POST" action="{{ route('admin.saas.plans.update', $plan) }}" class="space-y-3">
                        @csrf
                        @method('PATCH')

                        <input name="name" value="{{ $plan->name }}" class="plans-input">
                        <textarea name="description" rows="2" class="plans-input">{{ $plan->description }}</textarea>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                            <input type="number" step="0.01" min="0" name="price" value="{{ $plan->price }}" class="plans-input plans-price-input">
                            <div class="plans-select-wrap">
                                <select name="billing_period" class="plans-input">
                                    @foreach($billingLabels as $value => $label)
                                        <option value="{{ $value }}" @selected($plan->billing_period === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <input type="number" min="1" name="max_farms" value="{{ $plan->max_farms }}" class="plans-input" placeholder="Fincas">
                            <input type="number" min="1" name="max_users" value="{{ $plan->max_users }}" class="plans-input" placeholder="Usuarios">
                            <input type="number" min="1" name="max_animals" value="{{ $plan->max_animals }}" class="plans-input" placeholder="Animales">
                        </div>

                        <textarea name="features" rows="4" class="plans-input">{{ implode("\n", $plan->features ?? []) }}</textarea>

                        <div class="flex items-center justify-between gap-3">
                            <label class="inline-flex items-center gap-2 text-sm font-black text-gray-700 dark:text-gray-200">
                                <input type="checkbox" name="is_active" value="1" @checked($plan->is_active) class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                Activo
                            </label>
                            <button class="rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white">Guardar</button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.saas.plans.destroy', $plan) }}" class="mt-3" onsubmit="return confirm('¿Eliminar este plan?')">
                        @csrf
                        @method('DELETE')
                        <button class="w-full rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-black text-red-600 transition hover:bg-red-100 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                            Eliminar plan
                        </button>
                    </form>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300 lg:col-span-3">
                    Crea el primer plan para organizar la oferta comercial.
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection

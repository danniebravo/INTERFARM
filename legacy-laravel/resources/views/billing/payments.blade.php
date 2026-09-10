@extends('layouts.app')

@section('title', 'Pagos')

@section('content')
@php
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'failed' => 'Fallido', 'refunded' => 'Reembolsado'];
    $statusClasses = [
        'paid' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-200',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-200',
        'refunded' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200',
    ];
@endphp

@include('billing.partials.styles')

<div class="space-y-6">
    @include('billing.partials.header', [
        'title' => 'Pagos realizados',
        'description' => 'Consulta los pagos aplicados a tu suscripción y las referencias registradas.',
    ])

    @if(! $paymentsReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            El módulo de pagos todavía está pendiente por activar.
        </div>
    @endif

    <section class="billing-shell p-4 md:p-5">
        <form method="GET" action="{{ route('client.billing.payments') }}" class="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_180px_140px]">
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                </span>
                <input name="q" value="{{ request('q') }}" placeholder="Busca por referencia o método..." class="billing-control w-full pl-12 pr-4">
            </div>

            <select name="status" class="billing-control w-full px-4">
                <option value="">Todos los estados</option>
                @foreach($statusLabels as $status => $label)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                @endforeach
            </select>

            <button class="rounded-2xl bg-green-600 px-4 py-2.5 text-sm font-black text-white transition hover:bg-green-700">
                Filtrar
            </button>
        </form>
    </section>

    <section class="billing-shell">
        <div class="overflow-x-auto">
            <table class="min-w-[860px] w-full text-sm">
                <thead class="bg-white/70 text-left text-xs font-black uppercase text-slate-600 dark:bg-slate-950/60 dark:text-slate-300">
                    <tr>
                        <th class="px-5 py-4">Referencia</th>
                        <th class="px-5 py-4">Factura</th>
                        <th class="px-5 py-4">Fecha</th>
                        <th class="px-5 py-4">Método</th>
                        <th class="px-5 py-4">Valor</th>
                        <th class="px-5 py-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr class="border-t border-black/5 dark:border-white/10">
                            <td class="px-5 py-4">
                                <div class="font-black text-slate-900 dark:text-white">{{ $payment->reference ?? 'Sin referencia' }}</div>
                                <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $payment->plan?->name ?? 'Plan no asignado' }}</div>
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-600 dark:text-slate-300">{{ $payment->invoice?->invoice_number ?? 'No asociada' }}</td>
                            <td class="px-5 py-4 font-semibold text-slate-600 dark:text-slate-300">{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}</td>
                            <td class="px-5 py-4 font-black text-slate-900 dark:text-white">{{ ucfirst($payment->payment_method ?? 'manual') }}</td>
                            <td class="px-5 py-4 font-black text-green-700 dark:text-green-200">{{ $payment->currency }} ${{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-4">
                                <span class="billing-pill {{ $statusClasses[$payment->status] ?? $statusClasses['pending'] }}">
                                    {{ $statusLabels[$payment->status] ?? ucfirst($payment->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="text-sm font-black text-slate-700 dark:text-slate-200">No encontramos pagos con esos filtros.</div>
                                <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Cuando un pago sea confirmado, aparecerá en este historial.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

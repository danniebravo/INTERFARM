@extends('layouts.app')

@section('title', 'Facturas')

@section('content')
@php
    $statusLabels = ['pending' => 'Sin pagar', 'paid' => 'Pagado', 'overdue' => 'Vencida', 'cancelled' => 'Cancelada'];
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'paid' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-200',
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-200',
        'cancelled' => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200',
    ];
@endphp

@include('billing.partials.styles')

<div class="space-y-6">
    @include('billing.partials.header', [
        'title' => 'Facturas de tu finca',
        'description' => 'Revisa tus facturas generadas automáticamente. Te avisaremos desde 10 días antes de cada vencimiento.',
    ])

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-800 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if(! $invoicesReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            El módulo de facturas todavía está pendiente por activar.
        </div>
    @endif

    <section class="billing-shell p-4 md:p-5">
        <form method="GET" action="{{ route('client.billing.invoices') }}" class="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_180px_140px]">
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z"/></svg>
                </span>
                <input name="q" value="{{ request('q') }}" placeholder="Busca por número de factura..." class="billing-control w-full pl-12 pr-4">
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
            <table class="min-w-[920px] w-full text-sm">
                <thead class="bg-white/70 text-left text-xs font-black uppercase text-slate-600 dark:bg-slate-950/60 dark:text-slate-300">
                    <tr>
                        <th class="px-5 py-4">Factura #</th>
                        <th class="px-5 py-4">Publicado</th>
                        <th class="px-5 py-4">Vence</th>
                        <th class="px-5 py-4">Importe total</th>
                        <th class="px-5 py-4">Importe restante</th>
                        <th class="px-5 py-4">Estado</th>
                        <th class="px-5 py-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr class="border-t border-black/5 dark:border-white/10">
                            <td class="px-5 py-4">
                                <div class="font-black text-slate-900 dark:text-white">{{ $invoice->invoice_number }}</div>
                                <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ $invoice->plan?->name ?? 'Plan no asignado' }}</div>
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-600 dark:text-slate-300">{{ optional($invoice->issue_date)->format('d/m/Y') }}</td>
                            <td class="px-5 py-4 font-black text-slate-900 dark:text-white">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                            <td class="px-5 py-4 font-black text-slate-900 dark:text-white">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 font-black text-slate-900 dark:text-white">{{ $invoice->status === 'paid' ? $invoice->currency . ' $0' : $invoice->currency . ' $' . number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-4">
                                <span class="billing-pill {{ $statusClasses[$invoice->status] ?? $statusClasses['cancelled'] }}">
                                    {{ $statusLabels[$invoice->status] ?? ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('client.billing.invoices.show', $invoice) }}"
                                       class="rounded-full bg-sky-100 px-4 py-2 text-xs font-black text-slate-900 transition hover:bg-sky-200 dark:bg-sky-400/15 dark:text-sky-100">
                                        Ver
                                    </a>
                                    <a href="{{ route('client.billing.invoices.download', $invoice) }}"
                                       class="rounded-full bg-green-600 px-4 py-2 text-xs font-black text-white transition hover:bg-green-700">
                                        PDF
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <div class="text-sm font-black text-slate-700 dark:text-slate-200">No encontramos facturas con esos filtros.</div>
                                <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Cuando se acerque tu próximo vencimiento, se generará la factura 10 días antes.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

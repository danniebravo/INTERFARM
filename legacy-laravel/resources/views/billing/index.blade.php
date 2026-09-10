@extends('layouts.app')

@section('title', 'Mi facturación')

@section('content')
@php
    $periodLabels = ['monthly' => 'Mensual', 'yearly' => 'Anual', 'one_time' => 'Pago único'];
    $statusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagada', 'overdue' => 'Vencida', 'cancelled' => 'Cancelada'];
@endphp

<style>
    .billing-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.68);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .billing-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
    }

    .dark .billing-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
    }
</style>

<div class="space-y-6">
    <div>
        <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
            Mi plan y facturación
        </div>
        <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Facturación de tu cuenta</h1>
        <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
            Consulta tu plan, próximos pagos, facturas y pagos realizados.
        </p>
    </div>

    @if(! $invoicesReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            El módulo de facturas todavía está pendiente por activar.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Plan actual</div>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ $user->subscriptionPlan?->name ?? 'Sin plan asignado' }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">{{ $periodLabels[$user->subscriptionPlan?->billing_period] ?? 'Sin periodo' }}</div>
        </section>

        <section class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Próximo vencimiento</div>
            <div class="mt-2 text-2xl font-black text-gray-900 dark:text-white">{{ $nextChargeDate ? $nextChargeDate->format('d/m/Y') : 'No aplica' }}</div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">Factura generada 10 días antes</div>
        </section>

        <section class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Valor próximo pago</div>
            <div class="mt-2 text-2xl font-black text-green-700 dark:text-green-200">
                ${{ number_format((float) ($nextInvoice?->amount ?? $user->subscriptionPlan?->price ?? 0), 0, ',', '.') }}
            </div>
            <div class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">{{ $nextInvoice ? 'Factura pendiente' : 'Según plan actual' }}</div>
        </section>
    </div>

    <section class="billing-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Facturas</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Facturas generadas para tu suscripción.</p>
        </div>

        @if($invoices->count())
            <div class="overflow-x-auto rounded-2xl border border-black/10 dark:border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/60 text-left text-xs font-black uppercase text-gray-500 dark:bg-slate-950/50 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Factura</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Emisión</th>
                            <th class="px-4 py-3">Vence</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr class="border-t border-black/5 dark:border-white/10">
                                <td class="px-4 py-3 font-black text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $invoice->plan?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ optional($invoice->issue_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-black text-green-700 dark:text-green-200">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <span class="billing-pill bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        {{ $statusLabels[$invoice->status] ?? ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if(in_array($invoice->status, ['pending', 'overdue'], true))
                                        <button class="rounded-xl bg-green-600 px-3 py-2 text-xs font-black text-white">
                                            Pagar
                                        </button>
                                    @else
                                        <span class="text-xs font-bold text-gray-500 dark:text-gray-300">Sin acción</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300">
                Aún no tienes facturas generadas.
            </div>
        @endif
    </section>

    <section class="billing-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Pagos realizados</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Historial de pagos de tu suscripción.</p>
        </div>

        @if($payments->count())
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($payments as $payment)
                    <div class="rounded-2xl border border-black/10 bg-white/60 p-4 dark:border-white/10 dark:bg-slate-950/50">
                        <div class="text-sm font-black text-gray-900 dark:text-white">{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}</div>
                        <div class="mt-1 text-xs font-bold text-gray-500 dark:text-gray-300">{{ $payment->invoice?->invoice_number ?? $payment->reference ?? 'Sin referencia' }}</div>
                        <div class="mt-3 text-lg font-black text-green-700 dark:text-green-200">{{ $payment->currency }} ${{ number_format((float) $payment->amount, 0, ',', '.') }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300">
                Aún no hay pagos registrados.
            </div>
        @endif
    </section>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Factura ' . $invoice->invoice_number)

@section('content')
@php
    $statusLabels = ['pending' => 'Sin pagar', 'paid' => 'Pagada', 'overdue' => 'Vencida', 'cancelled' => 'Cancelada'];
    $statusClasses = [
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        'paid' => 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-200',
        'overdue' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-200',
        'cancelled' => 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200',
    ];
    $remainingAmount = $invoice->status === 'paid' ? 0 : (float) $invoice->amount;
    $periodText = trim((optional($invoice->period_start)->format('d/m/Y') ?: '-') . ' - ' . (optional($invoice->period_end)->format('d/m/Y') ?: '-'));
@endphp

@include('billing.partials.styles')

<style>
    .invoice-page {
        color: #0f172a;
    }

    .invoice-paper {
        overflow: hidden;
        border-radius: 28px;
        border: 1px solid rgba(15,23,42,.08);
        background: #ffffff;
        box-shadow: 0 24px 70px rgba(15,23,42,.10);
    }

    .invoice-paper-top {
        height: 8px;
        background: linear-gradient(90deg, #166534, #22c55e, #a3e635);
    }

    .invoice-brand-mark {
        display: inline-flex;
        height: 48px;
        width: 48px;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        background: #166534;
        color: #fff;
        font-weight: 1000;
        letter-spacing: .04em;
        box-shadow: 0 14px 28px rgba(22,101,52,.20);
    }

    .invoice-mini-card {
        border-radius: 18px;
        border: 1px solid rgba(15,23,42,.08);
        background: #f8fafc;
        padding: 16px;
    }

    .invoice-table th {
        padding: 14px 0;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 11px;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .invoice-table td {
        padding: 22px 0;
        border-bottom: 1px solid #eef2f7;
    }

    .invoice-actions {
        border-radius: 24px;
        border: 1px solid rgba(15,23,42,.08);
        background: rgba(255,255,255,.78);
        box-shadow: 0 18px 45px rgba(15,23,42,.06);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
    }

    .dark .invoice-page {
        color: #f8fafc;
    }

    .dark .invoice-paper {
        border-color: rgba(148,163,184,.20);
        background: #020617;
        box-shadow: 0 24px 70px rgba(0,0,0,.35);
    }

    .dark .invoice-mini-card {
        border-color: rgba(148,163,184,.18);
        background: rgba(15,23,42,.84);
    }

    .dark .invoice-table th {
        border-bottom-color: rgba(148,163,184,.20);
        color: #cbd5e1;
    }

    .dark .invoice-table td {
        border-bottom-color: rgba(148,163,184,.12);
    }

    .dark .invoice-actions {
        border-color: rgba(148,163,184,.18);
        background: rgba(15,23,42,.78);
        box-shadow: 0 18px 45px rgba(0,0,0,.24);
    }
</style>

<div class="invoice-page space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-800 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">{{ session('error') }}</div>
    @endif

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Factura
            </div>
            <h1 class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $invoice->invoice_number }}</h1>
            <p class="mt-2 text-sm font-semibold text-slate-500 dark:text-slate-300">
                Revisa el detalle, paga si está pendiente y descarga el soporte.
            </p>
        </div>

        <a href="{{ route('client.billing.invoices') }}"
           class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-slate-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-slate-100">
            Volver a facturas
        </a>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="invoice-paper">
            <div class="invoice-paper-top"></div>

            <div class="p-6 md:p-8 lg:p-10">
                <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="invoice-brand-mark">IF</div>
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-2xl font-black text-slate-950 dark:text-white">Factura</h2>
                                <span class="billing-pill {{ $statusClasses[$invoice->status] ?? $statusClasses['cancelled'] }}">
                                    {{ $statusLabels[$invoice->status] ?? ucfirst($invoice->status) }}
                                </span>
                            </div>
                            <div class="mt-2 text-sm font-bold text-slate-500 dark:text-slate-400">#{{ $invoice->invoice_number }}</div>
                        </div>
                    </div>

                    <div class="text-left md:text-right">
                        <x-brand-logo class="h-12 w-auto md:ml-auto" />
                        <div class="mt-3 text-sm font-black text-slate-950 dark:text-white">InterFarm</div>
                        <div class="text-sm font-semibold text-slate-500 dark:text-slate-400">Gestión ganadera SaaS</div>
                    </div>
                </div>

                <div class="mt-10 grid gap-4 md:grid-cols-2">
                    <div class="invoice-mini-card">
                        <div class="text-xs font-black uppercase text-slate-400">Factura a</div>
                        <div class="mt-2 text-lg font-black text-slate-950 dark:text-white">{{ $user->full_name ?: $user->email }}</div>
                        <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                    </div>

                    <div class="invoice-mini-card">
                        <div class="text-xs font-black uppercase text-slate-400">Resumen</div>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="font-bold text-slate-500 dark:text-slate-400">Emitida</div>
                                <div class="font-black text-slate-950 dark:text-white">{{ optional($invoice->issue_date)->format('d/m/Y') ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-slate-500 dark:text-slate-400">Vence</div>
                                <div class="font-black text-slate-950 dark:text-white">{{ optional($invoice->due_date)->format('d/m/Y') ?: '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-green-50 p-5 dark:bg-green-500/10">
                        <div class="text-xs font-black uppercase text-green-700 dark:text-green-200">Total</div>
                        <div class="mt-2 text-2xl font-black text-green-800 dark:text-green-100">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-5 dark:bg-white/5">
                        <div class="text-xs font-black uppercase text-slate-400">Pendiente</div>
                        <div class="mt-2 text-2xl font-black text-slate-950 dark:text-white">{{ $invoice->currency }} ${{ number_format($remainingAmount, 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-5 dark:bg-white/5">
                        <div class="text-xs font-black uppercase text-slate-400">Periodo</div>
                        <div class="mt-2 text-sm font-black text-slate-950 dark:text-white">{{ $periodText }}</div>
                    </div>
                </div>

                <div class="mt-10 overflow-x-auto">
                    <table class="invoice-table min-w-[720px] w-full text-sm">
                        <thead>
                            <tr class="text-left">
                                <th>Concepto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-right">Precio unitario</th>
                                <th class="text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="text-base font-black text-slate-950 dark:text-white">{{ $invoice->plan?->name ?? 'Suscripción InterFarm' }}</div>
                                    <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Acceso a plataforma InterFarm</div>
                                </td>
                                <td class="text-center font-black text-slate-950 dark:text-white">1</td>
                                <td class="text-right font-black text-slate-950 dark:text-white">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                                <td class="text-right font-black text-slate-950 dark:text-white">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-10 flex justify-end">
                    <div class="w-full max-w-sm rounded-2xl bg-slate-50 p-5 dark:bg-white/5">
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between font-bold text-slate-500 dark:text-slate-400">
                                <span>Subtotal</span>
                                <span>{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between border-t border-slate-200 pt-3 text-lg font-black text-slate-950 dark:border-white/10 dark:text-white">
                                <span>Total</span>
                                <span>{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-black text-green-700 dark:text-green-200">
                                <span>Saldo pendiente</span>
                                <span>{{ $invoice->currency }} ${{ number_format($remainingAmount, 0, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-10 rounded-2xl border border-dashed border-slate-300 p-5 text-sm font-semibold text-slate-500 dark:border-white/10 dark:text-slate-400">
                    Esta factura fue generada automáticamente por InterFarm. Conserva este soporte para tus registros.
                </div>
            </div>
        </section>

        <aside class="invoice-actions overflow-hidden">
            <div class="border-b border-black/10 p-5 dark:border-white/10">
                <div class="text-xs font-black uppercase text-slate-400">Acciones</div>
                <h3 class="mt-2 text-xl font-black text-slate-900 dark:text-white">Factura #{{ $invoice->invoice_number }}</h3>

                <div class="mt-5 grid gap-3">
                    @if(in_array($invoice->status, ['pending', 'overdue'], true))
                        <form method="POST" action="{{ route('client.billing.invoices.checkout', $invoice) }}">
                            @csrf
                            <button class="w-full rounded-2xl bg-green-600 px-4 py-3 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                                Pagar ahora
                            </button>
                        </form>
                    @endif

                    <a href="{{ route('client.billing.invoices.download', $invoice) }}"
                       class="inline-flex items-center justify-center rounded-2xl border border-black/10 bg-white px-4 py-3 text-sm font-black text-slate-700 transition hover:bg-slate-50 dark:border-white/10 dark:bg-slate-950 dark:text-white dark:hover:bg-white/5">
                        Descargar PDF
                    </a>
                </div>
            </div>

            <div class="border-b border-black/10 p-5 dark:border-white/10">
                <div class="text-sm font-black text-slate-900 dark:text-white">Visión general</div>
                <div class="mt-4 space-y-3 text-sm font-semibold text-slate-500 dark:text-slate-300">
                    <div class="flex justify-between gap-4"><span>Estado</span><span class="text-right">{{ $statusLabels[$invoice->status] ?? ucfirst($invoice->status) }}</span></div>
                    <div class="flex justify-between gap-4"><span>Total</span><span class="text-right">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</span></div>
                    <div class="flex justify-between gap-4"><span>Vence</span><span class="text-right">{{ optional($invoice->due_date)->format('d/m/Y') ?: '-' }}</span></div>
                    <div class="flex justify-between gap-4"><span>Plan</span><span class="text-right">{{ $invoice->plan?->name ?? 'Sin plan' }}</span></div>
                </div>
            </div>

            <div class="p-5">
                <div class="text-sm font-black text-slate-900 dark:text-white">Historial de pagos</div>
                <div class="mt-4 space-y-3">
                    @forelse($invoice->payments as $payment)
                        <div class="rounded-2xl border border-black/10 bg-white/60 p-3 text-sm dark:border-white/10 dark:bg-white/5">
                            <div class="font-black text-slate-900 dark:text-white">{{ $payment->currency }} ${{ number_format((float) $payment->amount, 0, ',', '.') }}</div>
                            <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: 'Sin fecha' }}</div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm font-semibold text-slate-500 dark:border-white/10 dark:text-slate-400">
                            Aún no hay pagos asociados.
                        </div>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

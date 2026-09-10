@extends('layouts.app')

@section('title', 'Cuenta suspendida')

@section('content')
@php
    $pendingTotal = $invoices->sum(fn ($invoice) => (float) $invoice->amount);
    $nextInvoice = $invoices->sortBy('due_date')->first();
@endphp

<style>
    .suspended-card {
        border-radius: 30px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.76);
        box-shadow: 0 18px 46px rgba(15,23,42,.08);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }

    .suspended-icon {
        display: inline-flex;
        width: 64px;
        height: 64px;
        align-items: center;
        justify-content: center;
        border-radius: 22px;
        background: linear-gradient(135deg, #f59e0b, #dc2626);
        color: #fff;
        box-shadow: 0 18px 34px rgba(220,38,38,.20);
    }

    .suspended-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 950;
        transition: .18s ease;
    }

    .suspended-action.primary {
        background: #16a34a;
        color: #fff;
        box-shadow: 0 16px 30px rgba(22,163,74,.22);
    }

    .suspended-action.primary:hover {
        background: #15803d;
    }

    .suspended-action.secondary {
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.78);
        color: #334155;
    }

    .suspended-action.secondary:hover {
        background: #fff;
    }

    .suspended-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        background: rgba(245,158,11,.14);
        color: #92400e;
        font-size: 11px;
        font-weight: 950;
    }

    .dark .suspended-card {
        border-color: rgba(148,163,184,.20);
        background: rgba(15,23,42,.88);
        box-shadow: 0 22px 54px rgba(0,0,0,.36), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .suspended-action.secondary {
        border-color: rgba(255,255,255,.10);
        background: rgba(2,6,23,.68);
        color: #e2e8f0;
    }

    .dark .suspended-action.secondary:hover {
        background: rgba(15,23,42,.92);
    }

    .dark .suspended-pill {
        background: rgba(245,158,11,.18);
        color: #fde68a;
    }
</style>

<div class="mx-auto max-w-5xl space-y-6">
    <section class="suspended-card overflow-hidden">
        <div class="grid gap-0 lg:grid-cols-[1.1fr_.9fr]">
            <div class="p-6 sm:p-8">
                <div class="suspended-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
                        <path stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.4 2.8 17.5A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.5L13.7 4.4a2 2 0 0 0-3.4 0Z"/>
                    </svg>
                </div>

                <div class="mt-6">
                    <span class="suspended-pill">Acceso temporalmente suspendido</span>
                    <h1 class="mt-4 text-3xl font-black tracking-tight text-gray-900 dark:text-white">
                        Tu cuenta necesita atención para continuar
                    </h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-300">
                        El acceso a los módulos de InterFarm fue pausado porque hay un pago pendiente o el plan llegó a su fecha de vencimiento. Tus datos siguen guardados y seguros; cuando el pago sea actualizado, volverás a ingresar normalmente a tu finca.
                    </p>
                </div>

                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    @if(Route::has('client.billing.invoices'))
                        <a href="{{ route('client.billing.invoices') }}" class="suspended-action primary">
                            Ver facturas pendientes
                        </a>
                    @endif

                    @if(Route::has('client.billing.payment-methods'))
                        <a href="{{ route('client.billing.payment-methods') }}" class="suspended-action secondary">
                            Actualizar forma de pago
                        </a>
                    @endif
                </div>
            </div>

            <div class="border-t border-black/5 bg-white/42 p-6 sm:p-8 dark:border-white/10 dark:bg-white/5 lg:border-l lg:border-t-0">
                <div class="rounded-3xl border border-black/5 bg-white/72 p-5 dark:border-white/10 dark:bg-slate-950/48">
                    <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Saldo pendiente</div>
                    <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">
                        COP ${{ number_format($pendingTotal, 0, ',', '.') }}
                    </div>

                    <div class="mt-4 space-y-2 text-sm font-bold text-gray-500 dark:text-gray-300">
                        <div class="flex justify-between gap-4">
                            <span>Facturas pendientes</span>
                            <span class="text-gray-900 dark:text-white">{{ $invoices->count() }}</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span>Próximo vencimiento</span>
                            <span class="text-right text-gray-900 dark:text-white">
                                {{ $nextInvoice?->due_date?->format('d/m/Y') ?? 'Sin fecha' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-3xl border border-green-500/15 bg-green-50/70 p-5 text-sm font-bold leading-6 text-green-800 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-100">
                    Si ya realizaste el pago, espera la confirmación del equipo de InterFarm o comunícate con soporte para reactivar tu cuenta.
                </div>
            </div>
        </div>
    </section>

    <section class="suspended-card overflow-hidden">
        <div class="border-b border-black/5 p-5 dark:border-white/10">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Facturas por revisar</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Estas son las facturas pendientes o vencidas asociadas a tu cuenta.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-white/60 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-300">
                    <tr>
                        <th class="px-5 py-3">Factura</th>
                        <th class="px-5 py-3">Plan</th>
                        <th class="px-5 py-3">Vence</th>
                        <th class="px-5 py-3">Valor</th>
                        <th class="px-5 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5 dark:divide-white/10">
                    @forelse($invoices as $invoice)
                        <tr>
                            <td class="px-5 py-4 font-black text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</td>
                            <td class="px-5 py-4 font-bold text-gray-500 dark:text-gray-300">{{ $invoice->plan?->name ?? 'Plan InterFarm' }}</td>
                            <td class="px-5 py-4 font-bold text-gray-500 dark:text-gray-300">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-5 py-4 font-black text-gray-900 dark:text-white">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                            <td class="px-5 py-4 text-right">
                                @if(Route::has('client.billing.invoices.show'))
                                    <a href="{{ route('client.billing.invoices.show', $invoice) }}" class="rounded-xl bg-green-600 px-3 py-2 text-xs font-black text-white">
                                        Ver
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm font-bold text-gray-500 dark:text-gray-300">
                                No encontramos facturas pendientes. Contacta a soporte para revisar la suspensión de la cuenta.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Facturación')

@section('content')
@php
    $periodLabels = ['monthly' => 'Mensual', 'yearly' => 'Anual', 'one_time' => 'Pago único'];
    $statusLabels = ['trial' => 'Periodo de prueba', 'active' => 'Pago al día', 'past_due' => 'Pendiente', 'cancelled' => 'Cancelado', 'manual' => 'Manual'];
    $invoiceStatusLabels = ['pending' => 'Pendiente', 'paid' => 'Pagada', 'overdue' => 'Vencida', 'cancelled' => 'Cancelada'];
    $paymentStatusLabels = ['paid' => 'Pagado', 'pending' => 'Pendiente', 'failed' => 'Fallido', 'refunded' => 'Devuelto'];
@endphp

<style>
    .billing-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.68);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .billing-input {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.9);
        padding: 11px 13px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        outline: none;
    }

    .billing-select-wrap {
        position: relative;
    }

    .billing-select-wrap select {
        appearance: none;
        padding-right: 38px;
    }

    .billing-select-wrap::after {
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

    .dark .billing-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .billing-select-wrap::after {
        border-color: #cbd5e1;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Facturación
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Cobros de clientes</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Revisa clientes, facturas generadas y pagos realizados. La configuración de la pasarela se administra desde Configuración.
            </p>
        </div>

        <a href="{{ route('admin.saas.settings.index') }}#payment-settings"
           class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Configurar Wompi
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if(! $paymentsReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            Falta la migración de pagos SaaS. Ejecuta las migraciones para registrar pagos realizados.
        </div>
    @endif

    @if(! $invoicesReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            Falta la migración de facturas SaaS. Ejecuta las migraciones para generar cobros automáticos.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Clientes con plan</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ $billingStats['clients_with_plan'] }}</div>
        </div>
        <div class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Facturas pendientes</div>
            <div class="mt-2 text-3xl font-black text-amber-700 dark:text-amber-200">{{ $billingStats['pending_invoices'] }}</div>
        </div>
        <div class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Pagos registrados</div>
            <div class="mt-2 text-3xl font-black text-gray-900 dark:text-white">{{ $billingStats['payments_count'] }}</div>
        </div>
        <div class="billing-card p-5">
            <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Total pagado</div>
            <div class="mt-2 text-3xl font-black text-green-700 dark:text-green-200">${{ number_format((float) $billingStats['paid_total'], 0, ',', '.') }}</div>
        </div>
    </div>

    <section class="billing-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Registrar pago</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Útil para pagos manuales o conciliaciones mientras los cobros automáticos quedan activos.</p>
        </div>

        <form method="POST" action="{{ route('admin.saas.payments.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <div class="xl:col-span-2">
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Cliente</label>
                <div class="billing-select-wrap mt-1">
                    <select name="user_id" class="billing-input">
                        @foreach($billingClients as $row)
                            <option value="{{ $row['client']->id }}">
                                {{ $row['client']->full_name ?: $row['client']->email }} · {{ $row['plan']?->name ?? 'Sin plan' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="xl:col-span-2">
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Factura asociada</label>
                <div class="billing-select-wrap mt-1">
                    <select name="subscription_invoice_id" class="billing-input">
                        <option value="">Sin asociar</option>
                        @foreach($invoices->whereIn('status', ['pending', 'overdue']) as $invoice)
                            <option value="{{ $invoice->id }}">
                                {{ $invoice->invoice_number }} · {{ $invoice->user?->full_name ?: $invoice->user?->email }} · ${{ number_format((float) $invoice->amount, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Valor</label>
                <input type="number" step="0.01" min="0" name="amount" class="billing-input mt-1" required>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Moneda</label>
                <input name="currency" value="{{ $settings['billing_currency'] ?? 'COP' }}" class="billing-input mt-1" required>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Fecha pago</label>
                <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\\TH:i') }}" class="billing-input mt-1" required>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Estado</label>
                <div class="billing-select-wrap mt-1">
                    <select name="status" class="billing-input">
                        @foreach($paymentStatusLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Método</label>
                <input name="payment_method" class="billing-input mt-1" placeholder="Wompi, transferencia, efectivo...">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Referencia</label>
                <input name="reference" class="billing-input mt-1">
            </div>

            <div class="md:col-span-2 xl:col-span-4">
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Notas</label>
                <textarea name="notes" rows="2" class="billing-input mt-1"></textarea>
            </div>

            <div class="md:col-span-2 xl:col-span-4 flex justify-end">
                <button class="rounded-xl bg-green-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                    Registrar pago
                </button>
            </div>
        </form>
    </section>

    <section class="billing-card p-5">
        <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Facturas generadas</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Aquí se listarán las facturas automáticas generadas para cada cliente.</p>
            </div>
            <a href="{{ route('admin.saas.settings.index') }}#payment-settings" class="inline-flex rounded-xl border border-green-500/25 bg-green-50 px-4 py-2 text-xs font-black text-green-700 transition hover:bg-green-100 dark:border-green-400/25 dark:bg-green-500/10 dark:text-green-200">
                Configurar facturación
            </a>
        </div>

        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px]">
            <input type="search" class="billing-input" placeholder="Buscar factura por cliente, referencia o correo">
            <input type="date" class="billing-input">
            <input type="date" class="billing-input">
        </div>

        @if($invoices->count())
            <div class="mt-4 overflow-x-auto rounded-2xl border border-black/10 dark:border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/60 text-left text-xs font-black uppercase text-gray-500 dark:bg-slate-950/50 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Factura</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Emisión</th>
                            <th class="px-4 py-3">Vence</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr class="border-t border-black/5 dark:border-white/10">
                                <td class="px-4 py-3 font-black text-gray-900 dark:text-white">{{ $invoice->invoice_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $invoice->user?->full_name ?: 'Sin nombre' }}</div>
                                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $invoice->user?->email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $invoice->plan?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ optional($invoice->issue_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-black text-green-700 dark:text-green-200">{{ $invoice->currency }} ${{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">
                                    <span class="billing-pill bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        {{ $invoiceStatusLabels[$invoice->status] ?? ucfirst($invoice->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($invoice->user)
                                        <a href="{{ route('admin.users.show', $invoice->user) }}" class="inline-flex rounded-xl border border-black/10 bg-white px-3 py-2 text-xs font-black text-gray-700 dark:border-white/10 dark:bg-slate-950 dark:text-gray-100">
                                            Cliente
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mt-4 rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300">
                Todavía no hay facturas automáticas generadas.
            </div>
        @endif
    </section>

    <section class="billing-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Próximos cobros por cliente</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Incluye métricas operativas para entender el tamaño de cada cuenta.</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-black/10 dark:border-white/10">
            <table class="min-w-full text-sm">
                <thead class="bg-white/60 text-left text-xs font-black uppercase text-gray-500 dark:bg-slate-950/50 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Cobro</th>
                        <th class="px-4 py-3">Siguiente fecha</th>
                        <th class="px-4 py-3">Datos</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($billingClients as $row)
                        <tr class="border-t border-black/5 dark:border-white/10">
                            <td class="px-4 py-3">
                                <div class="font-black text-gray-900 dark:text-white">{{ $row['client']->full_name ?: 'Sin nombre' }}</div>
                                <div class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $row['client']->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-800 dark:text-gray-100">{{ $row['plan']?->name ?? 'Sin plan' }}</div>
                                <div class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $periodLabels[$row['period']] ?? 'Sin periodo' }}</div>
                            </td>
                            <td class="px-4 py-3 font-black text-green-700 dark:text-green-200">
                                ${{ number_format((float) $row['amount'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-700 dark:text-gray-200">
                                {{ $row['next_charge_date'] ? $row['next_charge_date']->format('d/m/Y') : 'No aplica' }}
                            </td>
                            <td class="px-4 py-3 text-xs font-bold text-gray-600 dark:text-gray-300">
                                {{ $row['farms_count'] }} fincas · {{ $row['animals_count'] }} animales · {{ $row['lots_count'] }} lotes
                            </td>
                            <td class="px-4 py-3">
                                <span class="billing-pill bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                    {{ $statusLabels[$row['client']->billing_status] ?? 'Periodo de prueba' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.users.show', $row['client']) }}" class="inline-flex rounded-xl border border-black/10 bg-white px-3 py-2 text-xs font-black text-gray-700 dark:border-white/10 dark:bg-slate-950 dark:text-gray-100">
                                    Editar cliente
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="billing-card p-5">
        <div class="mb-5">
            <h2 class="text-lg font-black text-gray-900 dark:text-white">Pagos realizados</h2>
            <p class="text-sm text-gray-500 dark:text-gray-300">Últimos pagos registrados en la plataforma.</p>
        </div>

        @if($payments->count())
            <div class="overflow-x-auto rounded-2xl border border-black/10 dark:border-white/10">
                <table class="min-w-full text-sm">
                    <thead class="bg-white/60 text-left text-xs font-black uppercase text-gray-500 dark:bg-slate-950/50 dark:text-gray-300">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Referencia</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                            <tr class="border-t border-black/5 dark:border-white/10">
                                <td class="px-4 py-3 font-bold text-gray-700 dark:text-gray-200">{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: '—' }}</td>
                                <td class="px-4 py-3 font-black text-gray-900 dark:text-white">{{ $payment->user?->full_name ?: $payment->user?->email }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $payment->plan?->name ?? '—' }}</td>
                                <td class="px-4 py-3 font-black text-green-700 dark:text-green-200">{{ $payment->currency }} ${{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ $payment->reference ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="billing-pill bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-200">
                                        {{ $paymentStatusLabels[$payment->status] ?? ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.saas.payments.destroy', $payment) }}" onsubmit="return confirm('¿Eliminar este pago?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-black text-red-600 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300">
                Todavía no hay pagos registrados.
            </div>
        @endif
    </section>
</div>
@endsection

@php
    $billingTabs = [
        ['label' => 'Facturas', 'route' => 'client.billing.invoices'],
        ['label' => 'Pagos', 'route' => 'client.billing.payments'],
        ['label' => 'Formas de pago', 'route' => 'client.billing.payment-methods'],
    ];
@endphp

<div class="space-y-4">
    <div>
        <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
            Facturación
        </div>
        <h1 class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $title ?? 'Facturación de tu cuenta' }}</h1>
        <p class="mt-2 max-w-3xl text-sm font-semibold text-slate-500 dark:text-slate-300">
            {{ $description ?? 'Consulta tus cobros, pagos y formas de pago recurrentes según el periodo de tu plan.' }}
        </p>
    </div>

    <section class="billing-shell p-4 md:p-5">
        <nav class="billing-tabs md:grid-cols-3">
            @foreach($billingTabs as $tab)
                <a href="{{ route($tab['route']) }}"
                   class="billing-tab {{ request()->routeIs($tab['route']) || request()->routeIs($tab['route'] . '.*') ? 'active' : '' }}">
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    </section>
</div>

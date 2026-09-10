@extends('layouts.app')

@section('title', 'Forma de pago')

@section('content')
@include('billing.partials.styles')

<div class="space-y-6">
    @include('billing.partials.header', [
        'title' => 'Formas de pago',
        'description' => 'Los datos completos de la tarjeta se ingresan directamente en Wompi al pagar una factura pendiente.',
    ])

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-800 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">{{ session('error') }}</div>
    @endif

    @if(! $paymentMethodsReady)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200">
            El módulo de formas de pago todavía está pendiente por activar.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <section class="billing-shell p-5">
            <div class="mb-5">
                <h2 class="text-lg font-black text-slate-900 dark:text-white">Referencias guardadas</h2>
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
                    No guardamos el número completo ni el código de seguridad de tus tarjetas. Wompi recibe esos datos en su portal de pago seguro.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @forelse($paymentMethods as $method)
                    <div class="rounded-3xl border border-black/10 bg-white/70 p-5 dark:border-white/10 dark:bg-slate-950/55">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs font-black uppercase tracking-wide text-slate-400">{{ ucfirst($method->provider) }}</div>
                                <div class="mt-2 text-xl font-black text-slate-900 dark:text-white">{{ strtoupper($method->brand ?? 'Tarjeta') }} **** {{ $method->last_four }}</div>
                                <div class="mt-1 text-sm font-bold text-slate-500 dark:text-slate-400">
                                    {{ $method->holder_name ?: 'Titular no definido' }} · vence {{ str_pad((string) $method->expiry_month, 2, '0', STR_PAD_LEFT) }}/{{ $method->expiry_year }}
                                </div>
                            </div>
                            @if($method->is_default)
                                <span class="billing-pill bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-200">Principal</span>
                            @endif
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            @if(! $method->is_default)
                                <form method="POST" action="{{ route('client.billing.payment-methods.default', $method) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="rounded-xl border border-green-500/25 bg-green-50 px-3 py-2 text-xs font-black text-green-700 transition hover:bg-green-100 dark:border-green-400/25 dark:bg-green-500/10 dark:text-green-200">
                                        Usar como principal
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('client.billing.payment-methods.destroy', $method) }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-xl border border-red-500/20 bg-red-50 px-3 py-2 text-xs font-black text-red-700 transition hover:bg-red-100 dark:border-red-400/25 dark:bg-red-500/10 dark:text-red-200">
                                    Desactivar
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-3xl border border-dashed border-slate-300 p-8 text-center md:col-span-2 dark:border-white/10">
                        <div class="text-sm font-black text-slate-700 dark:text-slate-200">Aún no hay referencias guardadas.</div>
                        <div class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Cuando pagues desde una factura, el cobro se completará en el portal de pago seguro.</div>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="billing-shell p-5">
            <div class="mb-5">
                <h2 class="text-lg font-black text-slate-900 dark:text-white">Checkout seguro</h2>
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">
                    Para pagar, entra a una factura pendiente y presiona Pagar ahora. Te enviaremos a Wompi para ingresar la tarjeta, PSE u otro método disponible.
                </p>
            </div>

            <div class="rounded-2xl border border-green-500/15 bg-green-50 p-4 text-sm font-bold text-green-800 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-100">
                Por seguridad, InterFarm no almacena números completos de tarjeta ni CVV.
            </div>

            <a href="{{ route('client.billing.invoices', ['status' => 'pending']) }}"
               class="mt-4 inline-flex w-full items-center justify-center rounded-2xl bg-green-600 px-4 py-3 text-sm font-black text-white transition hover:bg-green-700">
                Ver facturas pendientes
            </a>
        </section>
    </div>
</div>
@endsection

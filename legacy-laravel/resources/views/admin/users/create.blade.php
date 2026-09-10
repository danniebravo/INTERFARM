@extends('layouts.app')

@section('title', 'Crear cliente')

@section('content')
<style>
    .client-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.68);
        box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }

    .client-input {
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

    .client-input:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 4px rgba(34,197,94,.12);
    }

    .client-select-wrap {
        position: relative;
    }

    .client-select-wrap select {
        appearance: none;
        padding-right: 38px;
    }

    .client-select-wrap::after {
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

    .dark .client-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
    }

    .dark .client-input {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .client-select-wrap::after {
        border-color: #cbd5e1;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Clientes
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Crear cliente</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Registra un nuevo cliente y deja listo su acceso inicial a la plataforma.
            </p>
        </div>

        <a href="{{ route('admin.users.index') }}"
           class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Volver a clientes
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
            Revisa los campos marcados antes de crear el cliente.
        </div>
    @endif

    <section class="client-card p-5">
        <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @csrf

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Nombre</label>
                <input name="first_name" value="{{ old('first_name') }}" class="client-input mt-1" required autofocus>
                @error('first_name') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Apellido</label>
                <input name="last_name" value="{{ old('last_name') }}" class="client-input mt-1" required>
                @error('last_name') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" class="client-input mt-1" required>
                @error('email') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Tipo documento</label>
                <div class="client-select-wrap mt-1">
                    <select name="document_type" class="client-input" required>
                        <option value="">Selecciona una opción</option>
                        <option value="cc" @selected(old('document_type') === 'cc')>Cédula de ciudadanía</option>
                        <option value="ce" @selected(old('document_type') === 'ce')>Cédula de extranjería</option>
                        <option value="nit" @selected(old('document_type') === 'nit')>NIT</option>
                        <option value="passport" @selected(old('document_type') === 'passport')>Pasaporte</option>
                    </select>
                </div>
                @error('document_type') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Documento</label>
                <input name="document" value="{{ old('document') }}" class="client-input mt-1" required>
                @error('document') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Teléfono</label>
                <input name="phone" value="{{ old('phone') }}" class="client-input mt-1">
                @error('phone') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Contraseña</label>
                <input type="password" name="password" class="client-input mt-1" required>
                @error('password') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="client-input mt-1" required>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Estado</label>
                <div class="client-select-wrap mt-1">
                    <select name="status" class="client-input">
                        <option value="active" @selected(old('status', 'active') === 'active')>Activo</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactivo</option>
                        <option value="suspended" @selected(old('status') === 'suspended')>Suspendido</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Plan</label>
                <div class="client-select-wrap mt-1">
                    <select name="subscription_plan_id" class="client-input">
                        <option value="">Sin plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) old('subscription_plan_id') === (string) $plan->id)>
                                {{ $plan->name }} · ${{ number_format((float) $plan->price, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Facturación</label>
                <div class="client-select-wrap mt-1">
                    <select name="billing_status" class="client-input">
                        <option value="trial" @selected(old('billing_status', 'trial') === 'trial')>Periodo de prueba</option>
                        <option value="active" @selected(old('billing_status') === 'active')>Pago al día</option>
                        <option value="past_due" @selected(old('billing_status') === 'past_due')>Pago pendiente</option>
                        <option value="manual" @selected(old('billing_status') === 'manual')>Manual</option>
                        <option value="cancelled" @selected(old('billing_status') === 'cancelled')>Cancelado</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Días periodo de prueba</label>
                <input type="number" min="0" max="365" name="trial_days" value="{{ old('trial_days', 15) }}" class="client-input mt-1">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Fecha de vencimiento</label>
                <input type="date" name="next_billing_date" value="{{ old('next_billing_date') }}" class="client-input mt-1">
            </div>

            <div class="md:col-span-2 xl:col-span-3 flex justify-end">
                <button class="rounded-xl bg-green-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                    Crear cliente
                </button>
            </div>
        </form>
    </section>
</div>
@endsection

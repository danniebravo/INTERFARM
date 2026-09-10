@extends('layouts.app')

@section('title', 'Detalle de usuario')

@section('content')

@php
    $status = $user->status ?? 'inactive';

    $statusClass = match($status) {
        'active' => 'active',
        'suspended' => 'suspended',
        default => 'inactive',
    };

    $farms = $user->farms ?? collect();
    $plans = $plans ?? collect();
    $billingStatus = $user->billing_status ?? 'trial';

    $activityAreaLabel = function ($activity) {
        $action = (string) ($activity->action ?? '');

        if (str_contains($action, 'invoice') || str_contains($action, 'payment')) {
            return 'Facturación';
        }

        if (str_contains($action, 'animal')) {
            return 'Animales';
        }

        if (str_contains($action, 'lot')) {
            return 'Lotes';
        }

        if (str_contains($action, 'production') || str_contains($action, 'milk') || str_contains($action, 'meat')) {
            return 'Producción';
        }

        if (str_contains($action, 'finance')) {
            return 'Finanzas';
        }

        if (str_contains($action, 'event')) {
            return 'Calendario';
        }

        if (str_contains($action, 'report')) {
            return 'Reportes';
        }

        if (str_contains($action, 'setting') || str_contains($action, 'role') || str_contains($action, 'member')) {
            return 'Configuración';
        }

        if (str_contains($action, 'farm')) {
            return 'Fincas';
        }

        if (str_contains($action, 'profile')) {
            return 'Perfil';
        }

        if (str_contains($action, 'dashboard')) {
            return 'Panel principal';
        }

        return 'Actividad registrada';
    };
@endphp

<style>
    .admin-detail-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.62);
        border-radius: 24px;
        padding: 20px;
        box-shadow: 0 12px 28px rgba(0,0,0,.06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }

    .admin-status-pill {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .admin-status-pill.active {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .admin-status-pill.inactive {
        background: rgba(107,114,128,.12);
        color: #4b5563;
    }

    .admin-status-pill.suspended {
        background: rgba(239,68,68,.10);
        color: #dc2626;
    }

    .admin-info-label {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .admin-info-value {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }

    .admin-farm-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.52);
        border-radius: 20px;
        padding: 16px;
    }

    .admin-metric-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.56);
        border-radius: 20px;
        padding: 16px;
    }

    .admin-select {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.82);
        padding: 12px 14px;
        outline: none;
        font-weight: 700;
        color: #111827;
        appearance: none;
    }

    .admin-select-wrap {
        position: relative;
    }

    .admin-select-wrap::after {
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

    .dark .admin-detail-card,
    .dark .admin-farm-card,
    .dark .admin-metric-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.78);
    }

    .dark .admin-info-label,
    .dark .admin-detail-card .text-gray-500,
    .dark .admin-farm-card .text-gray-500 {
        color: #cbd5e1 !important;
    }

    .dark .admin-info-value,
    .dark .admin-detail-card .text-gray-900,
    .dark .admin-farm-card .text-gray-900,
    .dark .admin-metric-card .text-gray-900 {
        color: #f8fafc !important;
    }

    .dark .admin-select {
        border-color: rgba(148,163,184,.28);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .admin-select-wrap::after {
        border-color: #cbd5e1;
    }

    .activity-item {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 14px;
        align-items: start;
        border-radius: 18px;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.56);
        padding: 14px;
    }

    .activity-dot {
        width: 11px;
        height: 11px;
        border-radius: 999px;
        margin-top: 6px;
        background: #16a34a;
        box-shadow: 0 0 0 5px rgba(22,163,74,.12);
    }

    .dark .activity-item {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.78);
    }

    .admin-animals-table-wrap {
        overflow-x: auto;
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 22px;
        background: rgba(255,255,255,.50);
    }

    .admin-animals-table {
        min-width: 980px;
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .admin-animals-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748b;
        background: rgba(248,250,252,.88);
        border-bottom: 1px solid rgba(0,0,0,.08);
        white-space: nowrap;
    }

    .admin-animals-table td {
        padding: 15px 16px;
        border-bottom: 1px solid rgba(0,0,0,.06);
        vertical-align: top;
        color: #334155;
    }

    .admin-animals-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .admin-animal-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 900;
        white-space: nowrap;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.72);
        color: #334155;
    }

    .admin-animal-pill.active {
        border-color: rgba(22,163,74,.20);
        background: rgba(22,163,74,.10);
        color: #166534;
    }

    .admin-animal-pill.inactive {
        border-color: rgba(239,68,68,.18);
        background: rgba(239,68,68,.10);
        color: #b91c1c;
    }

    .dark .admin-animals-table-wrap {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.72);
    }

    .dark .admin-animals-table th {
        border-color: rgba(148,163,184,.18);
        background: rgba(30,41,59,.88);
        color: #cbd5e1;
    }

    .dark .admin-animals-table td {
        border-color: rgba(148,163,184,.14);
        color: #e2e8f0;
    }

    .dark .admin-animals-table .text-gray-900 {
        color: #f8fafc !important;
    }

    .dark .admin-animals-table .text-gray-500 {
        color: #cbd5e1 !important;
    }

    .dark .admin-animal-pill {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.82);
        color: #e2e8f0;
    }

    .dark .admin-animal-pill.active {
        border-color: rgba(74,222,128,.28);
        background: rgba(22,163,74,.16);
        color: #bbf7d0;
    }

    .dark .admin-animal-pill.inactive {
        border-color: rgba(248,113,113,.28);
        background: rgba(239,68,68,.16);
        color: #fecaca;
    }
</style>

<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">
                {{ $user->full_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: 'Usuario' }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Información general y gestión del estado del usuario.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.users.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver a usuarios
            </a>

            <a href="{{ route('admin.saas.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Centro SaaS
            </a>

            @if($farms->count())
                <form method="POST" action="{{ route('admin.saas.clients.view-as', $user) }}">
                    @csrf
                    <button class="rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                        Ver como cliente
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="glass rounded-[28px] p-6 xl:col-span-2">
            <h2 class="text-lg font-extrabold text-gray-900 mb-5">Información del usuario</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="admin-detail-card">
                    <div class="admin-info-label">Nombre</div>
                    <div class="admin-info-value">
                        {{ $user->full_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: '—' }}
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Correo</div>
                    <div class="admin-info-value">{{ $user->email ?: '—' }}</div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Documento</div>
                    <div class="admin-info-value">
                        {{ ($user->document_type ? ucfirst($user->document_type) . ': ' : '') . ($user->document ?: '—') }}
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Teléfono</div>
                    <div class="admin-info-value">{{ $user->phone ?: '—' }}</div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Estado actual</div>
                    <div class="mt-1">
                        <span class="admin-status-pill {{ $statusClass }}">
                            {{ ucfirst($status) }}
                        </span>
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Fin periodo de prueba</div>
                    <div class="admin-info-value">
                        {{ $user->trial_ends_at ? $user->trial_ends_at->format('d/m/Y H:i') : '—' }}
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Próxima facturación</div>
                    <div class="admin-info-value">
                        {{ $user->next_billing_date ? $user->next_billing_date->format('d/m/Y') : '—' }}
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Correo verificado</div>
                    <div class="admin-info-value">
                        {{ $user->email_verified_at ? $user->email_verified_at->format('d/m/Y H:i') : 'No verificado' }}
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Último inicio de sesión</div>
                    <div class="admin-info-value">
                        {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca ha iniciado sesión' }}
                    </div>
                </div>

                <div class="admin-detail-card">
                    <div class="admin-info-label">Registrado el</div>
                    <div class="admin-info-value">
                        {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="glass rounded-[28px] p-6">
            <h2 class="text-lg font-extrabold text-gray-900 mb-5">Cambiar estado</h2>

            <form method="POST" action="{{ route('admin.users.update-status', $user) }}" class="space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nuevo estado</label>
                    <div class="admin-select-wrap">
                        <select name="status" class="admin-select">
                            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                            <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                        </select>
                    </div>
                    @error('status')
                        <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Guardar estado
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Fincas</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['farms'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Animales</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['animals'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Activos</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['active_animals'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Hembras</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['female_animals'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Machos</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['male_animals'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Lotes</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['lots'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Pagos</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['payments'] }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Total pago</div>
            <div class="mt-2 text-xl font-black text-gray-900">${{ number_format((float) $metrics['paid_total'], 0, ',', '.') }}</div>
        </div>
        <div class="admin-metric-card">
            <div class="text-xs font-black uppercase text-gray-500">Actividad mes</div>
            <div class="mt-2 text-2xl font-black text-gray-900">{{ $metrics['activity_last_month'] }}</div>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Animales del cliente</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Inventario completo registrado en las fincas asociadas a este cliente.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="admin-animal-pill active">{{ $metrics['active_animals'] }} activos</span>
                <span class="admin-animal-pill">{{ $metrics['female_animals'] }} hembras</span>
                <span class="admin-animal-pill">{{ $metrics['male_animals'] }} machos</span>
            </div>
        </div>

        @if($clientAnimals->count())
            <div class="admin-animals-table-wrap">
                <table class="admin-animals-table text-sm">
                    <thead>
                        <tr>
                            <th>Animal</th>
                            <th>Finca</th>
                            <th>Lote</th>
                            <th>Tipo</th>
                            <th>Sexo</th>
                            <th>Raza</th>
                            <th>Peso</th>
                            <th>Edad</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clientAnimals as $animal)
                            @php
                                $animalName = $animal->name ?: ($animal->internal_code ?: 'Animal #' . $animal->id);
                                $animalCode = $animal->internal_code ?: $animal->ear_tag;
                                $purposeLabel = match($animal->purpose) {
                                    'leche' => 'Leche',
                                    'carne' => 'Carne',
                                    'doble_proposito' => 'Doble propósito',
                                    default => $animal->purpose ? ucfirst(str_replace('_', ' ', $animal->purpose)) : '—',
                                };
                                $sexLabel = $animal->sex ? ucfirst($animal->sex) : '—';
                                $ageLabel = $animal->ageInYears() !== null
                                    ? $animal->ageInYears() . ' años'
                                    : '—';
                            @endphp

                            <tr>
                                <td>
                                    <div class="font-black text-gray-900">{{ $animalName }}</div>
                                    <div class="mt-1 text-xs font-bold text-gray-500">
                                        {{ $animalCode ? 'Código: ' . $animalCode : 'Sin código' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="font-bold text-gray-900">{{ $animal->client_farm_name ?: '—' }}</div>
                                </td>
                                <td>{{ $animal->lot?->name ?: 'Sin lote' }}</td>
                                <td>{{ $purposeLabel }}</td>
                                <td>{{ $sexLabel }}</td>
                                <td>{{ $animal->breed ?: '—' }}</td>
                                <td>
                                    {{ $animal->weight_current ? number_format((float) $animal->weight_current, 1, ',', '.') . ' kg' : '—' }}
                                </td>
                                <td>{{ $ageLabel }}</td>
                                <td>
                                    <span class="admin-animal-pill {{ $animal->isActive() ? 'active' : 'inactive' }}">
                                        {{ $animal->statusLabel() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-6 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                Este cliente aún no tiene animales registrados.
            </div>
        @endif
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Actividad del último mes</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Acciones registradas por el cliente durante los últimos 30 días.
                </p>
            </div>

            <span class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1.5 text-xs font-black text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                {{ $activityLogs->count() }} registro(s)
            </span>
        </div>

        @if($activityLogs->count())
            <div class="grid gap-3">
                @foreach($activityLogs as $activity)
                    <div class="activity-item">
                        <span class="activity-dot"></span>
                        <div>
                            <div class="text-sm font-black text-gray-900">{{ $activity->description }}</div>
                            <div class="mt-1 text-xs font-bold text-gray-500">
                                {{ $activityAreaLabel($activity) }}
                            </div>
                        </div>
                        <div class="text-right text-xs font-bold text-gray-500">
                            <div>{{ $activity->created_at?->format('d/m/Y') }}</div>
                            <div>{{ $activity->created_at?->format('H:i') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-6 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                No hay actividad registrada durante el último mes.
            </div>
        @endif
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Editar usuario</h2>
            <p class="text-sm text-gray-500 mt-1">Actualiza los datos principales del cliente.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre</label>
                <input name="first_name" value="{{ old('first_name', $user->first_name) }}" class="admin-select">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Apellido</label>
                <input name="last_name" value="{{ old('last_name', $user->last_name) }}" class="admin-select">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Correo</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="admin-select">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Tipo documento</label>
                <input name="document_type" value="{{ old('document_type', $user->document_type) }}" class="admin-select">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Documento</label>
                <input name="document" value="{{ old('document', $user->document) }}" class="admin-select">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Teléfono</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}" class="admin-select">
            </div>

            <div class="md:col-span-2 xl:col-span-3 flex justify-end">
                <button type="submit"
                        class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Guardar usuario
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Restablecer contraseña</h2>
            <p class="text-sm text-gray-500 mt-1">
                Asigna una nueva contraseña para que el cliente pueda volver a ingresar.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.users.update-password', $user) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nueva contraseña</label>
                <input type="password" name="password" class="admin-select" required>
                @error('password')
                    <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" class="admin-select" required>
            </div>

            <div class="flex items-end">
                <button type="submit"
                        class="w-full rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Actualizar contraseña
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Plan y facturación</h2>
            <p class="text-sm text-gray-500 mt-1">
                Asigna el plan comercial, el estado de pago y la próxima fecha de vencimiento.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.users.update-plan', $user) }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Plan</label>
                <div class="admin-select-wrap">
                    <select name="subscription_plan_id" class="admin-select">
                        <option value="">Sin plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((int) ($user->subscription_plan_id ?? 0) === (int) $plan->id)>
                                {{ $plan->name }} · ${{ number_format((float) $plan->price, 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Estado de facturación</label>
                <div class="admin-select-wrap">
                    <select name="billing_status" class="admin-select">
                        <option value="trial" @selected($billingStatus === 'trial')>Periodo de prueba</option>
                        <option value="active" @selected($billingStatus === 'active')>Activo / pago al día</option>
                        <option value="past_due" @selected($billingStatus === 'past_due')>Pago pendiente</option>
                        <option value="cancelled" @selected($billingStatus === 'cancelled')>Cancelado</option>
                        <option value="manual" @selected($billingStatus === 'manual')>Manual</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha de vencimiento</label>
                <input type="date"
                       name="next_billing_date"
                       value="{{ old('next_billing_date', $user->next_billing_date?->format('Y-m-d')) }}"
                       class="admin-select">
                @error('next_billing_date')
                    <span class="mt-2 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-end">
                <button type="submit"
                        class="w-full rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Guardar facturación
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Fincas asociadas</h2>
            <p class="text-sm text-gray-500 mt-1">
                Relación actual del usuario con sus fincas registradas.
            </p>
        </div>

        @if($farms->count())
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($farms as $farm)
                    <div class="admin-farm-card">
                        <div class="text-sm font-extrabold text-gray-900">
                            {{ $farm->name ?: 'Finca sin nombre' }}
                        </div>

                        <div class="text-xs text-gray-500 mt-2">
                            Ubicación: {{ $farm->location ?: '—' }}
                        </div>

                        <div class="text-xs text-gray-500 mt-1">
                            Rol: {{ ucfirst(str_replace('_', ' ', $farm->pivot->role ?? 'usuario')) }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-5 text-sm text-gray-500">
                Este usuario aún no tiene fincas asociadas.
            </div>
        @endif
    </div>

</div>

@endsection

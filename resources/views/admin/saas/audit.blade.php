@extends('layouts.app')

@section('title', 'Auditoria administrativa')

@section('content')
@php
    $actionLabels = [
        'admin_view_dashboard' => 'Vio panel admin',
        'admin_view_saas' => 'Vio Centro SaaS',
        'admin_view_clients' => 'Vio clientes',
        'admin_view_client' => 'Vio cliente',
        'admin_prepare_client' => 'Abrió cliente',
        'admin_view_staff' => 'Vio administradores',
        'admin_prepare_staff' => 'Abrió administrador',
        'admin_edit_staff_form' => 'Editó administrador',
        'admin_view_plans' => 'Vio planes',
        'admin_view_billing' => 'Vio facturación',
        'admin_view_invoices' => 'Vio facturas',
        'admin_view_payments' => 'Vio pagos',
        'admin_view_payment_methods' => 'Vio formas de pago',
        'admin_view_notifications' => 'Vio notificaciones',
        'admin_view_settings' => 'Vio configuración',
        'admin_view_audit' => 'Vio auditoría',
        'admin_create_client' => 'Creó cliente',
        'admin_update_client' => 'Actualizó cliente',
        'admin_update_client_password' => 'Cambió contraseña',
        'admin_update_client_status' => 'Cambió estado',
        'admin_update_client_plan' => 'Cambió plan',
        'admin_create_staff' => 'Creó administrador',
        'admin_update_staff' => 'Actualizó administrador',
        'admin_delete_staff' => 'Eliminó administrador',
        'admin_create_plan' => 'Creó plan',
        'admin_update_plan' => 'Actualizó plan',
        'admin_delete_plan' => 'Eliminó plan',
        'admin_update_settings' => 'Configuró SaaS',
        'admin_create_payment' => 'Registró pago',
        'admin_delete_payment' => 'Eliminó pago',
        'admin_send_notification' => 'Envió notificación',
        'admin_view_as_client' => 'Ver como cliente',
        'admin_stop_view_as_client' => 'Salir de cliente',
    ];

    $billingUrl = Route::has('admin.saas.billing.index') ? route('admin.saas.billing.index') : route('admin.saas.index');
    $plansUrl = Route::has('admin.saas.plans.index') ? route('admin.saas.plans.index') : route('admin.saas.index');
    $settingsUrl = Route::has('admin.saas.settings.index') ? route('admin.saas.settings.index') : route('admin.saas.index');
@endphp

<style>
    .audit-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.70);
        box-shadow: 0 14px 34px rgba(15,23,42,.06);
    }

    .audit-input {
        width: 100%;
        min-height: 46px;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background-color: rgba(255,255,255,.94);
        padding: 11px 13px;
        font-size: 13px;
        font-weight: 800;
        color: #111827;
        outline: none;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
    }

    .audit-input::placeholder {
        color: #94a3b8;
        font-weight: 750;
    }

    .audit-input:focus {
        border-color: rgba(34,197,94,.62);
        background-color: #fff;
        box-shadow: 0 0 0 4px rgba(34,197,94,.12);
    }

    select.audit-input {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%2364758b' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
        background-position: right 14px center;
        background-repeat: no-repeat;
        padding-right: 44px;
    }

    select.audit-input option {
        color: #111827;
        background: #fff;
        font-weight: 800;
    }

    .audit-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        background: rgba(22,101,52,.10);
        color: #166534;
        padding: 6px 10px;
        font-size: 11px;
        font-weight: 950;
    }

    .saas-subnav-link {
        border-radius: 999px;
        border: 1px solid rgba(148,163,184,.32);
        background: rgba(255,255,255,.66);
        color: #475569;
        padding: 9px 13px;
        font-size: 12px;
        font-weight: 900;
        transition: .18s ease;
    }

    .saas-subnav-link.active,
    .saas-subnav-link:hover {
        border-color: rgba(34,197,94,.42);
        background: rgba(34,197,94,.12);
        color: #15803d;
    }

    .audit-row {
        border-radius: 18px;
        border: 1px solid rgba(148,163,184,.22);
        background: rgba(255,255,255,.62);
        padding: 16px;
    }

    .dark .audit-card,
    .dark .audit-row {
        border-color: rgba(148,163,184,.20);
        background: rgba(15,23,42,.88);
    }

    .dark .audit-input {
        border-color: rgba(148,163,184,.22);
        background-color: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .audit-input::placeholder {
        color: #64748b;
    }

    .dark .audit-input:focus {
        border-color: rgba(74,222,128,.55);
        background-color: rgba(2,6,23,.95);
        box-shadow: 0 0 0 4px rgba(34,197,94,.16);
    }

    .dark select.audit-input option {
        color: #f8fafc;
        background: #020617;
    }

    .dark .saas-subnav-link {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.82);
        color: #cbd5e1;
    }

    .dark .saas-subnav-link.active,
    .dark .saas-subnav-link:hover {
        border-color: rgba(74,222,128,.35);
        background: rgba(34,197,94,.14);
        color: #bbf7d0;
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Auditoria
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Historial administrativo</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Consulta acciones realizadas por el equipo administrativo: clientes, planes, pagos, configuraciones y comunicados.
            </p>
        </div>

        <a href="{{ route('admin.saas.index') }}"
           class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Volver al Centro SaaS
        </a>
    </div>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.saas.index') }}" class="saas-subnav-link">Resumen</a>
        <a href="{{ route('admin.users.index') }}" class="saas-subnav-link">Clientes</a>
        <a href="{{ $billingUrl }}" class="saas-subnav-link">Facturación</a>
        <a href="{{ $plansUrl }}" class="saas-subnav-link">Planes</a>
        <a href="{{ route('admin.saas.audit.index') }}" class="saas-subnav-link active">Auditoría</a>
        <a href="{{ $settingsUrl }}" class="saas-subnav-link">Configuración</a>
    </div>

    <section class="audit-card p-5">
        <form method="GET" action="{{ route('admin.saas.audit.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="xl:col-span-2">
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Buscar</label>
                <input type="search" name="search" value="{{ $search }}" class="audit-input mt-1" placeholder="Acción, ruta, IP...">
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Administrador</label>
                <select name="admin_id" class="audit-input mt-1">
                    <option value="">Todos</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" @selected((int) $adminId === (int) $admin->id)>
                            {{ $admin->full_name ?: $admin->email }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Acción</label>
                <select name="action" class="audit-input mt-1">
                    <option value="">Todas</option>
                    @foreach($actions as $actionOption)
                        <option value="{{ $actionOption }}" @selected($action === $actionOption)>
                            {{ $actionLabels[$actionOption] ?? $actionOption }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button class="rounded-xl bg-green-600 px-4 py-3 text-sm font-black text-white">Filtrar</button>
                <a href="{{ route('admin.saas.audit.index') }}" class="rounded-xl border border-black/10 bg-white px-4 py-3 text-sm font-black text-gray-700 dark:border-white/10 dark:bg-slate-950 dark:text-gray-100">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="audit-card p-5">
        <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white">Movimientos registrados</h2>
                <p class="text-sm text-gray-500 dark:text-gray-300">Mostrando acciones administrativas recientes.</p>
            </div>
            <span class="audit-pill">{{ $logs->total() }} registro(s)</span>
        </div>

        <div class="space-y-3">
            @forelse($logs as $log)
                @php
                    $metadata = (array) ($log->metadata ?? []);
                    $params = (array) ($metadata['route_parameters'] ?? []);
                    $input = (array) ($metadata['input'] ?? []);
                    $actorName = $log->user?->full_name ?: $log->user?->email ?: 'Administrador eliminado';
                @endphp

                <article class="audit-row">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="audit-pill">{{ $actionLabels[$log->action] ?? $log->action }}</span>
                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-black text-gray-900 dark:text-white">{{ $log->description }}</h3>
                            <p class="mt-1 text-sm font-bold text-gray-500 dark:text-gray-300">
                                {{ $actorName }} · {{ $log->method }} · {{ $log->route_name ?: $metadata['path'] ?? 'Sin ruta' }}
                            </p>
                        </div>

                        <div class="text-left text-xs font-bold text-gray-500 dark:text-gray-300 lg:text-right">
                            <div>IP: {{ $log->ip_address ?: '—' }}</div>
                            <div>HTTP: {{ $metadata['status_code'] ?? '—' }}</div>
                        </div>
                    </div>

                    @if($params || $input)
                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            @if($params)
                                <div class="rounded-2xl bg-white/70 p-3 text-xs font-bold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    <div class="mb-2 font-black uppercase text-gray-400">Objeto</div>
                                    @foreach($params as $key => $value)
                                        <div class="break-words">
                                            {{ $key }}:
                                            @if(is_array($value))
                                                {{ $value['label'] ?? $value['id'] ?? json_encode($value, JSON_UNESCAPED_UNICODE) }}
                                            @else
                                                {{ $value }}
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if($input)
                                <div class="rounded-2xl bg-white/70 p-3 text-xs font-bold text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                    <div class="mb-2 font-black uppercase text-gray-400">Datos enviados</div>
                                    @foreach($input as $key => $value)
                                        <div class="break-words">
                                            {{ $key }}:
                                            {{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 p-8 text-center text-sm font-bold text-gray-500 dark:border-white/10 dark:text-gray-300">
                    No hay movimientos administrativos con estos filtros.
                </div>
            @endforelse
        </div>

        <div class="mt-5">
            {{ $logs->links() }}
        </div>
    </section>
</div>
@endsection

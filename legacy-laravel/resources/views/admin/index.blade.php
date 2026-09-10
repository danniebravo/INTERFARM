@extends('layouts.app')

@section('title', 'Administrador')

@section('content')

@php
    $stats = $stats ?? [
        'total_users' => 0,
        'active_users' => 0,
        'trial_users' => 0,
        'inactive_users' => 0,
    ];

    $recentUsers = $recentUsers ?? collect();
@endphp

<style>
    .admin-stat-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.62);
        border-radius: 24px;
        padding: 20px;
        box-shadow: 0 12px 28px rgba(0,0,0,.06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }

    .admin-stat-value {
        font-size: 32px;
        line-height: 1;
        font-weight: 900;
        color: #111827;
        margin-top: 10px;
    }

    .admin-stat-label {
        font-size: 13px;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .admin-stat-helper {
        font-size: 13px;
        color: #6b7280;
        margin-top: 8px;
    }

    .admin-quick-card {
        display: block;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.62);
        border-radius: 28px;
        padding: 24px;
        box-shadow: 0 12px 28px rgba(0,0,0,.06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        transition: all .2s ease;
    }

    .admin-quick-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(0,0,0,.10);
    }

    .admin-quick-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .admin-table-wrap {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.42);
    }

    .admin-user-row {
        transition: background .18s ease;
    }

    .admin-user-row:hover {
        background: rgba(255,255,255,.72);
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
        background: rgba(22, 101, 52, .10);
        color: #166534;
    }

    .admin-status-pill.trial {
        background: rgba(245, 158, 11, .12);
        color: #b45309;
    }

    .admin-status-pill.inactive {
        background: rgba(239, 68, 68, .10);
        color: #dc2626;
    }

    .admin-empty-box {
        border: 1px dashed rgba(0,0,0,.10);
        background: rgba(255,255,255,.40);
        border-radius: 24px;
        padding: 40px 24px;
        text-align: center;
    }
</style>

<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Panel administrador</h1>
            <p class="text-sm text-gray-500 mt-1">
                Control general de usuarios registrados en InterFarm.
            </p>
        </div>

        <a href="{{ route('admin.users.index') }}"
           class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
            Gestionar usuarios
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="admin-stat-card">
            <div class="admin-stat-label">Usuarios totales</div>
            <div class="admin-stat-value">{{ $stats['total_users'] ?? 0 }}</div>
            <div class="admin-stat-helper">Todos los registros creados en la plataforma.</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Activos</div>
            <div class="admin-stat-value">{{ $stats['active_users'] ?? 0 }}</div>
            <div class="admin-stat-helper">Usuarios con estado activo.</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">En prueba</div>
            <div class="admin-stat-value">{{ $stats['trial_users'] ?? 0 }}</div>
            <div class="admin-stat-helper">Usuarios que siguen en trial.</div>
        </div>

        <div class="admin-stat-card">
            <div class="admin-stat-label">Inactivos</div>
            <div class="admin-stat-value">{{ $stats['inactive_users'] ?? 0 }}</div>
            <div class="admin-stat-helper">Usuarios pausados o desactivados.</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <a href="{{ route('admin.users.index') }}" class="admin-quick-card">
            <div class="admin-quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4" stroke-width="1.8"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>

            <h2 class="text-2xl font-extrabold text-gray-900">Usuarios</h2>
            <p class="text-sm text-gray-500 mt-2">
                Revisa usuarios registrados, consulta sus datos y cambia su estado desde el panel.
            </p>

            <div class="mt-5 inline-flex rounded-full bg-brand/10 px-4 py-2 text-sm font-bold text-brand">
                Ir al módulo
            </div>
        </a>

        <div class="admin-quick-card">
            <div class="admin-quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-8 w-8">
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M3 12h18"/>
                    <path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 3v18"/>
                </svg>
            </div>

            <h2 class="text-2xl font-extrabold text-gray-900">Más módulos</h2>
            <p class="text-sm text-gray-500 mt-2">
                Este espacio queda listo para después sumar suscripciones, pagos, planes y fincas por cliente.
            </p>

            <div class="mt-5 inline-flex rounded-full bg-white/80 px-4 py-2 text-sm font-bold text-gray-700 border border-black/10">
                Próximamente
            </div>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Usuarios recientes</h2>
                <p class="text-sm text-gray-500 mt-1">Los últimos registros creados en la plataforma.</p>
            </div>

            <a href="{{ route('admin.users.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Ver todos
            </a>
        </div>

        @if($recentUsers->count())
            <div class="admin-table-wrap overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-black/10 text-left text-gray-500 bg-white/35">
                            <th class="py-4 px-5">Usuario</th>
                            <th class="py-4 pr-5">Documento</th>
                            <th class="py-4 pr-5">Teléfono</th>
                            <th class="py-4 pr-5">Estado</th>
                            <th class="py-4 px-5">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentUsers as $user)
                            @php
                                $status = $user->status ?? 'inactive';
                                $statusClass = match($status) {
                                    'active' => 'active',
                                    'trial' => 'trial',
                                    default => 'inactive',
                                };
                            @endphp

                            <tr class="admin-user-row border-b border-black/5 last:border-b-0">
                                <td class="py-4 px-5">
                                    <div class="font-extrabold text-gray-900">
                                        {{ $user->full_name ?: (($user->first_name ?? '').' '.($user->last_name ?? '')) }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $user->email }}
                                    </div>
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $user->document ?: '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $user->phone ?: '—' }}
                                </td>

                                <td class="py-4 pr-5">
                                    <span class="admin-status-pill {{ $statusClass }}">
                                        {{ ucfirst($status) }}
                                    </span>
                                </td>

                                <td class="py-4 px-5">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="admin-empty-box">
                <div class="text-lg font-extrabold text-gray-900">Aún no hay usuarios registrados</div>
                <p class="text-sm text-gray-500 mt-2">
                    Cuando se registren usuarios, aparecerán aquí automáticamente.
                </p>
            </div>
        @endif
    </div>

</div>

@endsection
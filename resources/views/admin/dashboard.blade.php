@extends('layouts.app')

@section('title', 'Administrador')

@section('content')

<style>
    .admin-kpi-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.62);
        border-radius: 24px;
        padding: 20px;
        box-shadow: 0 12px 28px rgba(0,0,0,.06);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        transition: all .2s ease;
    }

    .admin-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(0,0,0,.10);
    }

    .admin-kpi-title {
        font-size: 13px;
        font-weight: 700;
        color: #6b7280;
    }

    .admin-kpi-value {
        font-size: 30px;
        font-weight: 900;
        margin-top: 4px;
    }

    .admin-kpi-value.sm {
        font-size: 24px;
    }

    .admin-recent-card {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.60);
        border-radius: 20px;
        padding: 14px 16px;
        transition: .18s ease;
    }

    .admin-recent-card:hover {
        background: rgba(255,255,255,.80);
    }

    .admin-status-pill {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
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
</style>

<div class="space-y-6">

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900">
                Panel de administrador
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Control general de usuarios y estado del sistema.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.saas.index') }}"
               class="rounded-xl bg-brand px-4 py-2 text-sm font-semibold text-white shadow-glow hover:bg-brand-dark transition">
                Centro SaaS
            </a>

            <a href="{{ route('admin.users.index') }}"
               class="rounded-xl bg-brand/10 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand/15 transition">
                Gestionar usuarios
            </a>
        </div>
    </div>

    {{-- KPIs principales --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Total usuarios</div>
            <div class="admin-kpi-value text-gray-900">
                {{ $totalUsers }}
            </div>
        </div>

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Activos</div>
            <div class="admin-kpi-value text-green-600">
                {{ $activeUsers }}
            </div>
        </div>

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Inactivos</div>
            <div class="admin-kpi-value text-gray-500">
                {{ $inactiveUsers }}
            </div>
        </div>

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Suspendidos</div>
            <div class="admin-kpi-value text-red-600">
                {{ $suspendedUsers }}
            </div>
        </div>

    </div>

    {{-- KPIs secundarios --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Usuarios con fincas</div>
            <div class="admin-kpi-value sm text-gray-900">
                {{ $usersWithFarms }}
            </div>
        </div>

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Usuarios sin fincas</div>
            <div class="admin-kpi-value sm text-gray-900">
                {{ $usersWithoutFarms }}
            </div>
        </div>

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Prueba activa</div>
            <div class="admin-kpi-value sm text-brand">
                {{ $trialUsers }}
            </div>
        </div>

        <div class="admin-kpi-card">
            <div class="admin-kpi-title">Prueba vencida</div>
            <div class="admin-kpi-value sm text-red-500">
                {{ $expiredTrialUsers }}
            </div>
        </div>

    </div>

    {{-- USUARIOS RECIENTES --}}
    <div class="glass rounded-[28px] p-6">

        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">
                    Usuarios recientes
                </h2>
                <p class="text-sm text-gray-500 mt-1">
                    Últimos registros en la plataforma.
                </p>
            </div>

            <a href="{{ route('admin.users.index') }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Ver todos
            </a>
        </div>

        <div class="space-y-3">

            @forelse($recentUsers as $user)
                <div class="admin-recent-card flex items-center justify-between">

                    <div>
                        <div class="font-semibold text-gray-900">
                            {{ $user->full_name ?: $user->email }}
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $user->email }}
                        </div>
                    </div>

                    <div class="flex items-center gap-3">

                        <span class="admin-status-pill
                            @if($user->status === 'active') active
                            @elseif($user->status === 'inactive') inactive
                            @else suspended
                            @endif">
                            {{ ucfirst($user->status) }}
                        </span>

                        <a href="{{ route('admin.users.show', $user) }}"
                           class="text-sm font-semibold text-brand hover:underline">
                            Ver
                        </a>

                    </div>

                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 p-6 text-sm text-gray-500 text-center">
                    No hay usuarios recientes.
                </div>
            @endforelse

        </div>

    </div>

</div>

@endsection

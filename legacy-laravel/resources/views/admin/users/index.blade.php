@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')

<style>
    .admin-table-wrap {
        overflow-x: auto;
        overflow-y: hidden;
        width: 100%;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
        border-radius: 24px;
        border: 1px solid rgba(0, 0, 0, .06);
        background: rgba(255,255,255,.42);
    }

    .admin-users-table {
        min-width: 1280px;
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

    .admin-trial-pill {
        display: inline-flex;
        align-items: center;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        white-space: nowrap;
    }

    .admin-trial-pill.active {
        background: rgba(22,101,52,.10);
        color: #166534;
    }

    .admin-trial-pill.expired {
        background: rgba(239,68,68,.10);
        color: #dc2626;
    }

    .admin-trial-pill.none {
        background: rgba(107,114,128,.12);
        color: #4b5563;
    }

    .admin-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.78);
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 700;
        color: #374151;
        transition: all .18s ease;
        white-space: nowrap;
    }

    .admin-action-btn:hover {
        background: rgba(255,255,255,.96);
        transform: translateY(-1px);
    }

    .admin-filter-input,
    .admin-filter-select {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,.10);
        background: rgba(255,255,255,.75);
        padding: 12px 16px;
        outline: none;
        transition: .18s ease;
        color: #111827;
    }

    .admin-filter-select {
        appearance: none;
        padding-right: 42px;
        cursor: pointer;
    }

    .admin-select-wrap {
        position: relative;
    }

    .admin-select-wrap::after {
        content: "";
        position: absolute;
        right: 16px;
        top: 50%;
        width: 9px;
        height: 9px;
        border-right: 2px solid #64748b;
        border-bottom: 2px solid #64748b;
        transform: translateY(-70%) rotate(45deg);
        pointer-events: none;
    }

    .admin-filter-input:focus,
    .admin-filter-select:focus {
        border-color: rgba(22,101,52,.25);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .admin-table-head th {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .admin-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.72);
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 700;
        color: #4b5563;
    }

    .dark .admin-users-page .glass {
        border-color: rgba(148,163,184,.18);
        background: rgba(15,23,42,.82);
        box-shadow: 0 18px 42px rgba(0,0,0,.32), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .admin-users-page .admin-table-wrap {
        border-color: rgba(148,163,184,.2);
        background: rgba(2,6,23,.42);
    }

    .dark .admin-users-page .admin-table-head tr {
        border-color: rgba(148,163,184,.18);
        background: rgba(15,23,42,.92) !important;
        color: #cbd5e1 !important;
    }

    .dark .admin-users-page .admin-user-row {
        border-color: rgba(148,163,184,.12);
    }

    .dark .admin-users-page .admin-user-row:hover {
        background: rgba(30,41,59,.7);
    }

    .dark .admin-users-page .text-gray-900,
    .dark .admin-users-page .text-gray-800,
    .dark .admin-users-page .font-semibold,
    .dark .admin-users-page .font-bold,
    .dark .admin-users-page .font-extrabold {
        color: #f8fafc !important;
    }

    .dark .admin-users-page .text-gray-700,
    .dark .admin-users-page .text-gray-600,
    .dark .admin-users-page .text-gray-500 {
        color: #cbd5e1 !important;
    }

    .dark .admin-users-page .admin-filter-input,
    .dark .admin-users-page .admin-filter-select {
        border-color: rgba(148,163,184,.24);
        background: rgba(2,6,23,.72);
        color: #f8fafc;
    }

    .dark .admin-users-page .admin-select-wrap::after {
        border-color: #cbd5e1;
    }

    .dark .admin-users-page .admin-filter-input::placeholder {
        color: #94a3b8;
    }

    .dark .admin-users-page .admin-count-badge,
    .dark .admin-users-page .admin-action-btn,
    .dark .admin-users-page a.bg-white\/70 {
        border-color: rgba(148,163,184,.24);
        background: rgba(2,6,23,.72) !important;
        color: #f8fafc !important;
    }

    .dark .admin-users-page .admin-action-btn:hover,
    .dark .admin-users-page a.bg-white\/70:hover {
        background: rgba(30,41,59,.86) !important;
    }

    .dark .admin-users-page .admin-status-pill.inactive,
    .dark .admin-users-page .admin-trial-pill.none {
        background: rgba(148,163,184,.14);
        color: #e2e8f0;
    }
</style>

<div class="admin-users-page space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900">Usuarios registrados</h1>
            <p class="text-sm text-gray-500 mt-1">
                Gestiona los usuarios de la plataforma, su estado y su información general.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.users.create') }}"
               class="inline-flex rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">
                Crear cliente
            </a>

            <a href="{{ route('admin.saas.index') }}"
               class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver al Centro SaaS
            </a>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4" data-auto-filter>
            <div class="md:col-span-2">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar usuario</label>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Nombre, apellido, correo, documento o teléfono"
                    class="admin-filter-input"
                    data-live-search>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Estado</label>
                <div class="admin-select-wrap">
                    <select name="status" class="admin-filter-select">
                        <option value="">Todos</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Activos</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactivos</option>
                        <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>Suspendidos</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Ordenar</label>
                <div class="admin-select-wrap">
                    <select name="sort" class="admin-filter-select">
                        <option value="latest" {{ ($sort ?? 'latest') === 'latest' ? 'selected' : '' }}>Más recientes</option>
                        <option value="name_asc" {{ ($sort ?? 'latest') === 'name_asc' ? 'selected' : '' }}>Nombre A-Z</option>
                        <option value="email_asc" {{ ($sort ?? 'latest') === 'email_asc' ? 'selected' : '' }}>Correo A-Z</option>
                        <option value="trial_ends_asc" {{ ($sort ?? 'latest') === 'trial_ends_asc' ? 'selected' : '' }}>Fin periodo de prueba</option>
                    </select>
                </div>
            </div>

            <div class="md:col-span-4 flex flex-wrap gap-3">
                <button type="submit"
                        class="rounded-xl bg-brand hover:bg-brand-dark transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Filtrar
                </button>

                <a href="{{ route('admin.users.index') }}"
                   class="rounded-xl border border-black/10 bg-white/70 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-white transition">
                    Limpiar
                </a>
            </div>
        </form>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Listado general</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Usuarios registrados en la plataforma.
                </p>
            </div>

            <div class="admin-count-badge">
                {{ $users->total() }} usuario(s) encontrado(s)
            </div>
        </div>

        @if($users->count())
            <div class="admin-table-wrap">
                <table class="admin-users-table text-sm">
                    <thead class="admin-table-head">
                        <tr class="border-b border-black/10 bg-white/40 text-left text-gray-500">
                            <th class="px-5 py-4">Usuario</th>
                            <th class="px-5 py-4">Contacto</th>
                            <th class="px-5 py-4">Documento</th>
                            <th class="px-5 py-4">Fincas</th>
                            <th class="px-5 py-4">Datos</th>
                            <th class="px-5 py-4">Último acceso</th>
                            <th class="px-5 py-4">Actividad mes</th>
                            <th class="px-5 py-4">Periodo de prueba</th>
                            <th class="px-5 py-4">Estado</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($users as $user)
                            <tr class="admin-user-row border-b border-black/5 last:border-b-0">
                                <td class="px-5 py-4">
                                    <div class="min-w-[220px]">
                                        <div class="font-bold text-gray-900">
                                            {{ $user->full_name ?: 'Sin nombre' }}
                                        </div>

                                        <div class="text-xs text-gray-500 mt-1">
                                            Registrado: {{ optional($user->created_at)->format('d/m/Y') ?: '—' }}
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="min-w-[220px]">
                                        <div class="text-gray-800">
                                            {{ $user->email ?: '—' }}
                                        </div>

                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $user->phone ?: 'Sin teléfono' }}
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="text-gray-800">
                                        {{ $user->document ?: '—' }}
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $user->document_type ? ucfirst($user->document_type) : 'Sin tipo' }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">
                                        {{ $user->farms->count() }}
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        {{ $user->farms->count() ? 'Asignadas' : 'Sin fincas' }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="min-w-[150px] text-xs font-bold text-gray-600">
                                        {{ $user->farms->sum(fn ($farm) => $farm->animals->count()) }} animales
                                    </div>
                                    <div class="mt-1 text-xs font-bold text-gray-500">
                                        {{ $user->farms->sum(fn ($farm) => $farm->lots->count()) }} lotes
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="min-w-[150px]">
                                        <div class="font-semibold text-gray-900">
                                            {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y') : 'Nunca' }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $user->last_login_at ? $user->last_login_at->format('H:i') : 'Sin ingresos' }}
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-semibold text-gray-900">
                                        {{ (int) ($user->recent_activity_count ?? 0) }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        acciones últimos 30 días
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if($user->trial_ends_at)
                                        @if($user->trial_ends_at->isFuture())
                                            <span class="admin-trial-pill active">
                                                Activo hasta {{ $user->trial_ends_at->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="admin-trial-pill expired">
                                                Vencido
                                            </span>
                                        @endif
                                    @else
                                        <span class="admin-trial-pill none">
                                            Sin periodo de prueba
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if($user->status === 'active')
                                        <span class="admin-status-pill active">Activo</span>
                                    @elseif($user->status === 'inactive')
                                        <span class="admin-status-pill inactive">Inactivo</span>
                                    @elseif($user->status === 'suspended')
                                        <span class="admin-status-pill suspended">Suspendido</span>
                                    @else
                                        <span class="admin-status-pill inactive">
                                            {{ ucfirst($user->status ?? 'inactivo') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('admin.users.show', $user) }}"
                                       class="admin-action-btn">
                                        Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $users->links() }}
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 px-6 py-12 text-center">
                <div class="text-lg font-extrabold text-gray-900">No se encontraron usuarios</div>
                <p class="text-sm text-gray-500 mt-2">
                    Intenta ajustando la búsqueda o quitando los filtros.
                </p>
            </div>
        @endif
    </div>

</div>

@endsection

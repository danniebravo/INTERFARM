@extends('layouts.app')

@section('title', 'Administradores')

@section('content')
<style>
    .staff-card { border-radius: 24px; border: 1px solid rgba(0,0,0,.07); background: rgba(255,255,255,.68); box-shadow: 0 12px 30px rgba(15,23,42,.05); }
    .staff-input { width: 100%; min-height: 46px; border-radius: 14px; border: 1px solid rgba(148,163,184,.45); background: rgba(255,255,255,.92); padding: 11px 13px; font-size: 13px; font-weight: 800; color: #111827; outline: none; box-shadow: inset 0 1px 0 rgba(255,255,255,.7); transition: border-color .18s ease, box-shadow .18s ease, background .18s ease; }
    .staff-input::placeholder { color: #94a3b8; font-weight: 750; }
    .staff-input:focus { border-color: rgba(34,197,94,.62); background: #fff; box-shadow: 0 0 0 4px rgba(34,197,94,.12); }
    .staff-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 900; border: 1px solid rgba(148,163,184,.28); background: rgba(255,255,255,.72); color: #475569; white-space: nowrap; }
    .staff-pill.green { border-color: rgba(34,197,94,.24); background: rgba(34,197,94,.10); color: #15803d; }
    .dark .staff-card { border-color: rgba(148,163,184,.22); background: rgba(15,23,42,.88); }
    .dark .staff-input { border-color: rgba(148,163,184,.22); background: rgba(2,6,23,.78); color: #f8fafc; }
    .dark .staff-input::placeholder { color: #64748b; }
    .dark .staff-input:focus { border-color: rgba(74,222,128,.55); background: rgba(2,6,23,.95); box-shadow: 0 0 0 4px rgba(34,197,94,.16); }
    .dark .staff-pill { border-color: rgba(148,163,184,.22); background: rgba(2,6,23,.56); color: #e2e8f0; }
    .dark .staff-pill.green { border-color: rgba(74,222,128,.28); background: rgba(34,197,94,.14); color: #bbf7d0; }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">Equipo admin</div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Administradores</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">Crea usuarios internos y controla a que modulos del panel SaaS pueden entrar.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if(auth()->user()?->isSuperAdmin() && Route::has('admin.saas.audit.index'))
                <a href="{{ route('admin.saas.audit.index') }}" class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">Ver auditoría</a>
            @endif

            <a href="{{ route('admin.staff.create') }}" class="inline-flex items-center justify-center rounded-xl bg-green-600 px-4 py-2 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">Crear administrador</a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">{{ session('success') }}</div>
    @endif

    <section class="staff-card p-5">
        <form method="GET" action="{{ route('admin.staff.index') }}" class="grid gap-3 md:grid-cols-[1fr_auto]">
            <input name="search" value="{{ $search }}" class="staff-input" placeholder="Buscar por nombre o correo">
            <button class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950">Buscar</button>
        </form>
    </section>

    <section class="staff-card overflow-hidden">
        @if($admins->count())
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full text-sm">
                    <thead>
                        <tr class="border-b border-black/10 bg-white/50 text-left text-xs font-black uppercase text-gray-500 dark:border-white/10 dark:bg-slate-950/40 dark:text-gray-300">
                            <th class="px-5 py-4">Administrador</th>
                            <th class="px-5 py-4">Rol</th>
                            <th class="px-5 py-4">Permisos</th>
                            <th class="px-5 py-4">Estado</th>
                            <th class="px-5 py-4">Ultimo acceso</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($admins as $admin)
                            @php
                                $adminPermissions = $admin->isSuperAdmin()
                                    ? array_keys($permissions)
                                    : (array) ($admin->admin_permissions ?? array_keys($permissions));
                            @endphp
                            <tr class="border-b border-black/5 last:border-b-0 dark:border-white/10">
                                <td class="px-5 py-4">
                                    <div class="font-black text-gray-900 dark:text-white">{{ $admin->full_name ?: $admin->name ?: 'Sin nombre' }}</div>
                                    <div class="mt-1 text-xs font-bold text-gray-500 dark:text-gray-300">{{ $admin->email }}</div>
                                </td>
                                <td class="px-5 py-4"><span class="staff-pill green">{{ $admin->isSuperAdmin() ? 'Super administrador' : 'Administrador' }}</span></td>
                                <td class="px-5 py-4">
                                    <div class="flex max-w-[420px] flex-wrap gap-2">
                                        @foreach($adminPermissions as $permission)
                                            @if(isset($permissions[$permission]))
                                                <span class="staff-pill">{{ $permissions[$permission]['label'] }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-4"><span class="staff-pill">{{ $admin->status ?: 'Sin estado' }}</span></td>
                                <td class="px-5 py-4 text-sm font-bold text-gray-600 dark:text-gray-300">{{ optional($admin->last_login_at)->format('d/m/Y H:i') ?: 'Nunca' }}</td>
                                <td class="px-5 py-4 text-right">
                                    <div class="inline-flex flex-wrap justify-end gap-2">
                                        <a href="{{ route('admin.staff.edit', $admin) }}" class="inline-flex rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">Editar</a>

                                        @if((int) $admin->id !== (int) auth()->id())
                                            <form method="POST" action="{{ route('admin.staff.destroy', $admin) }}" onsubmit="return confirm('¿Eliminar este administrador? Esta acción no se puede deshacer.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="inline-flex rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-black text-red-600 transition hover:bg-red-100 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-5">{{ $admins->links() }}</div>
        @else
            <div class="px-6 py-12 text-center">
                <div class="text-lg font-black text-gray-900 dark:text-white">No hay administradores registrados</div>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-300">Crea el primer usuario interno para delegar tareas del panel.</p>
            </div>
        @endif
    </section>
</div>
@endsection

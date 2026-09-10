@extends('layouts.app')

@section('title', 'Configuración')

@section('content')

<style>
    .settings-card {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.64);
        box-shadow: 0 12px 30px rgba(0,0,0,.05);
        padding: 22px;
    }

    .settings-card::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72);
        pointer-events: none;
    }

    .settings-input,
    .settings-select,
    .settings-textarea {
        width: 100%;
        border-radius: 16px;
        border: 1px solid rgba(0,0,0,.10);
        background: linear-gradient(135deg, rgba(255,255,255,.92), rgba(255,255,255,.72));
        padding: 12px 16px;
        outline: none;
        transition: .18s ease;
        color: #111827;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.72), 0 8px 18px rgba(15,23,42,.04);
    }

    .settings-textarea {
        resize: vertical;
    }

    .settings-input:focus,
    .settings-select:focus,
    .settings-textarea:focus {
        border-color: rgba(22,101,52,.25);
        box-shadow: 0 0 0 4px rgba(22,101,52,.08);
    }

    .settings-select-shell {
        position: relative;
    }

    .settings-select-shell::after {
        content: "";
        position: absolute;
        top: 50%;
        right: 16px;
        width: 9px;
        height: 9px;
        border-right: 2px solid rgba(22,101,52,.76);
        border-bottom: 2px solid rgba(22,101,52,.76);
        transform: translateY(-66%) rotate(45deg);
        pointer-events: none;
    }

    .settings-select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        padding-right: 44px;
    }

    .settings-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 900;
        transition: .18s ease;
    }

    .settings-btn-primary {
        background: linear-gradient(135deg, #166534, #14532d);
        color: #fff;
        box-shadow: 0 10px 24px rgba(22,101,52,.22);
    }

    .settings-btn-secondary {
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.78);
        color: #374151;
    }

    .settings-btn-danger {
        border: 1px solid rgba(220,38,38,.18);
        background: rgba(254,242,242,.88);
        color: #b91c1c;
    }

    .settings-danger-card {
        border-color: rgba(220,38,38,.18);
        background:
            radial-gradient(700px 220px at 0% 0%, rgba(239,68,68,.08), transparent 42%),
            rgba(255,255,255,.64);
    }

    .settings-role-card {
        border-radius: 20px;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.52);
        padding: 16px;
    }

    .settings-permission-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 6px 10px;
        background: rgba(22,101,52,.09);
        color: #166534;
        font-size: 11px;
        font-weight: 900;
    }

    .settings-table-wrap {
        overflow-x: auto;
        border-radius: 22px;
        border: 1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.42);
    }

    .settings-table {
        min-width: 820px;
        width: 100%;
    }

    .settings-table th,
    .settings-table td {
        padding: 14px 16px;
        text-align: left;
        white-space: nowrap;
    }

    .settings-table th {
        color: #6b7280;
        font-size: 12px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .settings-table tbody tr {
        border-top: 1px solid rgba(0,0,0,.05);
    }
</style>

@php
    $productionLabels = [
        'leche' => 'Leche',
        'carne' => 'Carne',
        'doble_proposito' => 'Doble propósito',
    ];
@endphp

<div class="space-y-6">
    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            {{ session('warning') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
            Revisa los campos del formulario. Hay información pendiente o inválida.
        </div>
    @endif

    <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">Configuración</h1>
        <p class="text-sm text-gray-500 mt-1">Gestiona la finca, roles, permisos y acceso de empleados.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="settings-card">
            <div class="text-xs font-black uppercase tracking-wide text-gray-500">Finca actual</div>
            <div class="mt-3 text-2xl font-black text-gray-900">{{ $farm->name }}</div>
            <div class="mt-2 text-sm text-gray-500">{{ $farm->location ?: 'Ubicación no definida' }}</div>
        </div>

        <div class="settings-card">
            <div class="text-xs font-black uppercase tracking-wide text-gray-500">Miembros</div>
            <div class="mt-3 text-2xl font-black text-gray-900">{{ $members->count() }}</div>
            <div class="mt-2 text-sm text-gray-500">Usuarios con acceso a esta finca.</div>
        </div>

        <div class="settings-card">
            <div class="text-xs font-black uppercase tracking-wide text-gray-500">Plan actual</div>
            <div class="mt-3 text-2xl font-black text-gray-900">{{ $planName }}</div>
            <div class="mt-2 text-sm text-gray-500">Los planes se asignarán desde administración.</div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="settings-card xl:col-span-2">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Información de la finca</h2>
                <p class="text-sm text-gray-500 mt-1">Mantén actualizados los datos principales de esta finca.</p>
            </div>

            <form method="POST" action="{{ route('settings.farm.update') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ old('name', $farm->name) }}" class="settings-input" required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Ubicación</label>
                    <input type="text" name="location" value="{{ old('location', $farm->location) }}" class="settings-input" placeholder="Municipio, vereda o dirección">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Hectáreas</label>
                    <input type="number" step="0.01" min="0" name="hectares" value="{{ old('hectares', $farm->hectares) }}" class="settings-input" placeholder="0.00">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tipo de producción</label>
                    <div class="settings-select-shell">
                        <select name="production_type" class="settings-select" required>
                            @foreach($productionLabels as $value => $label)
                                <option value="{{ $value }}" {{ old('production_type', $farm->production_type) === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción</label>
                    <textarea name="description" rows="4" class="settings-textarea" placeholder="Notas generales de la finca...">{{ old('description', $farm->description) }}</textarea>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="settings-btn settings-btn-primary">
                        Guardar finca
                    </button>
                </div>
            </form>
        </div>

        <div class="settings-card">
            <div class="text-xs font-black uppercase tracking-wide text-gray-500">Roles disponibles</div>
            <div class="mt-3 text-2xl font-black text-gray-900">{{ count($roles) }}</div>
            <div class="mt-2 text-sm text-gray-500">Incluye roles base y roles creados para esta finca.</div>
            <div class="mt-5 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3 text-sm font-semibold text-emerald-800">
                La administración de planes quedará conectada cuando desarrollemos el módulo administrativo.
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="settings-card xl:col-span-2">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Roles de la finca</h2>
                <p class="text-sm text-gray-500 mt-1">Define cómo se agrupan los permisos de tus empleados.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($roles as $key => $role)
                    <div class="settings-role-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-black text-gray-900">{{ $role['name'] }}</div>
                                <div class="mt-1 text-sm text-gray-500">{{ $role['description'] ?? 'Sin descripción.' }}</div>
                            </div>

                            <span class="rounded-full bg-white/70 border border-black/10 px-3 py-1 text-[11px] font-black text-gray-600">
                                {{ ($role['custom'] ?? false) ? 'Creado' : 'Base' }}
                            </span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($role['permissions'] ?? [] as $permission)
                                <span class="settings-permission-pill">
                                    {{ $availablePermissions[$permission] ?? $permission }}
                                </span>
                            @endforeach
                        </div>

                        @if($role['custom'] ?? false)
                            <form method="POST" action="{{ route('settings.roles.destroy', $key) }}" class="mt-4" onsubmit="return confirm('¿Eliminar este rol? Solo se puede eliminar si no está asignado a usuarios.')">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="settings-btn settings-btn-danger">
                                    Eliminar rol
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="settings-card">
            <div class="mb-5">
                <h2 class="text-lg font-extrabold text-gray-900">Crear rol</h2>
                <p class="text-sm text-gray-500 mt-1">Crea perfiles de acceso propios para tu operación.</p>
            </div>

            <form method="POST" action="{{ route('settings.roles.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Nombre del rol</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="settings-input" placeholder="Ej: Ordeñador" required>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Descripción</label>
                    <textarea name="description" rows="3" class="settings-textarea" placeholder="Qué puede hacer este rol...">{{ old('description') }}</textarea>
                </div>

                <div>
                    <div class="text-sm font-semibold text-gray-700 mb-2">Permisos</div>
                    <div class="space-y-2">
                        @foreach($availablePermissions as $permission => $label)
                            <label class="flex items-center gap-3 rounded-2xl border border-black/5 bg-white/50 px-3 py-2 text-sm font-semibold text-gray-700">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" class="rounded border-gray-300 text-brand focus:ring-brand">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="settings-btn settings-btn-primary w-full">
                    Guardar rol
                </button>
            </form>
        </div>
    </div>

    <div class="settings-card">
        <div class="mb-5">
            <h2 class="text-lg font-extrabold text-gray-900">Acceso de empleados</h2>
            <p class="text-sm text-gray-500 mt-1">Agrega usuarios registrados a esta finca y asígnales un rol.</p>
            <p class="text-xs font-semibold text-gray-400 mt-1">Al quitar acceso, el usuario no se elimina de la plataforma; solo deja de pertenecer a esta finca.</p>
        </div>

        <form method="POST" action="{{ route('settings.members.attach') }}" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Correo del usuario</label>
                <input type="email" name="email" value="{{ old('email') }}" class="settings-input" placeholder="empleado@correo.com" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Rol</label>
                <div class="settings-select-shell">
                    <select name="role" class="settings-select" required>
                        @foreach($roles as $key => $role)
                            <option value="{{ $key }}">{{ $role['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex items-end">
                <button type="submit" class="settings-btn settings-btn-primary w-full">
                    Agregar empleado
                </button>
            </div>
        </form>

        <div class="settings-table-wrap">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Actualizar rol</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $member)
                        @php
                            $memberRole = $member->pivot->role ?? 'employee';
                        @endphp

                        <tr>
                            <td class="font-bold text-gray-900">
                                {{ $member->full_name ?: $member->name ?: 'Usuario' }}
                            </td>
                            <td>{{ $member->email }}</td>
                            <td>
                                <span class="settings-permission-pill">
                                    {{ $roles[$memberRole]['name'] ?? ucfirst($memberRole) }}
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('settings.members.role', $member) }}" class="flex gap-2">
                                    @csrf
                                    @method('PATCH')

                                    <div class="settings-select-shell min-w-[220px]">
                                        <select name="role" class="settings-select">
                                            @foreach($roles as $key => $role)
                                                <option value="{{ $key }}" {{ $memberRole === $key ? 'selected' : '' }}>
                                                    {{ $role['name'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <button type="submit" class="settings-btn settings-btn-secondary">
                                        Guardar
                                    </button>
                                </form>
                            </td>
                            <td>
                                @if((int) $member->id !== (int) auth()->id())
                                    <form method="POST" action="{{ route('settings.members.detach', $member) }}" onsubmit="return confirm('¿Quitar este empleado de la finca?')">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="settings-btn settings-btn-danger">
                                            Eliminar acceso
                                        </button>
                                    </form>
                                @else
                                    <span class="text-sm font-bold text-gray-400">Tu usuario</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="settings-card settings-danger-card">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="text-xs font-black uppercase tracking-wide text-red-600">Zona de peligro</div>
                <h2 class="mt-2 text-lg font-extrabold text-gray-900">Eliminar finca</h2>
                <p class="mt-1 max-w-3xl text-sm text-gray-500">
                    Esta acción elimina definitivamente la finca actual junto con sus animales, lotes, producción, finanzas, eventos, roles y fotos asociadas.
                </p>
            </div>
        </div>

        <form method="POST"
              action="{{ route('settings.farm.destroy') }}"
              class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-[1fr_auto]"
              onsubmit="return confirm('¿Eliminar definitivamente esta finca? Esta acción no se puede deshacer.')">
            @csrf
            @method('DELETE')

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">
                    Escribe el nombre de la finca para confirmar
                </label>
                <input type="text"
                       name="confirmation_name"
                       class="settings-input"
                       placeholder="{{ $farm->name }}"
                       autocomplete="off"
                       required>
                @error('confirmation_name')
                    <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-end">
                <button type="submit" class="settings-btn settings-btn-danger w-full md:w-auto">
                    Eliminar finca
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

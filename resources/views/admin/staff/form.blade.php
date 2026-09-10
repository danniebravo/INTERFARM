<style>
    .staff-form-card { border-radius: 24px; border: 1px solid rgba(0,0,0,.07); background: rgba(255,255,255,.68); box-shadow: 0 12px 30px rgba(15,23,42,.05); }
    .staff-form-input { width: 100%; border-radius: 14px; border: 1px solid rgba(148,163,184,.45); background: rgba(255,255,255,.9); padding: 11px 13px; font-size: 13px; font-weight: 700; color: #111827; outline: none; }
    .staff-permission { display: flex; min-height: 104px; gap: 12px; border-radius: 18px; border: 1px solid rgba(148,163,184,.24); background: rgba(248,250,252,.76); padding: 14px; }
    .dark .staff-form-card { border-color: rgba(148,163,184,.22); background: rgba(15,23,42,.88); }
    .dark .staff-form-input, .dark .staff-permission { border-color: rgba(148,163,184,.22); background: rgba(2,6,23,.58); color: #f8fafc; }
</style>

@php
    $selectedPermissions = old('admin_permissions', $staff?->admin_permissions ?? array_keys($permissions));
    $selectedPermissions = is_array($selectedPermissions) ? $selectedPermissions : [];
    $selectedRole = old('role', $staff?->role === 'superadmin' ? 'super_admin' : ($staff?->role ?? 'admin'));
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">Equipo admin</div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">{{ $description }}</p>
        </div>

        <a href="{{ route('admin.staff.index') }}" class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">Volver a administradores</a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">Revisa los campos marcados antes de guardar.</div>
    @endif

    <section class="staff-form-card p-5">
        <form method="POST" action="{{ $action }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @if($method !== 'POST')
                @method($method)
            @endif

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Nombre</label>
                <input name="first_name" value="{{ old('first_name', $staff?->first_name) }}" class="staff-form-input mt-1" required autofocus>
                @error('first_name') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Apellido</label>
                <input name="last_name" value="{{ old('last_name', $staff?->last_name) }}" class="staff-form-input mt-1" required>
                @error('last_name') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Correo</label>
                <input type="email" name="email" value="{{ old('email', $staff?->email) }}" class="staff-form-input mt-1" required>
                @error('email') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Telefono</label>
                <input name="phone" value="{{ old('phone', $staff?->phone) }}" class="staff-form-input mt-1">
                @error('phone') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Rol administrativo</label>
                <select name="role" class="staff-form-input mt-1">
                    <option value="admin" @selected($selectedRole === 'admin')>Administrador con permisos</option>
                    <option value="super_admin" @selected($selectedRole === 'super_admin')>Super administrador</option>
                </select>
                @error('role') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Estado</label>
                <select name="status" class="staff-form-input mt-1">
                    <option value="active" @selected(old('status', $staff?->status ?? 'active') === 'active')>Activo</option>
                    <option value="inactive" @selected(old('status', $staff?->status) === 'inactive')>Inactivo</option>
                    <option value="suspended" @selected(old('status', $staff?->status) === 'suspended')>Suspendido</option>
                </select>
                @error('status') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Contrasena {{ $staff ? 'nueva' : '' }}</label>
                <input type="password" name="password" class="staff-form-input mt-1" @if(! $staff) required @endif>
                @error('password') <div class="mt-1 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Confirmar contrasena</label>
                <input type="password" name="password_confirmation" class="staff-form-input mt-1" @if(! $staff) required @endif>
            </div>

            <div class="md:col-span-2">
                <div class="mb-3">
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Permisos</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Los super administradores siempre tienen acceso completo. Para administradores normales, marca solo los modulos permitidos.</p>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($permissions as $key => $permission)
                        <label class="staff-permission">
                            <input type="checkbox" name="admin_permissions[]" value="{{ $key }}" class="mt-1 h-5 w-5 rounded border-gray-300 text-green-600 focus:ring-green-500" @checked(in_array($key, $selectedPermissions, true))>
                            <span>
                                <span class="block text-sm font-black text-gray-900 dark:text-white">{{ $permission['label'] }}</span>
                                <span class="mt-1 block text-xs font-bold text-gray-500 dark:text-gray-300">{{ $permission['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('admin_permissions') <div class="mt-2 text-xs font-bold text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="md:col-span-2 flex justify-end">
                <button class="inline-flex min-h-[46px] items-center justify-center rounded-xl bg-green-600 px-5 py-3 text-sm font-black text-white shadow-lg shadow-green-600/20 transition hover:bg-green-700">{{ $buttonText }}</button>
            </div>
        </form>
    </section>
</div>

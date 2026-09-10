@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
@php
    $displayName = $user->full_name ?: $user->name ?: 'Usuario InterFarm';
    $initials = $user->initials ?? 'U';
    $documentTypes = [
        'cc' => 'Cedula de ciudadania (CC)',
        'ce' => 'Cedula de extranjeria (CE)',
        'nit' => 'NIT',
        'passport' => 'Pasaporte',
    ];
    $statusLabels = [
        'active' => 'Activo',
        'inactive' => 'Inactivo',
        'suspended' => 'Suspendido',
    ];
@endphp

<style>
    .profile-page-card {
        border-radius: 24px;
        border: 1px solid rgba(0,0,0,.07);
        background: rgba(255,255,255,.70);
        box-shadow: 0 14px 34px rgba(15,23,42,.06);
    }

    .profile-page-input,
    .profile-page-select {
        width: 100%;
        border-radius: 14px;
        border: 1px solid rgba(148,163,184,.45);
        background: rgba(255,255,255,.92);
        padding: 12px 13px;
        font-size: 13px;
        font-weight: 700;
        color: #111827;
        outline: none;
    }

    .profile-page-input:focus,
    .profile-page-select:focus {
        border-color: #22c55e;
        box-shadow: 0 0 0 4px rgba(34,197,94,.12);
    }

    .profile-page-btn {
        display: inline-flex;
        min-height: 46px;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #16a34a;
        padding: 12px 20px;
        font-size: 14px;
        font-weight: 900;
        color: #fff;
        box-shadow: 0 14px 24px rgba(22,163,74,.20);
        transition: .18s ease;
    }

    .profile-page-btn:hover {
        background: #15803d;
    }

    .profile-page-stat {
        border-radius: 18px;
        border: 1px solid rgba(148,163,184,.22);
        background: rgba(248,250,252,.72);
        padding: 14px;
    }

    .dark .profile-page-card {
        border-color: rgba(148,163,184,.22);
        background: rgba(15,23,42,.88);
        box-shadow: 0 18px 42px rgba(0,0,0,.34), inset 0 1px 0 rgba(255,255,255,.06);
    }

    .dark .profile-page-input,
    .dark .profile-page-select {
        border-color: rgba(148,163,184,.22);
        background: rgba(2,6,23,.78);
        color: #f8fafc;
    }

    .dark .profile-page-stat {
        border-color: rgba(148,163,184,.18);
        background: rgba(2,6,23,.42);
    }

    @media (max-width: 640px) {
        .profile-page-btn {
            width: 100%;
        }
    }
</style>

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <div class="inline-flex rounded-full border border-green-500/20 bg-green-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-green-700 dark:border-green-400/20 dark:bg-green-500/10 dark:text-green-200">
                Cuenta
            </div>
            <h1 class="mt-3 text-3xl font-black text-gray-900 dark:text-white">Mi perfil</h1>
            <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-300">
                Actualiza tus datos personales, correo de acceso y contrasena de la plataforma.
            </p>
        </div>

        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center justify-center rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-black text-gray-700 transition hover:bg-white dark:border-white/10 dark:bg-slate-950/70 dark:text-gray-100">
            Volver al panel
        </a>
    </div>

    @if(session('status') === 'profile-updated')
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            Perfil actualizado correctamente.
        </div>
    @endif

    @if(session('status') === 'password-updated')
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-bold text-green-700 dark:border-green-400/30 dark:bg-green-500/10 dark:text-green-200">
            Contrasena actualizada correctamente.
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
            Revisa los campos marcados antes de guardar.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[.78fr_1.22fr]">
        <aside class="profile-page-card p-5">
            <div class="flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-green-600 text-xl font-black text-white shadow-lg shadow-green-600/20">
                    {{ $initials }}
                </div>
                <div class="min-w-0">
                    <h2 class="truncate text-xl font-black text-gray-900 dark:text-white">{{ $displayName }}</h2>
                    <p class="truncate text-sm font-bold text-gray-500 dark:text-gray-300">{{ $user->email }}</p>
                </div>
            </div>

            <div class="mt-6 grid gap-3">
                <div class="profile-page-stat">
                    <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Estado</div>
                    <div class="mt-1 text-sm font-black text-gray-900 dark:text-white">{{ $statusLabels[$user->status] ?? 'Sin estado' }}</div>
                </div>

                <div class="profile-page-stat">
                    <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Rol</div>
                    <div class="mt-1 text-sm font-black text-gray-900 dark:text-white">
                        {{ $user->canAccessAdminPanel() ? 'Administrador' : 'Cliente' }}
                    </div>
                </div>

                <div class="profile-page-stat">
                    <div class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Ultimo acceso</div>
                    <div class="mt-1 text-sm font-black text-gray-900 dark:text-white">
                        {{ optional($user->last_login_at)->format('d/m/Y H:i') ?: 'Sin registro' }}
                    </div>
                </div>
            </div>
        </aside>

        <div class="space-y-6">
            <section class="profile-page-card p-5">
                <div class="mb-5">
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Datos personales</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Esta informacion se usa para identificar tu cuenta y tus accesos.</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="grid gap-4 md:grid-cols-2">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Nombre</label>
                        <input name="first_name" value="{{ old('first_name', $user->first_name) }}" class="profile-page-input mt-1" autocomplete="given-name">
                        @error('first_name')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Apellidos</label>
                        <input name="last_name" value="{{ old('last_name', $user->last_name) }}" class="profile-page-input mt-1" autocomplete="family-name">
                        @error('last_name')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Tipo de documento</label>
                        <select name="document_type" class="profile-page-select mt-1">
                            <option value="">Sin seleccionar</option>
                            @foreach($documentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type', $user->document_type) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Documento</label>
                        <input name="document" value="{{ old('document', $user->document) }}" class="profile-page-input mt-1">
                        @error('document')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Telefono</label>
                        <input name="phone" value="{{ old('phone', $user->phone) }}" class="profile-page-input mt-1" autocomplete="tel">
                        @error('phone')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Correo electronico</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="profile-page-input mt-1" required autocomplete="username">
                        @error('email')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="profile-page-btn">Guardar perfil</button>
                    </div>
                </form>
            </section>

            <section class="profile-page-card p-5">
                <div class="mb-5">
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">Seguridad</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-300">Actualiza tu contrasena usando tu clave actual.</p>
                </div>

                <form method="POST" action="{{ route('password.update') }}" class="grid gap-4 md:grid-cols-3">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Contrasena actual</label>
                        <input type="password" name="current_password" class="profile-page-input mt-1" autocomplete="current-password">
                        @error('current_password', 'updatePassword')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Nueva contrasena</label>
                        <input type="password" name="password" class="profile-page-input mt-1" autocomplete="new-password">
                        @error('password', 'updatePassword')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-xs font-black uppercase text-gray-500 dark:text-gray-400">Confirmar contrasena</label>
                        <input type="password" name="password_confirmation" class="profile-page-input mt-1" autocomplete="new-password">
                        @error('password_confirmation', 'updatePassword')
                            <p class="mt-2 text-sm font-bold text-red-600 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="profile-page-btn">Actualizar contrasena</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Salud · ' . ($animal->name ?: 'Animal'))

@section('content')

@php
    $animalName = $animal->name ?: 'Animal sin nombre';
    $healthRecords = collect($healthRecords ?? [])->sortByDesc('date')->values();

    $totalRecords = $healthRecords->count();
    $latestRecord = $healthRecords->first();

    $vaccinesCount = $healthRecords->filter(function ($record) {
        return strtolower((string) ($record['treatment_type'] ?? '')) === 'vacuna';
    })->count();

    $treatmentsCount = $healthRecords->filter(function ($record) {
        return !empty($record['treatment_type']);
    })->count();

    $medicationsCount = $healthRecords->filter(function ($record) {
        return !empty($record['medication']);
    })->count();
@endphp

<style>
    .health-stat-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255, 255, 255, .60);
        border-radius: 20px;
        padding: 18px;
    }

    .health-table-wrap {
        overflow: hidden;
        border-radius: 24px;
        border: 1px solid rgba(0, 0, 0, .06);
        background: rgba(255,255,255,.42);
    }

    .health-table-head th {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .02em;
        text-transform: uppercase;
    }

    .health-record-card {
        border: 1px solid rgba(0, 0, 0, .08);
        background: rgba(255,255,255,.60);
        border-radius: 20px;
        padding: 16px;
    }

    .health-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid rgba(239,68,68,.14);
        background: rgba(239,68,68,.08);
        color: #dc2626;
        white-space: nowrap;
    }

    .health-muted-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid rgba(0,0,0,.08);
        background: rgba(255,255,255,.7);
        color: #374151;
        white-space: nowrap;
    }
</style>

<div class="space-y-6">

    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">
                Salud · {{ $animalName }}
            </h1>
            <p class="text-sm text-gray-500 mt-1">
                Historial completo de salud individual del animal.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('animals.show', $animal) }}"
               class="rounded-xl border border-black/10 bg-white/70 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white transition">
                Volver al perfil
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="health-stat-card">
            <div class="text-sm text-gray-500">Registros totales</div>
            <div class="text-2xl font-extrabold text-gray-900 mt-1">{{ $totalRecords }}</div>
            <div class="text-xs text-gray-500 mt-1">Historial acumulado</div>
        </div>

        <div class="health-stat-card">
            <div class="text-sm text-gray-500">Último registro</div>
            <div class="text-2xl font-extrabold text-gray-900 mt-1">
                {{ $latestRecord['date'] ?? '—' }}
            </div>
            <div class="text-xs text-gray-500 mt-1">Fecha más reciente</div>
        </div>

        <div class="health-stat-card">
            <div class="text-sm text-gray-500">Vacunas registradas</div>
            <div class="text-2xl font-extrabold text-gray-900 mt-1">{{ $vaccinesCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Aplicaciones tipo vacuna</div>
        </div>

        <div class="health-stat-card">
            <div class="text-sm text-gray-500">Medicamentos</div>
            <div class="text-2xl font-extrabold text-red-600 mt-1">{{ $medicationsCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Registros con medicación</div>
        </div>
    </div>

    <div class="glass rounded-[28px] p-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-gray-900">Historial de salud</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Vacunas, tratamientos, enfermedades, diagnósticos, medicamentos y observaciones.
                </p>
            </div>

            <div class="health-muted-badge">
                {{ $treatmentsCount }} tratamiento(s) registrado(s)
            </div>
        </div>

        @if($healthRecords->count())
            <div class="hidden lg:block health-table-wrap overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="health-table-head">
                        <tr class="border-b border-black/10 text-left text-gray-500 bg-white/35">
                            <th class="py-4 px-5">Fecha</th>
                            <th class="py-4 pr-5">Tratamiento</th>
                            <th class="py-4 pr-5">Enfermedad</th>
                            <th class="py-4 pr-5">Diagnóstico</th>
                            <th class="py-4 pr-5">Medicamento</th>
                            <th class="py-4 pr-5">Días</th>
                            <th class="py-4 px-5">Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($healthRecords as $record)
                            <tr class="border-b border-black/5 last:border-b-0">
                                <td class="py-4 px-5 font-semibold text-gray-900">
                                    {{ $record['date'] ?? '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $record['treatment_type'] ?? '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $record['disease'] ?? '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $record['diagnosis'] ?? '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ $record['medication'] ?? '—' }}
                                </td>

                                <td class="py-4 pr-5 text-gray-600">
                                    {{ isset($record['days']) && $record['days'] !== null && $record['days'] !== '' ? $record['days'] : '—' }}
                                </td>

                                <td class="py-4 px-5 text-gray-600">
                                    {{ $record['notes'] ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:hidden">
                @foreach($healthRecords as $record)
                    <div class="health-record-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-extrabold text-gray-900">
                                    {{ $record['date'] ?? '—' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $record['treatment_type'] ?? 'Sin tratamiento definido' }}
                                </div>
                            </div>

                            <div class="health-badge">
                                {{ $record['treatment_type'] ?? 'Salud' }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 text-sm">
                            <div>
                                <div class="text-gray-500">Enfermedad</div>
                                <div class="font-semibold text-gray-900 mt-1">
                                    {{ $record['disease'] ?? '—' }}
                                </div>
                            </div>

                            <div>
                                <div class="text-gray-500">Diagnóstico</div>
                                <div class="font-semibold text-gray-900 mt-1">
                                    {{ $record['diagnosis'] ?? '—' }}
                                </div>
                            </div>

                            <div>
                                <div class="text-gray-500">Medicamento</div>
                                <div class="font-semibold text-gray-900 mt-1">
                                    {{ $record['medication'] ?? '—' }}
                                </div>
                            </div>

                            <div>
                                <div class="text-gray-500">Días</div>
                                <div class="font-semibold text-gray-900 mt-1">
                                    {{ isset($record['days']) && $record['days'] !== null && $record['days'] !== '' ? $record['days'] : '—' }}
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <div class="text-gray-500">Notas</div>
                                <div class="font-semibold text-gray-900 mt-1">
                                    {{ $record['notes'] ?? 'Sin observaciones.' }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-black/10 bg-white/40 px-6 py-12 text-center">
                <div class="text-lg font-extrabold text-gray-900">Aún no hay registros de salud</div>
                <p class="text-sm text-gray-500 mt-2">
                    Agrega un registro rápido desde el perfil del animal o desde la lista de animales.
                </p>

                <a href="{{ route('animals.show', $animal) }}"
                   class="mt-5 inline-flex rounded-xl bg-red-600 hover:bg-red-700 transition px-5 py-3 text-sm font-semibold text-white shadow-glow">
                    Volver al animal
                </a>
            </div>
        @endif
    </div>
</div>

@endsection
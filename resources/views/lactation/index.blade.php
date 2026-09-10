@extends('layouts.app')

@section('title', 'Lactancia')

@section('content')

@php
    $fmtDate = fn ($d) => $d ? $d->format('d/m/Y') : '—';
    $toneClass = [
        'red' => 'bg-red-100 text-red-700 border border-red-200',
        'amber' => 'bg-amber-100 text-amber-700 border border-amber-200',
    ];
@endphp

<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Lactancia y reproducción</h1>
        <p class="text-sm text-gray-500 mt-1">
            Fechas de parto, tiempo en leche y próximos eventos (parto y secado) por vaca.
            Gestación {{ $gestationDays }} días · secado {{ $dryOffDays }} días antes del parto.
        </p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl bg-white border border-black/5 p-4 shadow-sm">
            <div class="text-sm font-semibold text-gray-500">En leche</div>
            <div class="mt-1 text-3xl font-extrabold text-gray-900">{{ $summary['lactating'] }}</div>
        </div>
        <div class="rounded-2xl bg-white border border-black/5 p-4 shadow-sm">
            <div class="text-sm font-semibold text-gray-500">Preñadas</div>
            <div class="mt-1 text-3xl font-extrabold text-gray-900">{{ $summary['pregnant'] }}</div>
        </div>
        <div class="rounded-2xl bg-white border border-black/5 p-4 shadow-sm">
            <div class="text-sm font-semibold text-gray-500">Promedio en leche</div>
            <div class="mt-1 text-lg font-extrabold text-gray-900">{{ $summary['avg_label'] }}</div>
        </div>
        <div class="rounded-2xl bg-white border border-black/5 p-4 shadow-sm">
            <div class="text-sm font-semibold text-gray-500">Con alertas</div>
            <div class="mt-1 text-3xl font-extrabold text-gray-900">{{ $summary['alerts'] }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('lactation.index') }}"
          class="rounded-2xl bg-white border border-black/5 p-4 shadow-sm grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar vaca</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Nombre o arete"
                   class="w-full rounded-xl border border-black/10 px-4 py-2.5 outline-none focus:border-green-600/40">
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Mostrar</label>
            <select name="filter" class="w-full rounded-xl border border-black/10 px-4 py-2.5 bg-white">
                <option value="relevant" @selected($filter === 'relevant')>Vacas activas</option>
                <option value="lactating" @selected($filter === 'lactating')>En leche</option>
                <option value="pregnant" @selected($filter === 'pregnant')>Preñadas</option>
                <option value="alerts" @selected($filter === 'alerts')>Con alertas</option>
                <option value="all" @selected($filter === 'all')>Todas las hembras</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Ordenar</label>
            <select name="sort" class="w-full rounded-xl border border-black/10 px-4 py-2.5 bg-white">
                <option value="lactation_desc" @selected($sort === 'lactation_desc')>Más tiempo en leche</option>
                <option value="lactation_asc" @selected($sort === 'lactation_asc')>Menos tiempo en leche</option>
                <option value="calving_soon" @selected($sort === 'calving_soon')>Próximas a parir</option>
                <option value="name_asc" @selected($sort === 'name_asc')>Nombre A-Z</option>
            </select>
        </div>
        <div class="md:col-span-4 flex flex-wrap gap-3">
            <button type="submit" class="rounded-xl bg-green-700 text-white font-semibold px-5 py-2.5 hover:bg-green-800 transition">
                Aplicar filtros
            </button>
            <a href="{{ route('lactation.index') }}"
               class="rounded-xl border border-black/10 px-5 py-2.5 font-semibold text-gray-700 hover:bg-gray-50 transition">
                Limpiar
            </a>
        </div>
    </form>

    @if($rows->isEmpty())
        <div class="rounded-2xl border border-dashed border-black/10 bg-white/60 p-6 text-center text-sm font-semibold text-gray-500">
            No hay vacas que coincidan con el filtro.
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($rows as $r)
                <div class="rounded-2xl bg-white border border-black/5 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <a href="{{ route('animals.show', $r['id']) }}"
                               class="text-lg font-extrabold text-gray-900 hover:text-green-700">{{ $r['name'] }}</a>
                            @if($r['tag'])
                                <div class="text-xs font-semibold text-gray-400">Arete {{ $r['tag'] }}</div>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1 justify-end">
                            @foreach($r['states'] as $st)
                                <span class="text-xs font-bold px-2 py-1 rounded-full
                                    {{ $st === 'Preñada' ? 'bg-purple-100 text-purple-700' : ($st === 'En producción' ? 'bg-green-100 text-green-700' : ($st === 'En secado' ? 'bg-amber-100 text-amber-700' : ($st === 'Seca' ? 'bg-blue-100 text-blue-700' : ($st === 'Vendido' ? 'bg-orange-100 text-orange-700' : ($st === 'Fallecido' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600'))))) }}">
                                    {{ $st }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Último parto</dt><dd class="font-semibold text-gray-900 text-right">{{ $fmtDate($r['last_calving']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Tiempo en leche</dt><dd class="font-semibold text-gray-900 text-right">{{ $r['milk_duration'] ?? '—' }}@if($r['milk_stopped'] ?? false) <span class="text-xs font-normal text-amber-600">(detenido)</span>@endif</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">N.º de partos</dt><dd class="font-semibold text-gray-900 text-right">{{ $r['calving_count'] }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Fecha de preñez</dt><dd class="font-semibold text-gray-900 text-right">{{ $fmtDate($r['service_date']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Tiempo preñada</dt><dd class="font-semibold text-gray-900 text-right">{{ $r['pregnant_duration'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Secado probable</dt><dd class="font-semibold text-gray-900 text-right">{{ $fmtDate($r['expected_dry_off']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Parto probable</dt><dd class="font-semibold text-gray-900 text-right">{{ $fmtDate($r['expected_calving']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Falta para el parto</dt><dd class="font-semibold text-gray-900 text-right">{{ $r['countdown_calving'] ?? '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Destete sugerido</dt><dd class="font-semibold text-gray-900 text-right">{{ $r['weaning_display'] ?? $fmtDate($r['expected_weaning']) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">Servicio</dt><dd class="font-semibold text-gray-900 text-right">{{ $r['service'] ?? '—' }}</dd></div>
                    </dl>

                    @if(count($r['alerts']))
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach($r['alerts'] as $al)
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full {{ $toneClass[$al['tone']] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $al['label'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                    @if((in_array($r['lactation_state'] ?? '', ['En producción', 'En secado'], true) || ($r['is_pregnant'] ?? false)) && ! ($r['is_gone'] ?? false))
                        <div class="mt-4 flex flex-wrap gap-2 border-t border-black/5 pt-4">
                            @if(in_array($r['lactation_state'] ?? '', ['En producción', 'En secado'], true))
                                <form method="POST" action="{{ route('lactation.dry-off', $r['id']) }}" onsubmit="return confirm('¿Confirmar el secado hoy?');">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold rounded-xl border border-amber-300 bg-amber-50 text-amber-700 px-3 py-1.5 hover:bg-amber-100 transition">Confirmar secado</button>
                                </form>
                            @endif
                            @if($r['is_pregnant'] ?? false)
                                <div x-data="{ open:false, withCalf:true }" class="inline-block">
                                    <button type="button" @click="open=true" class="text-xs font-semibold rounded-xl border border-green-300 bg-green-50 text-green-700 px-3 py-1.5 hover:bg-green-100 transition">Registrar parto</button>
                                    <div x-show="open" x-cloak style="display:none;" @click.self="open=false" @keydown.escape.window="open=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                                        <form method="POST" action="{{ route('lactation.calving', $r['id']) }}" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                                            @csrf
                                            <p class="text-base font-extrabold text-gray-900 mb-1">Registrar parto</p>
                                            <p class="text-xs text-gray-500 mb-3">{{ $r['name'] ?? 'Esta vaca' }} — inicia una nueva lactancia.</p>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha del parto</label>
                                            <input type="date" name="calving_date" value="{{ now()->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl border border-black/10 px-3 py-2 text-sm mb-3">
                                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                                                <input type="checkbox" name="register_calf" value="1" x-model="withCalf"> Registrar la cría
                                            </label>
                                            <div x-show="withCalf" class="space-y-2 mb-1">
                                                <input type="text" name="calf_name" placeholder="Nombre de la cría" class="w-full rounded-xl border border-black/10 px-3 py-2 text-sm">
                                                <div class="grid grid-cols-2 gap-2">
                                                    <select name="calf_sex" class="w-full rounded-xl border border-black/10 px-3 py-2 text-sm">
                                                        <option value="hembra">Hembra</option>
                                                        <option value="macho">Macho</option>
                                                    </select>
                                                    <input type="text" name="calf_ear_tag" placeholder="Arete (opcional)" class="w-full rounded-xl border border-black/10 px-3 py-2 text-sm">
                                                </div>
                                                <p class="text-[11px] text-gray-400">La cría se guarda con esta vaca como madre para la genealogía.</p>
                                            </div>
                                            <div class="flex gap-2 mt-4">
                                                <button type="submit" class="flex-1 text-sm font-semibold rounded-xl bg-green-600 text-white px-3 py-2 hover:bg-green-700 transition">Confirmar parto</button>
                                                <button type="button" @click="open=false" class="text-sm font-semibold rounded-xl border border-black/10 px-3 py-2 text-gray-600">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if(isset($youngStock) && $youngStock->flatten(1)->isNotEmpty())
        @php
            $ifStageOrder = ['Ternera','Ternero','Novilla','Novillo','Toro','Vaca','Sin edad registrada'];
            $ifStages = collect($ifStageOrder)->filter(fn($s) => isset($youngStock[$s]))
                ->merge($youngStock->keys()->reject(fn($s) => in_array($s, $ifStageOrder)))->values();
        @endphp
        <section class="mt-10">
            <div class="flex items-center justify-between gap-3 mb-1">
                <h2 class="text-xl font-extrabold text-gray-900">Novillas, terneros y toros (no lactantes)</h2>
                <span class="text-xs font-semibold text-gray-400">{{ $youngStock->flatten(1)->count() }} animales</span>
            </div>
            <p class="text-sm text-gray-500 mb-4">Animales que aún no entran al ciclo de lactancia o reproducción. Información básica para no tener que abrir cada ficha.</p>
            <div class="space-y-6">
                @foreach($ifStages as $ifStage)
                    @php $ifGroup = $youngStock[$ifStage]; @endphp
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-extrabold text-gray-700">{{ $ifStage }}</span>
                            <span class="text-xs font-semibold text-white bg-gray-400 rounded-full px-2 py-0.5">{{ count($ifGroup) }}</span>
                        </div>
                        <div class="overflow-x-auto rounded-2xl border border-black/5 bg-white">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase text-gray-400 border-b border-black/5">
                                        <th class="py-2.5 px-4 font-bold">Animal</th>
                                        <th class="py-2.5 px-3 font-bold">Arete</th>
                                        <th class="py-2.5 px-3 font-bold">Sexo</th>
                                        <th class="py-2.5 px-3 font-bold">Edad</th>
                                        <th class="py-2.5 px-3 font-bold">Lote</th>
                                        <th class="py-2.5 px-3 font-bold text-right">Peso</th>
                                        <th class="py-2.5 px-3 font-bold text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ifGroup as $ifA)
                                        <tr class="border-b border-black/5 last:border-b-0">
                                            <td class="py-2.5 px-4 font-bold text-gray-900">{{ $ifA['name'] }}</td>
                                            <td class="py-2.5 px-3 text-gray-600">{{ $ifA['tag'] ?: '—' }}</td>
                                            <td class="py-2.5 px-3 text-gray-600">{{ $ifA['sex'] }}</td>
                                            <td class="py-2.5 px-3 text-gray-600">{{ $ifA['age'] ?: '—' }}</td>
                                            <td class="py-2.5 px-3 text-gray-600">{{ $ifA['lot'] ?: '—' }}</td>
                                            <td class="py-2.5 px-3 text-gray-600 text-right">{{ $ifA['weight'] ? $ifA['weight'].' kg' : '—' }}</td>
                                            <td class="py-2.5 px-3 text-right"><a href="{{ route('animals.edit', $ifA['id']) }}" class="text-xs font-semibold text-green-700 hover:underline">Editar</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>

@endsection
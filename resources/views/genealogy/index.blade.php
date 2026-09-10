@extends('layouts.app')

@section('title', 'Genealogía')

@section('content')

<div class="space-y-6">

    <div>
        <h1 class="text-2xl font-extrabold text-gray-900">Árbol genealógico</h1>
        <p class="text-sm text-gray-500 mt-1">Elige un animal para ver sus ancestros (padres, abuelos…) y sus descendientes (crías).</p>
    </div>

    <form method="GET" action="{{ route('genealogy.index') }}" class="rounded-2xl bg-white border border-black/5 p-4 shadow-sm flex flex-col md:flex-row gap-4 md:items-end">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-gray-700 mb-1">Animal</label>
            <select name="animal" class="w-full rounded-xl border border-black/10 px-4 py-2.5 bg-white">
                <option value="">Selecciona un animal</option>
                @foreach($animals as $a)
                    <option value="{{ $a->id }}" @selected($selected && $selected->id === $a->id)>{{ $a->name ?: ('Animal '.$a->id) }}{{ ($a->ear_tag ?: $a->internal_code) ? ' ('.($a->ear_tag ?: $a->internal_code).')' : '' }}{{ (! $a->isActive()) ? ' - '.$a->statusLabel() : '' }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl bg-green-700 text-white font-semibold px-5 py-2.5 hover:bg-green-800 transition">Ver árbol</button>
    </form>

    @if(! $selected)
        <div class="rounded-2xl border border-dashed border-black/10 bg-white/60 p-6 text-center text-sm font-semibold text-gray-500">
            Selecciona un animal arriba para ver su árbol genealógico.
        </div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="rounded-2xl bg-white border border-black/5 p-5 shadow-sm">
                <div class="text-lg font-extrabold text-gray-900 mb-4">Ancestros (hacia arriba)</div>
                @if(empty($ancestors['children']))
                    <div class="text-sm text-gray-500">Sin ancestros registrados para {{ $selected->name ?: 'este animal' }}.</div>
                @else
                    <ul class="genealogy-tree">
                        @include('genealogy._node', ['node' => $ancestors])
                    </ul>
                @endif
            </div>

            <div class="rounded-2xl bg-white border border-black/5 p-5 shadow-sm">
                <div class="text-lg font-extrabold text-gray-900 mb-4">Descendientes (crías)</div>
                @if(empty($descendants['children']))
                    <div class="text-sm text-gray-500">Sin descendientes registrados para {{ $selected->name ?: 'este animal' }}.</div>
                @else
                    <ul class="genealogy-tree">
                        @include('genealogy._node', ['node' => $descendants])
                    </ul>
                @endif
            </div>
        </div>
    @endif

</div>

<style>
    .genealogy-tree, .genealogy-tree ul { list-style: none; margin: 0; padding: 0; }
    .genealogy-tree ul { margin-left: 18px; padding-left: 14px; border-left: 2px solid rgba(0,0,0,.08); }
    .genealogy-node { display: flex; align-items: center; gap: 8px; padding: 6px 0; flex-wrap: wrap; }
    .genealogy-role { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 9999px; background: #f3f4f6; color: #6b7280; }
    .genealogy-role.madre { background: #fce7f3; color: #be185d; }
    .genealogy-role.padre { background: #dbeafe; color: #1d4ed8; }
    .genealogy-role.cria { background: #dcfce7; color: #166534; }
    .genealogy-name { font-weight: 700; color: #111827; }
    a.genealogy-name:hover { color: #166534; text-decoration: underline; }
</style>

@endsection
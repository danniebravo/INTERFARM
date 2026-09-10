@extends('layouts.app')

@section('content')
    <h1 class="text-3xl font-bold mb-6">Panel</h1>

    <div class="grid grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded shadow">
            <h3 class="text-gray-500">Total Animales</h3>
            <p class="text-2xl font-bold">0</p>
        </div>

        <div class="bg-white p-6 rounded shadow">
            <h3 class="text-gray-500">Partos</h3>
            <p class="text-2xl font-bold">0</p>
        </div>

        <div class="bg-white p-6 rounded shadow">
            <h3 class="text-gray-500">Eventos</h3>
            <p class="text-2xl font-bold">0</p>
        </div>
    </div>
@endsection

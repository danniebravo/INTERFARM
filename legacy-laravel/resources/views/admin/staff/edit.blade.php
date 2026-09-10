@extends('layouts.app')

@section('title', 'Editar administrador')

@section('content')
@include('admin.staff.form', [
    'staff' => $staff,
    'title' => 'Editar administrador',
    'description' => 'Actualiza datos, rol y permisos de este usuario interno.',
    'action' => route('admin.staff.update', $staff),
    'method' => 'PATCH',
    'buttonText' => 'Guardar cambios',
])
@endsection

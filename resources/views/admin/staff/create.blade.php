@extends('layouts.app')

@section('title', 'Crear administrador')

@section('content')
@include('admin.staff.form', [
    'staff' => null,
    'title' => 'Crear administrador',
    'description' => 'Agrega un usuario interno y define que puede gestionar dentro del panel SaaS.',
    'action' => route('admin.staff.store'),
    'method' => 'POST',
    'buttonText' => 'Crear administrador',
])
@endsection

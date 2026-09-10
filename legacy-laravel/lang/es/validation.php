<?php

return [
    'accepted' => 'Debes aceptar :attribute.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña actual no es correcta.',
    'date' => ':attribute debe ser una fecha válida.',
    'email' => ':attribute debe ser un correo electrónico válido.',
    'in' => ':attribute no tiene una opción válida.',
    'max' => [
        'numeric' => ':attribute no puede ser mayor que :max.',
        'file' => ':attribute no puede pesar más de :max kilobytes.',
        'string' => ':attribute no puede tener más de :max caracteres.',
        'array' => ':attribute no puede tener más de :max elementos.',
    ],
    'min' => [
        'numeric' => ':attribute debe ser al menos :min.',
        'file' => ':attribute debe pesar al menos :min kilobytes.',
        'string' => ':attribute debe tener al menos :min caracteres.',
        'array' => ':attribute debe tener al menos :min elementos.',
    ],
    'numeric' => ':attribute debe ser un número.',
    'required' => 'El campo :attribute es obligatorio.',
    'string' => ':attribute debe ser texto.',
    'unique' => ':attribute ya está en uso.',
    'password' => [
        'letters' => ':attribute debe incluir al menos una letra.',
        'mixed' => ':attribute debe incluir al menos una letra mayúscula y una minúscula.',
        'numbers' => ':attribute debe incluir al menos un número.',
        'symbols' => ':attribute debe incluir al menos un símbolo.',
        'uncompromised' => 'Esta contraseña apareció en una filtración de datos. Usa una diferente.',
    ],

    'attributes' => [
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'current_password' => 'contraseña actual',
        'name' => 'nombre',
        'phone' => 'teléfono',
        'token' => 'enlace',
    ],
];

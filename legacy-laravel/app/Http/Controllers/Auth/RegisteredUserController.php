<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Mostrar vista de registro.
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Procesar registro de usuario.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'document_type'  => ['required', 'in:cc,ce,nit,passport'],
            'document'       => ['required', 'string', 'max:50', 'unique:users,document'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name'                       => trim($request->first_name . ' ' . $request->last_name),
            'first_name'                 => $request->first_name,
            'last_name'                  => $request->last_name,
            'document_type'              => $request->document_type,
            'document'                   => $request->document,
            'phone'                      => $request->phone,
            'email'                      => $request->email,
            'password'                   => Hash::make($request->password),
            'trial_ends_at'              => now()->addDays(15),
            'status'                     => 'active',
            'has_completed_onboarding'   => false,
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('farms.create');
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Farm;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    /**
     * Mostrar formulario para crear finca
     */
    public function create()
    {
        $user = request()->user();

        if ($user?->currentFarm()) {
            return view('farms.create-inside');
        }

        return view('farms.create');
    }

    /**
     * Guardar nueva finca
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'location'        => ['nullable', 'string', 'max:255'],
            'hectares'        => ['nullable', 'numeric', 'min:0'],
            'production_type' => ['required', 'in:leche,carne,doble_proposito'],
            'description'     => ['nullable', 'string', 'max:2000'],
        ]);

        $farm = Farm::create($data);

        $request->user()->farms()->attach($farm, [
            'role' => 'owner',
        ]);

        $request->session()->put('current_farm_id', $farm->id);
        $request->user()->forceFill(['last_farm_id' => $farm->id])->save();

        if (! $request->user()->has_completed_onboarding) {
            return redirect()
                ->route('lots.create')
                ->with('success', 'Finca creada correctamente. Ahora crea tu primer lote.');
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Finca creada correctamente.');
    }

    public function switchFarm(Request $request)
    {
        $data = $request->validate([
            'farm_id' => ['required', 'integer'],
        ]);

        $farmsOwner = $request->user();

        if ($request->user()?->canAccessAdminPanel() && $request->session()->has('admin_view_client_id')) {
            $farmsOwner = \App\Models\User::find($request->session()->get('admin_view_client_id')) ?: $farmsOwner;
        }

        $farm = $farmsOwner
            ->farms()
            ->where('farms.id', $data['farm_id'])
            ->firstOrFail();

        $request->session()->put('current_farm_id', $farm->id);

        if (! $request->user()?->canAccessAdminPanel() || ! $request->session()->has('admin_view_client_id')) {
            $request->user()->forceFill(['last_farm_id' => $farm->id])->save();
        }

        return redirect()
            ->route('dashboard', ['farm' => $farm->id])
            ->with('success', 'Ahora estás trabajando en ' . $farm->name . '.');
    }
}

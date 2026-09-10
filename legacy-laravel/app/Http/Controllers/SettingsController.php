<?php

namespace App\Http\Controllers;

use App\Models\FarmSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    protected array $availablePermissions = [
        'animals.view' => 'Ver animales',
        'animals.manage' => 'Crear y editar animales',
        'production.manage' => 'Registrar producción',
        'lots.manage' => 'Gestionar lotes',
        'events.manage' => 'Gestionar eventos',
        'settings.manage' => 'Administrar configuración',
    ];

    public function index()
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $roles = $this->rolesForFarm($farm->id);
        $members = $farm->users()
            ->orderBy('first_name')
            ->orderBy('email')
            ->get();

        return view('settings.index', [
            'farm' => $farm,
            'roles' => $roles,
            'members' => $members,
            'availablePermissions' => $this->availablePermissions,
            'planName' => $this->settingValue($farm->id, 'plan_name') ?: 'Pendiente de asignar',
        ]);
    }

    public function storeRole(Request $request)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $roles = $this->rolesForFarm($farm->id);
        $key = Str::slug($data['name'], '_');

        if (array_key_exists($key, $roles)) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe un rol con ese nombre.',
            ]);
        }

        $roles[$key] = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => collect($data['permissions'] ?? [])
                ->filter(fn ($permission) => array_key_exists($permission, $this->availablePermissions))
                ->values()
                ->all(),
            'custom' => true,
        ];

        $this->saveRoles($farm->id, $roles);

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function updateFarm(Request $request)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'hectares' => ['nullable', 'numeric', 'min:0'],
            'production_type' => ['required', 'in:leche,carne,doble_proposito'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $farm->update($data);

        return back()->with('success', 'Información de la finca actualizada.');
    }

    public function destroyFarm(Request $request)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $data = $request->validate([
            'confirmation_name' => ['required', 'string'],
        ], [
            'confirmation_name.required' => 'Escribe el nombre de la finca para confirmar.',
        ]);

        if (trim($data['confirmation_name']) !== trim($farm->name)) {
            throw ValidationException::withMessages([
                'confirmation_name' => 'El nombre escrito no coincide con la finca actual.',
            ]);
        }

        $user = $request->user();
        $farmId = $farm->id;

        DB::transaction(function () use ($farm) {
            $farm->load('animals.photos');

            foreach ($farm->animals as $animal) {
                foreach ($animal->photos as $photo) {
                    if ($photo->path && Storage::disk('public')->exists($photo->path)) {
                        Storage::disk('public')->delete($photo->path);
                    }
                }
            }

            $farm->delete();
        });

        if ((int) session('current_farm_id') === (int) $farmId) {
            session()->forget('current_farm_id');
        }

        $nextFarm = $user->farms()
            ->latest('farms.id')
            ->first();

        if ($nextFarm) {
            session(['current_farm_id' => $nextFarm->id]);
            $user->forceFill(['last_farm_id' => $nextFarm->id])->save();

            return redirect()
                ->route('settings.index')
                ->with('success', 'Finca eliminada correctamente. Ahora estás trabajando en ' . $nextFarm->name . '.');
        }

        $user->forceFill(['last_farm_id' => null])->save();

        return redirect()
            ->route('farms.create')
            ->with('success', 'Finca eliminada correctamente. Crea una nueva finca para continuar.');
    }

    public function destroyRole(string $role)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $roles = $this->rolesForFarm($farm->id);
        $roleData = $roles[$role] ?? null;

        if (! $roleData) {
            throw ValidationException::withMessages([
                'role' => 'El rol seleccionado no existe.',
            ]);
        }

        if (! ($roleData['custom'] ?? false)) {
            return back()->with('warning', 'Los roles base no se pueden eliminar.');
        }

        $roleIsAssigned = $farm->users()
            ->wherePivot('role', $role)
            ->exists();

        if ($roleIsAssigned) {
            return back()->with('warning', 'No puedes eliminar un rol que está asignado a usuarios.');
        }

        unset($roles[$role]);
        $this->saveRoles($farm->id, $roles);

        return back()->with('success', 'Rol eliminado correctamente.');
    }

    public function attachMember(Request $request)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'El usuario debe estar registrado para poder agregarlo a la finca.',
            ]);
        }

        $this->validateRole($farm->id, $data['role']);

        $farm->users()->syncWithoutDetaching([
            $user->id => ['role' => $data['role']],
        ]);

        return back()->with('success', 'Empleado agregado correctamente.');
    }

    public function updateMemberRole(Request $request, User $user)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        $data = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $this->validateRole($farm->id, $data['role']);

        $farm->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
        ]);

        return back()->with('success', 'Rol del empleado actualizado.');
    }

    public function detachMember(User $user)
    {
        $farm = $this->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $this->authorizeFarmOwner($farm);

        if ((int) $user->id === (int) auth()->id()) {
            return back()->with('warning', 'No puedes quitarte el acceso a tu propia finca.');
        }

        $farm->users()->detach($user->id);

        return back()->with('success', 'Empleado removido de la finca.');
    }

    protected function currentFarm()
    {
        return auth()->user()?->currentFarm();
    }

    protected function authorizeFarmOwner($farm): void
    {
        $pivot = $farm->users()
            ->where('users.id', auth()->id())
            ->first()?->pivot;

        if (! $pivot || $pivot->role !== 'owner') {
            abort(403);
        }
    }

    protected function validateRole(int $farmId, string $role): void
    {
        if (! array_key_exists($role, $this->rolesForFarm($farmId))) {
            throw ValidationException::withMessages([
                'role' => 'El rol seleccionado no existe.',
            ]);
        }
    }

    protected function rolesForFarm(int $farmId): array
    {
        $defaults = [
            'owner' => [
                'name' => 'Propietario',
                'description' => 'Acceso completo a la finca.',
                'permissions' => array_keys($this->availablePermissions),
                'custom' => false,
            ],
            'manager' => [
                'name' => 'Administrador de finca',
                'description' => 'Puede gestionar la operación diaria.',
                'permissions' => [
                    'animals.view',
                    'animals.manage',
                    'production.manage',
                    'lots.manage',
                    'events.manage',
                ],
                'custom' => false,
            ],
            'employee' => [
                'name' => 'Empleado',
                'description' => 'Puede consultar y registrar operación básica.',
                'permissions' => [
                    'animals.view',
                    'production.manage',
                    'events.manage',
                ],
                'custom' => false,
            ],
        ];

        $stored = optional(
            FarmSetting::where('farm_id', $farmId)
                ->where('key', 'roles')
                ->first()
        )->value;

        $decoded = $stored ? json_decode($stored, true) : [];

        return array_merge($defaults, is_array($decoded) ? $decoded : []);
    }

    protected function saveRoles(int $farmId, array $roles): void
    {
        $customRoles = collect($roles)
            ->filter(fn ($role) => $role['custom'] ?? false)
            ->all();

        FarmSetting::updateOrCreate(
            [
                'farm_id' => $farmId,
                'key' => 'roles',
            ],
            [
                'value' => json_encode($customRoles),
            ]
        );
    }

    protected function settingValue(int $farmId, string $key): ?string
    {
        return FarmSetting::where('farm_id', $farmId)
            ->where('key', $key)
            ->value('value');
    }
}

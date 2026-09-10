<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EscapesLikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class AdminStaffController extends Controller
{
    use EscapesLikeSearch;

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));

        $admins = User::query()
            ->whereIn('role', ['admin', 'super_admin', 'superadmin'])
            ->when(User::hiddenAdminUserIds() !== [], function ($query) {
                $query->whereNotIn('id', User::hiddenAdminUserIds());
            })
            ->when($search !== '', function ($query) use ($search) {
                $this->whereLikeAny($query, ['first_name', 'last_name', 'name', 'email'], $search);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $permissions = User::adminPermissionOptions();

        return view('admin.staff.index', compact('admins', 'permissions', 'search'));
    }

    public function create()
    {
        return view('admin.staff.create', [
            'permissions' => User::adminPermissionOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        $permissions = $this->permissionsForRole($data['role'], $data['admin_permissions'] ?? []);

        $payload = [
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'document_type' => 'cc',
            'document' => $this->adminDocumentValue($data['email']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
            'role' => $data['role'],
            'billing_status' => 'manual',
        ];

        if (Schema::hasColumn('users', 'admin_permissions')) {
            $payload['admin_permissions'] = $permissions;
        }

        User::create($payload);

        return redirect()
            ->route('admin.staff.index')
            ->with('success', 'Administrador creado correctamente.');
    }

    public function edit(User $staff)
    {
        abort_unless($staff->canAccessAdminPanel(), 404);
        abort_unless($staff->isVisibleToAdmin(auth()->user()), 404);

        return view('admin.staff.edit', [
            'staff' => $staff,
            'permissions' => User::adminPermissionOptions(),
        ]);
    }

    public function update(Request $request, User $staff)
    {
        abort_unless($staff->canAccessAdminPanel(), 404);
        abort_unless($staff->isVisibleToAdmin(auth()->user()), 404);

        $data = $this->validatePayload($request, $staff);
        $permissions = $this->permissionsForRole($data['role'], $data['admin_permissions'] ?? []);

        $payload = [
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'role' => $data['role'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        if (Schema::hasColumn('users', 'admin_permissions')) {
            $payload['admin_permissions'] = $permissions;
        }

        $staff->update($payload);

        return redirect()
            ->route('admin.staff.edit', $staff)
            ->with('success', 'Administrador actualizado correctamente.');
    }

    public function destroy(User $staff)
    {
        abort_unless($staff->canAccessAdminPanel(), 404);
        abort_unless($staff->isVisibleToAdmin(auth()->user()), 404);
        abort_if((int) $staff->id === (int) auth()->id(), 422, 'No puedes eliminar tu propio usuario administrador.');

        $staff->delete();

        return redirect()
            ->route('admin.staff.index')
            ->with('success', 'Administrador eliminado correctamente.');
    }

    protected function validatePayload(Request $request, ?User $staff = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'role' => ['required', 'in:admin,super_admin'],
            'admin_permissions' => ['array'],
            'admin_permissions.*' => ['string', Rule::in(array_keys(User::adminPermissionOptions()))],
            'password' => [$staff ? 'nullable' : 'required', 'confirmed', Rules\Password::defaults()],
        ]);
    }

    protected function permissionsForRole(string $role, array $permissions): ?array
    {
        if ($role === 'super_admin') {
            return null;
        }

        return array_values(array_intersect(
            array_keys(User::adminPermissionOptions()),
            $permissions
        ));
    }

    protected function adminDocumentValue(string $email): string
    {
        $base = 'admin-' . substr(sha1(mb_strtolower($email)), 0, 20);
        $document = $base;
        $suffix = 1;

        while (User::where('document', $document)->exists()) {
            $document = $base . '-' . $suffix;
            $suffix++;
        }

        return $document;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\SubscriptionInvoiceService;
use App\Support\EscapesLikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserManagementController extends Controller
{
    use EscapesLikeSearch;

    public function index(Request $request)
    {
        $search = trim((string) $request->get('search'));
        $status = trim((string) $request->get('status'));
        $sort = $request->get('sort', 'latest');

        if (! in_array($sort, ['latest', 'name_asc', 'email_asc', 'trial_ends_asc'], true)) {
            $sort = 'latest';
        }

        $usersQuery = User::with(['farms.animals', 'farms.lots', 'subscriptionPlan'])
            ->when(User::hiddenAdminUserIds() !== [], function ($query) {
                $query->whereNotIn('id', User::hiddenAdminUserIds());
            })
            ->when(Schema::hasTable('user_activity_logs'), function ($query) {
                $query->withCount([
                    'activityLogs as recent_activity_count' => fn ($activityQuery) => $activityQuery
                        ->where('created_at', '>=', now()->subMonth()),
                ]);
            })
            ->when(Schema::hasColumn('users', 'role'), function ($query) {
                $query->whereNotIn('role', ['admin', 'super_admin', 'superadmin']);
            })
            ->when($search !== '', function ($query) use ($search) {
                $this->whereLikeAny($query, ['first_name', 'last_name', 'email', 'document', 'phone'], $search);
            })
            ->when(in_array($status, ['active', 'inactive', 'suspended']), function ($query) use ($status) {
                $query->where('status', $status);
            });

        match ($sort) {
            'name_asc' => $usersQuery->orderBy('first_name')->orderBy('last_name'),
            'email_asc' => $usersQuery->orderBy('email'),
            'trial_ends_asc' => $usersQuery->orderBy('trial_ends_at'),
            default => $usersQuery->latest(),
        };

        $users = $usersQuery
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search', 'status', 'sort'));
    }

    public function create()
    {
        $plans = Schema::hasTable('subscription_plans')
            ? SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get()
            : collect();

        return view('admin.users.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'document_type' => ['required', 'in:cc,ce,nit,passport'],
            'document' => ['required', 'string', 'max:80', 'unique:users,document'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'status' => ['required', 'in:active,inactive,suspended'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'billing_status' => ['required', 'in:trial,active,past_due,cancelled,manual'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'next_billing_date' => ['nullable', 'date'],
        ]);

        $trialDays = array_key_exists('trial_days', $data) && $data['trial_days'] !== null
            ? (int) $data['trial_days']
            : 15;

        $user = User::create([
            'name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'document_type' => $data['document_type'],
            'document' => $data['document'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
            'role' => 'client',
            'subscription_plan_id' => $data['subscription_plan_id'] ?: null,
            'billing_status' => $data['billing_status'],
            'next_billing_date' => $data['next_billing_date'] ?? null,
            'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
        ]);

        app(SubscriptionInvoiceService::class)->generateForUserIfDue($user->fresh('subscriptionPlan'), 10);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Cliente creado correctamente.');
    }

    public function show(User $user)
    {
        abort_unless($user->isVisibleToAdmin(auth()->user()), 404);

        $user->load(['farms.animals.lot', 'farms.lots', 'subscriptionPlan']);

        if (Schema::hasTable('subscription_payments')) {
            $user->load('subscriptionPayments');
        }

        $activityLogs = Schema::hasTable('user_activity_logs')
            ? UserActivityLog::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subMonth())
                ->latest('created_at')
                ->take(80)
                ->get()
            : collect();

        $plans = Schema::hasTable('subscription_plans')
            ? SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get()
            : collect();

        $clientAnimals = $user->farms
            ->flatMap(function ($farm) {
                return $farm->animals->map(function ($animal) use ($farm) {
                    $animal->client_farm_name = $farm->name;

                    return $animal;
                });
            })
            ->sortBy([
                ['client_farm_name', 'asc'],
                ['name', 'asc'],
                ['internal_code', 'asc'],
            ])
            ->values();

        $metrics = [
            'farms' => $user->farms->count(),
            'animals' => $clientAnimals->count(),
            'active_animals' => $clientAnimals->filter(fn ($animal) => $animal->isActive())->count(),
            'lots' => $user->farms->sum(fn ($farm) => $farm->lots->count()),
            'payments' => Schema::hasTable('subscription_payments') ? $user->subscriptionPayments->count() : 0,
            'paid_total' => Schema::hasTable('subscription_payments')
                ? $user->subscriptionPayments->where('status', 'paid')->sum(fn ($payment) => (float) $payment->amount)
                : 0,
            'activity_last_month' => $activityLogs->count(),
            'female_animals' => $clientAnimals->filter(fn ($animal) => $animal->isFemale())->count(),
            'male_animals' => $clientAnimals->filter(fn ($animal) => $animal->isMale())->count(),
        ];

        return view('admin.users.show', compact('user', 'plans', 'metrics', 'activityLogs', 'clientAnimals'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isVisibleToAdmin(auth()->user()), 404);

        $data = $request->validate([
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'document_type' => ['nullable', 'string', 'max:40'],
            'document' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Información del usuario actualizada correctamente.');
    }

    public function updatePassword(Request $request, User $user)
    {
        abort_unless($user->isVisibleToAdmin(auth()->user()), 404);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Contraseña del cliente actualizada correctamente.');
    }

    public function updateStatus(Request $request, User $user)
    {
        abort_unless($user->isVisibleToAdmin(auth()->user()), 404);

        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended'],
        ], [
            'status.required' => 'Debes seleccionar un estado.',
            'status.in' => 'El estado seleccionado no es válido.',
        ]);

        $user->update([
            'status' => $data['status'],
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Estado del usuario actualizado correctamente.');
    }

    public function updatePlan(Request $request, User $user)
    {
        abort_unless($user->isVisibleToAdmin(auth()->user()), 404);

        $data = $request->validate([
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'billing_status' => ['required', 'in:trial,active,past_due,cancelled,manual'],
            'next_billing_date' => ['nullable', 'date'],
        ]);

        $user->update([
            'subscription_plan_id' => $data['subscription_plan_id'] ?: null,
            'billing_status' => $data['billing_status'],
            'next_billing_date' => $data['next_billing_date'] ?? null,
        ]);

        app(SubscriptionInvoiceService::class)->generateForUserIfDue($user->fresh('subscriptionPlan'), 10);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Plan y fecha de facturación actualizados correctamente.');
    }
}

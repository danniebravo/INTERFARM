<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'document_type',
        'document',
        'phone',
        'email',
        'password',
        'last_login_at',
        'last_farm_id',
        'trial_ends_at',
        'status',
        'role',
        'admin_permissions',
        'subscription_plan_id',
        'billing_status',
        'next_billing_date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden
    |--------------------------------------------------------------------------
    */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Appends
    |--------------------------------------------------------------------------
    */

    protected $appends = [
        'full_name',
        'short_name',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'last_farm_id'      => 'integer',
            'trial_ends_at'     => 'datetime',
            'next_billing_date' => 'date',
            'admin_permissions' => 'array',
            'password'          => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function subscriptionPaymentMethods(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentMethod::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers SaaS
    |--------------------------------------------------------------------------
    */

    public function hasFarms(): bool
    {
        return $this->farms()->exists();
    }

    public function currentFarm(): ?Farm
    {
        if ($this->canAccessAdminPanel() && session('admin_view_client_id')) {
            $client = self::find(session('admin_view_client_id'));

            if ($client) {
                $selectedFarmId = session('current_farm_id');

                if ($selectedFarmId) {
                    $selectedFarm = $client->farms()
                        ->where('farms.id', $selectedFarmId)
                        ->first();

                    if ($selectedFarm) {
                        return $selectedFarm;
                    }
                }

                $farm = $client->farms()
                    ->latest('farms.id')
                    ->first();

                if ($farm) {
                    session(['current_farm_id' => $farm->id]);
                }

                return $farm;
            }

            session()->forget(['admin_view_client_id', 'current_farm_id']);
        }

        $selectedFarmId = session('current_farm_id');

        if ($selectedFarmId) {
            $selectedFarm = $this->farms()
                ->where('farms.id', $selectedFarmId)
                ->first();

            if ($selectedFarm) {
                return $selectedFarm;
            }

            session()->forget('current_farm_id');
        }

        if ($this->last_farm_id) {
            $lastFarm = $this->farms()
                ->where('farms.id', $this->last_farm_id)
                ->first();

            if ($lastFarm) {
                session(['current_farm_id' => $lastFarm->id]);

                return $lastFarm;
            }

            $this->forceFill(['last_farm_id' => null])->save();
        }

        $farm = $this->farms()
            ->latest('farms.id')
            ->first();

        if ($farm) {
            session(['current_farm_id' => $farm->id]);
            $this->forceFill(['last_farm_id' => $farm->id])->save();
        }

        return $farm;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasActiveTrial(): bool
    {
        return !is_null($this->trial_ends_at) && now()->lessThan($this->trial_ends_at);
    }

    public function trialExpired(): bool
    {
        return !is_null($this->trial_ends_at) && now()->greaterThanOrEqualTo($this->trial_ends_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de administración
    |--------------------------------------------------------------------------
    |
    | Esto queda listo para cuando agreguemos un campo "role" en users.
    | Por ahora no rompe nada si la columna todavía no existe.
    |
    */

    public function hasSystemRole(): bool
    {
        return array_key_exists('role', $this->getAttributes())
            || Schema::hasColumn($this->getTable(), 'role');
    }

    public function isAdmin(): bool
    {
        return mb_strtolower(trim((string) $this->getAttribute('role'))) === 'admin';
    }

    public function isSuperAdmin(): bool
    {
        return in_array(
            mb_strtolower(trim((string) $this->getAttribute('role'))),
            ['super_admin', 'superadmin'],
            true
        );
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    public static function adminPermissionOptions(): array
    {
        return [
            'overview' => [
                'label' => 'Resumen SaaS',
                'description' => 'Puede ver indicadores generales y entrar al centro SaaS.',
            ],
            'users' => [
                'label' => 'Clientes',
                'description' => 'Puede gestionar clientes, estados, planes y ver como cliente.',
            ],
            'admins' => [
                'label' => 'Administradores',
                'description' => 'Puede crear, editar y eliminar administradores y permisos.',
            ],
            'billing' => [
                'label' => 'Facturacion',
                'description' => 'Puede consultar facturas, pagos y formas de pago.',
            ],
            'plans' => [
                'label' => 'Planes',
                'description' => 'Puede crear y editar planes comerciales.',
            ],
            'notifications' => [
                'label' => 'Notificaciones',
                'description' => 'Puede enviar comunicados globales a usuarios.',
            ],
            'settings' => [
                'label' => 'Configuracion',
                'description' => 'Puede editar ajustes globales de soporte y pagos.',
            ],
            'audit' => [
                'label' => 'Auditoria',
                'description' => 'Puede consultar el historial de acciones administrativas.',
            ],
        ];
    }

    public static function adminPermissionForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        if (str_starts_with($routeName, 'admin.users.') || str_starts_with($routeName, 'admin.saas.clients.')) {
            return 'users';
        }

        if (str_starts_with($routeName, 'admin.staff.')) {
            return 'admins';
        }

        if (str_starts_with($routeName, 'admin.saas.billing.')
            || str_starts_with($routeName, 'admin.saas.payments.')) {
            return 'billing';
        }

        if (str_starts_with($routeName, 'admin.saas.plans.')) {
            return 'plans';
        }

        if (str_starts_with($routeName, 'admin.saas.notifications.')) {
            return 'notifications';
        }

        if (str_starts_with($routeName, 'admin.saas.settings.')) {
            return 'settings';
        }

        if (str_starts_with($routeName, 'admin.saas.audit.')) {
            return 'audit';
        }

        if ($routeName === 'admin.dashboard' || $routeName === 'admin.saas.index') {
            return 'overview';
        }

        return null;
    }

    public function hasAdminPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isAdmin()) {
            return false;
        }

        if (! array_key_exists('admin_permissions', $this->getAttributes())
            || $this->admin_permissions === null) {
            return true;
        }

        return in_array($permission, (array) $this->admin_permissions, true);
    }

    public static function hiddenAdminUserIds(): array
    {
        return array_map('intval', config('interfarm.hidden_admin_user_ids', []));
    }

    public function isHiddenAdminUser(): bool
    {
        return in_array((int) $this->id, self::hiddenAdminUserIds(), true);
    }

    public function isVisibleToAdmin(?User $viewer): bool
    {
        return ! $this->isHiddenAdminUser()
            || ((int) $viewer?->id === (int) $this->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de nombre
    |--------------------------------------------------------------------------
    */

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getShortNameAttribute(): string
    {
        return $this->first_name ?: 'Usuario';
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de visualización
    |--------------------------------------------------------------------------
    */

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'suspended' => 'Suspendido',
            default => 'Sin estado',
        };
    }

    public function getInitialsAttribute(): string
    {
        $first = $this->first_name ? mb_substr($this->first_name, 0, 1) : '';
        $last = $this->last_name ? mb_substr($this->last_name, 0, 1) : '';

        $initials = mb_strtoupper($first . $last);

        return $initials !== '' ? $initials : 'U';
    }
}

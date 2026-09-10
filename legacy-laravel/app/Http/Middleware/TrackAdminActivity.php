<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackAdminActivity
{
    protected const SENSITIVE_FIELDS = [
        '_token',
        '_method',
        'password',
        'password_confirmation',
        'current_password',
        'payment_secret_key',
        'payment_webhook_secret',
        'wompi_private_key',
        'wompi_events_key',
        'wompi_integrity_secret',
        'mail_password',
        'app_logo',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $actor = $request->user();

        if (! $this->shouldTrack($request, $actor)) {
            return $response;
        }

        $activity = $this->activityFor($request->route()?->getName(), $request->method());

        if (! $activity) {
            return $response;
        }

        UserActivityLog::create([
            'user_id' => $actor->id,
            'action' => $activity['action'],
            'description' => $activity['description'],
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => [
                'area' => 'admin',
                'path' => $request->path(),
                'route_parameters' => $this->routeParameters($request),
                'input' => $this->safeInput($request),
                'status_code' => $response->getStatusCode(),
            ],
            'created_at' => now(),
        ]);

        return $response;
    }

    protected function shouldTrack(Request $request, ?User $actor): bool
    {
        if (! $actor
            || ! $actor->canAccessAdminPanel()
            || $actor->isHiddenAdminUser()
            || ! Schema::hasTable('user_activity_logs')
            || ! in_array($request->method(), ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return false;
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof User && $parameter->isHiddenAdminUser()) {
                return false;
            }
        }

        return true;
    }

    protected function activityFor(?string $routeName, string $method): ?array
    {
        $exact = [
            'admin.dashboard' => ['admin_view_dashboard', 'Consultó el panel administrativo'],
            'admin.saas.index' => ['admin_view_saas', 'Consultó el Centro SaaS'],
            'admin.users.index' => ['admin_view_clients', 'Consultó clientes'],
            'admin.users.show' => ['admin_view_client', 'Consultó el detalle de un cliente'],
            'admin.users.create' => ['admin_prepare_client', 'Abrió el formulario de cliente'],
            'admin.staff.index' => ['admin_view_staff', 'Consultó administradores'],
            'admin.staff.create' => ['admin_prepare_staff', 'Abrió el formulario de administrador'],
            'admin.staff.edit' => ['admin_edit_staff_form', 'Abrió la edición de un administrador'],
            'admin.saas.plans.index' => ['admin_view_plans', 'Consultó planes'],
            'admin.saas.billing.index' => ['admin_view_billing', 'Consultó facturación'],
            'admin.saas.billing.invoices' => ['admin_view_invoices', 'Consultó facturas'],
            'admin.saas.billing.payments' => ['admin_view_payments', 'Consultó pagos'],
            'admin.saas.billing.payment-methods' => ['admin_view_payment_methods', 'Consultó formas de pago'],
            'admin.saas.notifications.index' => ['admin_view_notifications', 'Consultó notificaciones'],
            'admin.saas.settings.index' => ['admin_view_settings', 'Consultó configuración SaaS'],
            'admin.saas.audit.index' => ['admin_view_audit', 'Consultó auditoría administrativa'],
            'admin.users.store' => ['admin_create_client', 'Creó un cliente'],
            'admin.users.update' => ['admin_update_client', 'Actualizó datos de un cliente'],
            'admin.users.update-password' => ['admin_update_client_password', 'Actualizó la contraseña de un cliente'],
            'admin.users.update-status' => ['admin_update_client_status', 'Cambió el estado de un cliente'],
            'admin.users.update-plan' => ['admin_update_client_plan', 'Actualizó plan o facturación de un cliente'],
            'admin.staff.store' => ['admin_create_staff', 'Creó un administrador'],
            'admin.staff.update' => ['admin_update_staff', 'Actualizó un administrador'],
            'admin.staff.destroy' => ['admin_delete_staff', 'Eliminó un administrador'],
            'admin.saas.plans.store' => ['admin_create_plan', 'Creó un plan comercial'],
            'admin.saas.plans.update' => ['admin_update_plan', 'Actualizó un plan comercial'],
            'admin.saas.plans.destroy' => ['admin_delete_plan', 'Eliminó un plan comercial'],
            'admin.saas.settings.update' => ['admin_update_settings', 'Actualizó configuración SaaS'],
            'admin.saas.payments.store' => ['admin_create_payment', 'Registró un pago SaaS'],
            'admin.saas.payments.destroy' => ['admin_delete_payment', 'Eliminó un pago SaaS'],
            'admin.saas.notifications.send' => ['admin_send_notification', 'Envió una notificación masiva'],
            'admin.saas.clients.view-as' => ['admin_view_as_client', 'Entró a ver como cliente'],
            'admin.saas.clients.stop-viewing' => ['admin_stop_view_as_client', 'Salió de la vista como cliente'],
        ];

        if ($routeName && isset($exact[$routeName])) {
            return [
                'action' => $exact[$routeName][0],
                'description' => $exact[$routeName][1],
            ];
        }

        if ($method === 'GET') {
            return null;
        }

        return [
            'action' => 'admin_' . strtolower($method) . '_action',
            'description' => 'Realizó una acción administrativa',
        ];
    }

    protected function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(function ($value) {
                if ($value instanceof User) {
                    return [
                        'type' => User::class,
                        'id' => $value->id,
                        'label' => $value->full_name ?: $value->email,
                    ];
                }

                if (is_object($value) && method_exists($value, 'getKey')) {
                    return [
                        'type' => get_class($value),
                        'id' => $value->getKey(),
                        'label' => $value->name ?? $value->title ?? $value->email ?? null,
                    ];
                }

                return $value;
            })
            ->all();
    }

    protected function safeInput(Request $request): array
    {
        return collect($request->except(self::SENSITIVE_FIELDS))
            ->map(function ($value) {
                if (is_array($value)) {
                    return collect($value)->take(30)->all();
                }

                if (is_string($value)) {
                    return mb_substr($value, 0, 500);
                }

                return $value;
            })
            ->all();
    }
}

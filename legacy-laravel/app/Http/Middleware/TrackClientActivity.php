<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackClientActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user
            || $user->canAccessAdminPanel()
            || ! Schema::hasTable('user_activity_logs')) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if ($routeName && str_starts_with($routeName, 'notifications.')) {
            return $response;
        }

        $activity = $this->activityFor($routeName, $request->method());

        if (! $activity) {
            return $response;
        }

        UserActivityLog::create([
            'user_id' => $user->id,
            'action' => $activity['action'],
            'description' => $activity['description'],
            'route_name' => $routeName,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => [
                'path' => $request->path(),
                'farm_id' => session('current_farm_id'),
            ],
            'created_at' => now(),
        ]);

        return $response;
    }

    protected function activityFor(?string $routeName, string $method): ?array
    {
        if (! $routeName) {
            return null;
        }

        $exact = [
            'dashboard' => ['view_dashboard', 'Entró al panel principal'],
            'animals.index' => ['view_animals', 'Consultó la lista de animales'],
            'animals.create' => ['prepare_animal', 'Abrió el formulario para crear un animal'],
            'animals.store' => ['create_animal', 'Registró un animal'],
            'animals.update' => ['update_animal', 'Actualizó un animal'],
            'animals.destroy' => ['delete_animal', 'Eliminó un animal'],
            'animals.production.store' => ['create_animal_production', 'Registró producción desde un animal'],
            'animals.health.store' => ['create_animal_health', 'Registró información de salud de un animal'],
            'lots.index' => ['view_lots', 'Consultó lotes y praderas'],
            'lots.store' => ['create_lot', 'Creó un lote'],
            'lots.update' => ['update_lot', 'Actualizó un lote'],
            'lots.destroy' => ['delete_lot', 'Eliminó un lote'],
            'events.index' => ['view_events', 'Consultó eventos'],
            'events.store' => ['create_event', 'Creó un evento'],
            'events.update' => ['update_event', 'Actualizó un evento'],
            'events.destroy' => ['delete_event', 'Eliminó un evento'],
            'events.update-status' => ['update_event_status', 'Cambió el estado de un evento'],
            'production.index' => ['view_production', 'Consultó producción'],
            'production.milk.store' => ['create_milk_production', 'Registró producción de leche'],
            'production.milk.destroy' => ['delete_milk_production', 'Eliminó producción de leche'],
            'production.meat.store' => ['create_meat_production', 'Registró producción de carne'],
            'production.meat.destroy' => ['delete_meat_production', 'Eliminó producción de carne'],
            'finances.index' => ['view_finances', 'Consultó gastos e ingresos'],
            'finances.store' => ['create_finance', 'Registró un gasto o ingreso'],
            'finances.update' => ['update_finance', 'Actualizó un gasto o ingreso'],
            'finances.destroy' => ['delete_finance', 'Eliminó un gasto o ingreso'],
            'reports.index' => ['view_reports', 'Consultó reportes'],
            'client.billing.invoices' => ['view_invoices', 'Consultó sus facturas'],
            'client.billing.invoices.show' => ['view_invoice', 'Abrió el detalle de una factura'],
            'client.billing.invoices.download' => ['download_invoice', 'Descargó una factura en PDF'],
            'client.billing.payments' => ['view_payments', 'Consultó sus pagos'],
            'client.billing.payment-methods' => ['view_payment_methods', 'Consultó sus formas de pago'],
            'client.billing.payment-methods.store' => ['create_payment_method', 'Agregó una forma de pago'],
            'client.billing.payment-methods.default' => ['update_payment_method', 'Cambió la forma de pago principal'],
            'client.billing.payment-methods.destroy' => ['delete_payment_method', 'Desactivó una forma de pago'],
            'settings.index' => ['view_settings', 'Consultó configuración'],
            'settings.farm.update' => ['update_farm_settings', 'Actualizó información de la finca'],
            'settings.farm.destroy' => ['delete_farm', 'Eliminó una finca'],
            'settings.roles.store' => ['create_role', 'Creó un rol'],
            'settings.roles.destroy' => ['delete_role', 'Eliminó un rol'],
            'settings.members.attach' => ['attach_member', 'Agregó un empleado a la finca'],
            'settings.members.role' => ['update_member_role', 'Actualizó el rol de un empleado'],
            'settings.members.detach' => ['detach_member', 'Quitó un empleado de la finca'],
            'farms.store' => ['create_farm', 'Creó una finca'],
            'farms.switch' => ['switch_farm', 'Cambió de finca activa'],
            'profile.update' => ['update_profile', 'Actualizó su perfil'],
        ];

        if (isset($exact[$routeName])) {
            return [
                'action' => $exact[$routeName][0],
                'description' => $exact[$routeName][1],
            ];
        }

        if ($method !== 'GET') {
            return [
                'action' => 'client_action',
                'description' => 'Realizó una acción en la plataforma',
            ];
        }

        return null;
    }
}

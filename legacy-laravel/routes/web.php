<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\AnimalController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\LotController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\LactationController;
use App\Http\Controllers\GenealogyController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ClientBillingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminStaffController;
use App\Http\Controllers\Admin\SaasController;
use App\Http\Controllers\Admin\UserManagementController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/storage/{path}', function (string $path) {
    if (str_contains($path, '..')) {
        abort(404);
    }

    $root = realpath(storage_path('app/public'));
    $file = realpath(storage_path('app/public/' . $path));

    if (! $root || ! $file || ! str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    return response()->file($file);
})->where('path', '.*')->name('storage.public');

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()?->canAccessAdminPanel()
        ? redirect()->route('admin.saas.index')
        : redirect()->route('dashboard');
})->name('home');

Route::post('/webhooks/wompi', [ClientBillingController::class, 'wompiWebhook'])->name('wompi.webhook');

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = auth()->user();

    if ($user?->canAccessAdminPanel() && ! session('admin_view_client_id')) {
        return redirect()->route('admin.saas.index');
    }

    if ($request->filled('farm')) {
        $requestedFarm = $user->farms()
            ->where('farms.id', $request->integer('farm'))
            ->first();

        if ($requestedFarm) {
            $request->session()->put('current_farm_id', $requestedFarm->id);

            if (! $user?->canAccessAdminPanel() || ! session('admin_view_client_id')) {
                $user->forceFill(['last_farm_id' => $requestedFarm->id])->save();
            }
        }
    }

    $farm = $user->currentFarm();

    if (! $farm) {
        return redirect()->route('farms.create');
    }

    return response(view('dashboard', compact('farm')))
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->middleware(['auth', 'client.not_suspended'])->name('dashboard');

Route::middleware(['auth', 'client.not_suspended', 'track.client.activity'])->group(function () {

    Route::get('/cuenta-suspendida', function () {
        $user = auth()->user();

        if (! $user?->isSuspended()) {
            return redirect()->route('dashboard');
        }

        $invoices = class_exists(\App\Models\SubscriptionInvoice::class)
            ? $user->subscriptionInvoices()
                ->with('plan')
                ->whereIn('status', [
                    \App\Models\SubscriptionInvoice::STATUS_PENDING,
                    \App\Models\SubscriptionInvoice::STATUS_OVERDUE,
                ])
                ->latest('due_date')
                ->take(5)
                ->get()
            : collect();

        return view('billing.suspended', compact('user', 'invoices'));
    })->name('client.suspended');

    /*
    |--------------------------------------------------------------------------
    | Fincas
    |--------------------------------------------------------------------------
    */
    Route::get('/fincas/crear', [FarmController::class, 'create'])->name('farms.create');
    Route::post('/fincas', [FarmController::class, 'store'])->name('farms.store');
    Route::post('/fincas/cambiar', [FarmController::class, 'switchFarm'])->name('farms.switch');

    /*
    |--------------------------------------------------------------------------
    | Animales
    |--------------------------------------------------------------------------
    */
    Route::get('/animales', [AnimalController::class, 'index'])->name('animals.index');
    Route::get('/animales/crear', [AnimalController::class, 'create'])->name('animals.create');
    Route::post('/animales', [AnimalController::class, 'store'])->name('animals.store');
    Route::post('/animales/trasladar', [AnimalController::class, 'bulkTransfer'])->name('animals.bulk-transfer');
    Route::get('/animales/{animal}', [AnimalController::class, 'show'])->name('animals.show');

    Route::get('/animales/{animal}/editar', [AnimalController::class, 'edit'])->name('animals.edit');
    Route::put('/animales/{animal}', [AnimalController::class, 'update'])->name('animals.update');
    Route::patch('/animales/{animal}/trasladar', [AnimalController::class, 'transfer'])->name('animals.transfer');
    Route::delete('/animales/{animal}', [AnimalController::class, 'destroy'])->name('animals.destroy');

    Route::get('/animales/{animal}/produccion', [AnimalController::class, 'production'])->name('animals.production');
    Route::post('/animales/{animal}/produccion', [AnimalController::class, 'storeProduction'])->name('animals.production.store');

    Route::get('/animales/{animal}/salud', [AnimalController::class, 'health'])->name('animals.health');
    Route::post('/animales/{animal}/salud', [AnimalController::class, 'storeHealth'])->name('animals.health.store');
    Route::patch('/animales/{animal}/seguimiento-reproductivo', [AnimalController::class, 'updateReproductiveStatus'])->name('animals.reproductive.update');
    Route::post('/animales/{animal}/cria', [AnimalController::class, 'attachOffspring'])->name('animals.offspring.attach');

    /*
    |--------------------------------------------------------------------------
    | Producción General
    |--------------------------------------------------------------------------
    */
    Route::get('/produccion', [ProductionController::class, 'index'])->name('production.index');
    Route::get('/lactancia', [LactationController::class, 'index'])->name('lactation.index');
    Route::post('/lactancia/{animal}/secado', [LactationController::class, 'confirmDryOff'])->name('lactation.dry-off');
    Route::post('/lactancia/{animal}/parto', [LactationController::class, 'registerCalving'])->name('lactation.calving');
    Route::get('/genealogia', [GenealogyController::class, 'index'])->name('genealogy.index');

    Route::post('/produccion/leche', [ProductionController::class, 'storeMilk'])->name('production.milk.store');
    Route::post('/produccion/leche/total-diario', [ProductionController::class, 'storeDailyMilk'])->name('production.milk-daily.store');
    Route::post('/produccion/leche/uso-interno', [ProductionController::class, 'storeMilkUsage'])->name('production.milk-usage.store');
    Route::patch('/produccion/leche/dia', [ProductionController::class, 'updateMilkDay'])->name('production.milk-day.update');
    Route::delete('/produccion/leche/dia', [ProductionController::class, 'destroyMilkDay'])->name('production.milk-day.destroy');
    Route::patch('/produccion/leche/total-diario/{dailyMilkProduction}', [ProductionController::class, 'updateDailyMilk'])->name('production.milk-daily.update');
    Route::patch('/produccion/leche/{milkProduction}', [ProductionController::class, 'updateMilk'])->name('production.milk.update');
    Route::delete('/produccion/leche/{milkProduction}', [ProductionController::class, 'destroyMilk'])->name('production.milk.destroy');
    Route::post('/produccion/leche/borrar-multiple', [ProductionController::class, 'bulkDestroyMilk'])->name('production.milk.bulk-destroy');
    Route::delete('/produccion/leche/total-diario/{dailyMilkProduction}', [ProductionController::class, 'destroyDailyMilk'])->name('production.milk-daily.destroy');
    Route::delete('/produccion/leche/uso-interno/{milkUsage}', [ProductionController::class, 'destroyMilkUsage'])->name('production.milk-usage.destroy');

    Route::post('/produccion/carne', [ProductionController::class, 'storeMeat'])->name('production.meat.store');
    Route::patch('/produccion/carne/{meatProduction}', [ProductionController::class, 'updateMeat'])->name('production.meat.update');
    Route::delete('/produccion/carne/{meatProduction}', [ProductionController::class, 'destroyMeat'])->name('production.meat.destroy');

    /*
    |--------------------------------------------------------------------------
    | Reportes
    |--------------------------------------------------------------------------
    */
    Route::get('/reportes', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reportes/exportar/{module}', [ReportsController::class, 'export'])->name('reports.export');

    /*
    |--------------------------------------------------------------------------
    | Gastos e ingresos
    |--------------------------------------------------------------------------
    */
    Route::get('/finanzas', [FinanceController::class, 'index'])->name('finances.index');
    Route::post('/finanzas', [FinanceController::class, 'store'])->name('finances.store');
    Route::patch('/finanzas/{transaction}', [FinanceController::class, 'update'])->name('finances.update');
    Route::delete('/finanzas/{transaction}', [FinanceController::class, 'destroy'])->name('finances.destroy');

    /*
    |--------------------------------------------------------------------------
    | Facturación del cliente
    |--------------------------------------------------------------------------
    */
    Route::get('/mi-facturacion', [ClientBillingController::class, 'index'])->name('client.billing.index');
    Route::get('/facturacion/facturas', [ClientBillingController::class, 'invoices'])->name('client.billing.invoices');
    Route::get('/facturacion/facturas/{invoice}', [ClientBillingController::class, 'showInvoice'])->name('client.billing.invoices.show');
    Route::get('/facturacion/facturas/{invoice}/pdf', [ClientBillingController::class, 'downloadInvoice'])->name('client.billing.invoices.download');
    Route::post('/facturacion/facturas/{invoice}/pagar', [ClientBillingController::class, 'checkoutInvoice'])->name('client.billing.invoices.checkout');
    Route::get('/facturacion/pagos/respuesta', [ClientBillingController::class, 'paymentResponse'])->name('client.billing.payment-response');
    Route::get('/facturacion/pagos', [ClientBillingController::class, 'payments'])->name('client.billing.payments');
    Route::get('/facturacion/forma-de-pago', [ClientBillingController::class, 'paymentMethods'])->name('client.billing.payment-methods');
    Route::post('/facturacion/forma-de-pago', [ClientBillingController::class, 'storePaymentMethod'])->name('client.billing.payment-methods.store');
    Route::patch('/facturacion/forma-de-pago/{paymentMethod}/principal', [ClientBillingController::class, 'defaultPaymentMethod'])->name('client.billing.payment-methods.default');
    Route::delete('/facturacion/forma-de-pago/{paymentMethod}', [ClientBillingController::class, 'destroyPaymentMethod'])->name('client.billing.payment-methods.destroy');

    /*
    |--------------------------------------------------------------------------
    | Notificaciones
    |--------------------------------------------------------------------------
    */
    Route::post('/notificaciones/sincronizar', [NotificationController::class, 'sync'])->name('notifications.sync');
    Route::post('/notificaciones/push/suscribir', [NotificationController::class, 'subscribePush'])->name('notifications.push.subscribe');
    Route::delete('/notificaciones/push/suscribir', [NotificationController::class, 'unsubscribePush'])->name('notifications.push.unsubscribe');
    Route::patch('/notificaciones/marcar-todas', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::patch('/notificaciones/{notification}/leer', [NotificationController::class, 'read'])->name('notifications.read');
    Route::delete('/notificaciones/{notification}', [NotificationController::class, 'dismiss'])->name('notifications.dismiss');

    /*
    |--------------------------------------------------------------------------
    | Lotes
    |--------------------------------------------------------------------------
    */
    Route::get('/lotes', [LotController::class, 'index'])->name('lots.index');
    Route::get('/lotes/crear', [LotController::class, 'create'])->name('lots.create');
    Route::get('/lotes/buscar-ubicacion', [LotController::class, 'searchLocation'])->name('lots.location-search');
    Route::post('/lotes', [LotController::class, 'store'])->name('lots.store');
    Route::get('/lotes/{lot}', [LotController::class, 'show'])->name('lots.show');
    Route::post('/lotes/{lot}/animales', [LotController::class, 'assignAnimals'])->name('lots.animals.assign');
    Route::post('/lotes/{lot}/historial', [LotController::class, 'updateHistory'])->name('lots.history.update');
    Route::get('/lotes/{lot}/editar', [LotController::class, 'edit'])->name('lots.edit');
    Route::put('/lotes/{lot}', [LotController::class, 'update'])->name('lots.update');
    Route::delete('/lotes/{lot}', [LotController::class, 'destroy'])->name('lots.destroy');

    /*
    |--------------------------------------------------------------------------
    | Eventos
    |--------------------------------------------------------------------------
    */
    Route::get('/eventos', [EventController::class, 'index'])->name('events.index');
    Route::get('/eventos/feed', [EventController::class, 'feed'])->name('events.feed');
    Route::post('/eventos', [EventController::class, 'store'])->name('events.store');
    Route::get('/eventos/{event}', [EventController::class, 'show'])->name('events.show');
    Route::put('/eventos/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('/eventos/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::patch('/eventos/{event}/estado', [EventController::class, 'updateStatus'])->name('events.update-status');

    /*
    |--------------------------------------------------------------------------
    | Perfil
    |--------------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware(['admin', 'track.admin.activity'])->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('dashboard');
        Route::get('/saas', [SaasController::class, 'index'])->name('saas.index');
        Route::get('/saas/planes', [SaasController::class, 'plans'])->name('saas.plans.index');
        Route::get('/saas/facturacion', [SaasController::class, 'billing'])->name('saas.billing.index');
        Route::get('/saas/facturacion/facturas', [SaasController::class, 'billingInvoices'])->name('saas.billing.invoices');
        Route::get('/saas/facturacion/pagos', [SaasController::class, 'billingPayments'])->name('saas.billing.payments');
        Route::get('/saas/facturacion/formas-de-pago', [SaasController::class, 'billingPaymentMethods'])->name('saas.billing.payment-methods');
        Route::get('/saas/notificaciones', [SaasController::class, 'notifications'])->name('saas.notifications.index');
        Route::post('/saas/notificaciones', [SaasController::class, 'sendNotification'])->name('saas.notifications.send');
        Route::get('/saas/configuracion', [SaasController::class, 'settings'])->name('saas.settings.index');
        Route::get('/saas/auditoria', [SaasController::class, 'audit'])->name('saas.audit.index');
        Route::post('/saas/paquetes', [SaasController::class, 'storePlan'])->name('saas.plans.store');
        Route::patch('/saas/paquetes/{plan}', [SaasController::class, 'updatePlan'])->name('saas.plans.update');
        Route::delete('/saas/paquetes/{plan}', [SaasController::class, 'destroyPlan'])->name('saas.plans.destroy');
        Route::patch('/saas/configuracion', [SaasController::class, 'updateSettings'])->name('saas.settings.update');
        Route::post('/saas/pagos', [SaasController::class, 'storePayment'])->name('saas.payments.store');
        Route::delete('/saas/pagos/{payment}', [SaasController::class, 'destroyPayment'])->name('saas.payments.destroy');
        Route::post('/saas/clientes/{client}/ver-como', [SaasController::class, 'viewAsClient'])->name('saas.clients.view-as');
        Route::delete('/saas/ver-como-cliente', [SaasController::class, 'stopViewingClient'])->name('saas.clients.stop-viewing');

        Route::get('/usuarios', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/usuarios/crear', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/usuarios', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/usuarios/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::patch('/usuarios/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::patch('/usuarios/{user}/password', [UserManagementController::class, 'updatePassword'])->name('users.update-password');
        Route::patch('/usuarios/{user}/estado', [UserManagementController::class, 'updateStatus'])->name('users.update-status');
        Route::patch('/usuarios/{user}/plan', [UserManagementController::class, 'updatePlan'])->name('users.update-plan');

        Route::get('/administradores', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::get('/administradores/crear', [AdminStaffController::class, 'create'])->name('staff.create');
        Route::post('/administradores', [AdminStaffController::class, 'store'])->name('staff.store');
        Route::get('/administradores/{staff}/editar', [AdminStaffController::class, 'edit'])->name('staff.edit');
        Route::patch('/administradores/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');
        Route::delete('/administradores/{staff}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Otras vistas
    |--------------------------------------------------------------------------
    */
    Route::redirect('/settings', '/configuracion');
    Route::redirect('/configuración', '/configuracion');
    Route::get('/configuracion', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/configuracion/finca', [SettingsController::class, 'updateFarm'])->name('settings.farm.update');
    Route::delete('/configuracion/finca', [SettingsController::class, 'destroyFarm'])->name('settings.farm.destroy');
    Route::post('/configuracion/roles', [SettingsController::class, 'storeRole'])->name('settings.roles.store');
    Route::delete('/configuracion/roles/{role}', [SettingsController::class, 'destroyRole'])->name('settings.roles.destroy');
    Route::post('/configuracion/empleados', [SettingsController::class, 'attachMember'])->name('settings.members.attach');
    Route::patch('/configuracion/empleados/{user}/rol', [SettingsController::class, 'updateMemberRole'])->name('settings.members.role');
    Route::delete('/configuracion/empleados/{user}', [SettingsController::class, 'detachMember'])->name('settings.members.detach');
});

require __DIR__ . '/auth.php';
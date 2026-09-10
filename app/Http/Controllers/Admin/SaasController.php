<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\Farm;
use App\Models\FarmNotification;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\FarmNotificationService;
use App\Support\EscapesLikeSearch;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SaasController extends Controller
{
    use EscapesLikeSearch;

    public function index()
    {
        return $this->renderIndex('overview');
    }

    public function plans()
    {
        return $this->renderPlans();
    }

    public function billing()
    {
        return $this->renderBilling('overview');
    }

    public function billingInvoices()
    {
        return $this->renderBilling('invoices');
    }

    public function billingPayments()
    {
        return $this->renderBilling('payments');
    }

    public function billingPaymentMethods()
    {
        return $this->renderBilling('payment_methods');
    }

    public function settings()
    {
        return $this->renderSettings('support');
    }

    public function notifications()
    {
        abort_unless(Schema::hasTable('farm_notifications'), 409, 'La tabla de notificaciones todavía no está creada.');

        $recentNotifications = FarmNotification::query()
            ->where('source_type', 'admin_broadcast')
            ->latest('scheduled_for')
            ->latest()
            ->take(1000)
            ->get()
            ->groupBy('source_key')
            ->take(30)
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'title' => $first->title,
                    'message' => $first->message,
                    'level' => $first->level,
                    'scheduled_for' => $first->scheduled_for,
                    'recipients' => $items->count(),
                    'read_count' => $items->whereNotNull('read_at')->count(),
                ];
            })
            ->values();

        return view('admin.saas.notifications', compact('recentNotifications'));
    }

    public function audit(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless(Schema::hasTable('user_activity_logs'), 409, 'La tabla de historial todavía no está creada.');

        $search = trim((string) $request->get('search'));
        $action = trim((string) $request->get('action'));
        $adminId = $request->filled('admin_id') && ctype_digit((string) $request->get('admin_id'))
            ? (int) $request->get('admin_id')
            : null;

        $hiddenAdminIds = User::hiddenAdminUserIds();

        $logsQuery = UserActivityLog::with('user')
            ->where('action', 'like', 'admin_%')
            ->when($hiddenAdminIds !== [], fn ($query) => $query->whereNotIn('user_id', $hiddenAdminIds))
            ->when($adminId, fn ($query) => $query->where('user_id', $adminId))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($search !== '', function ($query) use ($search) {
                $this->whereLikeAny($query, ['description', 'action', 'route_name', 'ip_address'], $search);
            })
            ->latest('created_at');

        $logs = $logsQuery
            ->paginate(30)
            ->withQueryString();

        $admins = User::query()
            ->whereIn('role', ['admin', 'super_admin', 'superadmin'])
            ->when($hiddenAdminIds !== [], fn ($query) => $query->whereNotIn('id', $hiddenAdminIds))
            ->orderBy('first_name')
            ->orderBy('email')
            ->get();

        $actions = UserActivityLog::query()
            ->where('action', 'like', 'admin_%')
            ->when($hiddenAdminIds !== [], fn ($query) => $query->whereNotIn('user_id', $hiddenAdminIds))
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.saas.audit', compact(
            'logs',
            'admins',
            'actions',
            'search',
            'action',
            'adminId'
        ));
    }

    protected function renderIndex(string $activeSection = 'overview')
    {
        $plansReady = Schema::hasTable('subscription_plans');
        $settingsReady = Schema::hasTable('platform_settings');
        $hasPlanColumn = Schema::hasColumn('users', 'subscription_plan_id');

        $plans = $plansReady
            ? SubscriptionPlan::orderBy('sort_order')->orderBy('price')->get()
            : collect();

        $settings = $settingsReady
            ? PlatformSetting::all()->pluck('value', 'key')
            : collect();

        $clientsQuery = User::query();

        if (Schema::hasColumn('users', 'role')) {
            $clientsQuery->whereNotIn('role', ['admin', 'super_admin', 'superadmin']);
        }

        $clients = (clone $clientsQuery)->get();

        $clientFarmIds = class_exists(Farm::class)
            ? Farm::whereHas('users', function ($query) use ($clients) {
                $query->whereIn('users.id', $clients->pluck('id'));
            })->pluck('farms.id')
            : collect();

        $billablePlanUsers = $plansReady && $hasPlanColumn
            ? $this->billablePlanUsers()
            : collect();

        $stats = [
            'clients' => $clients->count(),
            'active_clients' => Schema::hasColumn('users', 'status')
                ? (clone $clientsQuery)->where('status', 'active')->count()
                : $clients->count(),
            'farms' => class_exists(Farm::class) ? Farm::count() : 0,
            'animals' => Schema::hasTable('animals')
                ? Animal::whereIn('farm_id', $clientFarmIds)->count()
                : 0,
            'plans' => $plans->count(),
            'estimated_mrr' => $plansReady && $hasPlanColumn
                ? $this->estimatedMonthlyRevenue($billablePlanUsers)
                : 0,
            'billable_clients' => $billablePlanUsers->count(),
        ];

        $recentClients = (clone $clientsQuery)->with('farms')
            ->latest()
            ->take(8)
            ->get();

        $clientChart = collect(range(5, 0))
            ->map(function ($monthsAgo) use ($clients) {
                $date = now()->subMonths($monthsAgo);

                return [
                    'label' => $date->format('M'),
                    'value' => $clients
                        ->filter(fn ($client) => $client->created_at && $client->created_at->format('Y-m') === $date->format('Y-m'))
                        ->count(),
                ];
            })
            ->values();

        $revenueChart = $plansReady && $hasPlanColumn
            ? $this->planRevenueChart($plans)
            : collect();

        return view('admin.saas.index', compact(
            'plans',
            'settings',
            'stats',
            'recentClients',
            'clientChart',
            'revenueChart',
            'plansReady',
            'settingsReady',
            'activeSection'
        ));
    }

    protected function renderSettings(string $activeConfigSection = 'support')
    {
        $settingsReady = Schema::hasTable('platform_settings');

        $settings = $settingsReady
            ? PlatformSetting::all()->pluck('value', 'key')
            : collect();

        return view('admin.saas.settings', compact(
            'settings',
            'settingsReady',
            'activeConfigSection'
        ));
    }

    protected function renderPlans()
    {
        $plansReady = Schema::hasTable('subscription_plans');

        $plans = $plansReady
            ? SubscriptionPlan::orderBy('sort_order')->orderBy('price')->get()
            : collect();

        return view('admin.saas.plans', compact('plans', 'plansReady'));
    }

    protected function renderBilling(string $activeBillingSection = 'overview')
    {
        $settingsReady = Schema::hasTable('platform_settings');
        $plansReady = Schema::hasTable('subscription_plans');
        $paymentsReady = Schema::hasTable('subscription_payments');
        $invoicesReady = Schema::hasTable('subscription_invoices');

        $settings = $settingsReady
            ? PlatformSetting::all()->pluck('value', 'key')
            : collect();

        $clientsQuery = User::with(['farms.animals', 'farms.lots', 'subscriptionPlan'])
            ->when(Schema::hasColumn('users', 'role'), function ($query) {
                $query->whereNotIn('role', ['admin', 'super_admin', 'superadmin']);
            });

        $billingClients = $clientsQuery
            ->latest()
            ->get()
            ->map(function (User $client) {
                $plan = $client->subscriptionPlan;

                return [
                    'client' => $client,
                    'plan' => $plan,
                    'next_charge_date' => $this->nextChargeDate($client),
                    'amount' => $plan ? (float) $plan->price : 0,
                    'period' => $plan?->billing_period,
                    'farms_count' => $client->farms->count(),
                    'animals_count' => $client->farms->sum(fn ($farm) => $farm->animals->count()),
                    'lots_count' => $client->farms->sum(fn ($farm) => $farm->lots->count()),
                ];
            });

        $payments = $paymentsReady
            ? SubscriptionPayment::with(['user', 'plan'])->latest('paid_at')->latest()->take(40)->get()
            : collect();

        $invoices = $invoicesReady
            ? SubscriptionInvoice::with(['user', 'plan'])
                ->latest('due_date')
                ->take(60)
                ->get()
            : collect();

        $billingStats = [
            'clients_with_plan' => $billingClients->filter(fn ($row) => $row['plan'])->count(),
            'pending_clients' => $billingClients->filter(fn ($row) => in_array($row['client']->billing_status, ['trial', 'past_due'], true))->count(),
            'pending_invoices' => $invoices->whereIn('status', [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_OVERDUE])->count(),
            'paid_total' => $payments->where('status', 'paid')->sum(fn ($payment) => (float) $payment->amount),
            'payments_count' => $payments->count(),
        ];

        return view('admin.saas.billing', compact(
            'settings',
            'settingsReady',
            'plansReady',
            'paymentsReady',
            'invoicesReady',
            'billingClients',
            'invoices',
            'payments',
            'billingStats',
            'activeBillingSection'
        ));
    }

    public function storePayment(Request $request)
    {
        abort_unless(Schema::hasTable('subscription_payments'), 409, 'La tabla de pagos SaaS todavía no está creada.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subscription_invoice_id' => ['nullable', 'integer', 'exists:subscription_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'paid_at' => ['required', 'date'],
            'status' => ['required', 'in:paid,pending,failed,refunded'],
            'payment_method' => ['nullable', 'string', 'max:120'],
            'reference' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $client = User::with('subscriptionPlan')->findOrFail($data['user_id']);
        $plan = $client->subscriptionPlan;
        $invoice = ! empty($data['subscription_invoice_id'])
            ? SubscriptionInvoice::where('user_id', $client->id)->find($data['subscription_invoice_id'])
            : null;

        $payment = SubscriptionPayment::create([
            'user_id' => $client->id,
            'subscription_plan_id' => $plan?->id,
            'subscription_invoice_id' => $invoice?->id,
            'amount' => $data['amount'],
            'currency' => mb_strtoupper($data['currency']),
            'billing_period' => $plan?->billing_period,
            'paid_at' => $data['paid_at'],
            'status' => $data['status'],
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($data['status'] === 'paid') {
            $client->update(['billing_status' => 'active']);

            if ($invoice) {
                $invoice->update(['status' => SubscriptionInvoice::STATUS_PAID]);
            }

            if ($invoice && $plan && $plan->billing_period !== 'one_time') {
                $nextBillingDate = $invoice->due_date->copy();

                if ($plan->billing_period === 'yearly') {
                    $nextBillingDate->addYearNoOverflow();
                } else {
                    $nextBillingDate->addMonthNoOverflow();
                }

                $client->update(['next_billing_date' => $nextBillingDate->toDateString()]);
            }

            app(FarmNotificationService::class)->notifyAdminPaymentReceived($payment->fresh(['user', 'plan', 'invoice']));
        }

        return back()->with('success', 'Pago registrado correctamente.');
    }

    public function sendNotification(Request $request)
    {
        abort_unless(Schema::hasTable('farm_notifications'), 409, 'La tabla de notificaciones todavía no está creada.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:140'],
            'message' => ['required', 'string', 'max:1000'],
            'level' => ['required', 'in:low,medium,high'],
            'target' => ['required', 'in:all,active,trial,past_due'],
        ]);

        $clientsQuery = User::query();

        if (Schema::hasColumn('users', 'role')) {
            $clientsQuery->whereNotIn('role', ['admin', 'super_admin', 'superadmin']);
        }

        if ($data['target'] === 'active' && Schema::hasColumn('users', 'status')) {
            $clientsQuery->where('status', 'active');
        }

        if (in_array($data['target'], ['trial', 'past_due'], true) && Schema::hasColumn('users', 'billing_status')) {
            $clientsQuery->where('billing_status', $data['target']);
        }

        $clientIds = $clientsQuery->pluck('id');
        $sourceKey = 'broadcast-' . now()->format('YmdHis') . '-' . Str::random(8);
        $sentAt = now();
        $basePayload = [
            'farm_id' => null,
            'event_id' => null,
            'source_type' => 'admin_broadcast',
            'source_key' => $sourceKey,
            'level' => $data['level'],
            'title' => $data['title'],
            'message' => $data['message'],
            'event_date' => $sentAt->toDateString(),
            'lot_name' => null,
            'meta' => json_encode([
                'automatic' => false,
                'type_label' => 'Comunicado',
                'event_type' => 'admin_broadcast',
                'target' => $data['target'],
                'sent_by' => auth()->id(),
            ]),
            'scheduled_for' => $sentAt,
            'created_at' => $sentAt,
            'updated_at' => $sentAt,
        ];
        $sentCount = 0;

        $clientIds
            ->chunk(500)
            ->each(function ($chunk) use ($basePayload, &$sentCount) {
                $rows = $chunk
                    ->map(fn ($clientId) => [
                        ...$basePayload,
                        'user_id' => $clientId,
                    ])
                    ->all();

                if ($rows) {
                    FarmNotification::insert($rows);
                    $sentCount += count($rows);
                }
            });

        return back()->with('success', 'Notificación enviada a ' . $sentCount . ' cliente' . ($sentCount === 1 ? '' : 's') . '.');
    }

    public function destroyPayment(SubscriptionPayment $payment)
    {
        $payment->delete();

        return back()->with('success', 'Pago eliminado correctamente.');
    }

    public function storePlan(Request $request)
    {
        $this->ensurePlansTable();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', 'in:monthly,yearly,one_time'],
            'max_farms' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_animals' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        SubscriptionPlan::create([
            'name' => $data['name'],
            'slug' => $this->uniquePlanSlug($data['name']),
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'billing_period' => $data['billing_period'],
            'max_farms' => $data['max_farms'] ?? null,
            'max_users' => $data['max_users'] ?? null,
            'max_animals' => $data['max_animals'] ?? null,
            'features' => $this->parseFeatures($data['features'] ?? ''),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => (int) SubscriptionPlan::max('sort_order') + 1,
        ]);

        return back()->with('success', 'Paquete creado correctamente.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_period' => ['required', 'in:monthly,yearly,one_time'],
            'max_farms' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_animals' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $plan->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'billing_period' => $data['billing_period'],
            'max_farms' => $data['max_farms'] ?? null,
            'max_users' => $data['max_users'] ?? null,
            'max_animals' => $data['max_animals'] ?? null,
            'features' => $this->parseFeatures($data['features'] ?? ''),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', 'Paquete actualizado correctamente.');
    }

    public function destroyPlan(SubscriptionPlan $plan)
    {
        $plan->delete();

        return back()->with('success', 'Paquete eliminado correctamente.');
    }

    public function updateSettings(Request $request)
    {
        $this->ensureSettingsTable();

        $data = $request->validate([
            'support_whatsapp_url' => ['nullable', 'string', 'max:500'],
            'support_whatsapp_message' => ['nullable', 'string', 'max:160'],
            'payment_gateway' => ['nullable', 'string', 'max:80'],
            'payment_public_key' => ['nullable', 'string', 'max:500'],
            'payment_secret_key' => ['nullable', 'string', 'max:500'],
            'payment_webhook_secret' => ['nullable', 'string', 'max:500'],
            'wompi_environment' => ['nullable', 'in:sandbox,production'],
            'wompi_public_key' => ['nullable', 'string', 'max:500'],
            'wompi_private_key' => ['nullable', 'string', 'max:500'],
            'wompi_events_key' => ['nullable', 'string', 'max:500'],
            'wompi_integrity_secret' => ['nullable', 'string', 'max:500'],
            'wompi_webhook_url' => ['nullable', 'url', 'max:500'],
            'billing_currency' => ['nullable', 'string', 'max:10'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'billing_email' => ['nullable', 'email', 'max:180'],
            'google_maps_api_key' => ['nullable', 'string', 'max:500'],
            'app_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'app_primary_dark_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'app_accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'app_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'app_dark_background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'app_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'remove_app_logo' => ['nullable', 'boolean'],
        ]);

        $logoFile = $request->file('app_logo');
        $removeLogo = $request->boolean('remove_app_logo');

        unset($data['app_logo'], $data['remove_app_logo']);

        if (array_key_exists('support_whatsapp_url', $data)) {
            $data['support_whatsapp_url'] = $this->normalizeSupportWhatsappUrl($data['support_whatsapp_url']);
        }

        if (array_key_exists('wompi_webhook_url', $data) && blank($data['wompi_webhook_url'])) {
            $data['wompi_webhook_url'] = route('wompi.webhook');
        }

        if ($removeLogo || $logoFile) {
            $previousLogoPath = PlatformSetting::where('key', 'app_logo_path')->value('value');

            if ($previousLogoPath) {
                Storage::disk('public')->delete($previousLogoPath);
            }

            if ($logoFile) {
                $data['app_logo_path'] = $logoFile->store('branding', 'public');
            } else {
                $data['app_logo_path'] = null;
            }
        }

        foreach ($data as $key => $value) {
            PlatformSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $key === 'trial_days'
                        ? 'integer'
                        : (str_ends_with($key, '_color') ? 'color' : ($key === 'app_logo_path' ? 'image' : 'string')),
                    'group' => str_starts_with($key, 'app_')
                        ? 'appearance'
                        : (
                            $key === 'google_maps_api_key'
                                ? 'integrations'
                                : (
                                    str_starts_with($key, 'payment_') || str_starts_with($key, 'wompi_') || in_array($key, ['payment_gateway', 'billing_currency', 'billing_email'], true)
                                ? 'payments'
                                : 'support'
                                )
                        ),
                ]
            );
        }

        return back()->with('success', 'Configuración SaaS actualizada correctamente.');
    }

    protected function normalizeSupportWhatsappUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, ['http://', 'https://', 'whatsapp://'])) {
            return $url;
        }

        $digits = preg_replace('/\D+/', '', $url);

        if ($digits && preg_match('/^[+\d\s().-]+$/', $url)) {
            return 'https://wa.me/' . $digits;
        }

        if (Str::startsWith($url, ['wa.me/', 'api.whatsapp.com/', 'web.whatsapp.com/'])) {
            return 'https://' . $url;
        }

        return $url;
    }

    public function viewAsClient(Request $request, User $client)
    {
        $farm = $client->farms()
            ->latest('farms.id')
            ->first();

        if (! $farm) {
            return back()->with('warning', 'Este cliente todavía no tiene fincas para visualizar.');
        }

        $request->session()->put('admin_view_client_id', $client->id);
        $request->session()->put('current_farm_id', $farm->id);

        return redirect()
            ->route('dashboard', ['farm' => $farm->id])
            ->with('success', 'Estás viendo la plataforma como ' . ($client->full_name ?: $client->email) . '.');
    }

    public function stopViewingClient(Request $request)
    {
        $request->session()->forget(['admin_view_client_id', 'current_farm_id']);

        return redirect()
            ->route('admin.saas.index')
            ->with('success', 'Volviste al modo administrador.');
    }

    protected function parseFeatures(string $features): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $features))
            ->map(fn ($feature) => trim($feature))
            ->filter()
            ->values()
            ->all();
    }

    protected function uniquePlanSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 2;

        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    protected function ensurePlansTable(): void
    {
        abort_unless(Schema::hasTable('subscription_plans'), 409, 'La tabla de planes todavía no está creada.');
    }

    protected function ensureSettingsTable(): void
    {
        abort_unless(Schema::hasTable('platform_settings'), 409, 'La tabla de configuración SaaS todavía no está creada.');
    }

    protected function nextChargeDate(User $client): ?Carbon
    {
        $plan = $client->subscriptionPlan;

        if (! $plan || $plan->billing_period === 'one_time') {
            return null;
        }

        if ($client->next_billing_date) {
            return $client->next_billing_date->copy();
        }

        if ($client->trial_ends_at && $client->trial_ends_at->isFuture()) {
            return $client->trial_ends_at->copy();
        }

        $date = ($client->trial_ends_at ?: $client->created_at ?: now())->copy();
        $months = $plan->billing_period === 'yearly' ? 12 : 1;

        while ($date->isPast()) {
            $date->addMonthsNoOverflow($months);
        }

        return $date;
    }

    protected function estimatedMonthlyRevenue($billableUsers = null): float
    {
        return ($billableUsers ?? $this->billablePlanUsers())
            ->sum(function (User $user) {
                $plan = $user->subscriptionPlan;

                if (! $plan) {
                    return 0;
                }

                return match ($plan->billing_period) {
                    'yearly' => ((float) $plan->price) / 12,
                    'one_time' => 0,
                    default => (float) $plan->price,
                };
            });
    }

    protected function planRevenueChart($plans)
    {
        if (! Schema::hasColumn('users', 'subscription_plan_id')) {
            return collect();
        }

        $usersByPlan = $this->billablePlanUsers()
            ->groupBy('subscription_plan_id');

        return $plans
            ->map(function (SubscriptionPlan $plan) use ($usersByPlan) {
                $subscribers = $usersByPlan->get($plan->id, collect())->count();
                $monthlyValue = match ($plan->billing_period) {
                    'yearly' => ((float) $plan->price) / 12,
                    'one_time' => 0,
                    default => (float) $plan->price,
                };

                return [
                    'label' => $plan->name,
                    'value' => round($monthlyValue * $subscribers, 2),
                    'subscribers' => $subscribers,
                ];
            })
            ->filter(fn ($row) => $row['subscribers'] > 0 || $row['value'] > 0)
            ->values();
    }

    protected function billablePlanUsers()
    {
        return User::with('subscriptionPlan')
            ->whereNotNull('subscription_plan_id')
            ->when(Schema::hasColumn('users', 'role'), function ($query) {
                $query->whereNotIn('role', ['admin', 'super_admin', 'superadmin']);
            })
            ->when(Schema::hasColumn('users', 'status'), function ($query) {
                $query->where('status', 'active');
            })
            ->when(Schema::hasColumn('users', 'billing_status'), function ($query) {
                $query->whereIn('billing_status', ['active', 'manual']);
            })
            ->when(Schema::hasColumn('users', 'trial_ends_at'), function ($query) {
                $query->where(function ($trialQuery) {
                    $trialQuery->whereNull('trial_ends_at')
                        ->orWhere('trial_ends_at', '<=', now());
                });
            })
            ->get()
            ->filter(fn (User $user) => $this->isBillableForMonthlyRevenue($user))
            ->values();
    }

    protected function isBillableForMonthlyRevenue(User $user): bool
    {
        if (! $user->subscriptionPlan || $user->subscriptionPlan->billing_period === 'one_time') {
            return false;
        }

        if (Schema::hasColumn('users', 'status') && $user->status !== 'active') {
            return false;
        }

        if (Schema::hasColumn('users', 'billing_status')
            && ! in_array($user->billing_status, ['active', 'manual'], true)) {
            return false;
        }

        if (Schema::hasColumn('users', 'trial_ends_at') && $user->trial_ends_at?->isFuture()) {
            return false;
        }

        return true;
    }
}

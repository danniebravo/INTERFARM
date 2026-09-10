<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SubscriptionInvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateSubscriptionInvoices extends Command
{
    protected $signature = 'subscriptions:generate-invoices {--days=10 : Days before due date}';

    protected $description = 'Generate SaaS subscription invoices before each client due date.';

    public function handle(): int
    {
        if (! Schema::hasTable('subscription_invoices') || ! Schema::hasTable('subscription_plans')) {
            $missingTables = collect([
                'subscription_plans' => Schema::hasTable('subscription_plans'),
                'subscription_invoices' => Schema::hasTable('subscription_invoices'),
            ])
                ->filter(fn ($exists) => ! $exists)
                ->keys()
                ->implode(', ');

            $this->warn("Faltan tablas para generar facturas: {$missingTables}.");
            $this->line('Ejecuta primero: php artisan migrate');

            return self::SUCCESS;
        }

        $invoiceService = app(SubscriptionInvoiceService::class);
        $missingInvoiceColumns = collect($invoiceService->missingInvoiceColumns());

        if ($missingInvoiceColumns->isNotEmpty()) {
            $this->warn('La tabla subscription_invoices está incompleta.');
            $this->line('Columnas faltantes: ' . $missingInvoiceColumns->implode(', '));
            $this->line('Ejecuta la migración de reparación y luego: php artisan optimize:clear');

            return self::SUCCESS;
        }

        $daysBefore = max(0, (int) $this->option('days'));
        $clients = User::with('subscriptionPlan')
            ->whereNotNull('subscription_plan_id')
            ->when(Schema::hasColumn('users', 'role'), function ($query) {
                $query->whereNotIn('role', ['admin', 'super_admin', 'superadmin']);
            })
            ->get();

        $created = 0;

        foreach ($clients as $client) {
            $invoice = $invoiceService->generateForUserIfDue($client, $daysBefore);

            if ($invoice?->wasRecentlyCreated) {
                $created++;
            }
        }

        $invoiceService->markOverdueInvoices();

        $this->info("Invoices generated: {$created}");

        return self::SUCCESS;
    }
}

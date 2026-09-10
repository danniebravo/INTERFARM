<?php

namespace App\Console\Commands;

use App\Services\SubscriptionInvoiceService;
use Illuminate\Console\Command;

class MarkOverdueSubscriptionInvoices extends Command
{
    protected $signature = 'subscriptions:mark-overdue-invoices';

    protected $description = 'Mark pending SaaS subscription invoices as overdue after their due date.';

    public function handle(SubscriptionInvoiceService $invoiceService): int
    {
        if (! $invoiceService->isReady()) {
            $this->warn('El módulo de facturas SaaS todavía no está listo. Ejecuta las migraciones pendientes.');

            return self::SUCCESS;
        }

        $updated = $invoiceService->markOverdueInvoices();

        $this->info("Facturas vencidas actualizadas: {$updated}");

        return self::SUCCESS;
    }
}

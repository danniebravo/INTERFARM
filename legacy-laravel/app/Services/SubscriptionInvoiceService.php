<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SubscriptionInvoiceService
{
    public function generateForUserIfDue(User $user, int $daysBefore = 10): ?SubscriptionInvoice
    {
        if (! $this->isReady()) {
            return null;
        }

        $user->loadMissing('subscriptionPlan');
        $plan = $user->subscriptionPlan;

        if (! $plan || ! $plan->is_active || $plan->billing_period === 'one_time') {
            return null;
        }

        $dueDate = $this->nextDueDate($user);

        if (! $dueDate || now()->startOfDay()->lt($dueDate->copy()->subDays(max(0, $daysBefore))->startOfDay())) {
            return null;
        }

        return $this->createInvoice($user, $dueDate);
    }

    public function createInvoice(User $user, Carbon $dueDate): ?SubscriptionInvoice
    {
        if (! $this->isReady()) {
            return null;
        }

        $user->loadMissing('subscriptionPlan');
        $plan = $user->subscriptionPlan;

        if (! $plan) {
            return null;
        }

        $periodStart = $this->periodStart($dueDate, $plan->billing_period);

        $invoice = SubscriptionInvoice::firstOrCreate(
            [
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'due_date' => $dueDate->toDateString(),
            ],
            [
                'invoice_number' => $this->nextInvoiceNumber(),
                'amount' => $plan->price,
                'currency' => $this->currency(),
                'billing_period' => $plan->billing_period,
                'period_start' => $periodStart,
                'period_end' => $dueDate->copy()->subDay(),
                'issue_date' => now()->toDateString(),
                'status' => SubscriptionInvoice::STATUS_PENDING,
                'notes' => 'Factura generada automáticamente.',
            ]
        );

        if ($invoice->wasRecentlyCreated) {
            app(FarmNotificationService::class)->notifyInvoiceGenerated($invoice);
        }

        return $invoice;
    }

    public function nextDueDate(User $user): ?Carbon
    {
        $plan = $user->subscriptionPlan;

        if (! $plan) {
            return null;
        }

        if ($user->next_billing_date) {
            return $user->next_billing_date->copy()->startOfDay();
        }

        if ($user->trial_ends_at && $user->trial_ends_at->isFuture()) {
            return $user->trial_ends_at->copy()->startOfDay();
        }

        $date = ($user->trial_ends_at ?: $user->created_at ?: now())->copy()->startOfDay();
        $months = $plan->billing_period === 'yearly' ? 12 : 1;

        while ($date->isPast()) {
            $date->addMonthsNoOverflow($months);
        }

        return $date;
    }

    public function markOverdueInvoices(): int
    {
        if (! $this->isReady()) {
            return 0;
        }

        return SubscriptionInvoice::where('status', SubscriptionInvoice::STATUS_PENDING)
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => SubscriptionInvoice::STATUS_OVERDUE]);
    }

    public function isReady(): bool
    {
        if (! Schema::hasTable('subscription_invoices') || ! Schema::hasTable('subscription_plans')) {
            return false;
        }

        $requiredInvoiceColumns = [
            'user_id',
            'subscription_plan_id',
            'invoice_number',
            'amount',
            'currency',
            'billing_period',
            'period_start',
            'period_end',
            'issue_date',
            'due_date',
            'status',
        ];

        foreach ($requiredInvoiceColumns as $column) {
            if (! Schema::hasColumn('subscription_invoices', $column)) {
                return false;
            }
        }

        return true;
    }

    public function missingInvoiceColumns(): array
    {
        $requiredInvoiceColumns = [
            'user_id',
            'subscription_plan_id',
            'invoice_number',
            'amount',
            'currency',
            'billing_period',
            'period_start',
            'period_end',
            'issue_date',
            'due_date',
            'status',
        ];

        return collect($requiredInvoiceColumns)
            ->reject(fn ($column) => Schema::hasColumn('subscription_invoices', $column))
            ->values()
            ->all();
    }

    protected function periodStart(Carbon $dueDate, string $period): Carbon
    {
        return $period === 'yearly'
            ? $dueDate->copy()->subYearNoOverflow()
            : $dueDate->copy()->subMonthNoOverflow();
    }

    protected function nextInvoiceNumber(): string
    {
        return 'INV-' . now()->format('Ymd') . '-' . str_pad((string) (SubscriptionInvoice::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }

    protected function currency(): string
    {
        $currency = Schema::hasTable('platform_settings')
            ? (PlatformSetting::where('key', 'billing_currency')->value('value') ?: 'COP')
            : 'COP';

        return mb_strtoupper($currency);
    }
}

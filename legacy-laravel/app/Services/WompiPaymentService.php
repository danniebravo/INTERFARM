<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WompiPaymentService
{
    public function isConfigured(): bool
    {
        return filled($this->publicKey()) && filled($this->integritySecret());
    }

    public function createCheckout(SubscriptionInvoice $invoice, User $user): array
    {
        $publicKey = $this->publicKey();
        $integritySecret = $this->integritySecret();

        if (! $publicKey || ! $integritySecret) {
            throw new \RuntimeException('La pasarela Wompi no tiene llave pública o secreto de integridad configurado.');
        }

        $currency = mb_strtoupper($invoice->currency ?: 'COP');
        $amountInCents = $this->amountInCents($invoice->amount);
        $reference = $this->nextReference($invoice);

        $payment = DB::transaction(function () use ($invoice, $user, $currency, $amountInCents, $reference) {
            return SubscriptionPayment::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $invoice->subscription_plan_id,
                'subscription_invoice_id' => $invoice->id,
                'amount' => $amountInCents / 100,
                'currency' => $currency,
                'billing_period' => $invoice->billing_period,
                'status' => 'pending',
                'payment_method' => 'wompi',
                'reference' => $reference,
                'provider' => 'wompi',
                'provider_status' => 'PENDING',
            ]);
        });

        $params = array_filter([
            'public-key' => $publicKey,
            'currency' => $currency,
            'amount-in-cents' => $amountInCents,
            'reference' => $reference,
            'signature:integrity' => $this->checkoutSignature($reference, $amountInCents, $currency),
            'redirect-url' => route('client.billing.payment-response', ['reference' => $reference]),
            'customer-data:email' => $user->email,
            'customer-data:full-name' => $user->full_name ?: $user->name,
            'customer-data:phone-number' => $this->onlyDigits($user->phone),
            'customer-data:phone-number-prefix' => $user->phone ? '+57' : null,
            'customer-data:legal-id' => $this->onlyDigits($user->document),
            'customer-data:legal-id-type' => $this->documentType($user->document_type),
        ], fn ($value) => filled($value));

        return [
            'payment' => $payment,
            'url' => 'https://checkout.wompi.co/p?' . http_build_query($params),
        ];
    }

    public function applyTransaction(array $transaction, string $source = 'webhook'): ?SubscriptionPayment
    {
        $reference = $transaction['reference'] ?? null;

        if (! $reference) {
            return null;
        }

        $payment = SubscriptionPayment::with(['invoice', 'user.subscriptionPlan'])
            ->where('reference', $reference)
            ->first();

        if (! $payment) {
            return null;
        }

        $providerStatus = (string) ($transaction['status'] ?? 'PENDING');
        $status = $this->localStatus($providerStatus);
        $method = mb_strtolower((string) ($transaction['payment_method_type'] ?? $payment->payment_method ?? 'wompi'));
        $transactionId = $transaction['id'] ?? null;

        $amountMatches = ! isset($transaction['amount_in_cents'])
            || $this->amountInCents($payment->amount) === (int) $transaction['amount_in_cents'];
        $currencyMatches = ! isset($transaction['currency'])
            || mb_strtoupper($payment->currency) === mb_strtoupper((string) $transaction['currency']);

        if (! $amountMatches || ! $currencyMatches) {
            $status = 'failed';
            $providerStatus = 'AMOUNT_OR_CURRENCY_MISMATCH';
        }

        DB::transaction(function () use ($payment, $transaction, $transactionId, $providerStatus, $status, $method, $source) {
            $payment->update([
                'status' => $status,
                'paid_at' => $status === 'paid' ? ($payment->paid_at ?: now()) : $payment->paid_at,
                'payment_method' => $method,
                'provider' => 'wompi',
                'provider_transaction_id' => $transactionId,
                'provider_status' => $providerStatus,
                'provider_payload' => [
                    'source' => $source,
                    'transaction' => $transaction,
                    'received_at' => now()->toIso8601String(),
                ],
            ]);

            if ($status === 'paid') {
                $this->markInvoicePaid($payment);
            }
        });

        if ($status === 'paid') {
            app(FarmNotificationService::class)->notifyAdminPaymentReceived($payment->fresh(['invoice', 'user', 'plan']));
        }

        return $payment->fresh(['invoice']);
    }

    public function transaction(string $transactionId): ?array
    {
        $privateKey = $this->privateKey();

        if (! $privateKey) {
            return null;
        }

        $response = Http::withToken($privateKey)
            ->acceptJson()
            ->timeout(15)
            ->get($this->apiBaseUrl() . '/v1/transactions/' . urlencode($transactionId));

        if (! $response->successful()) {
            return null;
        }

        return $response->json('data');
    }

    public function validEvent(array $payload, ?string $headerChecksum = null): bool
    {
        $secret = $this->eventsSecret();
        $properties = data_get($payload, 'signature.properties', []);
        $checksum = $headerChecksum ?: data_get($payload, 'signature.checksum');
        $timestamp = data_get($payload, 'timestamp');

        if (! $secret || ! is_array($properties) || ! $checksum || ! $timestamp) {
            return false;
        }

        $base = '';
        foreach ($properties as $property) {
            $base .= (string) data_get($payload['data'] ?? [], $property, '');
        }

        $calculated = hash('sha256', $base . $timestamp . $secret);

        return hash_equals(mb_strtolower((string) $checksum), mb_strtolower($calculated));
    }

    protected function markInvoicePaid(SubscriptionPayment $payment): void
    {
        $invoice = $payment->invoice;
        $client = $payment->user;

        if (! $invoice || ! $client) {
            return;
        }

        $invoice->update(['status' => SubscriptionInvoice::STATUS_PAID]);
        $client->update(['billing_status' => 'active']);

        $plan = $client->subscriptionPlan ?: $invoice->plan;
        if (! $plan || $plan->billing_period === 'one_time' || ! $invoice->due_date) {
            return;
        }

        $nextBillingDate = $invoice->due_date->copy();
        $plan->billing_period === 'yearly'
            ? $nextBillingDate->addYearNoOverflow()
            : $nextBillingDate->addMonthNoOverflow();

        $client->update(['next_billing_date' => $nextBillingDate->toDateString()]);
    }

    protected function checkoutSignature(string $reference, int $amountInCents, string $currency): string
    {
        return hash('sha256', $reference . $amountInCents . $currency . $this->integritySecret());
    }

    protected function nextReference(SubscriptionInvoice $invoice): string
    {
        do {
            $reference = 'IF-' . $invoice->id . '-' . Carbon::now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
        } while (SubscriptionPayment::where('reference', $reference)->exists());

        return $reference;
    }

    protected function amountInCents(float|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    protected function localStatus(string $providerStatus): string
    {
        return match (mb_strtoupper($providerStatus)) {
            'APPROVED' => 'paid',
            'DECLINED', 'ERROR', 'VOIDED' => 'failed',
            default => 'pending',
        };
    }

    protected function documentType(?string $type): ?string
    {
        return match (mb_strtolower((string) $type)) {
            'cc', 'ce', 'nit', 'pp', 'ti', 'dni' => mb_strtoupper($type),
            default => filled($type) ? 'OTHER' : null,
        };
    }

    protected function onlyDigits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    protected function publicKey(): ?string
    {
        return $this->setting('wompi_public_key') ?: $this->setting('payment_public_key');
    }

    protected function privateKey(): ?string
    {
        return $this->setting('wompi_private_key') ?: $this->setting('payment_secret_key');
    }

    protected function integritySecret(): ?string
    {
        return $this->setting('wompi_integrity_secret');
    }

    protected function eventsSecret(): ?string
    {
        return $this->setting('wompi_events_key') ?: $this->setting('payment_webhook_secret');
    }

    protected function apiBaseUrl(): string
    {
        return $this->setting('wompi_environment') === 'production'
            ? 'https://production.wompi.co'
            : 'https://sandbox.wompi.co';
    }

    protected function setting(string $key): ?string
    {
        $value = PlatformSetting::where('key', $key)->value('value');

        return filled($value) ? (string) $value : null;
    }
}

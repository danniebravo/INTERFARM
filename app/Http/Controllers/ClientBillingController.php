<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentMethod;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\WompiPaymentService;
use App\Support\EscapesLikeSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ClientBillingController extends Controller
{
    use EscapesLikeSearch;

    public function index()
    {
        return redirect()->route('client.billing.invoices');
    }

    public function invoices(Request $request)
    {
        $user = $this->billingUser();
        $user->load('subscriptionPlan');

        $invoicesReady = Schema::hasTable('subscription_invoices');

        $invoices = collect();

        if ($invoicesReady) {
            $invoices = SubscriptionInvoice::with('plan')
                ->where('user_id', $user->id)
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
                ->when($request->filled('q'), function ($query) use ($request) {
                    $search = trim($request->q);

                    $this->whereLikeAny($query, ['invoice_number', 'currency'], $search);
                })
                ->latest('due_date')
                ->get();
        }

        $nextInvoice = $invoices
            ->whereIn('status', [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_OVERDUE])
            ->sortBy('due_date')
            ->first();

        $nextChargeDate = $nextInvoice?->due_date ?: $this->nextChargeDate($user);

        return view('billing.invoices', compact(
            'user',
            'invoices',
            'nextInvoice',
            'nextChargeDate',
            'invoicesReady'
        ));
    }

    public function payments(Request $request)
    {
        $user = $this->billingUser();
        $user->load('subscriptionPlan');

        $paymentsReady = Schema::hasTable('subscription_payments');
        $payments = $paymentsReady
            ? SubscriptionPayment::with(['plan', 'invoice'])
                ->where('user_id', $user->id)
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
                ->when($request->filled('q'), function ($query) use ($request) {
                    $search = trim($request->q);

                    $this->whereLikeAny($query, ['reference', 'payment_method', 'currency'], $search);
                })
                ->latest('paid_at')
                ->get()
            : collect();

        return view('billing.payments', compact(
            'user',
            'payments',
            'paymentsReady'
        ));
    }

    public function showInvoice(SubscriptionInvoice $invoice)
    {
        $user = $this->billingUser();

        abort_unless((int) $invoice->user_id === (int) $user->id, 403);

        $invoice->load('plan');

        $invoice->setRelation(
            'payments',
            Schema::hasColumn('subscription_payments', 'subscription_invoice_id')
                ? $invoice->payments()->latest('paid_at')->get()
                : collect()
        );
        $user->load('subscriptionPlan');

        return view('billing.invoice-show', compact('invoice', 'user'));
    }

    public function downloadInvoice(SubscriptionInvoice $invoice, InvoicePdfService $pdfService)
    {
        abort_unless((int) $invoice->user_id === (int) $this->billingUser()->id, 403);

        return response($pdfService->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $invoice->invoice_number . '.pdf"',
        ]);
    }

    public function checkoutInvoice(SubscriptionInvoice $invoice, WompiPaymentService $wompi)
    {
        $user = $this->billingUser();

        abort_unless((int) $invoice->user_id === (int) $user->id, 403);

        if (! Schema::hasTable('subscription_payments')) {
            return back()->with('error', 'Primero debes ejecutar la migración de pagos.');
        }

        if (! in_array($invoice->status, [SubscriptionInvoice::STATUS_PENDING, SubscriptionInvoice::STATUS_OVERDUE], true)) {
            return back()->with('success', 'Esta factura ya no tiene saldo pendiente.');
        }

        try {
            $checkout = $wompi->createCheckout($invoice, $user);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return redirect()->away($checkout['url']);
    }

    public function paymentResponse(Request $request, WompiPaymentService $wompi)
    {
        $transactionId = $request->query('id');
        $reference = $request->query('reference');

        if ($transactionId) {
            $transaction = $wompi->transaction($transactionId);

            if ($transaction) {
                $payment = $wompi->applyTransaction($transaction, 'redirect');

                if ($payment?->invoice) {
                    return redirect()
                        ->route('client.billing.invoices.show', $payment->invoice)
                        ->with($payment->status === 'paid' ? 'success' : 'error', $payment->status === 'paid'
                            ? 'Pago confirmado correctamente.'
                            : 'El pago todavía no fue aprobado por la pasarela.');
                }
            }
        }

        if ($reference) {
            $payment = SubscriptionPayment::with('invoice')
                ->where('user_id', $this->billingUser()->id)
                ->where('reference', $reference)
                ->first();

            if ($payment?->invoice) {
                return redirect()
                    ->route('client.billing.invoices.show', $payment->invoice)
                    ->with('success', 'Recibimos la respuesta de Wompi. La confirmación final llegará por la pasarela.');
            }
        }

        return redirect()
            ->route('client.billing.invoices')
            ->with('success', 'Recibimos la respuesta de Wompi. Revisa el estado de tu factura en unos segundos.');
    }

    public function wompiWebhook(Request $request, WompiPaymentService $wompi)
    {
        $payload = $request->json()->all();

        if (! $wompi->validEvent($payload, $request->header('X-Event-Checksum'))) {
            return response()->json(['message' => 'Invalid checksum'], 403);
        }

        if (($payload['event'] ?? null) === 'transaction.updated') {
            $transaction = data_get($payload, 'data.transaction', []);

            if (is_array($transaction)) {
                $wompi->applyTransaction($transaction, 'webhook');
            }
        }

        return response()->json(['ok' => true]);
    }

    public function paymentMethods()
    {
        $user = $this->billingUser();
        $user->load('subscriptionPlan');

        $paymentMethodsReady = Schema::hasTable('subscription_payment_methods');
        $paymentMethods = $paymentMethodsReady
            ? SubscriptionPaymentMethod::where('user_id', $user->id)
                ->where('status', SubscriptionPaymentMethod::STATUS_ACTIVE)
                ->latest('is_default')
                ->latest()
                ->get()
            : collect();

        return view('billing.payment-methods', compact(
            'user',
            'paymentMethods',
            'paymentMethodsReady'
        ));
    }

    public function storePaymentMethod(Request $request)
    {
        if (! Schema::hasTable('subscription_payment_methods')) {
            return back()->with('error', 'Primero debes ejecutar la migración de métodos de pago.');
        }

        $data = $request->validate([
            'holder_name' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:40'],
            'last_four' => ['required', 'digits:4'],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'min:' . now()->year, 'max:' . (now()->year + 25)],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $user = $this->billingUser();
        $makeDefault = (bool) ($data['is_default'] ?? false)
            || ! SubscriptionPaymentMethod::where('user_id', $user->id)
                ->where('status', SubscriptionPaymentMethod::STATUS_ACTIVE)
                ->exists();

        if ($makeDefault) {
            SubscriptionPaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        SubscriptionPaymentMethod::create([
            ...$data,
            'user_id' => $user->id,
            'provider' => 'wompi',
            'is_default' => $makeDefault,
            'status' => SubscriptionPaymentMethod::STATUS_ACTIVE,
        ]);

        return back()->with('success', 'Forma de pago vinculada correctamente.');
    }

    public function defaultPaymentMethod(SubscriptionPaymentMethod $paymentMethod)
    {
        $user = $this->billingUser();

        abort_unless((int) $paymentMethod->user_id === (int) $user->id, 403);

        SubscriptionPaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true, 'status' => SubscriptionPaymentMethod::STATUS_ACTIVE]);

        return back()->with('success', 'Forma de pago principal actualizada.');
    }

    public function destroyPaymentMethod(SubscriptionPaymentMethod $paymentMethod)
    {
        abort_unless((int) $paymentMethod->user_id === (int) $this->billingUser()->id, 403);

        $paymentMethod->update([
            'status' => SubscriptionPaymentMethod::STATUS_INACTIVE,
            'is_default' => false,
        ]);

        return back()->with('success', 'Forma de pago desactivada.');
    }

    protected function billingUser(): User
    {
        $user = auth()->user();

        if ($user?->canAccessAdminPanel() && session('admin_view_client_id')) {
            $client = User::find(session('admin_view_client_id'));

            if ($client) {
                return $client;
            }
        }

        return $user;
    }

    protected function nextChargeDate($user): ?Carbon
    {
        $plan = $user->subscriptionPlan;

        if (! $plan || $plan->billing_period === 'one_time') {
            return null;
        }

        if ($user->next_billing_date) {
            return $user->next_billing_date->copy();
        }

        if ($user->trial_ends_at && $user->trial_ends_at->isFuture()) {
            return $user->trial_ends_at->copy();
        }

        $date = ($user->trial_ends_at ?: $user->created_at ?: now())->copy();
        $months = $plan->billing_period === 'yearly' ? 12 : 1;

        while ($date->isPast()) {
            $date->addMonthsNoOverflow($months);
        }

        return $date;
    }
}

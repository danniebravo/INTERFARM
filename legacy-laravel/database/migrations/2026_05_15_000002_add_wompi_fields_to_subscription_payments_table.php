<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_payments')) {
            return;
        }

        Schema::table('subscription_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_payments', 'subscription_invoice_id')) {
                $table->foreignId('subscription_invoice_id')
                    ->nullable()
                    ->after('subscription_plan_id')
                    ->constrained('subscription_invoices')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('subscription_payments', 'provider')) {
                $table->string('provider', 80)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('subscription_payments', 'provider_transaction_id')) {
                $table->string('provider_transaction_id', 180)->nullable()->after('provider');
            }

            if (! Schema::hasColumn('subscription_payments', 'provider_status')) {
                $table->string('provider_status', 80)->nullable()->after('provider_transaction_id');
            }

            if (! Schema::hasColumn('subscription_payments', 'provider_payload')) {
                $table->json('provider_payload')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_payments')) {
            return;
        }

        Schema::table('subscription_payments', function (Blueprint $table) {
            foreach (['provider_payload', 'provider_status', 'provider_transaction_id', 'provider'] as $column) {
                if (Schema::hasColumn('subscription_payments', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('subscription_payments', 'subscription_invoice_id')) {
                $table->dropConstrainedForeignId('subscription_invoice_id');
            }
        });
    }
};

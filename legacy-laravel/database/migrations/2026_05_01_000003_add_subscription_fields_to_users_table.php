<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'subscription_plan_id')) {
                $table->foreignId('subscription_plan_id')
                    ->nullable()
                    ->after('status')
                    ->constrained('subscription_plans')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'billing_status')) {
                $table->string('billing_status', 40)
                    ->default('trial')
                    ->after('subscription_plan_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'subscription_plan_id')) {
                $table->dropConstrainedForeignId('subscription_plan_id');
            }

            if (Schema::hasColumn('users', 'billing_status')) {
                $table->dropColumn('billing_status');
            }
        });
    }
};

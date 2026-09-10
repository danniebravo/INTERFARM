<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        Schema::table('financial_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_transactions', 'milk_price_per_liter')) {
                $table->decimal('milk_price_per_liter', 12, 2)->nullable()->after('milk_liters_sold');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        Schema::table('financial_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('financial_transactions', 'milk_price_per_liter')) {
                $table->dropColumn('milk_price_per_liter');
            }
        });
    }
};

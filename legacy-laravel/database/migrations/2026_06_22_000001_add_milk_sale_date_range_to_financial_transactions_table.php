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

        $afterColumn = Schema::hasColumn('financial_transactions', 'milk_price_per_liter')
            ? 'milk_price_per_liter'
            : (Schema::hasColumn('financial_transactions', 'milk_liters_sold') ? 'milk_liters_sold' : 'amount');

        Schema::table('financial_transactions', function (Blueprint $table) use ($afterColumn) {
            if (! Schema::hasColumn('financial_transactions', 'milk_sale_start_date')) {
                $table->date('milk_sale_start_date')->nullable()->after($afterColumn);
            }

            if (! Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
                $table->date('milk_sale_end_date')->nullable()->after('milk_sale_start_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        Schema::table('financial_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
                $table->dropColumn('milk_sale_end_date');
            }

            if (Schema::hasColumn('financial_transactions', 'milk_sale_start_date')) {
                $table->dropColumn('milk_sale_start_date');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
                $table->decimal('milk_liters_sold', 10, 2)->nullable()->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('financial_transactions', 'milk_liters_sold')) {
                $table->dropColumn('milk_liters_sold');
            }
        });
    }
};

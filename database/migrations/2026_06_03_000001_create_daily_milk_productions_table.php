<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_milk_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->date('production_date');
            $table->decimal('liters', 10, 2)->default(0);
            $table->decimal('price_per_liter', 12, 2)->default(0);
            $table->decimal('total_income', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'production_date'], 'daily_milk_productions_farm_date_unique');
            $table->index(['farm_id', 'production_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_milk_productions');
    }
};

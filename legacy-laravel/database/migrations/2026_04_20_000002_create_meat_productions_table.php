<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meat_productions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('animal_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('production_date');

            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->decimal('weight_gain_kg', 10, 2)->nullable();

            $table->decimal('price_per_kg', 12, 2)->default(0);
            $table->decimal('estimated_total', 14, 2)->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['farm_id', 'production_date']);
            $table->index(['animal_id', 'production_date']);
            $table->index(['farm_id', 'animal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meat_productions');
    }
};
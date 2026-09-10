<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('animal_id')->nullable()->constrained()->nullOnDelete();

            $table->date('production_date');
            $table->enum('shift', ['mañana', 'tarde'])->nullable();

            $table->decimal('liters', 10, 2)->default(0);
            $table->decimal('price_per_liter', 12, 2)->default(0);
            $table->decimal('total_value', 14, 2)->default(0);

            $table->decimal('weight_kg', 10, 2)->nullable();
            $table->string('feeding_type')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['farm_id', 'production_date']);
            $table->index(['animal_id', 'production_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_productions');
    }
};
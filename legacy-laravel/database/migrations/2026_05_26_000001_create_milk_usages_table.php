<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milk_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->decimal('calf_liters', 10, 2)->default(0);
            $table->decimal('consumed_liters', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['farm_id', 'usage_date']);
            $table->index(['farm_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milk_usages');
    }
};

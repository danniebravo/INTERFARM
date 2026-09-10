<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('title');
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');
            $table->string('category')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['farm_id', 'transaction_date']);
            $table->index(['farm_id', 'type']);
            $table->index(['farm_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};

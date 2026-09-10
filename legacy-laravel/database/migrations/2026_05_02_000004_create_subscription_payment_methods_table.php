<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_payment_methods')) {
            Schema::create('subscription_payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider')->default('wompi');
                $table->string('provider_token')->nullable();
                $table->string('holder_name')->nullable();
                $table->string('brand', 40)->nullable();
                $table->string('last_four', 4);
                $table->unsignedTinyInteger('expiry_month')->nullable();
                $table->unsignedSmallInteger('expiry_year')->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('status', 30)->default('active');
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_methods');
    }
};

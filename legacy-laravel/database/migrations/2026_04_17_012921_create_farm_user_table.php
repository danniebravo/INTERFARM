<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('farm_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('role')->default('owner');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_user');
    }
};
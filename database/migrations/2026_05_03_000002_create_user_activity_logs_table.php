<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activity_logs')) {
            Schema::create('user_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('action', 120);
                $table->string('description', 255);
                $table->string('route_name', 160)->nullable();
                $table->string('method', 10)->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farm_notifications')) {
            Schema::create('farm_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('farm_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
                $table->string('source_type', 80)->nullable();
                $table->string('source_key', 120)->nullable();
                $table->string('level', 30)->default('medium');
                $table->string('title');
                $table->text('message');
                $table->date('event_date')->nullable();
                $table->string('lot_name')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('scheduled_for')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'source_type', 'source_key'], 'farm_notifications_unique_source');
                $table->index(['user_id', 'read_at']);
                $table->index(['farm_id', 'scheduled_for']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_notifications');
    }
};

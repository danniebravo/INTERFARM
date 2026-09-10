<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_push_subscriptions')) {
            Schema::create('web_push_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('endpoint');
                $table->string('endpoint_hash', 64);
                $table->text('public_key')->nullable();
                $table->text('auth_token')->nullable();
                $table->string('content_encoding', 32)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique('endpoint_hash', 'web_push_subscriptions_endpoint_hash_unique');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_push_subscriptions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_farm_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('last_farm_id')
                    ->nullable()
                    ->after('last_login_at')
                    ->constrained('farms')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_farm_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('last_farm_id');
            });
        }
    }
};

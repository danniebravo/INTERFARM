<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('farm_notifications', 'dismissed_at')) {
                $table->timestamp('dismissed_at')->nullable()->after('read_at');
            }

            if (! Schema::hasColumn('farm_notifications', 'dismissed_until')) {
                $table->timestamp('dismissed_until')->nullable()->after('dismissed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('farm_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('farm_notifications', 'dismissed_until')) {
                $table->dropColumn('dismissed_until');
            }

            if (Schema::hasColumn('farm_notifications', 'dismissed_at')) {
                $table->dropColumn('dismissed_at');
            }
        });
    }
};

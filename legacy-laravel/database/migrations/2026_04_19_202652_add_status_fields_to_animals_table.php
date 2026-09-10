<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (!Schema::hasColumn('animals', 'status')) {
                $table->string('status', 30)->default('activo')->after('last_weight_date');
            }

            if (!Schema::hasColumn('animals', 'status_date')) {
                $table->date('status_date')->nullable()->after('status');
            }

            if (!Schema::hasColumn('animals', 'status_notes')) {
                $table->text('status_notes')->nullable()->after('status_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (Schema::hasColumn('animals', 'status_notes')) {
                $table->dropColumn('status_notes');
            }

            if (Schema::hasColumn('animals', 'status_date')) {
                $table->dropColumn('status_date');
            }
        });
    }
};
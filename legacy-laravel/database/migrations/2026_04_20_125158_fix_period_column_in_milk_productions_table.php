<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('milk_productions', 'shift') && ! Schema::hasColumn('milk_productions', 'period')) {
            Schema::table('milk_productions', function (Blueprint $table) {
                $table->renameColumn('shift', 'period');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('milk_productions', 'period') && ! Schema::hasColumn('milk_productions', 'shift')) {
            Schema::table('milk_productions', function (Blueprint $table) {
                $table->renameColumn('period', 'shift');
            });
        }
    }
};
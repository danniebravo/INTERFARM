<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milk_productions', function (Blueprint $table) {
            if (Schema::hasColumn('milk_productions', 'shift') && ! Schema::hasColumn('milk_productions', 'period')) {
                $table->renameColumn('shift', 'period');
            }
        });
    }

    public function down(): void
    {
        Schema::table('milk_productions', function (Blueprint $table) {
            if (Schema::hasColumn('milk_productions', 'period') && ! Schema::hasColumn('milk_productions', 'shift')) {
                $table->renameColumn('period', 'shift');
            }
        });
    }
};
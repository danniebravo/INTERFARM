<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (! Schema::hasColumn('animals', 'service_type')) {
                $table->string('service_type', 30)->nullable()->after('pregnancy_sire_name_manual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (Schema::hasColumn('animals', 'service_type')) {
                $table->dropColumn('service_type');
            }
        });
    }
};
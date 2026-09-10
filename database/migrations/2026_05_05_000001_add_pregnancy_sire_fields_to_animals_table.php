<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (! Schema::hasColumn('animals', 'pregnancy_sire_id')) {
                $table->foreignId('pregnancy_sire_id')
                    ->nullable()
                    ->after('pregnancy_date')
                    ->constrained('animals')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('animals', 'pregnancy_sire_name_manual')) {
                $table->string('pregnancy_sire_name_manual')
                    ->nullable()
                    ->after('pregnancy_sire_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (Schema::hasColumn('animals', 'pregnancy_sire_id')) {
                $table->dropConstrainedForeignId('pregnancy_sire_id');
            }

            if (Schema::hasColumn('animals', 'pregnancy_sire_name_manual')) {
                $table->dropColumn('pregnancy_sire_name_manual');
            }
        });
    }
};

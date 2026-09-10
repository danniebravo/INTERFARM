<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            if (!Schema::hasColumn('animals', 'has_calved_before')) {
                $table->string('has_calved_before')->nullable();
            }

            if (!Schema::hasColumn('animals', 'is_pregnant')) {
                $table->string('is_pregnant')->nullable();
            }

            if (!Schema::hasColumn('animals', 'pregnancy_date')) {
                $table->date('pregnancy_date')->nullable();
            }

            if (!Schema::hasColumn('animals', 'last_calving_date')) {
                $table->date('last_calving_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('animals', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'has_calved_before',
                'is_pregnant',
                'pregnancy_date',
                'last_calving_date',
            ] as $column) {
                if (Schema::hasColumn('animals', $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lots')
            ->where('code', '')
            ->update(['code' => null]);

        DB::table('lots')
            ->select('farm_id', 'code')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->groupBy('farm_id', 'code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate) {
                DB::table('lots')
                    ->where('farm_id', $duplicate->farm_id)
                    ->where('code', $duplicate->code)
                    ->orderBy('id')
                    ->get(['id', 'code'])
                    ->skip(1)
                    ->each(function ($lot) {
                        DB::table('lots')
                            ->where('id', $lot->id)
                            ->update(['code' => substr($lot->code . '-' . $lot->id, 0, 100)]);
                    });
            });

        Schema::table('lots', function (Blueprint $table) {
            $table->unique(['farm_id', 'code'], 'lots_farm_id_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropUnique('lots_farm_id_code_unique');
        });
    }
};

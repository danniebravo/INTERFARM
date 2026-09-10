<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_transactions')
            || ! Schema::hasColumn('financial_transactions', 'milk_liters_sold')
            || ! Schema::hasColumn('financial_transactions', 'milk_sale_start_date')
            || ! Schema::hasColumn('financial_transactions', 'milk_sale_end_date')) {
            return;
        }

        $months = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        DB::table('financial_transactions')
            ->where('type', 'income')
            ->where('milk_liters_sold', '>', 0)
            ->whereNull('milk_sale_start_date')
            ->whereNull('milk_sale_end_date')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($transaction) use ($months) {
                $title = mb_strtolower((string) $transaction->title);

                if (! preg_match('/(\d{1,2})\s+al\s+(\d{1,2})\s+de\s+([a-záéíóúñ]+)\s+de\s+(\d{4})/u', $title, $matches)) {
                    return;
                }

                $month = $months[$matches[3]] ?? null;

                if (! $month) {
                    return;
                }

                $year = (int) $matches[4];
                $startDay = (int) $matches[1];
                $endDay = (int) $matches[2];

                try {
                    $startDate = CarbonImmutable::create($year, $month, $startDay)->startOfDay();
                    $endDate = CarbonImmutable::create($year, $month, $endDay)->startOfDay();
                } catch (Throwable) {
                    return;
                }

                if ($startDate->greaterThan($endDate)) {
                    return;
                }

                DB::table('financial_transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'milk_sale_start_date' => $startDate->toDateString(),
                        'milk_sale_end_date' => $endDate->toDateString(),
                    ]);
            });
    }

    public function down(): void
    {
        // Historical backfill only. The date ranges are valid data, so rollback leaves them intact.
    }
};

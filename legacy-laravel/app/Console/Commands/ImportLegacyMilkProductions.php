<?php

namespace App\Console\Commands;

use App\Models\Animal;
use App\Models\MilkProduction;
use Illuminate\Console\Command;

class ImportLegacyMilkProductions extends Command
{
    protected $signature = 'interfarm:import-legacy-milk';
    protected $description = 'Importa producciones antiguas guardadas en notes/meta hacia milk_productions';

    public function handle(): int
    {
        $animals = Animal::all();
        $imported = 0;

        foreach ($animals as $animal) {
            $notes = $animal->notes ?? '';
            $meta = [];

            if ($notes && preg_match('/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s', $notes, $matches)) {
                $decoded = json_decode(trim($matches[1]), true);
                $meta = is_array($decoded) ? $decoded : [];
            }

            $productions = collect($meta['productions'] ?? []);

            foreach ($productions as $record) {
                $date = $record['date'] ?? null;
                $period = $record['period'] ?? null;
                $liters = isset($record['liters']) ? (float) $record['liters'] : null;

                if (! $date || $liters === null || $liters <= 0) {
                    continue;
                }

                $exists = MilkProduction::where('farm_id', $animal->farm_id)
                    ->where('animal_id', $animal->id)
                    ->whereDate('production_date', $date)
                    ->where('period', $period)
                    ->exists();

                if ($exists) {
                    continue;
                }

                MilkProduction::create([
                    'farm_id' => $animal->farm_id,
                    'animal_id' => $animal->id,
                    'production_date' => $date,
                    'period' => $period,
                    'liters' => $liters,
                    'notes' => $record['notes'] ?? null,
                ]);

                $imported++;
            }
        }

        $this->info("Registros importados: {$imported}");

        return self::SUCCESS;
    }
}

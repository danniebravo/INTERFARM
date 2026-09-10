<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Event;
use App\Models\FinancialTransaction;
use App\Models\Lot;
use App\Models\MeatProduction;
use App\Models\MilkProduction;
use App\Support\EscapesLikeSearch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ReportsController extends Controller
{
    use EscapesLikeSearch;

    public function index(Request $request)
    {
        $farm = $request->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $filters = $this->resolveFilters($request);

        $lots = Lot::where('farm_id', $farm->id)
            ->orderBy('name')
            ->get();

        $animalsForFilter = Animal::where('farm_id', $farm->id)
            ->orderBy('name')
            ->get();

        $animalsQuery = Animal::query()
            ->with('lot')
            ->where('farm_id', $farm->id)
            ->when($filters['animal_id'], fn ($query) => $query->where('id', $filters['animal_id']))
            ->when($filters['lot_id'], fn ($query) => $query->where('lot_id', $filters['lot_id']))
            ->when($filters['sex'], fn ($query) => $query->where('sex', $filters['sex']))
            ->when($filters['purpose'], fn ($query) => $query->where('purpose', $filters['purpose']))
            ->when($filters['status'], function ($query) use ($filters) {
                if ($filters['status'] === 'activo') {
                    $query->where(function ($subQuery) {
                        $subQuery->whereNull('status')
                            ->orWhereNotIn('status', [
                                Animal::STATUS_SOLD,
                                Animal::STATUS_DECEASED,
                                'sold',
                                'deceased',
                                'dead',
                            ]);
                    });
                    return;
                }

                $query->where('status', $filters['status']);
            })
            ->when($filters['search'], function ($query) use ($filters) {
                $this->whereLikeAny($query, ['name', 'internal_code', 'ear_tag', 'breed'], $filters['search']);
            });

        $animals = $animalsQuery
            ->orderBy('name')
            ->get();

        $animalIds = $animals->pluck('id')->all();

        $milkRecords = collect();
        if (Schema::hasTable('milk_productions')) {
            $milkRecords = MilkProduction::with('animal.lot')
                ->where('farm_id', $farm->id)
                ->whereBetween('production_date', [$filters['start_date'], $filters['end_date']])
                ->when($filters['animal_id'], fn ($query) => $query->where('animal_id', $filters['animal_id']))
                ->when($filters['lot_id'] || $filters['sex'] || $filters['purpose'] || $filters['status'] || $filters['search'], fn ($query) => $query->whereIn('animal_id', $animalIds ?: [0]))
                ->orderByDesc('production_date')
                ->get();
        }

        $meatRecords = collect();
        if (Schema::hasTable('meat_productions')) {
            $meatRecords = MeatProduction::with('animal.lot')
                ->where('farm_id', $farm->id)
                ->whereBetween('production_date', [$filters['start_date'], $filters['end_date']])
                ->when($filters['animal_id'], fn ($query) => $query->where('animal_id', $filters['animal_id']))
                ->when($filters['lot_id'] || $filters['sex'] || $filters['purpose'] || $filters['status'] || $filters['search'], fn ($query) => $query->whereIn('animal_id', $animalIds ?: [0]))
                ->orderByDesc('production_date')
                ->get();
        }

        $financialRecords = collect();
        if (Schema::hasTable('financial_transactions')) {
            $financialRecords = FinancialTransaction::where('farm_id', $farm->id)
                ->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']])
                ->orderByDesc('transaction_date')
                ->get();
        }

        $events = collect();
        if (Schema::hasTable('events')) {
            $events = Event::with('animal')
                ->where('farm_id', $farm->id)
                ->where(function ($query) use ($filters) {
                    $query->whereBetween('event_date', [$filters['start_date'], $filters['end_date']])
                        ->orWhereBetween('start_datetime', [
                            Carbon::parse($filters['start_date'])->startOfDay(),
                            Carbon::parse($filters['end_date'])->endOfDay(),
                        ]);
                })
                ->when($filters['animal_id'], fn ($query) => $query->where('animal_id', $filters['animal_id']))
                ->when($filters['lot_id'] || $filters['sex'] || $filters['purpose'] || $filters['status'] || $filters['search'], fn ($query) => $query->whereIn('animal_id', $animalIds ?: [0]))
                ->orderByRaw('COALESCE(start_datetime, event_date) desc')
                ->get();
        }

        $summary = [
            'animals' => $animals->count(),
            'active_animals' => $animals->filter(fn ($animal) => $animal->isActive())->count(),
            'female_animals' => $animals->filter(fn ($animal) => $animal->isFemale())->count(),
            'male_animals' => $animals->filter(fn ($animal) => $animal->isMale())->count(),
            'milk_liters' => round((float) $milkRecords->sum('liters'), 2),
            'meat_weight' => round((float) $meatRecords->sum(fn ($record) => (float) ($record->weight_gain_kg ?: $record->weight_kg)), 2),
            'income' => round((float) $financialRecords->whereIn('type', [FinancialTransaction::TYPE_INCOME, 'ingreso'])->sum('amount'), 2),
            'expense' => round((float) $financialRecords->whereIn('type', [FinancialTransaction::TYPE_EXPENSE, 'gasto'])->sum('amount'), 2),
            'events' => $events->count(),
        ];
        $summary['balance'] = $summary['income'] - $summary['expense'];

        $animalsByLot = $animals
            ->groupBy(fn ($animal) => $animal->lot?->name ?: 'Sin lote')
            ->map(fn ($items, $lotName) => [
                'lot' => $lotName,
                'total' => $items->count(),
                'females' => $items->filter(fn ($animal) => $animal->isFemale())->count(),
                'males' => $items->filter(fn ($animal) => $animal->isMale())->count(),
            ])
            ->sortByDesc('total')
            ->values();

        $productionByAnimal = $milkRecords
            ->map(fn ($record) => [
                'animal_id' => $record->animal_id,
                'animal' => $record->animal,
                'milk' => (float) $record->liters,
                'meat' => 0,
            ])
            ->concat($meatRecords->map(fn ($record) => [
                'animal_id' => $record->animal_id,
                'animal' => $record->animal,
                'milk' => 0,
                'meat' => (float) ($record->weight_gain_kg ?: $record->weight_kg),
            ]))
            ->groupBy('animal_id')
            ->map(function ($items) {
                $first = collect($items)->first();
                return [
                    'animal' => $first['animal'] ?? null,
                    'milk' => round(collect($items)->sum('milk'), 2),
                    'meat' => round(collect($items)->sum('meat'), 2),
                ];
            })
            ->sortByDesc(fn ($row) => $row['milk'] + $row['meat'])
            ->values();

        $productionChart = $milkRecords
            ->map(fn ($record) => [
                'date' => optional($record->production_date)->format('Y-m-d'),
                'milk' => (float) $record->liters,
                'meat' => 0,
            ])
            ->concat($meatRecords->map(fn ($record) => [
                'date' => optional($record->production_date)->format('Y-m-d'),
                'milk' => 0,
                'meat' => (float) ($record->weight_gain_kg ?: $record->weight_kg),
            ]))
            ->filter(fn ($row) => ! empty($row['date']))
            ->groupBy('date')
            ->map(fn ($items, $date) => [
                'date' => $date,
                'label' => Carbon::parse($date)->format('d/m'),
                'milk' => round((float) $items->sum('milk'), 2),
                'meat' => round((float) $items->sum('meat'), 2),
            ])
            ->sortBy('date')
            ->take(-12)
            ->values();

        $financeChart = $financialRecords
            ->groupBy(fn ($record) => optional($record->transaction_date)->format('Y-m-d'))
            ->filter(fn ($items, $date) => ! empty($date))
            ->map(fn ($items, $date) => [
                'date' => $date,
                'label' => Carbon::parse($date)->format('d/m'),
                'income' => round((float) $items->whereIn('type', [FinancialTransaction::TYPE_INCOME, 'ingreso'])->sum('amount'), 2),
                'expense' => round((float) $items->whereIn('type', [FinancialTransaction::TYPE_EXPENSE, 'gasto'])->sum('amount'), 2),
            ])
            ->sortBy('date')
            ->take(-12)
            ->values();

        $statusChart = collect([
            ['label' => 'Activos', 'value' => $summary['active_animals'], 'color' => '#16a34a'],
            ['label' => 'Vendidos', 'value' => $animals->filter(fn ($animal) => $animal->isSold())->count(), 'color' => '#f59e0b'],
            ['label' => 'Fallecidos', 'value' => $animals->filter(fn ($animal) => $animal->isDeceased())->count(), 'color' => '#dc2626'],
        ]);

        $chartData = [
            'production' => $productionChart,
            'finance' => $financeChart,
            'status' => $statusChart,
        ];

        // Reportes de reproducción y crías (estado actual del hato)
        $reproRoster = Animal::query()
            ->where('farm_id', $farm->id)
            ->with(['dam', 'pregnancySire'])
            ->orderBy('name')
            ->get();

        $lactationReport = $reproRoster
            ->filter(fn ($animal) => $animal->sex === 'hembra' && $animal->last_calving_date && $animal->isActive())
            ->map(fn ($animal) => [
                'animal' => $animal,
                'calving_date' => $animal->last_calving_date,
                'milk_time' => $this->humanSpan($animal->last_calving_date, $animal->dry_off_date ?: now()),
                'dried' => (bool) $animal->dry_off_date,
            ])
            ->values();

        $pregnancyReport = $reproRoster
            ->filter(fn ($animal) => $animal->sex === 'hembra' && $animal->is_pregnant === 'si' && $animal->isActive())
            ->map(function ($animal) {
                $sire = $animal->pregnancySire?->name ?: ($animal->pregnancy_sire_name_manual ?: null);
                $type = $this->reproServiceLabel($animal->service_type);

                return [
                    'animal' => $animal,
                    'service_date' => $animal->pregnancy_date,
                    'how' => $type ? ($type . ($sire ? ' — ' . $sire : '')) : ($sire ?: '—'),
                    'dry_off_date' => $animal->dry_off_date ?: ($animal->pregnancy_date ? $animal->pregnancy_date->copy()->addMonths(7) : null),
                    'dry_off_confirmed' => (bool) $animal->dry_off_date,
                ];
            })
            ->values();

        $offspringReport = $reproRoster
            ->filter(fn ($animal) => $animal->dam_id || $animal->dam_name_manual)
            ->map(fn ($animal) => [
                'animal' => $animal,
                'birth_date' => $animal->birth_date,
                'dam' => $animal->dam?->name ?: ($animal->dam_name_manual ?: '—'),
                'age' => $animal->ageHuman() ?: '—',
            ])
            ->values();

        return view('reports.index', compact(
            'farm',
            'filters',
            'lots',
            'animalsForFilter',
            'animals',
            'milkRecords',
            'meatRecords',
            'financialRecords',
            'events',
            'summary',
            'animalsByLot',
            'productionByAnimal',
            'chartData',
            'lactationReport',
            'pregnancyReport',
            'offspringReport'
        ));
    }

    public function export(Request $request, string $module)
    {
        $farm = $request->user()->currentFarm();

        if (! $farm) {
            return redirect()->route('farms.create');
        }

        $module = strtolower($module);
        $allowedModules = ['inventario', 'lotes', 'produccion', 'finanzas', 'eventos', 'lactancia', 'prenez', 'crias', 'todo'];

        if (! in_array($module, $allowedModules, true)) {
            abort(404);
        }

        $filters = $this->resolveFilters($request);
        $animals = $this->filteredAnimals($farm->id, $filters);
        $animalIds = $animals->pluck('id')->all();
        if (in_array(strtolower((string) $request->get('format')), ['xlsx', 'excel'], true)) {
            return $this->exportXlsx($module, $farm, $filters, $animals, $animalIds);
        }
        $filename = 'reporte-' . $module . '-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($module, $farm, $filters, $animals, $animalIds) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            if ($module === 'todo') {
                foreach (['inventario', 'lotes', 'produccion', 'finanzas', 'eventos', 'lactancia', 'prenez', 'crias'] as $section) {
                    fputcsv($handle, ['Módulo', $this->exportModuleLabel($section)]);
                    $this->writeExportSection($handle, $section, $farm->id, $filters, $animals, $animalIds);
                    fputcsv($handle, []);
                }
            } else {
                $this->writeExportSection($handle, $module, $farm->id, $filters, $animals, $animalIds);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function exportXlsx(string $module, $farm, array $filters, $animals, array $animalIds)
    {
        $handle = fopen('php://temp', 'r+');

        if ($module === 'todo') {
            foreach (['inventario', 'lotes', 'produccion', 'finanzas', 'eventos', 'lactancia', 'prenez', 'crias'] as $section) {
                fputcsv($handle, ['Modulo', $this->exportModuleLabel($section)]);
                $this->writeExportSection($handle, $section, $farm->id, $filters, $animals, $animalIds);
                fputcsv($handle, []);
            }
        } else {
            $this->writeExportSection($handle, $module, $farm->id, $filters, $animals, $animalIds);
        }

        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        $xlsx = $this->buildXlsx($rows);
        $filename = 'reporte-' . $module . '-' . now()->format('Y-m-d-His') . '.xlsx';

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    protected function buildXlsx(array $rows): string
    {
        $escape = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        };

        $columnLetter = function (int $index): string {
            $letter = '';
            $index++;
            while ($index > 0) {
                $mod = ($index - 1) % 26;
                $letter = chr(65 + $mod) . $letter;
                $index = intdiv($index - 1, 26);
            }
            return $letter;
        };

        $sheetRows = '';
        foreach (array_values($rows) as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $sheetRows .= '<row r="' . $rowNumber . '">';
            foreach (array_values((array) $cells) as $colIndex => $value) {
                $ref = $columnLetter($colIndex) . $rowNumber;
                if ($value === null || $value === '') {
                    $sheetRows .= '<c r="' . $ref . '"/>';
                } elseif (is_numeric($value) && preg_match('/^-?\d+(\.\d+)?$/', (string) $value)) {
                    $sheetRows .= '<c r="' . $ref . '"><v>' . $escape($value) . '</v></c>';
                } else {
                    $sheetRows .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $escape($value) . '</t></is></c>';
                }
            }
            $sheetRows .= '</row>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';

        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets></workbook>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmpFile, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rootRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $data = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $data;
    }

    protected function filteredAnimals(int $farmId, array $filters)
    {
        return Animal::query()
            ->with('lot')
            ->where('farm_id', $farmId)
            ->when($filters['animal_id'], fn ($query) => $query->where('id', $filters['animal_id']))
            ->when($filters['lot_id'], fn ($query) => $query->where('lot_id', $filters['lot_id']))
            ->when($filters['sex'], fn ($query) => $query->where('sex', $filters['sex']))
            ->when($filters['purpose'], fn ($query) => $query->where('purpose', $filters['purpose']))
            ->when($filters['status'], function ($query) use ($filters) {
                if ($filters['status'] === 'activo') {
                    $query->where(function ($subQuery) {
                        $subQuery->whereNull('status')
                            ->orWhereNotIn('status', [
                                Animal::STATUS_SOLD,
                                Animal::STATUS_DECEASED,
                                'sold',
                                'deceased',
                                'dead',
                            ]);
                    });
                    return;
                }

                $query->where('status', $filters['status']);
            })
            ->when($filters['search'], function ($query) use ($filters) {
                $this->whereLikeAny($query, ['name', 'internal_code', 'ear_tag', 'breed'], $filters['search']);
            })
            ->orderBy('name')
            ->get();
    }

    protected function writeExportSection($handle, string $module, int $farmId, array $filters, $animals, array $animalIds): void
    {
        match ($module) {
            'inventario' => $this->writeInventoryCsv($handle, $animals),
            'lotes' => $this->writeLotsCsv($handle, $farmId, $filters),
            'produccion' => $this->writeProductionCsv($handle, $farmId, $filters, $animalIds),
            'finanzas' => $this->writeFinancesCsv($handle, $farmId, $filters),
            'eventos' => $this->writeEventsCsv($handle, $farmId, $filters, $animalIds),
            'lactancia' => $this->writeLactationCsv($handle, $farmId),
            'prenez' => $this->writePregnancyCsv($handle, $farmId),
            'crias' => $this->writeOffspringCsv($handle, $farmId),
            default => null,
        };
    }

    protected function writeInventoryCsv($handle, $animals): void
    {
        fputcsv($handle, ['ID', 'Animal', 'Código interno', 'Arete', 'Lote', 'Código lote', 'Sexo', 'Propósito', 'Estado', 'Fecha nacimiento', 'Peso actual', 'Ubicación', 'Notas']);

        foreach ($animals as $animal) {
            fputcsv($handle, [
                $animal->id,
                $animal->name,
                $animal->internal_code,
                $animal->ear_tag,
                $animal->lot?->name,
                $animal->lot?->code,
                $animal->sex,
                $animal->purpose,
                $animal->statusLabel(),
                optional($animal->birth_date)->format('Y-m-d'),
                $animal->weight_current,
                $animal->location,
                method_exists($animal, 'cleanNotes') ? $animal->cleanNotes() : $animal->notes,
            ]);
        }
    }

    protected function writeLotsCsv($handle, int $farmId, array $filters): void
    {
        fputcsv($handle, ['ID', 'Código', 'Nombre', 'Tipo', 'Estado', 'Área manual', 'Área calculada', 'Animales', 'Latitud centro', 'Longitud centro', 'Descripción']);

        Lot::where('farm_id', $farmId)
            ->withCount('animals')
            ->when($filters['lot_id'], fn ($query) => $query->where('id', $filters['lot_id']))
            ->when($filters['search'], function ($query) use ($filters) {
                $this->whereLikeAny($query, ['name', 'code', 'type', 'description'], $filters['search']);
            })
            ->orderBy('name')
            ->get()
            ->each(function ($lot) use ($handle) {
                fputcsv($handle, [
                    $lot->id,
                    $lot->code,
                    $lot->name,
                    $lot->type,
                    $lot->status,
                    $lot->area_manual,
                    $lot->area_calculated,
                    $lot->animals_count,
                    $lot->center_lat,
                    $lot->center_lng,
                    $lot->description,
                ]);
            });
    }

    protected function writeProductionCsv($handle, int $farmId, array $filters, array $animalIds): void
    {
        fputcsv($handle, ['Tipo', 'Fecha', 'Animal', 'Código animal', 'Lote', 'Periodo', 'Litros', 'Peso kg', 'Ganancia kg', 'Notas']);

        if (Schema::hasTable('milk_productions')) {
            MilkProduction::with('animal.lot')
                ->where('farm_id', $farmId)
                ->whereBetween('production_date', [$filters['start_date'], $filters['end_date']])
                ->when($filters['animal_id'], fn ($query) => $query->where('animal_id', $filters['animal_id']))
                ->when($filters['lot_id'] || $filters['sex'] || $filters['purpose'] || $filters['status'] || $filters['search'], fn ($query) => $query->whereIn('animal_id', $animalIds ?: [0]))
                ->orderByDesc('production_date')
                ->get()
                ->each(function ($record) use ($handle) {
                    fputcsv($handle, [
                        'Leche',
                        optional($record->production_date)->format('Y-m-d'),
                        $record->animal?->name,
                        $record->animal?->internal_code ?: $record->animal?->ear_tag,
                        $record->animal?->lot?->name,
                        $record->period,
                        $record->liters,
                        null,
                        null,
                        $record->notes,
                    ]);
                });
        }

        if (Schema::hasTable('meat_productions')) {
            MeatProduction::with('animal.lot')
                ->where('farm_id', $farmId)
                ->whereBetween('production_date', [$filters['start_date'], $filters['end_date']])
                ->when($filters['animal_id'], fn ($query) => $query->where('animal_id', $filters['animal_id']))
                ->when($filters['lot_id'] || $filters['sex'] || $filters['purpose'] || $filters['status'] || $filters['search'], fn ($query) => $query->whereIn('animal_id', $animalIds ?: [0]))
                ->orderByDesc('production_date')
                ->get()
                ->each(function ($record) use ($handle) {
                    fputcsv($handle, [
                        'Carne',
                        optional($record->production_date)->format('Y-m-d'),
                        $record->animal?->name,
                        $record->animal?->internal_code ?: $record->animal?->ear_tag,
                        $record->animal?->lot?->name,
                        null,
                        null,
                        $record->weight_kg,
                        $record->weight_gain_kg,
                        $record->notes,
                    ]);
                });
        }
    }

    protected function writeFinancesCsv($handle, int $farmId, array $filters): void
    {
        fputcsv($handle, ['Fecha', 'Tipo', 'Nombre', 'Categoría', 'Método de pago', 'Referencia', 'Valor', 'Descripción']);

        if (! Schema::hasTable('financial_transactions')) {
            return;
        }

        FinancialTransaction::where('farm_id', $farmId)
            ->whereBetween('transaction_date', [$filters['start_date'], $filters['end_date']])
            ->orderByDesc('transaction_date')
            ->get()
            ->each(function ($record) use ($handle) {
                fputcsv($handle, [
                    optional($record->transaction_date)->format('Y-m-d'),
                    in_array($record->type, [FinancialTransaction::TYPE_INCOME, 'ingreso'], true) ? 'Ingreso' : 'Gasto',
                    $record->title,
                    $record->category,
                    $record->payment_method,
                    $record->reference,
                    $record->amount,
                    $record->description,
                ]);
            });
    }

    protected function writeEventsCsv($handle, int $farmId, array $filters, array $animalIds): void
    {
        fputcsv($handle, ['Fecha', 'Inicio', 'Fin', 'Evento', 'Animal', 'Tipo', 'Estado', 'Prioridad', 'Lote', 'Descripción']);

        if (! Schema::hasTable('events')) {
            return;
        }

        Event::with('animal')
            ->where('farm_id', $farmId)
            ->where(function ($query) use ($filters) {
                $query->whereBetween('event_date', [$filters['start_date'], $filters['end_date']])
                    ->orWhereBetween('start_datetime', [
                        Carbon::parse($filters['start_date'])->startOfDay(),
                        Carbon::parse($filters['end_date'])->endOfDay(),
                    ]);
            })
            ->when($filters['animal_id'], fn ($query) => $query->where('animal_id', $filters['animal_id']))
            ->when($filters['lot_id'] || $filters['sex'] || $filters['purpose'] || $filters['status'] || $filters['search'], fn ($query) => $query->whereIn('animal_id', $animalIds ?: [0]))
            ->orderByRaw('COALESCE(start_datetime, event_date) desc')
            ->get()
            ->each(function ($event) use ($handle) {
                fputcsv($handle, [
                    optional($event->event_date)->format('Y-m-d'),
                    optional($event->start_datetime)->format('Y-m-d H:i'),
                    optional($event->end_datetime)->format('Y-m-d H:i'),
                    $event->title,
                    $event->animal?->name,
                    $event->type,
                    $event->status,
                    $event->priority,
                    $event->lot_name,
                    $event->description,
                ]);
            });
    }

    protected function humanSpan(?Carbon $from, ?Carbon $to): string
    {
        if (! $from || ! $to) {
            return '—';
        }

        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        if ($from->greaterThan($to)) {
            return '—';
        }

        $months = (int) $from->diffInMonths($to);
        $days = (int) round($from->copy()->addMonths($months)->diffInDays($to));

        $parts = [];
        if ($months > 0) {
            $parts[] = $months . ' ' . ($months === 1 ? 'mes' : 'meses');
        }
        $parts[] = $days . ' ' . ($days === 1 ? 'día' : 'días');

        return implode(' y ', $parts);
    }

    protected function reproServiceLabel(?string $type): ?string
    {
        return match ($type) {
            'monta_natural' => 'Monta natural (toro)',
            'inseminacion' => 'Inseminación (pajilla)',
            'embrion' => 'Transferencia de embrión',
            default => null,
        };
    }

    protected function writeLactationCsv($handle, int $farmId): void
    {
        fputcsv($handle, ['Animal', 'Arete/Código', 'Fecha de parto', 'Tiempo dando leche', 'Estado']);

        Animal::where('farm_id', $farmId)->where('sex', 'hembra')->whereNotNull('last_calving_date')->orderBy('name')->get()
            ->filter(fn ($animal) => $animal->isActive())
            ->each(function ($animal) use ($handle) {
                fputcsv($handle, [
                    $animal->name ?: ('Animal #' . $animal->id),
                    $animal->ear_tag ?: $animal->internal_code,
                    optional($animal->last_calving_date)->format('Y-m-d'),
                    $this->humanSpan($animal->last_calving_date, $animal->dry_off_date ?: now()),
                    $animal->dry_off_date ? 'Seca' : 'En producción',
                ]);
            });
    }

    protected function writePregnancyCsv($handle, int $farmId): void
    {
        fputcsv($handle, ['Animal', 'Arete/Código', 'Fecha de preñez', 'Servicio / padre', 'Fecha de secado']);

        Animal::where('farm_id', $farmId)->where('sex', 'hembra')->where('is_pregnant', 'si')->with('pregnancySire')->orderBy('name')->get()
            ->filter(fn ($animal) => $animal->isActive())
            ->each(function ($animal) use ($handle) {
                $sire = $animal->pregnancySire?->name ?: ($animal->pregnancy_sire_name_manual ?: null);
                $type = $this->reproServiceLabel($animal->service_type);
                $dryOff = $animal->dry_off_date ?: ($animal->pregnancy_date ? $animal->pregnancy_date->copy()->addMonths(7) : null);
                fputcsv($handle, [
                    $animal->name ?: ('Animal #' . $animal->id),
                    $animal->ear_tag ?: $animal->internal_code,
                    optional($animal->pregnancy_date)->format('Y-m-d'),
                    $type ? ($type . ($sire ? ' — ' . $sire : '')) : ($sire ?: '—'),
                    ($dryOff ? $dryOff->format('Y-m-d') : '') . ($animal->dry_off_date ? ' (confirmado)' : ' (estimado)'),
                ]);
            });
    }

    protected function writeOffspringCsv($handle, int $farmId): void
    {
        fputcsv($handle, ['Cría', 'Arete/Código', 'Fecha de nacimiento', 'Madre', 'Edad']);

        Animal::where('farm_id', $farmId)->where(function ($query) {
            $query->whereNotNull('dam_id')->orWhere('dam_name_manual', '!=', '');
        })->with('dam')->orderBy('name')->get()
            ->each(function ($animal) use ($handle) {
                fputcsv($handle, [
                    $animal->name ?: ('Animal #' . $animal->id),
                    $animal->ear_tag ?: $animal->internal_code,
                    optional($animal->birth_date)->format('Y-m-d'),
                    $animal->dam?->name ?: ($animal->dam_name_manual ?: '—'),
                    $animal->ageHuman() ?: '—',
                ]);
            });
    }

    protected function exportModuleLabel(string $module): string
    {
        return [
            'inventario' => 'Inventario',
            'lotes' => 'Lotes',
            'produccion' => 'Producción',
            'finanzas' => 'Finanzas',
            'eventos' => 'Eventos',
            'lactancia' => 'Lactancia',
            'prenez' => 'Preñez',
            'crias' => 'Crías',
            'todo' => 'Todo',
        ][$module] ?? ucfirst($module);
    }

    protected function resolveFilters(Request $request): array
    {
        $start = $request->date('start_date') ?: now()->startOfMonth();
        $end = $request->date('end_date') ?: now()->endOfMonth();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'animal_id' => $request->integer('animal_id') ?: null,
            'lot_id' => $request->integer('lot_id') ?: null,
            'sex' => in_array($request->get('sex'), ['hembra', 'macho'], true) ? $request->get('sex') : null,
            'purpose' => in_array($request->get('purpose'), ['leche', 'carne', 'doble_proposito', 'crianza'], true) ? $request->get('purpose') : null,
            'status' => in_array($request->get('status'), ['activo', 'vendido', 'fallecido'], true) ? $request->get('status') : null,
            'report_type' => in_array($request->get('report_type'), ['general', 'inventario', 'produccion', 'reproduccion', 'finanzas', 'eventos'], true)
                ? $request->get('report_type')
                : 'general',
            'search' => trim((string) $request->get('search')),
        ];
    }
}
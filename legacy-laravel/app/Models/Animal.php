<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Animal extends Model
{
    public const STATUS_ACTIVE = 'activo';
    public const STATUS_SOLD = 'vendido';
    public const STATUS_DECEASED = 'fallecido';

    protected $fillable = [
        'farm_id',
        'lot_id',
        'lot_assigned_at',

        // identificación
        'internal_code',
        'ear_tag',
        'name',

        // clasificación
        'species',
        'breed',
        'sex',
        'category',
        'purpose',

        // nacimiento
        'birth_date',

        // genealogía
        'dam_id',
        'sire_id',
        'dam_name_manual',
        'sire_name_manual',

        // reproducción
        'has_calved_before',
        'is_pregnant',
        'pregnancy_date',
        'pregnancy_sire_id',
        'pregnancy_sire_name_manual',
        'service_type',
        'last_calving_date',
        'dry_off_date',
        'calving_count',

        // producción
        'weight_birth',
        'weight_current',
        'last_weight_date',

        // estado
        'status',
        'status_date',
        'status_notes',

        // manejo
        'location',

        // extra
        'notes',
        'photo',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'pregnancy_date' => 'date',
        'last_calving_date' => 'date',
        'dry_off_date' => 'date',
        'last_weight_date' => 'date',
        'status_date' => 'date',
        'lot_assigned_at' => 'datetime',
        'weight_birth' => 'decimal:2',
        'weight_current' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $animal) {
            if ($animal->isDirty('lot_id')) {
                $animal->lot_assigned_at = $animal->lot_id ? now() : null;
            }
        });

        static::saved(function (self $animal) {
            if (! $animal->wasChanged('lot_id') || ! \Schema::hasTable('animal_lot_history')) {
                return;
            }

            $now = now();

            \DB::table('animal_lot_history')
                ->where('animal_id', $animal->id)
                ->whereNull('exited_at')
                ->update(['exited_at' => $now, 'updated_at' => $now]);

            if ($animal->lot_id) {
                \DB::table('animal_lot_history')->insert([
                    'farm_id' => $animal->farm_id,
                    'animal_id' => $animal->id,
                    'lot_id' => $animal->lot_id,
                    'entered_at' => $animal->lot_assigned_at ?: $now,
                    'exited_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function dam(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'dam_id');
    }

    public function sire(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'sire_id');
    }

    public function pregnancySire(): BelongsTo
    {
        return $this->belongsTo(Animal::class, 'pregnancy_sire_id');
    }

    public function offspringFromDam(): HasMany
    {
        return $this->hasMany(Animal::class, 'dam_id');
    }

    public function offspringFromSire(): HasMany
    {
        return $this->hasMany(Animal::class, 'sire_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AnimalPhoto::class)
            ->orderByDesc('is_main')
            ->orderBy('id');
    }

    public function mainPhoto(): HasMany
    {
        return $this->hasMany(AnimalPhoto::class)
            ->where('is_main', true);
    }

    public function milkProductions(): HasMany
    {
        return $this->hasMany(MilkProduction::class)
            ->orderByDesc('production_date')
            ->orderByDesc('id');
    }

    public function meatProductions(): HasMany
    {
        return $this->hasMany(MeatProduction::class)
            ->orderByDesc('production_date')
            ->orderByDesc('id');
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Activo',
            self::STATUS_SOLD => 'Vendido',
            self::STATUS_DECEASED => 'Fallecido',
        ];
    }

    public function isFemale(): bool
    {
        return $this->sex === 'hembra';
    }

    public function isMale(): bool
    {
        return $this->sex === 'macho';
    }

    public function isActive(): bool
    {
        $status = mb_strtolower(trim((string) ($this->status ?: self::STATUS_ACTIVE)));

        return in_array($status, [self::STATUS_ACTIVE, 'active'], true);
    }

    public function isSold(): bool
    {
        $status = mb_strtolower(trim((string) $this->status));

        return in_array($status, [self::STATUS_SOLD, 'vendida', 'sold'], true);
    }

    public function isDeceased(): bool
    {
        $status = mb_strtolower(trim((string) $this->status));

        return in_array($status, [self::STATUS_DECEASED, 'muerto', 'muerta', 'deceased', 'dead'], true);
    }

    public function statusLabel(): string
    {
        $status = $this->status ?: self::STATUS_ACTIVE;

        return self::statusOptions()[$status] ?? 'Activo';
    }

    public function ageInMonths(): ?int
    {
        if (! $this->birth_date) {
            return null;
        }

        return (int) floor($this->birth_date->diffInMonths(now()));
    }

    public function ageInYears(): ?float
    {
        $months = $this->ageInMonths();

        if ($months === null) {
            return null;
        }

        return round($months / 12, 1);
    }

    public function ageHuman(): ?string
    {
        if (! $this->birth_date) {
            return null;
        }

        $birth = $this->birth_date->copy()->startOfDay();
        $now = now()->startOfDay();

        if ($birth->greaterThan($now)) {
            return '0 días';
        }

        $years = (int) $birth->diffInYears($now);
        $afterYears = $birth->copy()->addYears($years);
        $months = (int) $afterYears->diffInMonths($now);
        $afterMonths = $afterYears->copy()->addMonths($months);
        $days = (int) $afterMonths->diffInDays($now);

        $parts = [];
        if ($years > 0) {
            $parts[] = $years . ' ' . ($years === 1 ? 'año' : 'años');
        }
        if ($months > 0) {
            $parts[] = $months . ' ' . ($months === 1 ? 'mes' : 'meses');
        }
        $parts[] = $days . ' ' . ($days === 1 ? 'día' : 'días');

        $last = array_pop($parts);

        return $parts ? implode(', ', $parts) . ' y ' . $last : $last;
    }

    public function ageYearsDays(): ?string
    {
        if (! $this->birth_date) {
            return null;
        }

        $birth = $this->birth_date->copy()->startOfDay();
        $now = now()->startOfDay();

        if ($birth->greaterThan($now)) {
            return '0 días';
        }

        $years = (int) $birth->diffInYears($now);
        $days = (int) round($birth->copy()->addYears($years)->diffInDays($now));
        $totalMonths = (int) floor($birth->diffInMonths($now));

        $parts = [];
        if ($years > 0) {
            $parts[] = $years . ' ' . ($years === 1 ? 'año' : 'años');
        }
        $parts[] = $days . ' ' . ($days === 1 ? 'día' : 'días');

        return implode(' y ', $parts) . ' (' . $totalMonths . ' ' . ($totalMonths === 1 ? 'mes' : 'meses') . ')';
    }

    public function canBeDam(): bool
    {
        $months = $this->ageInMonths();

        if ($months === null || ! $this->isFemale()) {
            return false;
        }

        return $months >= 22;
    }

    public function canRegisterMilkProduction(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if (! $this->isFemale()) {
            return false;
        }

        return $this->has_calved_before === 'si'
            || (int) ($this->calving_count ?? 0) > 0
            || $this->last_calving_date !== null;
    }

    public function canRegisterProduction(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        // El peso (producción de carne) puede registrarse en cualquier animal activo,
        // incluidas hembras/terneras sin partos. La leche sigue gobernada aparte por
        // canRegisterMilkProduction().
        return true;
    }

    public function milkProductionBlockedReason(): ?string
    {
        if ($this->isSold()) {
            return 'No se puede registrar producción de leche en un animal vendido.';
        }

        if ($this->isDeceased()) {
            return 'No se puede registrar producción en un animal fallecido.';
        }

        if (! $this->isActive()) {
            return 'Solo se puede registrar producción de leche en animales activos dentro de la finca.';
        }

        if (! $this->isFemale()) {
            return 'Solo las hembras pueden registrar producción de leche.';
        }

        if (! $this->canRegisterMilkProduction()) {
            return 'Este animal aún no tiene partos registrados, por eso no puede registrar producción de leche.';
        }

        return null;
    }

    public function productionBlockedReason(): ?string
    {
        if ($this->canRegisterProduction()) {
            return null;
        }

        if ($this->isDeceased()) {
            return 'No se puede registrar producción en un animal fallecido.';
        }

        if ($this->isSold()) {
            return 'No se puede registrar producción en un animal vendido.';
        }

        if (! $this->isActive()) {
            return 'Solo se puede registrar producción en animales activos dentro de la finca.';
        }

        return 'Este animal aún no tiene partos registrados, por eso no puede registrar producción.';
    }

    public function canBeSire(): bool
    {
        $months = $this->ageInMonths();

        if ($months === null || ! $this->isMale()) {
            return false;
        }

        return $months >= 18;
    }

    public function developmentStage(): ?string
    {
        $months = $this->ageInMonths();

        if ($months === null) {
            return null;
        }

        if ($months < 12) {
            return $this->sex === 'hembra' ? 'Ternera' : 'Ternero';
        }

        if ($months < 24) {
            return $this->sex === 'hembra' ? 'Novilla' : 'Novillo';
        }

        return $this->sex === 'hembra' ? 'Vaca' : 'Toro';
    }

    public function cleanNotes(): string
    {
        if (! $this->notes) {
            return '';
        }

        return trim(
            preg_replace(
                '/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s',
                '',
                $this->notes
            )
        );
    }

    public function notesMeta(): array
    {
        if (! $this->notes) {
            return [];
        }

        if (preg_match('/<!--INTERFARM_META_START-->(.*?)<!--INTERFARM_META_END-->/s', $this->notes, $matches)) {
            $decoded = json_decode(trim($matches[1]), true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function productionRecords(): array
    {
        $meta = $this->notesMeta();

        return collect($meta['productions'] ?? [])
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    public function healthRecords(): array
    {
        $meta = $this->notesMeta();

        return collect($meta['health_records'] ?? [])
            ->sortByDesc('date')
            ->values()
            ->all();
    }
}
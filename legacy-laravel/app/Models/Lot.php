<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    protected $fillable = [
        'farm_id',
        'name',
        'code',
        'type',
        'status',
        'area_manual',
        'area_calculated',
        'polygon',
        'center_lat',
        'center_lng',
        'description',
        'notes',
    ];

    protected $casts = [
        'area_manual' => 'decimal:2',
        'area_calculated' => 'decimal:2',
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'polygon' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function displayArea(): ?string
    {
        if (!is_null($this->area_calculated) && (float) $this->area_calculated > 0) {
            return number_format((float) $this->area_calculated, 2, ',', '.') . ' m²';
        }

        if (!is_null($this->area_manual) && (float) $this->area_manual > 0) {
            return number_format((float) $this->area_manual, 2, ',', '.') . ' m²';
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'activo';
    }

    public function hasPolygon(): bool
    {
        return is_array($this->polygon) && count($this->polygon) >= 3;
    }
}
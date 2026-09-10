<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkProduction extends Model
{
    protected $fillable = [
        'farm_id',
        'animal_id',
        'production_date',
        'period',
        'liters',
        'notes',
    ];

    protected $casts = [
        'production_date' => 'date',
        'liters' => 'decimal:2',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }
}

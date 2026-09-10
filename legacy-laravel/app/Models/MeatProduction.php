<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeatProduction extends Model
{
    protected $fillable = [
        'farm_id',
        'animal_id',
        'production_date',
        'weight_kg',
        'weight_gain_kg',
        'notes',
    ];

    protected $casts = [
        'production_date' => 'date',
        'weight_kg' => 'decimal:2',
        'weight_gain_kg' => 'decimal:2',
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

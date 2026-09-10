<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMilkProduction extends Model
{
    protected $fillable = [
        'farm_id',
        'production_date',
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
}

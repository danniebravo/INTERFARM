<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilkUsage extends Model
{
    protected $fillable = [
        'farm_id',
        'usage_date',
        'calf_liters',
        'consumed_liters',
        'notes',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'calf_liters' => 'decimal:2',
        'consumed_liters' => 'decimal:2',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function getTotalUsedLitersAttribute(): float
    {
        return round((float) $this->calf_liters + (float) $this->consumed_liters, 2);
    }
}

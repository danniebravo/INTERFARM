<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    protected $fillable = [
        'farm_id',
        'animal_id',
        'title',
        'description',
        'type',
        'event_date',
        'start_datetime',
        'end_datetime',
        'all_day',
        'status',
        'priority',
        'lot_name',
        'color',
        'meta',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'all_day' => 'boolean',
        'meta' => 'array',
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
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmNotification extends Model
{
    protected $fillable = [
        'farm_id',
        'user_id',
        'event_id',
        'source_type',
        'source_key',
        'level',
        'title',
        'message',
        'event_date',
        'lot_name',
        'meta',
        'scheduled_for',
        'read_at',
        'dismissed_at',
        'dismissed_until',
    ];

    protected $casts = [
        'event_date' => 'date',
        'meta' => 'array',
        'scheduled_for' => 'datetime',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'dismissed_until' => 'datetime',
    ];

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

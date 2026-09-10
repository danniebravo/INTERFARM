<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Farm extends Model
{
    protected $fillable = [
        'name',
        'location',
        'hectares',
        'production_type',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | Usuarios que pertenecen a la finca
    |--------------------------------------------------------------------------
    */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Animales de la finca
    |--------------------------------------------------------------------------
    */

    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Lotes de la finca
    |--------------------------------------------------------------------------
    */

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }
}
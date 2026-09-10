<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        try {
            return static::where('key', $key)->value('value') ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function googleMapsApiKey(): string
    {
        return (string) static::valueFor('google_maps_api_key', env('GOOGLE_MAPS_API_KEY', ''));
    }
}

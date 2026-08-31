<?php

namespace App\Services;

use Illuminate\Support\Str;

class TrackingNumber
{
    public static function make(): string
    {
        return 'LAB-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
    }
}

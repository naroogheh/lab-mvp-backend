<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'image_path',
        'status',
        'ai_payload',
        'operator_notes',
        'total_amount',
    ];

    protected $casts = [
        'status' => PrescriptionStatus::class,
        'ai_payload' => 'array',
        'total_amount' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function aiRequestLogs(): HasMany
    {
        return $this->hasMany(AiRequestLog::class);
    }
}

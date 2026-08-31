<?php

namespace App\Models;

use App\Enums\AiRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequestLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'provider',
        'model',
        'endpoint',
        'purpose',
        'status',
        'request_payload',
        'response_payload',
        'extracted_payload',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'estimated_cost_usd',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'status' => AiRequestStatus::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
        'extracted_payload' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost_usd' => 'decimal:8',
        'duration_ms' => 'integer',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}

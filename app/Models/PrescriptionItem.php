<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'lab_test_id',
        'raw_name',
        'display_name',
        'is_available',
        'price',
        'operator_confirmed',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'operator_confirmed' => 'boolean',
        'price' => 'integer',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function labTest(): BelongsTo
    {
        return $this->belongsTo(LabTest::class);
    }
}

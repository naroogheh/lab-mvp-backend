<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'prescription_id',
        'method',
        'amount',
        'status',
        'receipt_path',
        'reference_number',
    ];

    protected $casts = [
        'method' => PaymentMethod::class,
        'amount' => 'integer',
    ];
}

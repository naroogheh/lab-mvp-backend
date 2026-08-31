<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, string $trackingNumber)
    {
        $prescription = Prescription::where('tracking_number', $trackingNumber)->firstOrFail();

        $data = $request->validate([
            'method' => ['required', 'in:ONLINE,CARD_TO_CARD'],
            'receipt' => ['nullable', 'image', 'max:5120'],
            'reference_number' => ['nullable', 'string'],
        ]);

        $receiptPath = $request->file('receipt')?->store('payment-receipts');
        $method = PaymentMethod::from($data['method']);

        $payment = $prescription->payments()->create([
            'method' => $method,
            'amount' => $prescription->total_amount,
            'status' => $method === PaymentMethod::Online ? 'PENDING_GATEWAY' : 'NEEDS_OPERATOR_CONFIRMATION',
            'receipt_path' => $receiptPath,
            'reference_number' => $data['reference_number'] ?? null,
        ]);

        $prescription->update([
            'status' => $method === PaymentMethod::Online
                ? PrescriptionStatus::WaitingForPayment
                : PrescriptionStatus::PaymentReview,
        ]);

        return response()->json($payment, 201);
    }
}

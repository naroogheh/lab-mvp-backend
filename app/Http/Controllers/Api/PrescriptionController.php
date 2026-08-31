<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrescriptionWithAi;
use App\Models\Prescription;
use App\Services\TrackingNumber;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $path = $data['image']->store('prescriptions');

        $prescription = Prescription::create([
            'tracking_number' => TrackingNumber::make(),
            'image_path' => $path,
            'status' => PrescriptionStatus::Submitted,
            'total_amount' => 0,
        ]);

        ProcessPrescriptionWithAi::dispatch($prescription->id);

        return response()->json([
            'tracking_number' => $prescription->tracking_number,
            'status' => $prescription->status->value,
        ], 201);
    }

    public function show(string $trackingNumber)
    {
        $prescription = Prescription::where('tracking_number', $trackingNumber)->firstOrFail();

        return response()->json([
            'tracking_number' => $prescription->tracking_number,
            'status' => $prescription->status->value,
            'total_amount' => $prescription->total_amount,
        ]);
    }

    public function invoice(string $trackingNumber)
    {
        $prescription = Prescription::with('items')
            ->where('tracking_number', $trackingNumber)
            ->firstOrFail();

        abort_unless(
            in_array($prescription->status, [PrescriptionStatus::WaitingForPayment, PrescriptionStatus::PaymentReview, PrescriptionStatus::Paid], true),
            404
        );

        return response()->json([
            'tracking_number' => $prescription->tracking_number,
            'status' => $prescription->status->value,
            'items' => $prescription->items,
            'total_amount' => $prescription->total_amount,
        ]);
    }
}

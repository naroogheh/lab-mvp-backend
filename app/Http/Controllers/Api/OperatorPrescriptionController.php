<?php

namespace App\Http\Controllers\Api;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;

class OperatorPrescriptionController extends Controller
{
    public function index()
    {
        return Prescription::with('items')
            ->latest()
            ->paginate(20);
    }

    public function show(Prescription $prescription)
    {
        return $prescription->load('items.labTest');
    }

    public function updateItems(Request $request, Prescription $prescription)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.lab_test_id' => ['nullable', 'integer', 'exists:lab_tests,id'],
            'items.*.raw_name' => ['required', 'string'],
            'items.*.display_name' => ['required', 'string'],
            'items.*.is_available' => ['required', 'boolean'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.operator_confirmed' => ['boolean'],
        ]);

        $prescription->items()->delete();
        $prescription->items()->createMany($data['items']);
        $prescription->update(['total_amount' => collect($data['items'])->sum('price')]);

        return $prescription->load('items');
    }

    public function confirm(Request $request, Prescription $prescription)
    {
        $data = $request->validate([
            'operator_notes' => ['nullable', 'string'],
        ]);

        $prescription->items()->update(['operator_confirmed' => true]);
        $prescription->update([
            'operator_notes' => $data['operator_notes'] ?? null,
            'total_amount' => $prescription->items()->sum('price'),
            'status' => PrescriptionStatus::WaitingForPayment,
        ]);

        return $prescription->load('items');
    }
}

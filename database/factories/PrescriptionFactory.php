<?php

namespace Database\Factories;

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        return [
            'tracking_number' => 'LAB-' . fake()->unique()->numerify('######'),
            'image_path' => 'prescriptions/sample.jpg',
            'status' => PrescriptionStatus::Submitted,
            'ai_payload' => null,
            'operator_notes' => null,
            'total_amount' => 0,
        ];
    }
}

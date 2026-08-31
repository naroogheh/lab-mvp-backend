<?php

namespace App\Jobs;

use App\Enums\PrescriptionStatus;
use App\Models\Prescription;
use App\Services\AvalAiPrescriptionExtractor;
use App\Services\LabTestMatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPrescriptionWithAi implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $prescriptionId)
    {
    }

    public function handle(AvalAiPrescriptionExtractor $extractor, LabTestMatcher $matcher): void
    {
        $prescription = Prescription::findOrFail($this->prescriptionId);
        $prescription->update(['status' => PrescriptionStatus::AiProcessing]);

        try {
            $tests = $extractor->extract(
                storage_path('app/' . $prescription->image_path),
                $prescription->id
            );
            $items = $matcher->match($tests);

            $prescription->items()->delete();
            $prescription->items()->createMany($items);
            $prescription->update([
                'ai_payload' => ['tests' => $tests],
                'total_amount' => collect($items)->sum('price'),
                'status' => PrescriptionStatus::OperatorReview,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $prescription->update(['status' => PrescriptionStatus::AiFailed]);
        }
    }
}

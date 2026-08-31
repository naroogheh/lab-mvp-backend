<?php

namespace App\Services;

use App\Models\LabTest;

class LabTestMatcher
{
    public function match(array $aiTests): array
    {
        return collect($aiTests)
            ->map(function (array|string $test) {
                $rawName = is_array($test) ? ($test['name'] ?? '') : $test;
                $normalized = mb_strtolower(trim($rawName));

                $labTest = LabTest::query()
                    ->whereRaw('LOWER(name) = ?', [$normalized])
                    ->orWhereJsonContains('aliases', $rawName)
                    ->first();

                return [
                    'lab_test_id' => $labTest?->id,
                    'raw_name' => $rawName,
                    'display_name' => $labTest?->name ?? $rawName,
                    'is_available' => (bool) ($labTest?->is_active),
                    'price' => $labTest?->is_active ? $labTest->price : 0,
                    'operator_confirmed' => false,
                ];
            })
            ->values()
            ->all();
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AvalAiPrescriptionExtractor
{
    public function extract(string $absoluteImagePath): array
    {
        $endpoint = rtrim(config('services.avalai.endpoint'), '/') . '/prescriptions/extract';

        $response = Http::withToken(config('services.avalai.key'))
            ->attach('image', fopen($absoluteImagePath, 'r'), basename($absoluteImagePath))
            ->post($endpoint, [
                'schema' => 'lab_tests_v1',
                'language' => 'fa',
            ]);

        $response->throw();

        return $response->json('tests', []);
    }
}

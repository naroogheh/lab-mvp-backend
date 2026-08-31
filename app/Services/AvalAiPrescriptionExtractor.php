<?php

namespace App\Services;

use App\Enums\AiRequestStatus;
use App\Models\AiRequestLog;
use Illuminate\Support\Facades\Http;

class AvalAiPrescriptionExtractor
{
    public function extract(string $absoluteImagePath, ?int $prescriptionId = null, ?string $model = null): array
    {
        $model ??= config('services.avalai.vision_model');
        $endpoint = rtrim(config('services.avalai.endpoint'), '/') . '/responses';
        $imageDataUrl = $this->imageDataUrl($absoluteImagePath);
        $startedAt = microtime(true);

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->prompt(),
                        ],
                        [
                            'type' => 'input_image',
                            'image_url' => $imageDataUrl,
                            'detail' => 'high',
                        ],
                    ],
                ],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'lab_prescription_tests',
                    'schema' => $this->schema(),
                    'strict' => true,
                ],
            ],
        ];

        $log = AiRequestLog::create([
            'prescription_id' => $prescriptionId,
            'provider' => 'avalai',
            'model' => $model,
            'endpoint' => $endpoint,
            'purpose' => 'prescription_lab_test_extraction',
            'status' => AiRequestStatus::Pending,
            'request_payload' => $this->redactImagePayload($payload),
        ]);

        try {
            $response = Http::withToken(config('services.avalai.key'))
                ->timeout(120)
                ->post($endpoint, $payload);

            $responsePayload = $response->json() ?? ['raw_body' => $response->body()];
            $response->throw();

            $extractedPayload = $this->extractJsonPayload($responsePayload);
            $usage = $responsePayload['usage'] ?? [];

            $log->update([
                'status' => AiRequestStatus::Succeeded,
                'response_payload' => $responsePayload,
                'extracted_payload' => $extractedPayload,
                'input_tokens' => $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
                'estimated_cost_usd' => $this->estimateCostUsd($usage),
                'duration_ms' => $this->durationMs($startedAt),
            ]);

            return $extractedPayload['tests'] ?? [];
        } catch (\Throwable $exception) {
            $log->update([
                'status' => AiRequestStatus::Failed,
                'duration_ms' => $this->durationMs($startedAt),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
این تصویر نسخه آزمایشگاه است.
فقط آزمایش‌های درخواستی را استخراج کن.
اگر نسبت به یک مورد مطمئن نیستی همان را با confidence پایین برگردان و در notes توضیح بده.
هیچ قیمت‌گذاری انجام نده؛ قیمت از دیتابیس آزمایشگاه خوانده می‌شود.
خروجی باید فقط JSON مطابق schema باشد.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['tests'],
            'properties' => [
                'tests' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'raw_text', 'confidence', 'notes'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'raw_text' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'notes' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function imageDataUrl(string $absoluteImagePath): string
    {
        $mimeType = mime_content_type($absoluteImagePath) ?: 'image/jpeg';

        return 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($absoluteImagePath));
    }

    private function redactImagePayload(array $payload): array
    {
        $payload['input'][0]['content'][1]['image_url'] = '[base64 image redacted for log size]';

        return $payload;
    }

    private function extractJsonPayload(array $responsePayload): array
    {
        if (isset($responsePayload['output_text'])) {
            return json_decode($responsePayload['output_text'], true, flags: JSON_THROW_ON_ERROR);
        }

        $content = data_get($responsePayload, 'output.0.content.0.text')
            ?? data_get($responsePayload, 'choices.0.message.content');

        if (is_string($content)) {
            return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        }

        return $responsePayload['tests'] ?? ['tests' => []];
    }

    private function estimateCostUsd(array $usage): ?float
    {
        $inputTokens = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null;
        $outputTokens = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null;

        if ($inputTokens === null && $outputTokens === null) {
            return null;
        }

        $inputCost = ((int) $inputTokens / 1_000_000) * (float) config('services.avalai.input_cost_per_1m_tokens');
        $outputCost = ((int) $outputTokens / 1_000_000) * (float) config('services.avalai.output_cost_per_1m_tokens');

        return round($inputCost + $outputCost, 8);
    }

    private function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}

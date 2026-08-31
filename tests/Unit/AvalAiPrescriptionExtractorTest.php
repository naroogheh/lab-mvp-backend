<?php

namespace Tests\Unit;

use App\Enums\AiRequestStatus;
use App\Models\AiRequestLog;
use App\Services\AvalAiPrescriptionExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AvalAiPrescriptionExtractorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_model_payload_response_usage_cost_and_duration(): void
    {
        config([
            'services.avalai.key' => 'test-key',
            'services.avalai.endpoint' => 'https://api.avalai.ir/v1',
            'services.avalai.vision_model' => 'gpt-5.5',
            'services.avalai.input_cost_per_1m_tokens' => 5.00,
            'services.avalai.output_cost_per_1m_tokens' => 30.00,
        ]);

        Http::fake([
            'api.avalai.ir/v1/responses' => Http::response([
                'id' => 'resp_123',
                'output_text' => json_encode([
                    'tests' => [
                        [
                            'name' => 'CBC',
                            'raw_text' => 'CBC',
                            'confidence' => 0.95,
                            'notes' => '',
                        ],
                    ],
                ]),
                'usage' => [
                    'input_tokens' => 2000,
                    'output_tokens' => 500,
                    'total_tokens' => 2500,
                ],
            ]),
        ]);

        $imagePath = tempnam(sys_get_temp_dir(), 'prescription') . '.jpg';
        file_put_contents($imagePath, 'fake-image');

        $tests = app(AvalAiPrescriptionExtractor::class)->extract($imagePath, prescriptionId: 10);

        unlink($imagePath);

        $this->assertSame('CBC', $tests[0]['name']);

        $log = AiRequestLog::first();
        $this->assertSame(10, $log->prescription_id);
        $this->assertSame('avalai', $log->provider);
        $this->assertSame('gpt-5.5', $log->model);
        $this->assertSame(AiRequestStatus::Succeeded, $log->status);
        $this->assertSame('[base64 image redacted for log size]', $log->request_payload['input'][0]['content'][1]['image_url']);
        $this->assertSame('resp_123', $log->response_payload['id']);
        $this->assertSame('CBC', $log->extracted_payload['tests'][0]['name']);
        $this->assertSame(2000, $log->input_tokens);
        $this->assertSame(500, $log->output_tokens);
        $this->assertSame(2500, $log->total_tokens);
        $this->assertSame('0.02500000', $log->estimated_cost_usd);
        $this->assertNotNull($log->duration_ms);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AiRequestStatus;
use App\Models\AiRequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiRequestLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_list_ai_logs_with_full_payloads(): void
    {
        AiRequestLog::create([
            'provider' => 'avalai',
            'model' => 'gpt-5.5',
            'endpoint' => 'https://api.avalai.ir/v1/responses',
            'purpose' => 'prescription_lab_test_extraction',
            'status' => AiRequestStatus::Succeeded,
            'request_payload' => ['model' => 'gpt-5.5'],
            'response_payload' => ['id' => 'resp_123'],
            'extracted_payload' => ['tests' => [['name' => 'CBC']]],
            'input_tokens' => 2200,
            'output_tokens' => 400,
            'total_tokens' => 2600,
            'estimated_cost_usd' => 0.023,
            'duration_ms' => 1800,
        ]);

        $this->getJson('/api/operator/ai-logs?model=gpt-5.5')
            ->assertOk()
            ->assertJsonPath('data.0.model', 'gpt-5.5')
            ->assertJsonPath('data.0.response_payload.id', 'resp_123')
            ->assertJsonPath('data.0.extracted_payload.tests.0.name', 'CBC')
            ->assertJsonPath('data.0.estimated_cost_usd', '0.02300000');
    }

    public function test_operator_can_compare_model_cost_and_latency_summary(): void
    {
        AiRequestLog::create([
            'provider' => 'avalai',
            'model' => 'gpt-5.5',
            'endpoint' => 'https://api.avalai.ir/v1/responses',
            'purpose' => 'prescription_lab_test_extraction',
            'status' => AiRequestStatus::Succeeded,
            'request_payload' => [],
            'input_tokens' => 2000,
            'output_tokens' => 500,
            'total_tokens' => 2500,
            'estimated_cost_usd' => 0.025,
            'duration_ms' => 2000,
        ]);

        AiRequestLog::create([
            'provider' => 'avalai',
            'model' => 'gpt-5.4-mini',
            'endpoint' => 'https://api.avalai.ir/v1/responses',
            'purpose' => 'prescription_lab_test_extraction',
            'status' => AiRequestStatus::Failed,
            'request_payload' => [],
            'estimated_cost_usd' => 0.004,
            'duration_ms' => 900,
            'error_message' => 'Low confidence',
        ]);

        $this->getJson('/api/operator/ai-logs/summary')
            ->assertOk()
            ->assertJsonFragment([
                'model' => 'gpt-5.5',
                'total_requests' => 1,
                'successful_requests' => '1',
                'failed_requests' => '0',
            ])
            ->assertJsonFragment([
                'model' => 'gpt-5.4-mini',
                'total_requests' => 1,
                'successful_requests' => '0',
                'failed_requests' => '1',
            ]);
    }
}

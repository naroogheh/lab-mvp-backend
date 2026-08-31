<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Jobs\ProcessPrescriptionWithAi;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrescriptionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_prescription_and_receive_tracking_number(): void
    {
        Storage::fake();
        Queue::fake();

        $response = $this->postJson('/api/prescriptions', [
            'image' => UploadedFile::fake()->image('prescription.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['tracking_number', 'status'])
            ->assertJson(['status' => PrescriptionStatus::Submitted->value]);

        $this->assertDatabaseHas('prescriptions', [
            'tracking_number' => $response->json('tracking_number'),
            'status' => PrescriptionStatus::Submitted->value,
        ]);

        Queue::assertPushed(ProcessPrescriptionWithAi::class);
    }

    public function test_tracking_endpoint_returns_current_status_and_total(): void
    {
        $prescription = Prescription::factory()->create([
            'tracking_number' => 'LAB-260831-ABC123',
            'status' => PrescriptionStatus::OperatorReview,
            'total_amount' => 430000,
        ]);

        $this->getJson('/api/prescriptions/' . $prescription->tracking_number)
            ->assertOk()
            ->assertJson([
                'tracking_number' => 'LAB-260831-ABC123',
                'status' => PrescriptionStatus::OperatorReview->value,
                'total_amount' => 430000,
            ]);
    }

    public function test_invoice_is_hidden_until_operator_approves_it(): void
    {
        $prescription = Prescription::factory()->create([
            'tracking_number' => 'LAB-260831-HIDDEN',
            'status' => PrescriptionStatus::OperatorReview,
        ]);

        $this->getJson('/api/prescriptions/' . $prescription->tracking_number . '/invoice')
            ->assertNotFound();
    }

    public function test_operator_can_replace_items_and_confirm_final_invoice(): void
    {
        $prescription = Prescription::factory()->create([
            'status' => PrescriptionStatus::OperatorReview,
        ]);

        $this->putJson('/api/operator/prescriptions/' . $prescription->id . '/items', [
            'items' => [
                [
                    'lab_test_id' => null,
                    'raw_name' => 'CBC',
                    'display_name' => 'CBC',
                    'is_available' => true,
                    'price' => 250000,
                    'operator_confirmed' => false,
                ],
                [
                    'lab_test_id' => null,
                    'raw_name' => 'Rare Test',
                    'display_name' => 'Rare Test',
                    'is_available' => false,
                    'price' => 0,
                    'operator_confirmed' => false,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('total_amount', 250000);

        $this->postJson('/api/operator/prescriptions/' . $prescription->id . '/confirm', [
            'operator_notes' => 'Approved after manual review.',
        ])->assertOk()
            ->assertJsonPath('status', PrescriptionStatus::WaitingForPayment->value)
            ->assertJsonPath('total_amount', 250000);

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => PrescriptionStatus::WaitingForPayment->value,
            'total_amount' => 250000,
        ]);

        $this->assertDatabaseHas('prescription_items', [
            'prescription_id' => $prescription->id,
            'raw_name' => 'Rare Test',
            'is_available' => false,
            'price' => 0,
            'operator_confirmed' => true,
        ]);
    }

    public function test_user_can_create_card_to_card_payment_request(): void
    {
        $prescription = Prescription::factory()->create([
            'tracking_number' => 'LAB-260831-PAY001',
            'status' => PrescriptionStatus::WaitingForPayment,
            'total_amount' => 250000,
        ]);

        $this->postJson('/api/prescriptions/' . $prescription->tracking_number . '/payments', [
            'method' => PaymentMethod::CardToCard->value,
            'reference_number' => '123456',
        ])->assertCreated()
            ->assertJsonPath('method', PaymentMethod::CardToCard->value)
            ->assertJsonPath('amount', 250000)
            ->assertJsonPath('status', 'NEEDS_OPERATOR_CONFIRMATION');

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'status' => PrescriptionStatus::PaymentReview->value,
        ]);
    }
}

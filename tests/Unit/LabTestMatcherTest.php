<?php

namespace Tests\Unit;

use App\Models\LabTest;
use App\Services\LabTestMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabTestMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_available_tests_by_name_and_prices_from_database(): void
    {
        LabTest::create([
            'name' => 'CBC',
            'aliases' => ['Complete Blood Count'],
            'price' => 250000,
            'is_active' => true,
        ]);

        $items = app(LabTestMatcher::class)->match([
            ['name' => 'CBC'],
        ]);

        $this->assertSame('CBC', $items[0]['display_name']);
        $this->assertTrue($items[0]['is_available']);
        $this->assertSame(250000, $items[0]['price']);
        $this->assertFalse($items[0]['operator_confirmed']);
    }

    public function test_it_marks_unknown_or_inactive_tests_as_unavailable_without_price(): void
    {
        LabTest::create([
            'name' => 'Vitamin D',
            'aliases' => ['25-OH Vitamin D'],
            'price' => 480000,
            'is_active' => false,
        ]);

        $items = app(LabTestMatcher::class)->match([
            ['name' => 'Vitamin D'],
            ['name' => 'Unknown Test'],
        ]);

        $this->assertFalse($items[0]['is_available']);
        $this->assertSame(0, $items[0]['price']);
        $this->assertFalse($items[1]['is_available']);
        $this->assertSame(0, $items[1]['price']);
    }
}

<?php

namespace Database\Seeders;

use App\Models\LabTest;
use Illuminate\Database\Seeder;

class LabTestSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'CBC', 'aliases' => ['Complete Blood Count'], 'price' => 250000],
            ['name' => 'FBS', 'aliases' => ['Fasting Blood Sugar'], 'price' => 180000],
            ['name' => 'TSH', 'aliases' => ['Thyroid Stimulating Hormone'], 'price' => 320000],
            ['name' => 'Vitamin D', 'aliases' => ['25-OH Vitamin D'], 'price' => 480000],
        ] as $test) {
            LabTest::updateOrCreate(['name' => $test['name']], $test + ['is_active' => true]);
        }
    }
}

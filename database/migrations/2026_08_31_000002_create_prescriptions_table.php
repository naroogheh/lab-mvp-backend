<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->string('image_path');
            $table->string('status')->index();
            $table->json('ai_payload')->nullable();
            $table->text('operator_notes')->nullable();
            $table->unsignedInteger('total_amount')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};

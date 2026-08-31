<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_name');
            $table->string('display_name');
            $table->boolean('is_available')->default(false);
            $table->unsignedInteger('price')->default(0);
            $table->boolean('operator_confirmed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};

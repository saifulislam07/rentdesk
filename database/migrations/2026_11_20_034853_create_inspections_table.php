<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('type', 3);
            $table->string('condition', 10);
            $table->unsignedInteger('meter_reading')->nullable();
            $table->json('checklist')->nullable();
            $table->text('notes')->nullable();
            $table->string('signature_path')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['rental_id', 'type']);
            $table->index(['unit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};

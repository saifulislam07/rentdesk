<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('serial_no', 100);
            $table->string('qr_code', 64)->unique();
            $table->string('condition', 10)->default('new');
            $table->string('status', 15)->default('available');
            $table->unsignedInteger('quantity')->default(1);
            $table->date('purchased_at')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->unsignedInteger('meter_reading')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'serial_no']);
            $table->index(['location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};

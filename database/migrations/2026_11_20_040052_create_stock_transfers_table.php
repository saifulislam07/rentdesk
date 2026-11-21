<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->restrictOnDelete();
            $table->string('status', 10)->default('in_transit');
            $table->dateTime('sent_at');
            $table->dateTime('received_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};

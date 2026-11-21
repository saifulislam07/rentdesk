<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            // Serves the availability overlap check: unit_id = ? AND start_at < ? AND end_at > ?
            $table->index(['unit_id', 'start_at', 'end_at']);
            $table->index(['product_id', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_items');
    }
};

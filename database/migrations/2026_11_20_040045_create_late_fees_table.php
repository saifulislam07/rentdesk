<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_fees', function (Blueprint $table) {
            $table->id();
            // One live row per rental, refreshed by the daily calculation and fixed at check-in.
            $table->foreignId('rental_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('days_late');
            $table->decimal('amount', 12, 2);
            $table->timestamp('calculated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_fees');
    }
};

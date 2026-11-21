<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->text('message')->nullable();
            $table->string('status', 10)->default('new');
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};

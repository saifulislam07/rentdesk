<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 15);
            $table->string('status', 10)->default('held');
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};

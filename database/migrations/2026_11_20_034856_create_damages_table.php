<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->decimal('cost', 12, 2)->default(0);
            $table->decimal('charged_amount', 12, 2)->default(0);
            $table->string('status', 10)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damages');
    }
};

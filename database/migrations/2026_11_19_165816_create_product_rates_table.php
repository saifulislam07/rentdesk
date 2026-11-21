<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('period', 10);
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('min_qty_periods')->default(1);
            $table->decimal('weekend_price', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_rates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            // The customers table arrives with the customer module, which adds the foreign key.
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status', 15)->default('quote');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->dateTime('returned_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('deposit_required', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->index(['status', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};

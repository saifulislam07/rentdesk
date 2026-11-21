<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->default('person');
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('tax_no', 50)->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

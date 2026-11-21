<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Optional flat price per day for the whole kit, replacing the sum of its products' own rates.
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kits');
    }
};

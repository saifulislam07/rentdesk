<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('name', 60);
            $table->string('dir', 3)->default('ltr');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The languages that ship with the app.
        DB::table('languages')->insert([
            ['code' => 'en', 'name' => 'English', 'dir' => 'ltr', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'bn', 'name' => 'বাংলা', 'dir' => 'ltr', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('maintenance_plans')->nullOnDelete();
            $table->dateTime('due_at');
            $table->dateTime('done_at')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('open');
            $table->timestamps();

            $table->index(['unit_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_tasks');
    }
};

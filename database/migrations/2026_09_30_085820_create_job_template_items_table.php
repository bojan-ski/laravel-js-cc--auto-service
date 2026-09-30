<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_template_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->foreignId('part_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->decimal('quantity', 8, 2);
            $table->integer('position');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_template_items');
    }
};

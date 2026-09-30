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
        Schema::create('job_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('fuel_level', 20)->index();
            $table->text('damage_notes')->nullable();
            $table->text('valuables')->nullable();
            $table->string('signed_by_name')->nullable();
            $table->string('signature_method', 20)->nullable()->index();
            $table->string('signature_path')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_check_ins');
    }
};

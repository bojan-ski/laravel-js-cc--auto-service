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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->integer('quantity_change');
            $table->string('reason', 30)->index();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};

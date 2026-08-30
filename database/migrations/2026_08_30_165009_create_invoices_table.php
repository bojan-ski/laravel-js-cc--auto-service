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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->string('status', 20)->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total', 10, 2);
            $table->dateTime('issued_at');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

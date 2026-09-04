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
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('name')->constrained()->restrictOnDelete();
            $table->string('category', 60)->nullable()->after('brand_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropIndex(['category']);
            $table->dropColumn(['brand_id', 'category']);
        });
    }
};

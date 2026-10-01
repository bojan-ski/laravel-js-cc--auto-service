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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('next_service_on')->nullable()->index()->after('mileage');
            $table->unsignedInteger('next_service_mileage')->nullable()->after('next_service_on');
            $table->date('registration_expires_on')->nullable()->index()->after('next_service_mileage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['next_service_on']);
            $table->dropIndex(['registration_expires_on']);
            $table->dropColumn(['next_service_on', 'next_service_mileage', 'registration_expires_on']);
        });
    }
};

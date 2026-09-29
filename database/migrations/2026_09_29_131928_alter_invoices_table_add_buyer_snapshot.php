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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->after('status');
            $table->string('buyer_address')->nullable()->after('buyer_name');
            $table->string('buyer_tax_id', 9)->nullable()->after('buyer_address');
            $table->string('buyer_registration_number', 8)->nullable()->after('buyer_tax_id');
        });

        DB::table('invoices')
            ->join('jobs', 'jobs.id', '=', 'invoices.job_id')
            ->join('customers', 'customers.id', '=', 'jobs.customer_id')
            ->update([
                'invoices.buyer_name' => DB::raw('customers.name'),
                'invoices.buyer_address' => DB::raw('customers.address'),
                'invoices.buyer_tax_id' => DB::raw('customers.tax_id'),
                'invoices.buyer_registration_number' => DB::raw('customers.registration_number'),
            ]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('buyer_name')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['buyer_name', 'buyer_address', 'buyer_tax_id', 'buyer_registration_number']);
        });
    }
};

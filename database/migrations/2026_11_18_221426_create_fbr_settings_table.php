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
        Schema::create('fbr_settings', function (Blueprint $table) {
            $table->id();
            $table->text('token')->nullable();
            $table->string('seller_ntn_cnic')->nullable();
            $table->string('seller_business_name')->nullable();
            $table->string('seller_province')->default('Sindh');
            $table->text('seller_address')->nullable();
            $table->string('environment')->default('sandbox'); // sandbox or production
            $table->string('sandbox_url')->default('https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb');
            $table->string('production_url')->default('https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata');
            $table->string('scenario_id')->default('SN008'); // Default scenario for 3rd Schedule Goods
            $table->string('sale_type')->default('3rd Schedule Goods');
            $table->string('default_hs_code')->default('2105.0000');
            $table->float('default_tax_rate')->default(18.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fbr_settings');
    }
};

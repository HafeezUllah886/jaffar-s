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
        Schema::table('sales', function (Blueprint $table) {
            $table->string('fbr_status')->default('pending')->after('notes'); // pending, sent, issue_sending
            $table->string('fbr_invoice_number')->nullable()->after('fbr_status');
            $table->timestamp('fbr_submitted_at')->nullable()->after('fbr_invoice_number');
            $table->longText('fbr_response')->nullable()->after('fbr_submitted_at');
            $table->text('fbr_error')->nullable()->after('fbr_response');

            // Snapshot of buyer details submitted to FBR
            $table->string('buyer_ntn_cnic')->nullable()->after('fbr_error');
            $table->string('buyer_name')->nullable()->after('buyer_ntn_cnic');
            $table->string('buyer_province')->nullable()->after('buyer_name');
            $table->text('buyer_address')->nullable()->after('buyer_province');
            $table->string('buyer_registration_type')->default('Unregistered')->after('buyer_address');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('province')->nullable()->default('Sindh')->after('address');
            $table->string('registration_type')->default('Unregistered')->after('province');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('hs_code')->nullable()->default('2105.0000')->after('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'fbr_status',
                'fbr_invoice_number',
                'fbr_submitted_at',
                'fbr_response',
                'fbr_error',
                'buyer_ntn_cnic',
                'buyer_name',
                'buyer_province',
                'buyer_address',
                'buyer_registration_type',
            ]);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['province', 'registration_type']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hs_code');
        });
    }
};

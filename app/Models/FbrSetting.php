<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbrSetting extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get or initialize the single instance of FBR settings.
     */
    public static function getSettings(): self
    {
        $settings = self::first();
        if (!$settings) {
            $settings = self::create([
                'token' => '',
                'seller_ntn_cnic' => '1234567-8',
                'seller_business_name' => 'JAFFAR MARKETING',
                'seller_province' => 'Sindh',
                'seller_address' => 'Shop # 1, Commercial Market, Karachi',
                'environment' => 'sandbox',
                'sandbox_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb',
                'production_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata',
                'scenario_id' => 'SN008',
                'sale_type' => '3rd Schedule Goods',
                'default_hs_code' => '2105.0000',
                'default_tax_rate' => 18.0,
                'is_active' => true,
            ]);
        }
        return $settings;
    }

    /**
     * Get active API endpoint URL based on environment.
     */
    public function getActiveUrlAttribute(): string
    {
        if ($this->environment === 'production') {
            return $this->production_url ?: 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata';
        }
        return $this->sandbox_url ?: 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb';
    }
}

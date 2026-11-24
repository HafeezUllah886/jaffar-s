<?php

namespace Tests\Feature;

use App\Models\accounts;
use App\Models\categories;
use App\Models\FbrSetting;
use App\Models\order_details;
use App\Models\orders;
use App\Models\products;
use App\Models\sale_details;
use App\Models\sales;
use App\Models\units;
use App\Models\User;
use App\Services\FbrService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FbrIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected accounts $customer;

    protected products $product;

    protected units $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['HTTP_HOST'] = 'jaffar.test';
        $_SERVER['SERVER_NAME'] = 'jaffar.test';

        $this->user = User::firstOrCreate(
            ['email' => 'admin_test@jaffar.com'],
            ['name' => 'Admin Test', 'password' => bcrypt('password'), 'role' => 'Admin']
        );

        $this->unit = units::firstOrCreate(['name' => 'Pieces'], ['value' => 1]);
        $category = categories::firstOrCreate(['name' => 'General']);

        $this->customer = accounts::create([
            'title' => 'Test Retail Mart',
            'type' => 'Customer',
            'address' => 'Shop 12, Saddar, Karachi',
            'province' => 'Sindh',
            'registration_type' => 'Registered',
            'ntn' => '1234567-1',
            'cnic' => '42101-1234567-1',
            'contact' => '03001234567',
        ]);

        $this->product = products::create([
            'code' => 'PRD-TEST-'.uniqid(),
            'name' => 'Special Ice Cream 500ml '.uniqid(),
            'catID' => $category->id,
            'unitID' => $this->unit->id,
            'pprice' => 100,
            'wsprice' => 120,
            'price' => 150,
            'tp' => 150,
            'volume' => 500,
            'hs_code' => '2105.0000',
        ]);
    }

    /**
     * Test FbrSetting singleton helper.
     */
    public function test_fbr_settings_initializes_with_correct_defaults(): void
    {
        $settings = FbrSetting::getSettings();

        $this->assertNotNull($settings);
        $this->assertEquals('sandbox', $settings->environment);
        $this->assertEquals('https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb', $settings->active_url);
        $this->assertEquals('3rd Schedule Goods', $settings->sale_type);
        $this->assertEquals('SN008', $settings->scenario_id);
    }

    /**
     * Test updating FBR settings.
     */
    public function test_admin_can_update_fbr_settings(): void
    {
        $response = $this->actingAs($this->user)->post(route('settings.fbr.update'), [
            'seller_ntn_cnic' => '9876543-2',
            'seller_business_name' => 'NEW JAFFAR TRADERS',
            'seller_province' => 'Sindh',
            'seller_address' => 'Plot 45, Korangi Industrial Area, Karachi',
            'environment' => 'sandbox',
            'sandbox_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb',
            'production_url' => 'https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata',
            'scenario_id' => 'SN008',
            'sale_type' => '3rd Schedule Goods',
            'default_hs_code' => '2105.0000',
            'default_tax_rate' => 18,
            'is_active' => '1',
            'token' => 'sample-bearer-token-12345',
        ]);

        $response->assertRedirect(route('settings.fbr'));

        $settings = FbrSetting::getSettings();
        $this->assertEquals('9876543-2', $settings->seller_ntn_cnic);
        $this->assertEquals('NEW JAFFAR TRADERS', $settings->seller_business_name);
        $this->assertEquals('sample-bearer-token-12345', $settings->token);
    }

    /**
     * Test 3rd Schedule Goods payload construction.
     */
    public function test_fbr_service_builds_correct_3rd_schedule_payload(): void
    {
        $settings = FbrSetting::getSettings();
        $settings->update([
            'token' => 'test-token',
            'seller_ntn_cnic' => '7654321-0',
            'seller_business_name' => 'JAFFAR MARKETING',
            'seller_province' => 'Sindh',
            'seller_address' => 'Karachi',
            'environment' => 'sandbox',
            'scenario_id' => 'SN008',
            'sale_type' => '3rd Schedule Goods',
        ]);

        $ref = getRef();
        $sale = sales::create([
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'deliverymanID' => 1,
            'date' => '2026-11-18',
            'net' => 1770,
            'refID' => $ref,
            'buyer_ntn_cnic' => '1234567-1',
            'buyer_name' => 'Test Retail Mart',
            'buyer_province' => 'Sindh',
            'buyer_address' => 'Shop 12, Saddar, Karachi',
            'buyer_registration_type' => 'Registered',
            'fbr_status' => 'pending',
        ]);

        sale_details::create([
            'salesID' => $sale->id,
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'productID' => $this->product->id,
            'unitID' => $this->unit->id,
            'unitValue' => 1,
            'qty' => 10,
            'price' => 150,
            'tp' => 150,
            'discount' => 0,
            'ti' => 1770,
            'gst' => 18,
            'gstValue' => 270,
            'bonus' => 0,
            'date' => '2026-11-18',
            'refID' => $ref,
        ]);

        $service = new FbrService;
        $payload = $service->buildInvoicePayload($sale);

        // Assert header requirements
        $this->assertEquals('Sale Invoice', $payload['invoiceType']);
        $this->assertEquals('7654321', $payload['sellerNTNCNIC']); // Cleaned to exact 7 digits
        $this->assertEquals('JAFFAR MARKETING', $payload['sellerBusinessName']);
        $this->assertEquals('1234567', $payload['buyerNTNCNIC']); // Cleaned to exact 7 digits
        $this->assertEquals('Test Retail Mart', $payload['buyerBusinessName']);
        $this->assertEquals('Sindh', $payload['buyerProvince']);
        $this->assertEquals('Registered', $payload['buyerRegistrationType']);
        $this->assertEquals('SN008', $payload['scenarioId']);

        // Assert 3rd schedule line item requirements
        $this->assertCount(1, $payload['items']);
        $item = $payload['items'][0];
        $this->assertEquals('3rd Schedule Goods', $item['saleType']);
        $this->assertEquals('2105.0000', $item['hsCode']);
        $this->assertEquals('18%', $item['rate']);
        $this->assertEquals(10, $item['quantity']);
        $this->assertEquals(1500, $item['fixedNotifiedValueOrRetailPrice']); // 150 * 10
        $this->assertEquals(270, $item['salesTaxApplicable']); // 18% of 1500 = 270
    }

    /**
     * Test sending invoice with successful FBR response.
     */
    public function test_send_to_fbr_success_updates_invoice_status_and_number(): void
    {
        $settings = FbrSetting::getSettings();
        $settings->update([
            'token' => 'valid-bearer-token',
            'is_active' => true,
            'seller_ntn_cnic' => '7654321-0',
        ]);

        $ref = getRef();
        $sale = sales::create([
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'deliverymanID' => 1,
            'date' => '2026-11-18',
            'net' => 1770,
            'refID' => $ref,
            'buyer_ntn_cnic' => '1234567-1',
            'buyer_name' => 'Test Retail Mart',
            'buyer_province' => 'Sindh',
            'buyer_address' => 'Karachi',
            'buyer_registration_type' => 'Registered',
            'fbr_status' => 'pending',
        ]);

        sale_details::create([
            'salesID' => $sale->id,
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'productID' => $this->product->id,
            'unitID' => $this->unit->id,
            'unitValue' => 1,
            'qty' => 10,
            'price' => 150,
            'tp' => 150,
            'discount' => 0,
            'ti' => 1770,
            'gst' => 18,
            'gstValue' => 270,
            'bonus' => 0,
            'date' => '2026-11-18',
            'refID' => $ref,
        ]);

        // Mock FBR API response
        Http::fake([
            $settings->active_url => Http::response([
                'invoiceNumber' => '7000007DI1747119701593',
                'dated' => '2026-11-18 22:00:00',
                'validationResponse' => [
                    'statusCode' => '00',
                    'status' => 'Valid',
                    'error' => '',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('sale.sendToFbr', $sale->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'invoice_number' => '7000007DI1747119701593',
        ]);

        $sale->refresh();
        $this->assertEquals('sent', $sale->fbr_status);
        $this->assertEquals('7000007DI1747119701593', $sale->fbr_invoice_number);
        $this->assertNotNull($sale->fbr_submitted_at);
        $this->assertNull($sale->fbr_error);
    }

    /**
     * Test sending invoice with failed FBR response.
     */
    public function test_send_to_fbr_error_records_issue_sending_status(): void
    {
        $settings = FbrSetting::getSettings();
        $settings->update([
            'token' => 'invalid-token',
            'is_active' => true,
            'seller_ntn_cnic' => '7654321-0',
        ]);

        $ref = getRef();
        $sale = sales::create([
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'deliverymanID' => 1,
            'date' => '2026-11-18',
            'net' => 1770,
            'refID' => $ref,
            'buyer_ntn_cnic' => '1234567-1',
            'buyer_name' => 'Test Retail Mart',
            'buyer_province' => 'Sindh',
            'buyer_address' => 'Karachi',
            'buyer_registration_type' => 'Registered',
            'fbr_status' => 'pending',
        ]);

        sale_details::create([
            'salesID' => $sale->id,
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'productID' => $this->product->id,
            'unitID' => $this->unit->id,
            'unitValue' => 1,
            'qty' => 10,
            'price' => 150,
            'tp' => 150,
            'discount' => 0,
            'ti' => 1770,
            'gst' => 18,
            'gstValue' => 270,
            'bonus' => 0,
            'date' => '2026-11-18',
            'refID' => $ref,
        ]);

        // Mock FBR API rejection
        Http::fake([
            $settings->active_url => Http::response([
                'validationResponse' => [
                    'statusCode' => '01',
                    'status' => 'Invalid',
                    'error' => 'Provided sales tax amount does not match the calculated sales tax amount in case of 3rd schedule goods',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)->postJson(route('sale.sendToFbr', $sale->id));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
        ]);

        $sale->refresh();
        $this->assertEquals('issue_sending', $sale->fbr_status);
        $this->assertStringContainsString('3rd schedule goods', $sale->fbr_error);

        // Verify that the view renders the issue with d-print-none so it does NOT appear on print
        $viewResponse = $this->actingAs($this->user)->get(route('sale.show', $sale->id));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('d-print-none');
        $viewResponse->assertSee('FBR Transmission Issue');
    }

    /**
     * Test getCustomer endpoint returns FBR-compliant buyer fields.
     */
    public function test_get_customer_endpoint_returns_fbr_attributes(): void
    {
        $response = $this->actingAs($this->user)->getJson('/sales/getcustomer/'.$this->customer->id);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->customer->id,
            'title' => 'Test Retail Mart',
            'province' => 'Sindh',
            'registration_type' => 'Registered',
            'ntn' => '1234567-1',
            'cnic' => '42101-1234567-1',
            'address' => 'Shop 12, Saddar, Karachi',
        ]);
    }

    /**
     * Test sale creation snapshots buyer data and defaults fbr_status to pending.
     */
    public function test_sale_store_creates_sale_with_buyer_snapshot_and_pending_fbr_status(): void
    {
        $response = $this->actingAs($this->user)->post(route('sale.store'), [
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'deliverymanID' => 1,
            'date' => '2026-11-18',
            'id' => [$this->product->id],
            'unit' => [$this->unit->id],
            'qty' => [5],
            'price' => [150],
            'tp' => [150],
            'discount' => [0],
            'ti' => [885],
            'gst' => [18],
            'gstValue' => [135],
            'bonus' => [0],
            'discount1' => 0,
            'fright' => 0,
            'fright1' => 0,
            'whTax' => 0,
            'notes' => 'FBR store test',
            'buyer_name' => 'Custom Storefront',
            'buyer_ntn_cnic' => '7654321-0',
            'buyer_province' => 'Sindh',
            'buyer_address' => 'Clifton Block 2, Karachi',
            'buyer_registration_type' => 'Registered',
            'status' => 'unpaid',
        ]);

        $createdSale = sales::latest('id')->first();
        $this->assertNotNull($createdSale);
        $response->assertRedirect(route('sale.show', $createdSale->id));

        $this->assertEquals('Custom Storefront', $createdSale->buyer_name);
        $this->assertEquals('7654321-0', $createdSale->buyer_ntn_cnic);
        $this->assertEquals('Sindh', $createdSale->buyer_province);
        $this->assertEquals('Clifton Block 2, Karachi', $createdSale->buyer_address);
        $this->assertEquals('Registered', $createdSale->buyer_registration_type);
        $this->assertEquals('pending', $createdSale->fbr_status);
        $this->assertNull($createdSale->fbr_invoice_number);
    }

    /**
     * Test FBR preview endpoint returns exact payload structure.
     */
    public function test_fbr_preview_returns_exact_json_payload(): void
    {
        $ref = getRef();
        $sale = sales::create([
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'deliverymanID' => 1,
            'date' => '2026-11-18',
            'net' => 1770,
            'refID' => $ref,
            'buyer_ntn_cnic' => '1234567-1',
            'buyer_name' => 'Test Retail Mart',
            'buyer_province' => 'Sindh',
            'buyer_address' => 'Karachi',
            'buyer_registration_type' => 'Registered',
            'fbr_status' => 'pending',
        ]);

        sale_details::create([
            'salesID' => $sale->id,
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'productID' => $this->product->id,
            'unitID' => $this->unit->id,
            'unitValue' => 1,
            'qty' => 10,
            'price' => 150,
            'tp' => 150,
            'discount' => 0,
            'ti' => 1770,
            'gst' => 18,
            'gstValue' => 270,
            'bonus' => 0,
            'date' => '2026-11-18',
            'refID' => $ref,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('sale.fbrPreview', $sale->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'invoice_id',
            'payload' => [
                'invoiceType',
                'invoiceDate',
                'sellerNTNCNIC',
                'sellerBusinessName',
                'sellerProvince',
                'sellerAddress',
                'buyerNTNCNIC',
                'buyerBusinessName',
                'buyerProvince',
                'buyerAddress',
                'buyerRegistrationType',
                'scenarioId',
                'items' => [
                    '*' => [
                        'hsCode',
                        'productDescription',
                        'rate',
                        'quantity',
                        'saleType',
                        'fixedNotifiedValueOrRetailPrice',
                        'salesTaxApplicable',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test Finalize Order form renders FBR fields and converts to sale properly.
     */
    public function test_finalize_order_form_renders_fbr_fields_and_submits_sale(): void
    {
        $order = orders::create([
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'date' => '2026-11-23',
            'net' => 1770,
            'wh' => 0,
            'whValue' => 0,
            'status' => 'Pending',
        ]);

        order_details::create([
            'orderID' => $order->id,
            'productID' => $this->product->id,
            'qty' => 10,
            'price' => 150,
            'discount' => 0,
            'amount' => 1500,
            'date' => '2026-11-23',
            'unitID' => $this->unit->id,
            'unitValue' => 1,
            'bonus' => 0,
        ]);

        // 1. Check finalize order view renders FBR customer fields
        $response = $this->actingAs($this->user)->get(route('order.sale', $order->id));
        $response->assertStatus(200);
        $response->assertSee('FBR Digital Invoicing - Customer Details (Required)');
        $response->assertSee('name="buyer_name"', false);
        $response->assertSee('name="buyer_ntn_cnic"', false);
        $response->assertSee('name="buyer_province"', false);
        $response->assertSee('name="buyer_address"', false);
        $response->assertSee('name="buyer_registration_type"', false);

        // 2. Submit conversion from order to sale
        $postResponse = $this->actingAs($this->user)->post(route('sale.store'), [
            'orderID' => $order->id,
            'customerID' => $this->customer->id,
            'orderbookerID' => $this->user->id,
            'date' => '2026-11-23',
            'id' => [$this->product->id],
            'unit' => [$this->unit->id],
            'qty' => [10],
            'price' => [150],
            'tp' => [150],
            'discount' => [0],
            'ti' => [1770],
            'gst' => [18],
            'gstValue' => [270],
            'bonus' => [0],
            'discount1' => 0,
            'fright' => 0,
            'fright1' => 0,
            'whTax' => 0,
            'notes' => 'Converted from order',
            'buyer_name' => $this->customer->title,
            'buyer_ntn_cnic' => '1234567-1',
            'buyer_province' => 'Sindh',
            'buyer_address' => 'Saddar, Karachi',
            'buyer_registration_type' => 'Registered',
            'status' => 'pending',
        ]);

        $createdSale = sales::latest('id')->first();
        $this->assertNotNull($createdSale);
        $this->assertEquals('pending', $createdSale->fbr_status);
        $this->assertEquals($this->customer->title, $createdSale->buyer_name);

        $order->refresh();
        $this->assertEquals('Completed', $order->status);
        $this->assertEquals($createdSale->id, $order->saleID);
    }
}

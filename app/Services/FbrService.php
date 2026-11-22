<?php

namespace App\Services;

use App\Models\FbrSetting;
use App\Models\sales;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FbrService
{
    protected FbrSetting $settings;

    public function __construct()
    {
        $this->settings = FbrSetting::getSettings();
    }

    /**
     * Get the active settings.
     */
    public function getSettings(): FbrSetting
    {
        return $this->settings;
    }

    /**
     * Clean and format NTN/CNIC according to FBR requirements (exact 7 or 13 digits).
     */
    public static function cleanRegistrationNumber(?string $num): string
    {
        if (!$num) return '';
        $digits = preg_replace('/[^0-9]/', '', $num);

        // 13 digits CNIC
        if (strlen($digits) === 13) {
            return $digits;
        }

        // 7 digits NTN
        if (strlen($digits) === 7) {
            return $digits;
        }

        // 8 digits formatted with check-digit (e.g. 1234567-8), FBR uses the first 7 digits
        if (strlen($digits) === 8 && strpos($num, '-') !== false) {
            return substr($digits, 0, 7);
        }

        return (string) trim($num);
    }

    /**
     * Construct JSON payload for FBR Digital Invoicing.
     * Complies with "Sale of 3rd Schedule Goods" specification.
     */
    public function buildInvoicePayload(sales $sale): array
    {
        $settings = $this->settings;
        $customer = $sale->customer;

        // Buyer information
        $rawBuyerNtnCnic = $sale->buyer_ntn_cnic 
            ?: ($customer->ntn ?: ($customer->cnic ?: '9999999-9'));
        $buyerNtnCnic = self::cleanRegistrationNumber($rawBuyerNtnCnic);
        
        $buyerName = $sale->buyer_name 
            ?: ($customer->title ?: 'Walking Customer');
        
        $buyerProvince = $sale->buyer_province 
            ?: ($customer->province ?: ($settings->seller_province ?: 'Sindh'));

        $buyerAddress = $sale->buyer_address 
            ?: ($customer->address ?: 'Local Market');

        $buyerRegistrationType = $sale->buyer_registration_type 
            ?: ($customer->registration_type ?: ($customer->ntn ? 'Registered' : 'Unregistered'));

        // Seller NTN/CNIC cleaned to 7 or 13 digits
        $sellerNtnCnic = self::cleanRegistrationNumber($settings->seller_ntn_cnic);

        // Header payload
        $payload = [
            'invoiceType' => 'Sale Invoice',
            'invoiceDate' => date('Y-m-d', strtotime($sale->date)),
            'sellerNTNCNIC' => (string) $sellerNtnCnic,
            'sellerBusinessName' => (string) trim($settings->seller_business_name),
            'sellerProvince' => (string) trim($settings->seller_province),
            'sellerAddress' => (string) trim($settings->seller_address),
            'buyerNTNCNIC' => (string) $buyerNtnCnic,
            'buyerBusinessName' => (string) trim($buyerName),
            'buyerProvince' => (string) trim($buyerProvince),
            'buyerAddress' => (string) trim($buyerAddress),
            'buyerRegistrationType' => (string) trim($buyerRegistrationType),
            'invoiceRefNo' => 'INV-' . $sale->id,
        ];

        // Scenario ID for sandbox testing (e.g. SN008 for 3rd Schedule Goods)
        if ($settings->environment === 'sandbox' && !empty($settings->scenario_id)) {
            $payload['scenarioId'] = (string) trim($settings->scenario_id);
        }

        // Line items
        $items = [];
        $saleDetails = $sale->details()->with(['product', 'unit'])->get();

        foreach ($saleDetails as $index => $detail) {
            $product = $detail->product;
            $unit = $detail->unit;
            $unitValue = $detail->unitValue ?: ($unit->value ?? 1);
            $qty = $unitValue > 0 ? ($detail->qty / $unitValue) : $detail->qty;
            $bonus = (float) ($detail->bonus ?? 0);
            $totalPhysicalQty = $qty + $bonus;

            // Harmonized System (HS) Code
            $hsCode = $product->hs_code ?: ($settings->default_hs_code ?: '2105.0000');
            // Clean HS Code (keep digits and dots, max length standard 8-10 chars)
            $hsCode = trim($hsCode);

            // Rate calculation
            $taxRateNum = $detail->gst > 0 ? (float) $detail->gst : (float) ($settings->default_tax_rate ?: 18.0);
            $taxRateStr = round($taxRateNum, 2) . '%';

            // Third schedule goods: Tax is computed on printed Retail Price (MRP)
            // In the app, tp is the retail price unit base
            $unitRetailPrice = $detail->tp > 0 ? (float) $detail->tp : (float) $detail->price;
            $fixedNotifiedValueOrRetailPrice = round($unitRetailPrice * $totalPhysicalQty, 2);

            // Sales tax amount based on 3rd schedule formula
            $salesTaxApplicable = $detail->gstValue > 0 
                ? round((float) $detail->gstValue, 2) 
                : round($fixedNotifiedValueOrRetailPrice * ($taxRateNum / 100), 2);

            // Value of sales excluding sales tax (transaction value ex-tax)
            $valueSalesExcludingST = round(max(0, (float) $detail->ti - (float) $detail->gstValue), 2);
            if ($valueSalesExcludingST <= 0) {
                $valueSalesExcludingST = round(max(0, $fixedNotifiedValueOrRetailPrice - $salesTaxApplicable), 2);
            }

            $discount = round((float) ($detail->discount * $qty), 2);
            $totalValues = round($fixedNotifiedValueOrRetailPrice, 2);

            $items[] = [
                'hsCode' => $hsCode,
                'productDescription' => (string) ($product->name ?? 'Product ' . $detail->productID),
                'rate' => $taxRateStr,
                'uoM' => (string) ($unit->name ?? 'Numbers, pieces, units'),
                'quantity' => round($qty, 4),
                'totalValues' => $totalValues,
                'valueSalesExcludingST' => $valueSalesExcludingST,
                'fixedNotifiedValueOrRetailPrice' => $fixedNotifiedValueOrRetailPrice,
                'salesTaxApplicable' => $salesTaxApplicable,
                'salesTaxWithheldAtSource' => 0.00,
                'extraTax' => 0.00,
                'furtherTax' => 0.00,
                'sroScheduleNo' => '',
                'fedPayable' => 0.00,
                'discount' => $discount,
                'saleType' => (string) ($settings->sale_type ?: '3rd Schedule Goods'),
                'sroItemSerialNo' => '',
            ];
        }

        $payload['items'] = $items;

        return $payload;
    }

    /**
     * Send invoice to FBR Digital Invoicing API.
     */
    public function sendInvoice(sales $sale): array
    {
        $settings = $this->settings;

        if (!$settings->is_active) {
            return [
                'success' => false,
                'message' => 'FBR Digital Invoicing integration is disabled in Settings.',
            ];
        }

        if (empty(trim($settings->token))) {
            return [
                'success' => false,
                'message' => 'FBR Security Token is not configured. Please add it in FBR Settings.',
            ];
        }

        if (empty(trim($settings->seller_ntn_cnic))) {
            return [
                'success' => false,
                'message' => 'Seller NTN/CNIC is required in FBR Settings.',
            ];
        }

        try {
            $payload = $this->buildInvoicePayload($sale);
            $url = $settings->active_url;

            Log::info("Submitting invoice #{$sale->id} to FBR endpoint: {$url}", ['payload' => $payload]);

            $response = Http::withToken(trim($settings->token))
                ->withHeaders([
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US',
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'JaffarManagementSystem/1.0',
                ])
                ->timeout(30)
                ->post($url, $payload);

            $status = $response->status();
            $body = $response->body();
            $data = $response->json();

            Log::info("FBR response for invoice #{$sale->id}", [
                'status' => $status,
                'body' => $body,
            ]);

            // Check if response indicates success
            $isValid = false;
            $fbrInvoiceNumber = null;
            $errorMessage = '';

            if (is_array($data)) {
                // Check if invoiceNumber returned directly
                if (!empty($data['invoiceNumber'])) {
                    $fbrInvoiceNumber = $data['invoiceNumber'];
                }

                // Check validationResponse
                if (isset($data['validationResponse'])) {
                    $valResp = $data['validationResponse'];
                    if (($valResp['status'] ?? '') === 'Valid' || ($valResp['statusCode'] ?? '') === '00') {
                        $isValid = true;
                    }

                    if (empty($fbrInvoiceNumber) && !empty($valResp['invoiceStatuses'][0]['invoiceNo'])) {
                        $fbrInvoiceNumber = $valResp['invoiceStatuses'][0]['invoiceNo'];
                    }

                    if (!empty($valResp['error'])) {
                        $errorMessage = $valResp['error'];
                    } elseif (isset($valResp['invoiceStatuses'])) {
                        $errors = [];
                        foreach ($valResp['invoiceStatuses'] as $statusItem) {
                            if (!empty($statusItem['error'])) {
                                $errors[] = "Item " . ($statusItem['itemSNo'] ?? '') . ": " . $statusItem['error'];
                            }
                        }
                        if (!empty($errors)) {
                            $errorMessage = implode(' | ', $errors);
                        }
                    }
                }

                // In some versions, top-level status or invoiceNumber presence means valid
                if (!empty($fbrInvoiceNumber) && empty($errorMessage)) {
                    $isValid = true;
                }
            }

            if ($response->successful() && $isValid && !empty($fbrInvoiceNumber)) {
                $sale->update([
                    'fbr_status' => 'sent',
                    'fbr_invoice_number' => $fbrInvoiceNumber,
                    'fbr_submitted_at' => now(),
                    'fbr_response' => json_encode($data),
                    'fbr_error' => null,
                ]);

                return [
                    'success' => true,
                    'invoice_number' => $fbrInvoiceNumber,
                    'message' => "Invoice sent to FBR successfully! FBR Invoice #: {$fbrInvoiceNumber}",
                    'data' => $data,
                ];
            } else {
                if (empty($errorMessage)) {
                    $errorMessage = !empty($data['message']) 
                        ? $data['message'] 
                        : ($response->successful() ? 'FBR rejected the invoice. Check payload details.' : "HTTP {$status}: " . substr($body, 0, 200));
                }

                $sale->update([
                    'fbr_status' => 'issue_sending',
                    'fbr_error' => $errorMessage,
                    'fbr_response' => json_encode($data ?: ['raw' => $body, 'status' => $status]),
                ]);

                return [
                    'success' => false,
                    'message' => "FBR Submission Error: {$errorMessage}",
                    'error' => $errorMessage,
                    'data' => $data,
                ];
            }
        } catch (Throwable $e) {
            Log::error("FBR Transmission exception for invoice #{$sale->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $errorMsg = 'Connection / Request failed: ' . $e->getMessage();

            $sale->update([
                'fbr_status' => 'issue_sending',
                'fbr_error' => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => $errorMsg,
            ];
        }
    }
}

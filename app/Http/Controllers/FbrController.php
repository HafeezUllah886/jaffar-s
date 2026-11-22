<?php

namespace App\Http\Controllers;

use App\Models\FbrSetting;
use App\Models\sales;
use App\Services\FbrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class FbrController extends Controller
{
    /**
     * Show FBR Digital Invoicing settings.
     */
    public function settings()
    {
        $settings = FbrSetting::getSettings();
        return view('settings.fbr', compact('settings'));
    }

    /**
     * Update FBR settings.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'seller_ntn_cnic' => 'required|string|max:50',
            'seller_business_name' => 'required|string|max:255',
            'seller_province' => 'required|string|max:100',
            'seller_address' => 'required|string|max:500',
            'environment' => 'required|in:sandbox,production',
            'sandbox_url' => 'required|url',
            'production_url' => 'required|url',
            'scenario_id' => 'nullable|string|max:50',
            'sale_type' => 'required|string|max:100',
            'default_hs_code' => 'required|string|max:20',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
        ]);

        $settings = FbrSetting::getSettings();
        $settings->update([
            'token' => $request->token,
            'seller_ntn_cnic' => $request->seller_ntn_cnic,
            'seller_business_name' => $request->seller_business_name,
            'seller_province' => $request->seller_province,
            'seller_address' => $request->seller_address,
            'environment' => $request->environment,
            'sandbox_url' => $request->sandbox_url,
            'production_url' => $request->production_url,
            'scenario_id' => $request->scenario_id ?? 'SN008',
            'sale_type' => $request->sale_type ?? '3rd Schedule Goods',
            'default_hs_code' => $request->default_hs_code,
            'default_tax_rate' => $request->default_tax_rate,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('settings.fbr')->with('success', 'FBR IRIS Settings updated successfully.');
    }

    /**
     * Test connection to FBR API.
     */
    public function testConnection(Request $request)
    {
        $settings = FbrSetting::getSettings();

        if (empty(trim($settings->token))) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a valid Security Token before testing connection.',
            ]);
        }

        try {
            $url = $settings->active_url;
            $response = Http::withToken(trim($settings->token))
                ->timeout(15)
                ->withHeaders([
                    'Accept' => '*/*',
                    'Accept-Language' => 'en-US',
                ])
                ->post($url, []);

            // If we receive an answer from FBR gateway (even validation error on empty payload), gateway is reachable and token was handled
            return response()->json([
                'success' => true,
                'message' => "Successfully reached FBR gateway ({$settings->environment}). Status code: " . $response->status(),
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reach FBR API: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Transmit sale invoice to FBR.
     */
    public function sendInvoice($id, FbrService $fbrService, Request $request)
    {
        $sale = sales::with(['customer', 'details.product', 'details.unit'])->findOrFail($id);

        if ($sale->fbr_status === 'sent') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Invoice #{$id} is already transmitted to FBR (FBR Invoice No: {$sale->fbr_invoice_number}).",
                    'invoice_number' => $sale->fbr_invoice_number,
                    'status' => $sale->fbr_status,
                ]);
            }
            return back()->with('info', "Invoice #{$id} is already transmitted to FBR.");
        }

        $result = $fbrService->sendInvoice($sale);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return back()->with('success', $result['message']);
        } else {
            return back()->with('error', $result['message']);
        }
    }

    /**
     * View detailed FBR error / response for an invoice.
     */
    public function viewError($id)
    {
        $sale = sales::findOrFail($id);

        return response()->json([
            'id' => $sale->id,
            'fbr_status' => $sale->fbr_status,
            'fbr_invoice_number' => $sale->fbr_invoice_number,
            'fbr_error' => $sale->fbr_error,
            'fbr_response' => json_decode($sale->fbr_response, true) ?? $sale->fbr_response,
            'fbr_submitted_at' => $sale->fbr_submitted_at ? $sale->fbr_submitted_at->format('d M Y, h:i A') : null,
        ]);
    }

    /**
     * Preview JSON payload that will be sent to FBR.
     */
    public function previewPayload($id, FbrService $fbrService)
    {
        $sale = sales::with(['customer', 'details.product', 'details.unit'])->findOrFail($id);
        $payload = $fbrService->buildInvoicePayload($sale);

        return response()->json([
            'invoice_id' => $sale->id,
            'payload' => $payload,
        ]);
    }
}

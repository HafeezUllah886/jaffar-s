<?php

namespace App\Http\Controllers\reports;

use App\Http\Controllers\Controller;
use App\Models\accounts;
use App\Models\purchase;
use App\Models\purchase_details;
use Illuminate\Http\Request;

class VendorWisePurchaseReportController extends Controller
{
    public function index()
    {
        $vendors = accounts::vendor()->get();
        return view('reports.vendorwisepurchase.index', compact('vendors'));
    }

    public function data($from, $to, $vendorID)
    {
        $purchases = purchase::where('vendorID', $vendorID)->whereBetween('date', [$from, $to])->get();

        $vendor = accounts::find($vendorID);

        return view('reports.vendorwisepurchase.details', compact('from', 'to', 'purchases', 'vendor'));
    }
}

<?php

namespace App\Http\Controllers\reports;

use App\Http\Controllers\Controller;
use App\Models\accounts;
use App\Models\products;
use Illuminate\Http\Request;

class StockReportController extends Controller
{
    public function index()
    {
        $vendors = accounts::vendor()->get();
        return view('reports.stockReport.index', compact('vendors'));
    }

    public function details($vendorID)
    {
       $products = products::where('vendor_id', $vendorID)->get();
       $vendor = accounts::find($vendorID);
        return view('reports.stockReport.details', compact('products', 'vendor'));
    }
}

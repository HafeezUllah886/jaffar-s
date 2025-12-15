<?php

namespace App\Http\Controllers\reports;

use App\Http\Controllers\Controller;
use App\Models\accounts;
use App\Models\purchase;
use App\Models\purchase_details;
use App\Models\sales;
use Illuminate\Http\Request;

class CustomerWiseSaleReportController extends Controller
{
    public function index()
    {
        $customers = accounts::customer()->get();
        return view('reports.customerwisesale.index', compact('customers'));
    }

    public function data($from, $to, $customerID)
    {
        $sales = sales::where('customerID', $customerID)->whereBetween('date', [$from, $to])->get();

        $customer = accounts::find($customerID);

        return view('reports.customerwisesale.details', compact('from', 'to', 'sales', 'customer'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\accounts;
use App\Models\orders;
use App\Models\products;
use App\Models\sale_details;
use App\Models\sale_payments;
use App\Models\sales;
use App\Models\stock;
use App\Models\transactions;
use App\Models\units;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $start = $request->start ?? now()->toDateString();
        $end = $request->end ?? now()->toDateString();

        $sales = sales::with('payments')->whereBetween('date', [$start, $end])->orderby('id', 'desc')->get();

        return view('sales.index', compact('sales', 'start', 'end'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $products = products::orderby('name', 'asc')->get();
        foreach ($products as $product) {
            $stock = getStock($product->id);
            $product->stock = $stock;
        }
        $units = units::all();
        $customers = accounts::customer()->get();
        $accounts = accounts::business()->get();
        $orderbookers = User::where('role', 'Orderbooker')->get();

        return view('sales.create', compact('products', 'units', 'customers', 'accounts', 'orderbookers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            if ($request->isNotFilled('id')) {
                throw new Exception('Please Select Atleast One Product');
            }

            $request->validate([
                'customerID' => 'required',
                'date' => 'required',
                'buyer_name' => 'required|string',
                'buyer_ntn_cnic' => 'required|string',
                'buyer_province' => 'required|string',
                'buyer_address' => 'required|string',
                'buyer_registration_type' => 'required|string',
            ], [
                'buyer_name.required' => 'Buyer Name is required for FBR Digital Invoicing.',
                'buyer_ntn_cnic.required' => 'Buyer NTN/CNIC is required for FBR Digital Invoicing (use 9999999-9 if unregistered end consumer).',
                'buyer_province.required' => 'Buyer Province is required for FBR Digital Invoicing.',
                'buyer_address.required' => 'Buyer Address is required for FBR Digital Invoicing.',
                'buyer_registration_type.required' => 'Buyer Registration Type is required for FBR Digital Invoicing.',
            ]);

            DB::beginTransaction();
            $ref = getRef();
            $sale = sales::create(
                [
                    'customerID' => $request->customerID,
                    'date' => $request->date,
                    'notes' => $request->notes,
                    'discount' => $request->discount1,
                    'fright' => $request->fright,
                    'fright1' => $request->fright1,
                    'wh' => $request->whTax ?? 0,
                    'orderbookerID' => $request->orderbookerID,
                    'deliverymanID' => $request->deliverymanID ?? 1,
                    'refID' => $ref,
                    'buyer_ntn_cnic' => $request->buyer_ntn_cnic,
                    'buyer_name' => $request->buyer_name,
                    'buyer_province' => $request->buyer_province,
                    'buyer_address' => $request->buyer_address,
                    'buyer_registration_type' => $request->buyer_registration_type ?? 'Unregistered',
                    'fbr_status' => 'pending',
                ]
            );

            // Sync master customer data if fields were previously empty
            $customer = accounts::find($request->customerID);
            if ($customer) {
                $custUpdates = [];
                if (empty($customer->ntn) && empty($customer->cnic) && $request->filled('buyer_ntn_cnic')) {
                    if (strlen(preg_replace('/[^0-9]/', '', $request->buyer_ntn_cnic)) == 13) {
                        $custUpdates['cnic'] = $request->buyer_ntn_cnic;
                    } else {
                        $custUpdates['ntn'] = $request->buyer_ntn_cnic;
                    }
                }
                if (empty($customer->province) && $request->filled('buyer_province')) {
                    $custUpdates['province'] = $request->buyer_province;
                }
                if (empty($customer->address) && $request->filled('buyer_address')) {
                    $custUpdates['address'] = $request->buyer_address;
                }
                if (empty($customer->registration_type) && $request->filled('buyer_registration_type')) {
                    $custUpdates['registration_type'] = $request->buyer_registration_type;
                }
                if (!empty($custUpdates)) {
                    $customer->update($custUpdates);
                }
            }

            $ids = $request->id;

            $total = 0;
            foreach ($ids as $key => $id) {
                $unit = units::find($request->unit[$key]);
                $qty1 = ($request->qty[$key] * $unit->value) + $request->bonus[$key];
                $qty = $request->qty[$key] * $unit->value;
                $price = $request->price[$key];
                $total += $request->ti[$key];
                sale_details::create(
                    [
                        'salesID' => $sale->id,
                        'customerID' => $sale->customerID,
                        'orderbookerID' => $sale->orderbookerID,
                        'productID' => $id,
                        'price' => $price,
                        'qty' => $qty,
                        'discount' => $request->discount[$key],
                        'ti' => $request->ti[$key],
                        'tp' => $request->tp[$key],
                        'gst' => $request->gst[$key],
                        'gstValue' => $request->gstValue[$key],
                        'date' => $request->date,
                        'bonus' => $request->bonus[$key],
                        'unitID' => $unit->id,
                        'unitValue' => $unit->value,
                        'refID' => $ref,
                    ]
                );
                createStock($id, 0, $qty1, $request->date, "Sold in Inv # $sale->id", $ref);
            }

            $whTax = $total * $request->whTax / 100;

            $net = ($total + $whTax + $request->fright1) - ($request->discount1 + $request->fright);

            $sale->update(
                [

                    'whValue' => $whTax,
                    'net' => $net,
                ]
            );

            if ($request->status == 'paid') {
                sale_payments::create(
                    [
                        'salesID' => $sale->id,
                        'accountID' => $request->accountID,
                        'date' => $request->date,
                        'amount' => $net,
                        'notes' => 'Full Paid',
                        'refID' => $ref,
                    ]
                );

                createTransaction($request->accountID, $request->date, $net, 0, "Payment of Inv No. $sale->id", $ref);
                createTransaction($request->customerID, $request->date, $net, $net, "Payment of Inv No. $sale->id", $ref);
            } else {
                createTransaction($request->customerID, $request->date, 0, $net, "Pending Amount of Inv No. $sale->id", $ref);
            }

            if ($request->orderID) {
                $order = orders::find($request->orderID);
                $order->update(
                    [
                        'saleID' => $sale->id,
                        'status' => 'Completed',
                    ]
                );
            }

            DB::commit();

            return to_route('sale.show', $sale->id)->with('success', 'Sale Created');

        } catch (Exception $e) {
            DB::rollback();

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(sales $sale)
    {
        $balance = spotBalance($sale->customerID, $sale->refID);

        return view('sales.view', compact('sale', 'balance'));
    }

    public function printAll($from, $to)
    {
        $start = $from;
        $end = $to;
        $sales = sales::with('payments')->whereBetween('date', [$start, $end])->orderby('id', 'desc')->get();
        foreach ($sales as $sale) {
            $sale->balance = spotBalance($sale->customerID, $sale->refID);
        }

        return view('sales.printall', compact('sales', 'start', 'end'));
    }

    public function gatePass($id)
    {
        $sale = sales::find($id);

        return view('sales.gatepass', compact('sale'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(sales $sale)
    {
        $products = products::orderby('name', 'asc')->get();
        $units = units::all();
        $customers = accounts::customer()->get();
        $accounts = accounts::business()->get();
        $orderbookers = User::where('role', 'Orderbooker')->get();

        return view('sales.edit', compact('products', 'units', 'customers', 'accounts', 'sale', 'orderbookers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $sale = sales::find($id);
            foreach ($sale->payments as $payment) {
                transactions::where('refID', $payment->refID)->delete();
                $payment->delete();
            }
            foreach ($sale->details as $product) {
                stock::where('refID', $product->refID)->delete();
                $product->delete();
            }
            transactions::where('refID', $sale->refID)->delete();
            $ref = $sale->refID;
            $sale->update(
                [
                    'customerID' => $request->customerID,
                    'date' => $request->date,
                    'notes' => $request->notes,
                    'discount' => $request->discount1,
                    'fright' => $request->fright,
                    'fright1' => $request->fright1,
                    'wh' => $request->whTax,
                    'orderbookerID' => $request->orderbookerID,
                    'refID' => $ref,
                    'buyer_ntn_cnic' => $request->buyer_ntn_cnic ?? $sale->buyer_ntn_cnic,
                    'buyer_name' => $request->buyer_name ?? $sale->buyer_name,
                    'buyer_province' => $request->buyer_province ?? $sale->buyer_province,
                    'buyer_address' => $request->buyer_address ?? $sale->buyer_address,
                    'buyer_registration_type' => $request->buyer_registration_type ?? $sale->buyer_registration_type,
                ]
            );

            $ids = $request->id;

            $total = 0;
            foreach ($ids as $key => $id) {
                $unit = units::find($request->unit[$key]);
                $qty1 = ($request->qty[$key] * $unit->value) + $request->bonus[$key];
                $qty = $request->qty[$key] * $unit->value;
                $price = $request->price[$key];
                $total += $request->ti[$key];
                sale_details::create(
                    [
                        'salesID' => $sale->id,
                        'customerID' => $sale->customerID,
                        'orderbookerID' => $sale->orderbookerID,
                        'productID' => $id,
                        'price' => $price,
                        'qty' => $qty,
                        'discount' => $request->discount[$key],
                        'ti' => $request->ti[$key],
                        'tp' => $request->tp[$key],
                        'gst' => $request->gst[$key],
                        'gstValue' => $request->gstValue[$key],
                        'date' => $request->date,
                        'bonus' => $request->bonus[$key],
                        'unitID' => $unit->id,
                        'unitValue' => $unit->value,
                        'refID' => $ref,
                    ]
                );
                createStock($id, 0, $qty1, $request->date, "Sold in Inv # $sale->id", $ref);
            }
            $whTax = $total * $request->whTax / 100;

            $net = ($total + $whTax + $request->fright1) - ($request->discount1 + $request->fright);
            dashboard();
            $sale->update(
                [

                    'whValue' => $whTax,
                    'net' => $net,
                ]
            );

            if ($request->status == 'paid') {
                sale_payments::create(
                    [
                        'salesID' => $sale->id,
                        'accountID' => $request->accountID,
                        'date' => $request->date,
                        'amount' => $net,
                        'notes' => 'Full Paid',
                        'refID' => $sale->refID,
                    ]
                );
                createTransaction($request->accountID, $request->date, $net, 0, "Payment of Inv No. $sale->id", $sale->refID);
                createTransaction($request->customerID, $request->date, $net, $net, "Payment of Inv No. $sale->id", $ref);
            } else {
                createTransaction($request->customerID, $request->date, 0, $net, "Pending Amount of Inv No. $sale->id", $sale->refID);
            }
            DB::commit();

            return to_route('sale.index')->with('success', 'Sale Updated');
        } catch (Exception $e) {
            DB::rollBack();

            return to_route('sale.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $sale = sales::find($id);
            foreach ($sale->payments as $payment) {
                transactions::where('refID', $payment->refID)->delete();
                $payment->delete();
            }
            foreach ($sale->details as $product) {
                stock::where('refID', $product->refID)->delete();
                $product->delete();
            }
            transactions::where('refID', $sale->refID)->delete();
            $sale->delete();
            DB::commit();
            session()->forget('confirmed_password');

            return to_route('sale.index')->with('success', 'Sale Deleted');
        } catch (Exception $e) {
            DB::rollBack();
            session()->forget('confirmed_password');

            return to_route('sale.index')->with('error', $e->getMessage());
        }
    }

    public function getSignleProduct($id)
    {
        $product = products::with('unit')->find($id);
        $stocks = stock::select(DB::raw('SUM(cr) - SUM(db) AS balance'))
            ->where('productID', $product->id)
            ->get();

        $product->stock = getStock($id);

        return $product;
    }

    public function getCustomer($id)
    {
        $customer = accounts::find($id);

        if (!$customer) {
            return response()->json([], 404);
        }

        return response()->json([
            'id' => $customer->id,
            'title' => $customer->title,
            'ntn' => $customer->ntn,
            'cnic' => $customer->cnic,
            'strn' => $customer->strn,
            'address' => $customer->address,
            'province' => $customer->province ?: 'Sindh',
            'registration_type' => $customer->registration_type ?: ($customer->ntn ? 'Registered' : 'Unregistered'),
            'contact' => $customer->contact,
        ]);
    }

    public function getProductByCode($code)
    {
        $product = products::where('code', $code)->first();
        if ($product) {
            return $product->id;
        }

        return 'Not Found';
    }
}

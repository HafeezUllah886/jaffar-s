@extends('layout.popups')
@section('content')
    <div class="row justify-content-center">
        <div class="col-xxl-9">
            <div class="card" id="demo">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hstack gap-2 justify-content-end d-print-none p-2 mt-4">
                            @if ($sale->fbr_status !== 'sent')
                                <form action="{{ route('sale.sendToFbr', $sale->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="ri-send-plane-fill align-middle me-1"></i> Send to FBR
                                    </button>
                                </form>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle fs-12 p-2">
                                    <i class="ri-checkbox-circle-fill me-1"></i> FBR Verified:
                                    {{ $sale->fbr_invoice_number }}
                                </span>
                            @endif
                            <a href="https://web.whatsapp.com/" target="_blank" class="btn btn-success ml-4"><i
                                    class="ri-whatsapp-line mr-4"></i> Whatsapp</a>
                            <a href="javascript:window.print()" class="btn btn-primary ml-4"><i
                                    class="ri-printer-line mr-4"></i> Print</a>
                        </div>
                        <div class="card-header border-bottom-dashed p-4">
                            @include('layout.header')
                        </div>
                        <!--end card-header-->
                    </div><!--end col-->
                    <div class="col-lg-12 ">
                        <div class="row">
                            <div class="col-4"></div>
                            <div class="col-4 text-center">
                                <h2>SALES TAX INVOICE</h2>
                            </div>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-2">
                                    <p class="text-muted mb-2 text-uppercase fw-semibold">Inv #</p>
                                    <h5 class="fs-14 mb-0">{{ $sale->id }}</h5>
                                </div>
                                <div class="col-5">
                                    <p class="text-muted mb-2 text-uppercase fw-semibold">Customer (Buyer)</p>
                                    <h5 class="fs-14 mb-0"> <span class="text-muted">M/S :</span>
                                        {{ $sale->buyer_name ?: $sale->customer->title }}</h5>
                                    @if ($sale->customerID != 2)
                                        <h5 class="fs-14 mb-0"> <span class="text-muted">CNIC/NTN :</span>
                                            {{ $sale->buyer_ntn_cnic ?: ($sale->customer->ntn ?: $sale->customer->cnic ?? 'NA') }}
                                            | <span class="text-muted">Contact :</span>
                                            {{ $sale->customer->contact ?? 'NA' }}</h5>
                                        <h5 class="fs-14 mb-0"> <span class="text-muted">Type :</span>
                                            {{ $sale->buyer_registration_type ?: $sale->customer->c_type }} | <span
                                                class="text-muted">Province :</span>
                                            {{ $sale->buyer_province ?: $sale->customer->province ?? 'Sindh' }} | <span
                                                class="text-muted">STRN #</span> {{ $sale->customer->strn ?? 'NA' }}</h5>
                                        <h5 class="fs-14 mb-0"> <span class="text-muted">Address :</span>
                                            {{ $sale->buyer_address ?: $sale->customer->address ?? 'NA' }}</h5>
                                    @endif

                                </div>
                                <div class="col-3">
                                    <p class="text-muted mb-2 text-uppercase fw-semibold">Order Booker</p>
                                    <h5 class="fs-14 mb-0">{{ $sale->orderbooker->name }}</h5>
                                </div>
                                <div class="col-2">
                                    <p class="text-muted mb-2 text-uppercase fw-semibold">Date</p>
                                    <h5 class="fs-14 mb-0">{{ date('d M Y', strtotime($sale->date)) }}</h5>
                                </div>
                                <!--end col-->
                                <!--end col-->
                            </div>
                            <!--end row-->
                        </div>
                        <!--end card-body-->
                    </div><!--end col-->
                    <div class="col-lg-12">
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-borderless text-center table-nowrap align-middle mb-0">
                                    <thead>
                                        <tr class="table-active">
                                            <th scope="col" style="width: 50px;">#</th>
                                            <th scope="col" class="text-start">Code</th>
                                            <th scope="col" class="text-start">Product</th>
                                            <th scope="col" class="text-end">Unit</th>
                                            <th scope="col" class="text-end">Qty</th>
                                            <th scope="col" class="text-end">Bonus</th>
                                            <th scope="col" class="text-end">Price</th>
                                            <th scope="col" class="text-end">Discount</th>
                                            <th scope="col" class="text-end">Tax (Inc)</th>
                                            <th scope="col" class="text-end">RP</th>
                                            <th scope="col" class="text-end">GST {{ $sale->details[0]->gst }}%</th>
                                        </tr>
                                    </thead>

                                    <tbody id="products-list">
                                        @php
                                            $totalQty = 0;
                                            $totalBonus = 0;
                                            $discount = 0;
                                            $totalRP = 0;
                                        @endphp
                                        @foreach ($sale->details as $key => $product)
                                            @php
                                                $discount += $product->qty * $product->discount;
                                                $qty = $product->qty / $product->unitValue;
                                                $totalQty += $qty;
                                                $totalBonus += $product->bonus;
                                                $totalRP += $product->tp * ($qty + $product->bonus);
                                            @endphp
                                            <tr class="border-1 border-dark">
                                                <td class="m-1 p-1 border-1 border-dark">{{ $key + 1 }}</td>
                                                <td class="text-start m-1 p-1 border-1 border-dark">
                                                    {{ $product->product->code }}</td>
                                                <td class="text-start m-1 p-1 border-1 border-dark">
                                                    {{ $product->product->name }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ $product->unit->name }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->qty / $product->unitValue) }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->bonus) }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->price, 2) }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->discount, 2) }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->ti, 2) }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->tp, 2) }}</td>
                                                <td class="text-end m-1 p-1 border-1 border-dark">
                                                    {{ number_format($product->gstValue, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        @php
                                            $totalDisc = $sale->details->sum('discount');
                                            $totalTi = $sale->details->sum('ti');
                                            $totalGstVal = $sale->details->sum('gstValue');
                                            $due = $sale->net - $sale->payments->sum('amount');
                                            $paid = $sale->payments->sum('amount');
                                        @endphp
                                        <tr class="border-1 border-dark">
                                            <th colspan="4" class="text-end">Total</th>
                                            <th class="text-end">{{ number_format($totalQty) }}</th>
                                            <th class="text-end">{{ number_format($totalBonus) }}</th>
                                            <th></th>
                                            <th class="text-end">{{ number_format($discount, 2) }}</th>
                                            <th class="text-end">{{ number_format($totalTi, 2) }}</th>
                                            <th class="text-end"></th>
                                            <th class="text-end">{{ number_format($totalGstVal, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <td colspan="6" rowspan="12" class="align-top border-0 p-2 text-start">
                                                @if ($sale->fbr_status === 'sent' && $sale->fbr_invoice_number)
                                                    <div class="border border-2 border-dark p-2 bg-white d-inline-block"
                                                        style="width: 290px;">
                                                        <div
                                                            class="d-flex justify-content-between align-items-center gap-2">
                                                            <div class="p-1 text-center d-flex align-items-center justify-content-center bg-white"
                                                                style="width: 130px; height: 110px;">
                                                                <img src="{{ asset('assets/images/fbr-digital-logo.png') }}"
                                                                    alt="FBR Digital Invoicing"
                                                                    style="max-height: 100px; max-width: 120px; object-fit: contain;">
                                                            </div>
                                                            <div class="p-1 text-center d-flex align-items-center justify-content-center bg-white"
                                                                style="width: 130px; height: 110px;">
                                                                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->margin(0)->generate($sale->fbr_invoice_number) !!}
                                                            </div>
                                                        </div>
                                                        <div class="text-center fw-bold text-dark font-monospace mt-2"
                                                            style="font-size: 10px; word-break: break-all;">
                                                            FBR Inv#: {{ $sale->fbr_invoice_number }}
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                            <th colspan="4" class="text-end p-0 m-0">Tax Exclusive</th>
                                            <th class="text-end p-0 m-0">{{ number_format($totalTi - $totalGstVal, 2) }}
                                            </th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Sales Tax</th>
                                            <th class="text-end p-0 m-0">{{ number_format($totalGstVal, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Gross</th>
                                            <th class="text-end p-0 m-0 border-2 border-start-0 border-end-0 border-dark">
                                                {{ number_format($totalTi, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">WH Tax {{ $sale->wh }}% (+)
                                            </th>
                                            <th class="text-end p-0 m-0">{{ number_format($sale->whValue, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Discount (-)</th>
                                            <th class="text-end p-0 m-0 ">{{ number_format($sale->discount, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Fright (-)</th>
                                            <th class="text-end p-0 m-0 ">{{ number_format($sale->fright, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Fright (+)</th>
                                            <th class="text-end p-0 m-0 ">{{ number_format($sale->fright1, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Net Bill</th>
                                            <th class="text-end p-0 m-0 border-2 border-start-0 border-end-0 border-dark">
                                                {{ number_format($sale->net, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Paid</th>
                                            <th class="text-end p-0 m-0">{{ number_format($paid, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Due</th>
                                            <th class="text-end p-0 m-0">{{ number_format($due, 2) }}</th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Previous Balance</th>
                                            <th class="text-end p-0 m-0">
                                                {{ number_format(spotBalanceBefore($sale->customerID, $sale->refID), 2) }}
                                            </th>
                                        </tr>
                                        <tr class="m-0 p-0">
                                            <th colspan="4" class="text-end p-0 m-0">Net Account Balance</th>
                                            <th class="text-end p-0 m-0">
                                                {{ number_format(spotBalance($sale->customerID, $sale->refID), 2) }}</th>
                                        </tr>
                                    </tfoot>
                                </table><!--end table-->
                            </div>

                            @if ($sale->fbr_status === 'issue_sending')
                                <!-- Only visible on screen, HIDDEN on print -->
                                <div class="mt-3 p-3 border border-danger rounded bg-light d-print-none">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="badge bg-danger fs-12 mb-1"><i
                                                    class="ri-error-warning-line me-1"></i> FBR Transmission Issue</span>
                                            <p class="text-danger mb-0 fs-12 fw-semibold">{{ $sale->fbr_error }}</p>
                                            <small class="text-muted">Please resolve this issue and re-transmit to
                                                FBR.</small>
                                        </div>
                                        <div class="text-end">
                                            <form action="{{ route('sale.sendToFbr', $sale->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="ri-refresh-line me-1"></i> Retry FBR
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @elseif($sale->fbr_status === 'pending')
                                <!-- Pending: Only visible on screen, HIDDEN on print -->
                                <div class="mt-3 p-3 border border-secondary border-dashed rounded bg-light d-print-none">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <span class="badge bg-warning text-dark fs-12 mb-1"><i
                                                    class="ri-time-line me-1"></i> FBR Status: Pending</span>
                                            <p class="text-muted mb-0 fs-12">Invoice not yet transmitted to FBR Digital
                                                Invoicing gateway.</p>
                                        </div>
                                        <div class="text-end">
                                            <form action="{{ route('sale.sendToFbr', $sale->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="ri-send-plane-fill me-1"></i> Send to FBR Now
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer">
                            @if ($sale->notes != '')
                                <p><strong>Notes: </strong>{{ $sale->notes }}</p>
                            @endif
                            <p class="text-center urdu"><strong>نوٹ: مال آپ کے آرڈر کے مطابق بھیجا جا رہا ہے۔ مال ایکسپائر
                                    یا خراب ہونے کی صورت میں واپس نہیں لیا جائے گا۔ دکاندار سیلزمین کے ساتھ کسی قسم کے ذاتی
                                    لین دین کا ذمہ دار خود ہوگا۔</strong></p>

                        </div>
                        <!--end card-body-->
                    </div><!--end col-->

                </div><!--end row-->
            </div>
            <!--end card-->
        </div>
        <!--end col-->
    </div>
    <!--end row-->
@endsection

@section('page-css')
    <link rel="stylesheet" href="{{ asset('assets/libs/datatable/datatable.bootstrap5.min.css') }}" />
    <!--datatable responsive css-->
    <link rel="stylesheet" href="{{ asset('assets/libs/datatable/responsive.bootstrap.min.css') }}" />

    <link rel="stylesheet" href="{{ asset('assets/libs/datatable/buttons.dataTables.min.css') }}">
    <link href='https://fonts.googleapis.com/css?family=Noto Nastaliq Urdu' rel='stylesheet'>
    <style>
        .urdu {
            font-family: 'Noto Nastaliq Urdu';
            font-size: 12px;
        }

        @media print {
            .d-print-none {
                display: none !important;
            }
        }
    </style>
@endsection
@section('page-js')
    <script src="{{ asset('assets/libs/datatable/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/buttons.print.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/vfs_fonts.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/pdfmake.min.js') }}"></script>
    <script src="{{ asset('assets/libs/datatable/jszip.min.js') }}"></script>

    <script src="{{ asset('assets/js/pages/datatables.init.js') }}"></script>
@endsection

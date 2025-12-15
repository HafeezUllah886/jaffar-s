@extends('layout.popups')
@section('content')
    <div class="row justify-content-center">
        <div class="col-xxl-9">
            <div class="card" id="demo">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hstack gap-2 justify-content-end d-print-none p-2 mt-4">
                            <a href="https://web.whatsapp.com/" target="_blank" class="btn btn-success ml-4"><i
                                    class="ri-whatsapp-line mr-4"></i> Whatsapp</a>
                            <a href="javascript:window.print()" class="btn btn-success ml-4"><i
                                    class="ri-printer-line mr-4"></i> Print</a>
                        </div>
                        <div class="card-header border-bottom-dashed p-4">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <h1>JAFFAR & BROTHERS</h1>
                                </div>
                                <div class="flex-shrink-0 mt-sm-0 mt-3">
                                    <h3>Stock Report</h3>
                                </div>
                            </div>
                        </div>
                        <!--end card-header-->
                    </div><!--end col-->
                    <div class="col-lg-12">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-lg-3 col-6">
                                    <p class="text-muted mb-2 text-uppercase fw-semibold">Vendor</p>
                                    <h5 class="fs-14 mb-0">{{ $vendor->title }}</h5>
                                </div>
                                <!--end col-->
                                <div class="col-lg-3 col-6">
                                    <p class="text-muted mb-2 text-uppercase fw-semibold">Printed On</p>
                                    <h5 class="fs-14 mb-0"><span id="total-amount">{{ date('d M Y') }}</span></h5>
                                    {{-- <h5 class="fs-14 mb-0"><span id="total-amount">{{ \Carbon\Carbon::now()->format('h:i A') }}</span></h5> --}}
                                </div>
                                <!--end col-->
                            </div>
                            <!--end row-->
                        </div>
                        <!--end card-body-->
                    </div><!--end col-->
                    <div class="col-lg-12">
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-bordered text-center table-nowrap align-middle mb-0"
                                    id="datatables">
                                    <thead>
                                        <tr class="table-active">
                                            <th scope="col" class="text-start p-1">#</th>
                                            <th scope="col" class="text-start p-1">Product</th>
                                            <th scope="col" class="text-start p-1">Category</th>
                                            <th scope="col" class="text-end p-1">Stock</th>
                                            <th scope="col" class="text-end p-1">Stock Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $totalValue = 0;
                                        @endphp

                                        @foreach ($products as $key => $product)
                                            @php
                                                $totalValue += productStockValue($product->id);
                                            @endphp
                                            <tr>
                                                <td class="text-start p-1">{{ $key + 1 }}</td>
                                                <td class="text-start p-1">{{ $product->name }}</td>
                                                <td class="text-start p-1">{{ $product->category->name }}</td>
                                                <td class="text-end p-1">{{ getStock($product->id) }}</td>
                                                <td class="text-end p-1">
                                                    {{ number_format(productStockValue($product->id), 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr>
                                            <th colspan="4" class="text-end p-1">Total:</th>
                                            <th class="text-end p-1">{{ number_format($totalValue, 2) }}</th>
                                        </tr>

                                    </tbody>
                                    <tfoot>

                                    </tfoot>
                                </table><!--end table-->
                            </div>

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
    <script>
        $('#datatables').DataTable({
            responsive: false,
            dom: 'Bfrtip',
            buttons: ['print', 'excel', 'pdf'],
            footerCallback: function(row, data, start, end, display) {
                var api = this.api();

                // Helper function to get integer or float from string
                var intVal = function(i) {
                    return typeof i === 'string' ?
                        i.replace(/[\$,]/g, '') * 1 :
                        typeof i === 'number' ?
                        i :
                        0;
                };

                // Total for Tax Exc column
                var totalTaxExc1 = api
                    .column(4, {
                        search: 'applied'
                    })
                    .data()
                    .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);

                var totalTaxExc = api
                    .column(5, {
                        search: 'applied'
                    })
                    .data()
                    .reduce(function(a, b) {
                        return intVal(a) + intVal(b);
                    }, 0);


                // Update the footer
                $(api.column(4).footer()).html(totalTaxExc1.toFixed(2));
                $(api.column(5).footer()).html(totalTaxExc.toFixed(2));
            },
        });
    </script>
@endsection

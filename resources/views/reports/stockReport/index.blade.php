@extends('layout.app')
@section('content')
    <div class="row d-flex justify-content-center">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h3>View Stock Report</h3>
                </div>
                <div class="card-body">
                    <div class="form-group mt-2">
                        <label for="vendorID">Vendor</label>
                        <select name="vendorID" id="vendorID" class="form-control">
                            @foreach ($vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group mt-2">
                        <button class="btn btn-success w-100" id="viewBtn">View Report</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('page-js')
    <script>
        $("#viewBtn").on("click", function() {

            var id = $("#vendorID").find(":selected").val();

            var url =
                "{{ route('reportStockReportData', ['vendorID' => ':id']) }}"
                .replace(':id', id);

            window.open(url, "_blank", "width=1000,height=800");
        });
    </script>
@endsection

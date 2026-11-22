@extends('layout.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <form>
                <div class="row">
                    <div class="col-md-4">
                        <div class="input-group mb-3">
                            <span class="input-group-text" id="basic-addon1">From</span>
                            <input type="date" class="form-control" placeholder="Username" id="start" name="start" value="{{$start}}" aria-label="Username" aria-describedby="basic-addon1">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group mb-3">
                            <span class="input-group-text" id="basic-addon1">To</span>
                            <input type="date" class="form-control" placeholder="Username" id="end" name="end" value="{{$end}}" aria-label="Username" aria-describedby="basic-addon1">
                        </div>
                    </div>
                    <div class="col-md-2">
                       <input type="submit" value="Filter" class="btn btn-success w-100">
                    </div>
                    <div class="col-md-2">
                       <button type="button" class="btn btn-info w-100" onclick="printAll()">Print All</button>
                    </div>
                </div>
            </form>
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <h3>Sales</h3>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <table class="table align-middle" id="buttons-datatables">
                        <thead>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>FBR Status</th>
                            {{-- <th>Paid</th>
                            <th>Due</th> --}}
                            <th>Action</th>
                        </thead>
                        <tbody>
                            @foreach ($sales as $key => $sale)
                                @php
                                    $amount = $sale->net;
                                    $paid = $sale->payments->sum('amount');
                                    $due = $amount - $paid;
                                @endphp
                                <tr id="sale_row_{{ $sale->id }}">
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <strong>{{ $sale->customer->title }}</strong>
                                        @if($sale->customer->ntn)
                                            <div class="text-muted" style="font-size: 11px;">NTN: {{ $sale->customer->ntn }}</div>
                                        @endif
                                    </td>
                                    <td>{{ date('d M Y', strtotime($sale->date)) }}</td>
                                    <td>{{ number_format($amount) }}</td>
                                    <td id="fbr_status_col_{{ $sale->id }}">
                                        @if($sale->fbr_status === 'sent')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="ri-checkbox-circle-fill me-1 align-middle"></i> Sent to FBR
                                            </span>
                                            <div class="mt-1 font-monospace text-primary fw-semibold" style="font-size: 11px;">
                                                <i class="ri-qr-code-line align-middle"></i> {{ $sale->fbr_invoice_number }}
                                            </div>
                                            @if($sale->fbr_submitted_at)
                                                <div class="text-muted" style="font-size: 10px;">{{ date('d M, h:i A', strtotime($sale->fbr_submitted_at)) }}</div>
                                            @endif
                                        @elseif($sale->fbr_status === 'issue_sending')
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="ri-error-warning-fill me-1 align-middle"></i> Issue Sending
                                            </span>
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;" onclick="viewFbrError({{ $sale->id }})">
                                                    <i class="ri-information-line"></i> View Issue
                                                </button>
                                            </div>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                <i class="ri-time-line me-1 align-middle"></i> Pending
                                            </span>
                                            <div class="text-muted" style="font-size: 10px;">Not sent to FBR</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            @if($sale->fbr_status !== 'sent')
                                                <button type="button" class="btn btn-sm btn-primary py-1 px-2" id="btn_send_fbr_{{ $sale->id }}" onclick="sendToFbr({{ $sale->id }})" title="Transmit to FBR IRIS">
                                                    <i class="ri-send-plane-fill align-middle me-1"></i> Send to FBR
                                                </button>
                                            @endif
                                            <div class="dropdown">
                                                <button class="btn btn-soft-secondary btn-sm dropdown" type="button"
                                                    data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="ri-more-fill align-middle"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($sale->fbr_status !== 'sent')
                                                    <li id="menu_fbr_{{ $sale->id }}">
                                                        <button type="button" class="dropdown-item text-primary" onclick="sendToFbr({{ $sale->id }})">
                                                            <i class="ri-send-plane-fill align-bottom me-2 text-primary"></i> Send to FBR
                                                        </button>
                                                    </li>
                                                    @endif
                                                    <li>
                                                        <button type="button" class="dropdown-item" onclick="previewFbrPayload({{ $sale->id }})">
                                                            <i class="ri-code-box-line align-bottom me-2 text-info"></i> FBR Payload
                                                        </button>
                                                    </li>
                                                    @if($sale->fbr_status === 'issue_sending')
                                                    <li id="menu_err_{{ $sale->id }}">
                                                        <button type="button" class="dropdown-item text-danger" onclick="viewFbrError({{ $sale->id }})">
                                                            <i class="ri-error-warning-line align-bottom me-2 text-danger"></i> View FBR Issue
                                                        </button>
                                                    </li>
                                                    @endif
                                                    <li>
                                                        <button class="dropdown-item" onclick="newWindow('{{route('sale.show', $sale->id)}}')"
                                                            onclick=""><i
                                                                class="ri-eye-fill align-bottom me-2 text-muted"></i>
                                                            View
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item" onclick="newWindow('{{route('sale.gatePass', $sale->id)}}')"
                                                            onclick=""><i
                                                                class="ri-eye-fill align-bottom me-2 text-muted"></i>
                                                            Gate Pass
                                                        </button>
                                                    </li>
                                                    @if(!$sale->orderID)
                                                    <li>
                                                        <a class="dropdown-item" onclick="newWindow('{{route('sale.edit', $sale->id)}}')">
                                                            <i class="ri-pencil-fill align-bottom me-2 text-muted"></i>
                                                            Edit
                                                        </a>
                                                    </li>
                                                    @endif
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="{{route('sale.delete', $sale->id)}}">
                                                            <i class="ri-delete-bin-2-fill align-bottom me-2 text-danger"></i>
                                                            Delete
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
    <!-- FBR Error Modal -->
    <div class="modal fade" id="fbrErrorModal" tabindex="-1" aria-labelledby="fbrErrorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="fbrErrorModalLabel"><i class="ri-error-warning-line me-1"></i> FBR Transmission Issue Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger mb-3" id="fbrErrorMessage"></div>
                    <div class="mb-2 fw-semibold">FBR Raw Response:</div>
                    <pre class="bg-light p-3 rounded border font-monospace text-dark" style="max-height: 300px; overflow-y: auto;" id="fbrErrorRaw"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="fbrRetryBtn" onclick="">
                        <i class="ri-refresh-line me-1"></i> Retry Sending
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FBR Payload Preview Modal -->
    <div class="modal fade" id="fbrPayloadModal" tabindex="-1" aria-labelledby="fbrPayloadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="fbrPayloadModalLabel"><i class="ri-code-box-line me-1"></i> FBR JSON Payload Preview (3rd Schedule Goods)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted fs-12 mb-2">This is the JSON structure prepared according to FBR Digital Invoicing specifications for Third Schedule items:</p>
                    <pre class="bg-dark text-success p-3 rounded font-monospace" style="max-height: 400px; overflow-y: auto;" id="fbrPayloadPre"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-css')
<link rel="stylesheet" href="{{ asset('assets/libs/datatable/datatable.bootstrap5.min.css') }}" />
<!--datatable responsive css-->
<link rel="stylesheet" href="{{ asset('assets/libs/datatable/responsive.bootstrap.min.css') }}" />

<link rel="stylesheet" href="{{ asset('assets/libs/datatable/buttons.dataTables.min.css') }}">
@endsection
@section('page-js')
    <script src="{{ asset('assets/libs/datatable/jquery.dataTables.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/dataTables.bootstrap5.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/dataTables.responsive.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/dataTables.buttons.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/buttons.print.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/buttons.html5.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/vfs_fonts.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/pdfmake.min.js')}}"></script>
    <script src="{{ asset('assets/libs/datatable/jszip.min.js')}}"></script>

    <script src="{{ asset('assets/js/pages/datatables.init.js') }}"></script>

    <script>
        function printAll()
        {
            var from = $("#start").val();
            var to = $("#end").val();
            window.open("{{ url('sales/printall') }}/" + from + "/" + to, '_blank');
        }

        function sendToFbr(saleId) {
            const btn = document.getElementById('btn_send_fbr_' + saleId);
            const origHtml = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';
            }

            fetch("{{ url('sales') }}/" + saleId + "/send-to-fbr", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({})
            })
            .then(res => res.json())
            .then(data => {
                const statusCol = document.getElementById('fbr_status_col_' + saleId);
                if (data.success) {
                    Toastify({
                        text: data.message,
                        className: "success",
                        duration: 4000,
                        close: true,
                        gravity: "top",
                        position: "center",
                        style: {
                            background: "linear-gradient(to right, #00b09b, #96c93d)",
                        }
                    }).showToast();

                    if (statusCol) {
                        statusCol.innerHTML = `
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="ri-checkbox-circle-fill me-1 align-middle"></i> Sent to FBR
                            </span>
                            <div class="mt-1 font-monospace text-primary fw-semibold" style="font-size: 11px;">
                                <i class="ri-qr-code-line align-middle"></i> ${data.invoice_number || ''}
                            </div>
                            <div class="text-muted" style="font-size: 10px;">Just now</div>
                        `;
                    }
                    if (btn) {
                        btn.remove();
                    }
                    const menuItem = document.getElementById('menu_fbr_' + saleId);
                    if (menuItem) menuItem.remove();
                } else {
                    Toastify({
                        text: data.message || "Failed to submit invoice to FBR",
                        className: "danger",
                        duration: 5000,
                        close: true,
                        gravity: "top",
                        position: "center",
                        style: {
                            background: "linear-gradient(to right, #FF5733, #E70000)",
                        }
                    }).showToast();

                    if (statusCol) {
                        statusCol.innerHTML = `
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                <i class="ri-error-warning-fill me-1 align-middle"></i> Issue Sending
                            </span>
                            <div class="mt-1">
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" style="font-size: 11px;" onclick="viewFbrError(${saleId})">
                                    <i class="ri-information-line"></i> View Issue
                                </button>
                            </div>
                        `;
                    }
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="ri-refresh-line align-middle me-1"></i> Retry FBR';
                        btn.className = "btn btn-sm btn-outline-danger py-1 px-2";
                    }
                }
            })
            .catch(err => {
                Toastify({
                    text: "Network or Server Error: " + err.message,
                    className: "danger",
                    duration: 5000,
                    close: true,
                    gravity: "top",
                    position: "center",
                    style: {
                        background: "linear-gradient(to right, #FF5733, #E70000)",
                    }
                }).showToast();
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml || '<i class="ri-send-plane-fill align-middle me-1"></i> Send to FBR';
                }
            });
        }

        function viewFbrError(saleId) {
            fetch("{{ url('sales') }}/" + saleId + "/fbr-error")
            .then(res => res.json())
            .then(data => {
                document.getElementById('fbrErrorMessage').innerHTML = '<strong>Error:</strong> ' + (data.fbr_error || 'Unknown issue.');
                document.getElementById('fbrErrorRaw').innerText = typeof data.fbr_response === 'object' 
                    ? JSON.stringify(data.fbr_response, null, 2) 
                    : (data.fbr_response || 'No raw response stored.');
                
                const retryBtn = document.getElementById('fbrRetryBtn');
                retryBtn.onclick = function() {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('fbrErrorModal'));
                    if (modal) modal.hide();
                    sendToFbr(saleId);
                };

                const modal = new bootstrap.Modal(document.getElementById('fbrErrorModal'));
                modal.show();
            })
            .catch(err => alert('Could not load error details: ' + err.message));
        }

        function previewFbrPayload(saleId) {
            fetch("{{ url('sales') }}/" + saleId + "/fbr-preview")
            .then(res => res.json())
            .then(data => {
                document.getElementById('fbrPayloadPre').innerText = JSON.stringify(data.payload, null, 2);
                const modal = new bootstrap.Modal(document.getElementById('fbrPayloadModal'));
                modal.show();
            })
            .catch(err => alert('Could not generate payload preview: ' + err.message));
        }
    </script>
@endsection

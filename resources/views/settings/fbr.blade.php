@extends('layout.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0">FBR IRIS Digital Invoicing Settings</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active">FBR Settings</li>
                </ol>
            </div>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ri-check-double-line me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="ri-error-warning-line me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<form action="{{ route('settings.fbr.update') }}" method="POST">
    @csrf
    <div class="row">
        <!-- Left Column: Settings -->
        <div class="col-lg-8">
            <!-- 1. Seller Profile -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-building-line text-primary me-2"></i>Seller Details (Required by FBR)
                    </h5>
                    <span class="badge bg-primary-subtle text-primary">Mandatory Header</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="seller_ntn_cnic" class="form-label fw-semibold">Seller NTN / CNIC <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="seller_ntn_cnic" name="seller_ntn_cnic"
                                value="{{ old('seller_ntn_cnic', $settings->seller_ntn_cnic) }}"
                                placeholder="e.g. 1234567-8 or 13-digit CNIC" required>
                            <div class="form-text">Your registered National Tax Number with FBR (exact 7 digits for NTN, 13 for CNIC).</div>
                        </div>

                        <div class="col-md-6">
                            <label for="seller_business_name" class="form-label fw-semibold">Seller Business Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="seller_business_name" name="seller_business_name"
                                value="{{ old('seller_business_name', $settings->seller_business_name) }}"
                                placeholder="e.g. JAFFAR MARKETING" required>
                            <div class="form-text">Must match the registered business name in IRIS portal.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="seller_province" class="form-label fw-semibold">Seller Province <span class="text-danger">*</span></label>
                            <select class="form-select" id="seller_province" name="seller_province" required>
                                @php
                                    $provinces = ['Sindh', 'Punjab', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Gilgit-Baltistan', 'Azad Jammu & Kashmir'];
                                @endphp
                                @foreach ($provinces as $prov)
                                    <option value="{{ $prov }}" @selected(old('seller_province', $settings->seller_province) == $prov)>{{ $prov }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Province where your business is tax-registered.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="seller_address" class="form-label fw-semibold">Seller Business Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="seller_address" name="seller_address"
                                value="{{ old('seller_address', $settings->seller_address) }}"
                                placeholder="e.g. Shop # 1, Main Market, Karachi" required>
                            <div class="form-text">Registered operational address.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Security Token & API Configuration -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="ri-key-2-line text-warning me-2"></i>Security Token & API Configuration
                    </h5>
                    <div class="form-check form-switch form-switch-md mb-0">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                            @checked(old('is_active', $settings->is_active))>
                        <label class="form-check-label fw-semibold ms-1" for="is_active">Enable Integration</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="token" class="form-label fw-semibold">FBR IRIS Security Token (Bearer Token)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="ri-lock-password-line"></i></span>
                                <input type="password" class="form-control font-monospace" id="token" name="token"
                                    value="{{ old('token', $settings->token) }}"
                                    placeholder="Enter your Bearer Token generated from IRIS portal">
                                <button class="btn btn-outline-secondary" type="button" id="toggleTokenBtn" onclick="toggleTokenVisibility()">
                                    <i class="ri-eye-line" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Obtained from the FBR IRIS portal under <strong>API Integration Settings</strong>. Passed as <code>Authorization: Bearer &lt;token&gt;</code> in all API calls.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Environment Mode <span class="text-danger">*</span></label>
                            <div class="d-flex gap-4 mt-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="environment" id="env_sandbox" value="sandbox"
                                        @checked(old('environment', $settings->environment) == 'sandbox')>
                                    <label class="form-check-label fw-bold text-info" for="env_sandbox">
                                        <i class="ri-test-tube-line"></i> Sandbox (Testing)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="environment" id="env_production" value="production"
                                        @checked(old('environment', $settings->environment) == 'production')>
                                    <label class="form-check-label fw-bold text-success" for="env_production">
                                        <i class="ri-shield-check-fill"></i> Production (Live)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 text-end align-self-end">
                            <button type="button" class="btn btn-outline-info" id="testConnBtn" onclick="testFbrConnection()">
                                <i class="ri-radar-line align-middle me-1"></i> Test Gateway Connection
                            </button>
                        </div>

                        <div class="col-12" id="testResultBox" style="display: none;">
                            <div class="alert mb-0" id="testResultAlert"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="sandbox_url" class="form-label fw-semibold">Sandbox Endpoint URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control font-monospace fs-12" id="sandbox_url" name="sandbox_url"
                                value="{{ old('sandbox_url', $settings->sandbox_url) }}" required>
                            <div class="form-text">Default: <code>https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata_sb</code></div>
                        </div>

                        <div class="col-md-6">
                            <label for="production_url" class="form-label fw-semibold">Production Endpoint URL <span class="text-danger">*</span></label>
                            <input type="url" class="form-control font-monospace fs-12" id="production_url" name="production_url"
                                value="{{ old('production_url', $settings->production_url) }}" required>
                            <div class="form-text">Default: <code>https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata</code></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Sale of 3rd Schedule Goods Configuration -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="ri-file-list-3-line text-success me-2"></i>3rd Schedule Goods Compliance Rules
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info border-0 mb-3" style="font-size: 13px;">
                        <i class="ri-information-line me-1"></i>
                        Under <strong>Section 3(2)(a) of the Sales Tax Act, 1990</strong>, goods specified in the Third Schedule are taxed on the printed <strong>Maximum Retail Price (MRP)</strong> rather than the trade or invoice transaction value. The system automatically populates <code>fixedNotifiedValueOrRetailPrice</code> and calculates output tax accordingly.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="sale_type" class="form-label fw-semibold">Sale Type String <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sale_type" name="sale_type"
                                value="{{ old('sale_type', $settings->sale_type) }}" required>
                            <div class="form-text">Mandatory string required by FBR: <code>3rd Schedule Goods</code>.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="scenario_id" class="form-label fw-semibold">Sandbox Scenario ID</label>
                            <input type="text" class="form-control" id="scenario_id" name="scenario_id"
                                value="{{ old('scenario_id', $settings->scenario_id) }}" placeholder="e.g. SN008">
                            <div class="form-text">Default scenario for 3rd Schedule Goods testing is <code>SN008</code>.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="default_hs_code" class="form-label fw-semibold">Default HS Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace" id="default_hs_code" name="default_hs_code"
                                value="{{ old('default_hs_code', $settings->default_hs_code) }}"
                                placeholder="e.g. 2105.0000" required>
                            <div class="form-text">Used when a product doesn't have an individual HS Code assigned.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="default_tax_rate" class="form-label fw-semibold">Default Sales Tax Rate (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="default_tax_rate" name="default_tax_rate"
                                    value="{{ old('default_tax_rate', $settings->default_tax_rate) }}" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Standard rate in Pakistan is currently 18.00%.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end mb-4">
                <button type="submit" class="btn btn-primary btn-lg px-4">
                    <i class="ri-save-line me-1 align-middle"></i> Save FBR Settings
                </button>
            </div>
        </div>

        <!-- Right Column: Status & Quick Guides -->
        <div class="col-lg-4">
            <!-- Active Endpoint Status -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="ri-server-line text-primary me-2"></i>Current Gateway Status</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Integration Status
                            @if ($settings->is_active)
                                <span class="badge bg-success"><i class="ri-checkbox-circle-line me-1"></i>Active</span>
                            @else
                                <span class="badge bg-secondary"><i class="ri-close-circle-line me-1"></i>Disabled</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            Current Environment
                            @if ($settings->environment === 'production')
                                <span class="badge bg-success fs-12">Production (Live)</span>
                            @else
                                <span class="badge bg-info fs-12">Sandbox (Testing)</span>
                            @endif
                        </li>
                        <li class="list-group-item px-0">
                            <small class="text-muted d-block fw-semibold">Active Endpoint URL:</small>
                            <code class="text-break fs-11">{{ $settings->active_url }}</code>
                        </li>
                        <li class="list-group-item px-0">
                            <small class="text-muted d-block fw-semibold">Token Configured:</small>
                            @if (!empty(trim($settings->token)))
                                <span class="text-success fw-bold"><i class="ri-shield-keyhole-line"></i> Token Present ({{ strlen($settings->token) }} characters)</span>
                            @else
                                <span class="text-danger fw-bold"><i class="ri-alert-line"></i> Missing Token</span>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>

            <!-- FBR IRIS Resources & Testing Guide -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="ri-links-line text-success me-2"></i>Official Documentation</h5>
                </div>
                <div class="card-body">
                    <p class="fs-13 text-muted">Use these official guides to register your company and software for FBR Digital Invoicing:</p>
                    <div class="d-grid gap-2">
                        <a href="https://gw.fbr.gov.pk/di_data/v1/di/postinvoicedata" target="_blank" class="btn btn-outline-dark btn-sm text-start">
                            <i class="ri-external-link-line me-1"></i> FBR Gateway Production Endpoint
                        </a>
                        <a href="https://iris.fbr.gov.pk" target="_blank" class="btn btn-outline-success btn-sm text-start">
                            <i class="ri-login-box-line me-1"></i> FBR IRIS 2.0 Portal Login
                        </a>
                    </div>
                </div>
            </div>

            <!-- Verification & Printing Info -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0"><i class="ri-qr-code-line text-info me-2"></i>Invoice Stamp & QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <div class="p-3 border rounded bg-white d-inline-block mb-2">
                        <img src="{{ asset('assets/images/fbr-logo.png') }}" alt="FBR Logo" style="height: 48px;" class="mb-2">
                        <div class="fw-bold fs-12 text-dark">FBR DIGITAL INVOICING</div>
                        <div class="my-2">
                            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(70)->generate('7000007DI1747119701593') !!}
                        </div>
                        <div class="fs-10 text-muted">FBR Invoice #: 7000007DI1747119701593</div>
                    </div>
                    <p class="fs-12 text-muted mb-0">Every transmitted invoice automatically receives this official fiscal seal and scannable QR code.</p>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('page-js')
<script>
    function toggleTokenVisibility() {
        const tokenInput = document.getElementById('token');
        const icon = document.getElementById('toggleIcon');
        if (tokenInput.type === 'password') {
            tokenInput.type = 'text';
            icon.className = 'ri-eye-off-line';
        } else {
            tokenInput.type = 'password';
            icon.className = 'ri-eye-line';
        }
    }

    function testFbrConnection() {
        const btn = document.getElementById('testConnBtn');
        const origHtml = btn.innerHTML;
        const resultBox = document.getElementById('testResultBox');
        const alertDiv = document.getElementById('testResultAlert');

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Testing Gateway...';

        fetch("{{ route('settings.fbr.test') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            resultBox.style.display = 'block';
            if (data.success) {
                alertDiv.className = 'alert alert-success';
                alertDiv.innerHTML = '<i class="ri-checkbox-circle-line me-1"></i> ' + data.message;
            } else {
                alertDiv.className = 'alert alert-warning';
                alertDiv.innerHTML = '<i class="ri-alert-line me-1"></i> ' + data.message;
            }
        })
        .catch(err => {
            resultBox.style.display = 'block';
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = '<i class="ri-error-warning-line me-1"></i> Connection test failed: ' + err.message;
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = origHtml;
        });
    }
</script>
@endsection

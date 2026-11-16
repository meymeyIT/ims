@extends('layouts.app')

@section('title', 'System Settings')

@section('breadcrumb')
    <li class="breadcrumb-item">System</li>
    <li class="breadcrumb-item active" aria-current="page">Settings</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate>
        @csrf
        @method('PUT')

        <div class="row g-4">
            <div class="col-xl-8">
                {{-- Business --}}
                <div class="card mb-4">
                    <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-shop me-1"></i> Business</h2></div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <label for="business_name" class="form-label">Business name <span class="text-danger">*</span></label>
                            <input id="business_name" name="business_name" type="text" maxlength="150" required
                                   value="{{ old('business_name', $values['business_name']) }}" class="form-control @error('business_name') is-invalid @enderror">
                            @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="business_phone" class="form-label">Phone number</label>
                            <input id="business_phone" name="business_phone" type="tel" maxlength="30"
                                   value="{{ old('business_phone', $values['business_phone']) }}" class="form-control @error('business_phone') is-invalid @enderror">
                            @error('business_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="business_email" class="form-label">Email</label>
                            <input id="business_email" name="business_email" type="email" maxlength="150"
                                   value="{{ old('business_email', $values['business_email']) }}" class="form-control @error('business_email') is-invalid @enderror">
                            @error('business_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="business_address" class="form-label">Address</label>
                            <textarea id="business_address" name="business_address" rows="2" maxlength="500"
                                      class="form-control @error('business_address') is-invalid @enderror">{{ old('business_address', $values['business_address']) }}</textarea>
                            @error('business_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 form-text mt-0">Shown on the sidebar, login page, printed purchase orders and reports.</div>
                    </div>
                </div>

                {{-- Localization --}}
                <div class="card mb-4">
                    <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-globe2 me-1"></i> Currency & date</h2></div>
                    <div class="card-body row g-3">
                        <div class="col-md-4">
                            <label for="currency" class="form-label">Currency <span class="text-danger">*</span></label>
                            <select id="currency" name="currency" class="form-select @error('currency') is-invalid @enderror" data-currency>
                                <option value="USD" @selected(old('currency', $values['currency']) === 'USD')>USD: US Dollar ($)</option>
                                <option value="KHR" @selected(old('currency', $values['currency']) === 'KHR')>KHR: Cambodian Riel (៛)</option>
                            </select>
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="khr_exchange_rate" class="form-label">Exchange rate <span class="text-danger">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">1 USD =</span>
                                <input id="khr_exchange_rate" name="khr_exchange_rate" type="number" step="0.01" min="1"
                                       value="{{ old('khr_exchange_rate', $values['khr_exchange_rate']) }}" class="form-control @error('khr_exchange_rate') is-invalid @enderror">
                                <span class="input-group-text">KHR</span>
                                @error('khr_exchange_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-text">Used to show the equivalent in the other currency.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="date_format" class="form-label">Date format <span class="text-danger">*</span></label>
                            <select id="date_format" name="date_format" class="form-select @error('date_format') is-invalid @enderror">
                                @foreach ($dateFormats as $format => $example)
                                    <option value="{{ $format }}" @selected(old('date_format', $values['date_format']) === $format)>{{ $example }}</option>
                                @endforeach
                            </select>
                            @error('date_format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="alert alert-warning small mb-0 d-none" data-currency-warning>
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Changing the currency changes how amounts are <strong>labelled</strong>; existing prices are <strong>not converted</strong>.
                                Choose the currency once, before entering prices.
                            </div>
                            <div class="small text-body-secondary">
                                Time zone: <strong>{{ config('app.timezone') }}</strong> (set with <code>APP_TIMEZONE</code> in the <code>.env</code> file).
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notifications & numbering --}}
                <div class="card">
                    <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-bell me-1"></i> Notifications & document numbers</h2></div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="hidden" name="low_stock_notifications" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="low_stock_notifications" name="low_stock_notifications" value="1"
                                       @checked(old('low_stock_notifications', $values['low_stock_notifications']))>
                                <label class="form-check-label" for="low_stock_notifications">Low-stock alerts (bell)</label>
                            </div>
                            <div class="form-check form-switch mt-2">
                                <input type="hidden" name="low_stock_email" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="low_stock_email" name="low_stock_email" value="1"
                                       @checked(old('low_stock_email', $values['low_stock_email']))>
                                <label class="form-check-label" for="low_stock_email">Also send low-stock alerts by email</label>
                            </div>
                            <div class="form-text">Email needs a mail server configured in <code>.env</code> (<code>MAIL_*</code>).</div>
                        </div>
                        <div class="col-md-3">
                            <label for="purchase_number_prefix" class="form-label">Purchase prefix</label>
                            <input id="purchase_number_prefix" name="purchase_number_prefix" type="text" maxlength="10"
                                   value="{{ old('purchase_number_prefix', $values['purchase_number_prefix']) }}" class="form-control text-uppercase @error('purchase_number_prefix') is-invalid @enderror">
                            @error('purchase_number_prefix')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">e.g. PO-20261116-0001</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label for="receipt_number_prefix" class="form-label">Receipt prefix</label>
                            <input id="receipt_number_prefix" name="receipt_number_prefix" type="text" maxlength="10"
                                   value="{{ old('receipt_number_prefix', $values['receipt_number_prefix']) }}" class="form-control text-uppercase @error('receipt_number_prefix') is-invalid @enderror">
                            @error('receipt_number_prefix')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">e.g. GRN-20261116-0001</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header bg-white"><h2 class="h6 mb-0"><i class="bi bi-image me-1"></i> Business logo</h2></div>
                    <div class="card-body">
                        <div class="image-preview mb-3" id="logoPreview" style="aspect-ratio: 2 / 1">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Business logo">
                            @else
                                <div class="text-body-tertiary text-center"><i class="bi bi-image fs-1 d-block"></i><span class="small">No logo</span></div>
                            @endif
                        </div>
                        <input type="file" id="business_logo" name="business_logo" accept="image/png,image/jpeg,image/webp"
                               class="form-control @error('business_logo') is-invalid @enderror" data-image-preview="#logoPreview">
                        @error('business_logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">PNG, JPG or WEBP, max 1 MB. SVG is not accepted for security reasons.</div>
                        @if ($logoUrl)
                            <div class="form-check mt-2">
                                <input type="hidden" name="remove_logo" value="0">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label small" for="remove_logo">Remove logo</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('dashboard') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save Settings</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script type="module">
        const select = document.querySelector('[data-currency]');
        const warning = document.querySelector('[data-currency-warning]');
        const original = select.value;
        select.addEventListener('change', () => warning.classList.toggle('d-none', select.value === original));
    </script>
@endpush

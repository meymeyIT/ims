@php
    $fields = [
        // name, label, type, columns, required, max, placeholder
        ['name', 'Supplier name', 'text', 'col-md-6', true, 150, ''],
        ['company_name', 'Company name', 'text', 'col-md-6', false, 150, ''],
        ['contact_person', 'Contact person', 'text', 'col-md-6', false, 100, ''],
        ['phone', 'Phone number', 'tel', 'col-md-6', false, 30, '012 345 678'],
        ['email', 'Email', 'email', 'col-md-6', false, 150, 'sales@supplier.com'],
        ['tax_number', 'Tax number (optional)', 'text', 'col-md-6', false, 50, 'VAT TIN'],
    ];
@endphp

<div class="row g-3">
    @foreach ($fields as [$field, $label, $type, $col, $required, $max, $placeholder])
        <div class="{{ $col }}">
            <label for="{{ $field }}" class="form-label">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
            <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field, $supplier->{$field}) }}"
                   class="form-control @error($field) is-invalid @enderror" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
                   @if ($required) required @endif>
            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endforeach

    <div class="col-12">
        <label for="address" class="form-label">Address</label>
        <textarea id="address" name="address" rows="2" maxlength="1000"
                  class="form-control @error('address') is-invalid @enderror">{{ old('address', $supplier->address) }}</textarea>
        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Notes</label>
        <textarea id="description" name="description" rows="3" maxlength="2000"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $supplier->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $supplier->is_active))>
            <label class="form-check-label" for="is_active">Active (available for new purchase orders)</label>
        </div>
    </div>
</div>

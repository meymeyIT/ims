@extends('layouts.app')

@section('title', 'New Stock Out')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item"><a href="{{ route('stock-out.index') }}">Stock Out</a></li>
    <li class="breadcrumb-item active" aria-current="page">New</li>
@endsection

@php use App\Support\Quantity; @endphp

@section('content')
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('stock-out.store') }}" class="card" novalidate>
                @csrf
                <input type="hidden" name="form_token" value="{{ $token }}">

                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required data-out-product>
                            <option value="">Select a product…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-unit="{{ $product->unit->abbreviation }}" data-stock="{{ $product->current_stock }}"
                                        @selected((string) old('product_id', $selectedProduct) === (string) $product->id)>
                                    {{ $product->name }} ({{ $product->sku }}): available {{ Quantity::format($product->current_stock) }} {{ $product->unit->abbreviation }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($products->isEmpty())
                            <div class="form-text text-danger">No active products currently have stock.</div>
                        @endif
                    </div>

                    <div class="col-md-6">
                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input id="quantity" type="number" name="quantity" step="0.001" min="0.001" value="{{ old('quantity') }}"
                                   class="form-control @error('quantity') is-invalid @enderror" required>
                            <span class="input-group-text" data-out-unit>—</span>
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text text-danger d-none" data-out-warning>More than the available stock.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="movement_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input id="movement_date" type="datetime-local" name="movement_date" max="{{ now()->format('Y-m-d\TH:i') }}"
                               value="{{ old('movement_date', now()->format('Y-m-d\TH:i')) }}"
                               class="form-control @error('movement_date') is-invalid @enderror" required>
                        @error('movement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required data-out-reason>
                            @foreach ($reasons as $reason)
                                <option value="{{ $reason->value }}" @selected(old('reason', 'sold') === $reason->value)>{{ $reason->label() }}</option>
                            @endforeach
                        </select>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6" data-out-supplier>
                        <label for="supplier_id" class="form-label">Returned to supplier <span class="text-danger">*</span></label>
                        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">Select a supplier…</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes <span class="text-danger d-none" data-out-notes-required>*</span></label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000"
                                  class="form-control @error('notes') is-invalid @enderror" placeholder="Invoice number, customer, reason details…">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('stock-out.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-box-arrow-up me-1"></i> Save Stock Out</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="stat-label">Available stock</div>
                    <div class="stat-value" data-out-available>—</div>
                    <div class="stat-label mt-2">After this stock out</div>
                    <div class="stat-value" data-out-after>—</div>
                </div>
            </div>
            <p class="small text-body-secondary mt-3">
                Stock can never go below zero. The available quantity is checked again at the moment you save,
                in case someone else changed it meanwhile.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const product = document.querySelector('[data-out-product]');
        const qty = document.getElementById('quantity');
        const reason = document.querySelector('[data-out-reason]');

        function refresh() {
            const option = product.selectedOptions[0];
            const unit = option?.dataset.unit || '';
            const stock = parseFloat(option?.dataset.stock) || 0;
            const out = parseFloat(qty.value) || 0;
            const after = stock - out;
            const fmt = (n) => n.toLocaleString(undefined, { maximumFractionDigits: 3 });

            document.querySelector('[data-out-unit]').textContent = unit || '—';
            document.querySelector('[data-out-available]').textContent = option?.value ? `${fmt(stock)} ${unit}` : '—';
            const afterEl = document.querySelector('[data-out-after]');
            afterEl.textContent = option?.value ? `${fmt(after)} ${unit}` : '—';
            afterEl.classList.toggle('text-danger', after < 0);
            document.querySelector('[data-out-warning]').classList.toggle('d-none', !(option?.value && after < 0));

            document.querySelector('[data-out-supplier]').classList.toggle('d-none', reason.value !== 'returned_to_supplier');
            document.querySelector('[data-out-notes-required]').classList.toggle('d-none', reason.value !== 'other');
        }

        [product, reason].forEach((el) => el.addEventListener('change', refresh));
        qty.addEventListener('input', refresh);
        refresh();
    </script>
@endpush

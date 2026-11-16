@extends('layouts.app')

@section('title', 'New Stock In')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item"><a href="{{ route('stock-in.index') }}">Stock In</a></li>
    <li class="breadcrumb-item active" aria-current="page">New</li>
@endsection

@php use App\Support\Quantity; @endphp

@section('content')
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('stock-in.store') }}" class="card" novalidate>
                @csrf
                <input type="hidden" name="form_token" value="{{ $token }}">

                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required data-stock-product>
                            <option value="">Select a product…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}"
                                        data-unit="{{ $product->unit->abbreviation }}"
                                        data-stock="{{ Quantity::format($product->current_stock) }}"
                                        data-cost="{{ bccomp($product->average_cost, '0', 4) > 0 ? $product->average_cost : $product->purchase_price }}"
                                        @selected((string) old('product_id', $selectedProduct) === (string) $product->id)>
                                    {{ $product->name }} ({{ $product->sku }}): stock {{ Quantity::format($product->current_stock) }} {{ $product->unit->abbreviation }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input id="quantity" type="number" name="quantity" step="0.001" min="0.001" value="{{ old('quantity') }}"
                                   class="form-control @error('quantity') is-invalid @enderror" required>
                            <span class="input-group-text" data-stock-unit>—</span>
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="unit_cost" class="form-label">Unit cost <span class="text-danger">*</span></label>
                        <input id="unit_cost" type="number" name="unit_cost" step="0.0001" min="0" value="{{ old('unit_cost') }}"
                               class="form-control @error('unit_cost') is-invalid @enderror" required data-stock-cost>
                        @error('unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Used for inventory valuation (weighted average).</div>
                    </div>

                    <div class="col-md-4">
                        <label for="movement_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input id="movement_date" type="datetime-local" name="movement_date" max="{{ now()->format('Y-m-d\TH:i') }}"
                               value="{{ old('movement_date', now()->format('Y-m-d\TH:i')) }}"
                               class="form-control @error('movement_date') is-invalid @enderror" required>
                        @error('movement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                            @foreach ($reasons as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason', 'restock') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="supplier_id" class="form-label">Supplier <span class="text-body-secondary small">(optional)</span></label>
                        <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror">
                            <option value="">None</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('stock-in.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i> Save Stock In</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="stat-label">Current stock</div>
                    <div class="stat-value" data-stock-current>—</div>
                    <div class="stat-label mt-2">After this stock in</div>
                    <div class="stat-value text-success" data-stock-after>—</div>
                </div>
            </div>
            <div class="alert alert-warning small">
                <i class="bi bi-receipt me-1"></i>
                <strong>Goods from a purchase order?</strong> Don't enter them here. Open the purchase and click
                <strong>Receive Products</strong>, so the order is updated and the goods are never counted twice.
                @can('purchases.view')
                    <div class="mt-2"><a href="{{ route('purchases.index', ['status' => 'approved']) }}">Approved purchases waiting for delivery →</a></div>
                @endcan
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const select = document.querySelector('[data-stock-product]');
        const qty = document.getElementById('quantity');
        const cost = document.querySelector('[data-stock-cost]');

        function refresh(fillCost) {
            const option = select.selectedOptions[0];
            const unit = option?.dataset.unit || '';
            const stock = parseFloat(option?.dataset.stock?.replace(/,/g, '')) || 0;
            const add = parseFloat(qty.value) || 0;

            document.querySelector('[data-stock-unit]').textContent = unit || '—';
            document.querySelector('[data-stock-current]').textContent = option?.value ? `${stock.toLocaleString()} ${unit}` : '—';
            document.querySelector('[data-stock-after]').textContent = option?.value ? `${(stock + add).toLocaleString(undefined, { maximumFractionDigits: 3 })} ${unit}` : '—';

            if (fillCost && option?.dataset.cost && cost.value === '') {
                cost.value = option.dataset.cost;
            }
        }

        select.addEventListener('change', () => { cost.value = ''; refresh(true); });
        qty.addEventListener('input', () => refresh(false));
        refresh(true);
    </script>
@endpush

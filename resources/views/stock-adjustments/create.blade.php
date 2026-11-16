@extends('layouts.app')

@section('title', 'Inventory Adjustment')

@section('breadcrumb')
    <li class="breadcrumb-item">Inventory</li>
    <li class="breadcrumb-item"><a href="{{ route('stock-movements.index') }}">Stock History</a></li>
    <li class="breadcrumb-item active" aria-current="page">Adjustment</li>
@endsection

@php use App\Support\Quantity; @endphp

@section('content')
    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('stock-adjustments.store') }}" class="card" novalidate
                  data-confirm="Set the stock to the counted quantity? The difference is recorded as an ADJUSTMENT." data-confirm-button="Adjust stock" data-confirm-color="#fd7e14">
                @csrf
                <input type="hidden" name="form_token" value="{{ $token }}">

                <div class="card-body row g-3">
                    <div class="col-12">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required data-adj-product>
                            <option value="">Select a product…</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" data-unit="{{ $product->unit->abbreviation }}" data-stock="{{ $product->current_stock }}"
                                        @selected((string) old('product_id', $selectedProduct) === (string) $product->id)>
                                    {{ $product->name }} ({{ $product->sku }}): system stock {{ Quantity::format($product->current_stock) }} {{ $product->unit->abbreviation }}{{ $product->is_active ? '' : ' [inactive]' }}
                                </option>
                            @endforeach
                        </select>
                        @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="counted_quantity" class="form-label">Counted quantity (physical) <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input id="counted_quantity" type="number" name="counted_quantity" step="0.001" min="0" value="{{ old('counted_quantity') }}"
                                   class="form-control @error('counted_quantity') is-invalid @enderror" required>
                            <span class="input-group-text" data-adj-unit>—</span>
                            @error('counted_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">What is really on the shelf right now.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                            @foreach ($reasons as $value => $label)
                                <option value="{{ $value }}" @selected(old('reason', 'stock_count') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="notes" class="form-label">Explanation <span class="text-danger">*</span></label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" required
                                  class="form-control @error('notes') is-invalid @enderror" placeholder="e.g. Monthly count 30/11, counted by Dara">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="card-footer bg-white d-flex justify-content-end gap-2">
                    <a href="{{ route('stock-movements.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-sliders me-1"></i> Save Adjustment</button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="stat-label">System stock</div>
                    <div class="stat-value" data-adj-system>—</div>
                    <div class="stat-label mt-2">Difference to record</div>
                    <div class="stat-value" data-adj-diff>—</div>
                </div>
            </div>
            <p class="small text-body-secondary mt-3">
                The difference is calculated again at the moment you save. If stock moved in the meantime,
                the result is still exactly the counted quantity.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const product = document.querySelector('[data-adj-product]');
        const counted = document.getElementById('counted_quantity');

        function refresh() {
            const option = product.selectedOptions[0];
            const unit = option?.dataset.unit || '';
            const system = parseFloat(option?.dataset.stock) || 0;
            const fmt = (n) => n.toLocaleString(undefined, { maximumFractionDigits: 3 });
            const diffEl = document.querySelector('[data-adj-diff]');

            document.querySelector('[data-adj-unit]').textContent = unit || '—';
            document.querySelector('[data-adj-system]').textContent = option?.value ? `${fmt(system)} ${unit}` : '—';

            if (option?.value && counted.value !== '') {
                const diff = (parseFloat(counted.value) || 0) - system;
                diffEl.textContent = `${diff > 0 ? '+' : ''}${fmt(diff)} ${unit}`;
                diffEl.className = 'stat-value ' + (diff > 0 ? 'text-success' : diff < 0 ? 'text-danger' : '');
            } else {
                diffEl.textContent = '—';
                diffEl.className = 'stat-value';
            }
        }

        product.addEventListener('change', refresh);
        counted.addEventListener('input', refresh);
        refresh();
    </script>
@endpush

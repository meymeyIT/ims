@php
    use App\Support\Currency;
    $rows = old('items', $lines);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-1"></i> Please fix the highlighted fields.
        @foreach (['items', 'discount', 'purchase'] as $key)
            @error($key)<div class="small mt-1">{{ $message }}</div>@enderror
        @endforeach
    </div>
@endif

<div class="row g-4" data-purchase-form>
    <div class="col-xl-9">
        <div class="card mb-4">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Order details</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label for="supplier_id" class="form-label">Supplier <span class="text-danger">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">Select a supplier…</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $purchase->supplier_id) === (string) $supplier->id)>
                                {{ $supplier->name }}{{ $supplier->company_name ? " — {$supplier->company_name}" : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($suppliers->isEmpty())
                        <div class="form-text text-danger">No active suppliers. <a href="{{ route('suppliers.create') }}">Add a supplier</a> first.</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label for="purchase_date" class="form-label">Purchase date <span class="text-danger">*</span></label>
                    <input id="purchase_date" type="date" name="purchase_date"
                           value="{{ old('purchase_date', $purchase->purchase_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                           class="form-control @error('purchase_date') is-invalid @enderror" required>
                    @error('purchase_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if ($purchase->exists)
                    <div class="col-md-3">
                        <label class="form-label">Purchase #</label>
                        <input type="text" class="form-control" value="{{ $purchase->purchase_number }}" disabled>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Products</h2>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-line><i class="bi bi-plus-lg me-1"></i> Add product</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                    <tr><th>Product</th><th>Quantity</th><th>Unit cost</th><th class="text-end">Subtotal</th><th></th></tr>
                    </thead>
                    <tbody data-lines data-next-index="{{ count($rows) }}">
                    @foreach ($rows as $i => $line)
                        @include('purchases._line', ['index' => $i, 'line' => $line])
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body text-center text-body-secondary py-4 {{ count($rows) ? 'd-none' : '' }}" data-empty-lines>
                <i class="bi bi-cart-plus fs-3 d-block mb-1"></i>No products yet. Click <strong>Add product</strong>.
            </div>
            @if ($products->isEmpty())
                <div class="card-footer bg-white small text-danger">No active products. <a href="{{ route('products.create') }}">Add a product</a> first.</div>
            @endif
        </div>

        <template id="purchase-line-template">
            @include('purchases._line', ['index' => '__INDEX__', 'line' => []])
        </template>
    </div>

    <div class="col-xl-3">
        <div class="card mb-4 position-sticky" style="top: calc(var(--ims-topbar-height) + 1rem)">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Summary</h2></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong data-subtotal>0.00</strong></div>
                <div class="mb-2">
                    <label for="discount" class="form-label small mb-1">Discount</label>
                    <input id="discount" type="number" name="discount" step="0.01" min="0" value="{{ old('discount', $purchase->discount ?? '0.00') }}"
                           class="form-control text-end @error('discount') is-invalid @enderror" data-discount>
                    @error('discount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="tax" class="form-label small mb-1">Tax</label>
                    <input id="tax" type="number" name="tax" step="0.01" min="0" value="{{ old('tax', $purchase->tax ?? '0.00') }}"
                           class="form-control text-end @error('tax') is-invalid @enderror" data-tax>
                    @error('tax')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="fw-semibold">Total</span>
                    <span class="fs-4 fw-bold"><span data-total>0.00</span> <small class="fs-6 text-body-secondary">{{ Currency::code() }}</small></span>
                </div>
                <div class="form-text">Final totals are recalculated on the server.</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <label for="notes" class="form-label">Notes</label>
                <textarea id="notes" name="notes" rows="4" maxlength="2000" class="form-control @error('notes') is-invalid @enderror"
                          placeholder="Delivery instructions, payment terms…">{{ old('notes', $purchase->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
    <a href="{{ $purchase->exists ? route('purchases.show', $purchase) : route('purchases.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" name="action" value="draft" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i> Save as Draft</button>
    <button type="submit" name="action" value="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i> Submit for Approval</button>
</div>

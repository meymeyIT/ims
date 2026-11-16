@php
    use App\Support\Quantity;
    $isEdit = $product->exists;
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Product details</h2></div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label for="name" class="form-label">Product name <span class="text-danger">*</span></label>
                    <input id="name" type="text" name="name" value="{{ old('name', $product->name) }}"
                           class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                    <input id="sku" type="text" name="sku" value="{{ old('sku', $product->sku) }}"
                           class="form-control text-uppercase @error('sku') is-invalid @enderror" required maxlength="50" placeholder="e.g. KB-LOGI-K120">
                    @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Unique stock code. Letters, numbers and . _ - /</div>
                </div>

                <div class="col-md-6">
                    <label for="barcode" class="form-label">Barcode</label>
                    <input id="barcode" type="text" name="barcode" value="{{ old('barcode', $product->barcode) }}"
                           class="form-control @error('barcode') is-invalid @enderror" maxlength="64" placeholder="Scan or type">
                    @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                    <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                        <option value="">Select a category…</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($categories->isEmpty())
                        <div class="form-text text-danger">No active categories. Create a category first.</div>
                    @endif
                </div>

                <div class="col-md-6">
                    <label for="unit_id" class="form-label">Unit <span class="text-danger">*</span></label>
                    <select id="unit_id" name="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                        <option value="">Select a unit…</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) old('unit_id', $product->unit_id) === (string) $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                        @endforeach
                    </select>
                    @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Pricing & stock level</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label for="purchase_price" class="form-label">Purchase price <span class="text-danger">*</span></label>
                    <input id="purchase_price" type="number" name="purchase_price" step="0.01" min="0"
                           value="{{ old('purchase_price', $product->purchase_price ?? '0.00') }}"
                           class="form-control @error('purchase_price') is-invalid @enderror" required>
                    @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="selling_price" class="form-label">Selling price <span class="text-danger">*</span></label>
                    <input id="selling_price" type="number" name="selling_price" step="0.01" min="0"
                           value="{{ old('selling_price', $product->selling_price ?? '0.00') }}"
                           class="form-control @error('selling_price') is-invalid @enderror" required>
                    @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="minimum_stock" class="form-label">Minimum stock <span class="text-danger">*</span></label>
                    <input id="minimum_stock" type="number" name="minimum_stock" step="0.001" min="0"
                           value="{{ old('minimum_stock', Quantity::format($product->minimum_stock ?? 0)) }}"
                           class="form-control @error('minimum_stock') is-invalid @enderror" required>
                    @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Low-stock alert when stock ≤ this.</div>
                </div>

                <div class="col-12">
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        @if ($isEdit)
                            Current stock: <strong>{{ Quantity::format($product->current_stock) }} {{ $product->unit?->abbreviation }}</strong>.
                        @else
                            New products start with 0 stock.
                        @endif
                        Stock can only change through <strong>Stock In</strong>, <strong>Stock Out</strong> or <strong>purchase receiving</strong>, so every change is recorded.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Product image</h2></div>
            <div class="card-body">
                <div class="image-preview mb-3" id="imagePreview">
                    @if ($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="Product image">
                    @else
                        <div class="text-body-tertiary text-center"><i class="bi bi-image fs-1 d-block"></i><span class="small">No image</span></div>
                    @endif
                </div>
                <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('image') is-invalid @enderror" data-image-preview="#imagePreview">
                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG or WEBP, max 2 MB.</div>

                @if ($product->image_path)
                    <div class="form-check mt-2">
                        <input type="hidden" name="remove_image" value="0">
                        <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="remove_image">
                        <label class="form-check-label small" for="remove_image">Remove current image</label>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                           @checked(old('is_active', $product->is_active))>
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
                <div class="form-text">Inactive products can't be used in new purchases or stock movements.</div>
            </div>
        </div>
    </div>
</div>

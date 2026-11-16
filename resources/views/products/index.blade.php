@extends('layouts.app')

@section('title', 'Products')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Products</li>
@endsection

@section('actions')
    @can('products.create')
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Product</a>
    @endcan
@endsection

@php
    use App\Support\Currency;
    use App\Support\Quantity;

    $stockBadges = [
        'in_stock' => ['In stock', 'success'],
        'low_stock' => ['Low stock', 'warning'],
        'out_of_stock' => ['Out of stock', 'danger'],
    ];
    $archivedView = request('status') === 'archived';
@endphp

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="direction" value="{{ $direction }}">

                <div class="col-lg-4">
                    <label for="search" class="form-label small mb-1">Search</label>
                    <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control"
                           placeholder="Product name, SKU or barcode" autocomplete="off">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="category" class="form-label small mb-1">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="stock" class="form-label small mb-1">Stock</label>
                    <select id="stock" name="stock" class="form-select">
                        <option value="">Any stock</option>
                        @foreach ($stockBadges as $value => [$text])
                            <option value="{{ $value }}" @selected(request('stock') === $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="status" class="form-label small mb-1">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All (not archived)</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                        @can('products.delete')
                            <option value="archived" @selected($archivedView)>Archived</option>
                        @endcan
                    </select>
                </div>
                <div class="col-sm-6 col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-grow-1" title="Filter"><i class="bi bi-search"></i></button>
                    @if (request()->hasAny(['search', 'category', 'stock', 'status']))
                        <a href="{{ route('products.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </form>
        </div>

        @if ($archivedView)
            <div class="alert alert-secondary rounded-0 border-0 border-bottom mb-0 small">
                <i class="bi bi-archive me-1"></i> Archived products are hidden from lists and new transactions. Their history is kept.
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th><x-sort-link column="name" label="Product" :sort="$sort" :direction="$direction" /></th>
                    <th><x-sort-link column="sku" label="SKU" :sort="$sort" :direction="$direction" /></th>
                    <th class="d-none d-lg-table-cell">Category</th>
                    <th class="text-end"><x-sort-link column="current_stock" label="Stock" :sort="$sort" :direction="$direction" /></th>
                    <th class="text-end d-none d-md-table-cell"><x-sort-link column="selling_price" label="Price" :sort="$sort" :direction="$direction" /></th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($products as $product)
                    @php [$stockText, $stockColor] = $stockBadges[$product->stockStatus()]; @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                @if ($product->imageUrl())
                                    <img src="{{ $product->imageUrl() }}" alt="" class="product-thumb">
                                @else
                                    <span class="product-thumb product-thumb-empty"><i class="bi bi-image"></i></span>
                                @endif
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $product) }}" class="fw-medium text-decoration-none text-body d-block text-truncate">{{ $product->name }}</a>
                                    @if ($product->barcode)
                                        <div class="small text-body-secondary"><i class="bi bi-upc"></i> {{ $product->barcode }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td><code>{{ $product->sku }}</code></td>
                        <td class="d-none d-lg-table-cell">{{ $product->category->name }}</td>
                        <td class="text-end text-nowrap">
                            <span class="fw-semibold">{{ Quantity::format($product->current_stock) }}</span>
                            <span class="small text-body-secondary">{{ $product->unit->abbreviation }}</span>
                            <div><span class="badge text-bg-{{ $stockColor }}-subtle text-{{ $stockColor }}-emphasis">{{ $stockText }}</span></div>
                        </td>
                        <td class="text-end d-none d-md-table-cell">{{ Currency::format($product->selling_price) }}</td>
                        <td>
                            @if ($product->trashed())
                                <span class="badge text-bg-dark">Archived</span>
                            @else
                                <x-status-badge :active="$product->is_active" />
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if ($product->trashed())
                                <form method="POST" action="{{ route('products.restore', $product) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-light" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                                <x-delete-button :action="route('products.force-destroy', $product)" title="Delete permanently" icon="x-octagon" confirm="Delete permanently"
                                                 :message="'Permanently delete “' . $product->name . '”? Only possible if it was never used in purchases or stock movements.'" />
                            @else
                                <a href="{{ route('products.show', $product) }}" class="btn btn-sm btn-light" title="View"><i class="bi bi-eye"></i></a>
                                @can('products.update')
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                @endcan
                                @can('products.delete')
                                    <x-delete-button :action="route('products.destroy', $product)" title="Archive" icon="archive" confirm="Archive"
                                                     :message="'Archive “' . $product->name . '”? It will be hidden but its history is kept. You can restore it later.'" />
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-5">
                            <i class="bi bi-box-seam d-block fs-2 mb-2"></i>
                            No products found.
                            @can('products.create')
                                <div class="mt-2"><a href="{{ route('products.create') }}">Add your first product</a></div>
                            @endcan
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($products->hasPages())
            <div class="card-footer bg-white">{{ $products->links() }}</div>
        @endif
    </div>
@endsection

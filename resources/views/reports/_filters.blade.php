{{-- Filter bar: only the controls this report supports are shown. --}}
@php
    $uses = fn (string $filter) => in_array($filter, $report->filters(), true);
    $q = $filters->toQuery();
@endphp

<form method="GET" action="{{ route('reports.show', $report->key()) }}" class="row g-2 align-items-end">
    @if ($uses('date_range'))
        <div class="col-sm-6 col-lg-2">
            <label for="start_date" class="form-label small mb-1">Start date</label>
            <input type="date" id="start_date" name="start_date" value="{{ $q['start_date'] ?? '' }}" class="form-control @error('start_date') is-invalid @enderror">
        </div>
        <div class="col-sm-6 col-lg-2">
            <label for="end_date" class="form-label small mb-1">End date</label>
            <input type="date" id="end_date" name="end_date" value="{{ $q['end_date'] ?? '' }}" class="form-control @error('end_date') is-invalid @enderror">
            @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif

    @if ($uses('as_of'))
        <div class="col-sm-6 col-lg-2">
            <label for="as_of" class="form-label small mb-1">As of date</label>
            <input type="date" id="as_of" name="as_of" value="{{ $q['as_of'] ?? '' }}" max="{{ now()->toDateString() }}" class="form-control @error('as_of') is-invalid @enderror">
            @error('as_of')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">Empty = today</div>@enderror
        </div>
    @endif

    @if ($uses('search'))
        <div class="col-sm-6 col-lg">
            <label for="search" class="form-label small mb-1">Search</label>
            <input type="search" id="search" name="search" value="{{ $q['search'] ?? '' }}" class="form-control" placeholder="Name, SKU, number…">
        </div>
    @endif

    @if ($uses('product'))
        <div class="col-sm-6 col-lg-2">
            <label for="product" class="form-label small mb-1">Product</label>
            <select id="product" name="product" class="form-select">
                <option value="">All products</option>
                @foreach ($options['products'] as $option)
                    <option value="{{ $option->id }}" @selected(($q['product'] ?? null) == $option->id)>{{ $option->name }} ({{ $option->sku }})</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($uses('category'))
        <div class="col-sm-6 col-lg-2">
            <label for="category" class="form-label small mb-1">Category</label>
            <select id="category" name="category" class="form-select">
                <option value="">All categories</option>
                @foreach ($options['categories'] as $option)
                    <option value="{{ $option->id }}" @selected(($q['category'] ?? null) == $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($uses('supplier'))
        <div class="col-sm-6 col-lg-2">
            <label for="supplier" class="form-label small mb-1">Supplier</label>
            <select id="supplier" name="supplier" class="form-select">
                <option value="">All suppliers</option>
                @foreach ($options['suppliers'] as $option)
                    <option value="{{ $option->id }}" @selected(($q['supplier'] ?? null) == $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($uses('purchase_status'))
        <div class="col-sm-6 col-lg-2">
            <label for="purchase_status" class="form-label small mb-1">Status</label>
            <select id="purchase_status" name="purchase_status" class="form-select">
                <option value="">All statuses</option>
                @foreach ($options['purchaseStatuses'] as $status)
                    <option value="{{ $status->value }}" @selected(($q['purchase_status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($uses('movement_type'))
        <div class="col-sm-6 col-lg-2">
            <label for="movement_type" class="form-label small mb-1">Type</label>
            <select id="movement_type" name="movement_type" class="form-select">
                <option value="">All types</option>
                @foreach ($options['movementTypes'] as $type)
                    <option value="{{ $type->value }}" @selected(($q['movement_type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($uses('out_reason'))
        <div class="col-sm-6 col-lg-2">
            <label for="out_reason" class="form-label small mb-1">Reason</label>
            <select id="out_reason" name="out_reason" class="form-select">
                <option value="">All reasons</option>
                @foreach ($options['outReasons'] as $reason)
                    <option value="{{ $reason->value }}" @selected(($q['out_reason'] ?? null) === $reason->value)>{{ $reason->label() }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if ($uses('stock_status'))
        <div class="col-sm-6 col-lg-2">
            <label for="stock_status" class="form-label small mb-1">Stock</label>
            <select id="stock_status" name="stock_status" class="form-select">
                <option value="">Any stock</option>
                @foreach (['in_stock' => 'In stock', 'low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock'] as $value => $text)
                    <option value="{{ $value }}" @selected(($q['stock_status'] ?? null) === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-sm-6 col-lg-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i> Apply</button>
        <a href="{{ route('reports.show', $report->key()) }}" class="btn btn-light" title="Reset filters"><i class="bi bi-arrow-counterclockwise"></i></a>
    </div>
</form>

{{--
    Search + status filter bar used by the Categories, Units and Suppliers lists.
    Extra filters can be passed in the default slot.
--}}
@props(['action', 'placeholder' => 'Search…', 'statuses' => ['active' => 'Active', 'inactive' => 'Inactive'], 'keys' => ['search', 'status']])

<form method="GET" action="{{ $action }}" class="row g-2 align-items-end">
    <div class="col-md">
        <label for="search" class="form-label small mb-1">Search</label>
        <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ $placeholder }}">
    </div>

    {{ $slot }}

    <div class="col-sm-6 col-md-2">
        <label for="status" class="form-label small mb-1">Status</label>
        <select id="status" name="status" class="form-select">
            <option value="">All</option>
            @foreach ($statuses as $value => $text)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $text }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6 col-md-auto d-flex gap-2">
        <button type="submit" class="btn btn-outline-primary flex-grow-1"><i class="bi bi-search"></i> Filter</button>
        @if (request()->hasAny($keys))
            <a href="{{ $action }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
        @endif
    </div>
</form>

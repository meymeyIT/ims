@extends('layouts.app')

@section('title', 'Categories')

@section('breadcrumb')
    <li class="breadcrumb-item">Products</li>
    <li class="breadcrumb-item active" aria-current="page">Categories</li>
@endsection

@section('actions')
    @can('categories.manage')
        <a href="{{ route('categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Category</a>
    @endcan
@endsection

@section('content')
    <div class="card">
        <div class="card-body border-bottom">
            <x-list-filters :action="route('categories.index')" placeholder="Category name or description" />
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th class="d-none d-md-table-cell">Description</th>
                    <th class="text-center">Products</th>
                    <th>Status</th>
                    @can('categories.manage')<th class="text-end">Actions</th>@endcan
                </tr>
                </thead>
                <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="fw-medium">{{ $category->name }}</td>
                        <td class="d-none d-md-table-cell text-body-secondary small">{{ Str::limit($category->description, 80) ?: '—' }}</td>
                        <td class="text-center">
                            @if (Route::has('products.index') && auth()->user()->can('products.view'))
                                <a href="{{ route('products.index', ['category' => $category->id]) }}" class="badge text-bg-light border text-decoration-none">{{ $category->products_count }}</a>
                            @else
                                {{ $category->products_count }}
                            @endif
                        </td>
                        <td><x-status-badge :active="$category->is_active" /></td>
                        @can('categories.manage')
                            <td class="text-end text-nowrap">
                                <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
                                <x-toggle-status-button :action="route('categories.toggle-status', $category)" :active="$category->is_active" :name="$category->name" />
                                @if ($category->products_count === 0)
                                    <x-delete-button :action="route('categories.destroy', $category)" :message="'Delete category “' . $category->name . '”?'" />
                                @else
                                    <button type="button" class="btn btn-sm btn-light text-body-tertiary" disabled title="Used by {{ $category->products_count }} product(s): deactivate instead"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        @endcan
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">No categories found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="card-footer bg-white">{{ $categories->links() }}</div>
        @endif
    </div>
@endsection

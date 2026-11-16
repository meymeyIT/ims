<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:categories.view', only: ['index']),
            new Middleware('can:categories.manage', except: ['index']),
        ];
    }

    public function index(Request $request): View
    {
        $categories = Category::query()
            ->withCount('products')   // includes archived products (see Category::products)
            ->search($request->query('search'))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.create', ['category' => new Category(['is_active' => true])]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        ActivityLogger::log('created', 'categories', "Created category {$category->name}", $category);

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" was created.");
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        ActivityLogger::log('updated', 'categories', "Updated category {$category->name}", $category);

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" was updated.");
    }

    public function toggleStatus(Category $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);
        $state = $category->is_active ? 'activated' : 'deactivated';

        ActivityLogger::log($state, 'categories', ucfirst($state) . " category {$category->name}", $category);

        return back()->with('success', "Category \"{$category->name}\" was {$state}.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->isInUse()) {
            return back()->with('error', "Category \"{$category->name}\" is used by products and cannot be deleted. Deactivate it instead.");
        }

        try {
            $category->delete();
        } catch (QueryException) {
            // Foreign key RESTRICT: a product was linked at the same moment.
            return back()->with('error', "Category \"{$category->name}\" is in use and cannot be deleted.");
        }

        ActivityLogger::log('deleted', 'categories', "Deleted category {$category->name}", $category);

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" was deleted.");
    }
}

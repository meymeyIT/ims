<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\LowStockMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProductController extends Controller implements HasMiddleware
{
    /** Columns the list may be sorted by (whitelist: never sort by raw user input). */
    private const SORTABLE = ['name', 'sku', 'current_stock', 'selling_price', 'created_at'];

    public static function middleware(): array
    {
        return [
            new Middleware('can:products.view', only: ['index', 'show']),
            new Middleware('can:products.create', only: ['create', 'store']),
            new Middleware('can:products.update', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('can:products.delete', only: ['destroy', 'restore', 'forceDestroy']),
        ];
    }

    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $status = $request->query('status');

        $products = Product::query()
            ->with(['category', 'unit'])
            ->when($status === 'archived', fn ($q) => $q->onlyTrashed())
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->search($request->query('search'))
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->stockStatus($request->query('stock'))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product(['is_active' => true, 'minimum_stock' => 0]),
            ...$this->formOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        if ($request->hasFile('image')) {
            // Random file name, stored in storage/app/public/products
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        app(LowStockMonitor::class)->check($product->id);   // new products start at 0 stock

        ActivityLogger::log('created', 'products', "Created product {$product->name} ({$product->sku})", $product);

        return redirect()->route('products.show', $product)
            ->with('success', "Product \"{$product->name}\" was created. Use Stock In to add its opening stock.");
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'unit']);

        return view('products.show', [
            'product' => $product,
            'movements' => $product->stockMovements()->with(['user', 'reversalOf'])->latest('movement_date')->latest('id')->limit(10)->get(),
            'purchaseItems' => $product->purchaseItems()->with('purchase.supplier')->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        return view('products.edit', ['product' => $product, ...$this->formOptions($product)]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);
        $oldImage = $product->image_path;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        $product->fill($data);
        $changed = array_keys($product->getDirty());
        $product->save();

        // Delete the old file only after the database was updated successfully.
        if ($oldImage && $oldImage !== $product->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        ActivityLogger::log('updated', 'products', "Updated product {$product->name} ({$product->sku})", $product, ['changed' => $changed]);

        // A new minimum stock may make the product low (or not low any more).
        app(LowStockMonitor::class)->check($product->id);

        return redirect()->route('products.show', $product)->with('success', "Product \"{$product->name}\" was updated.");
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);
        $state = $product->is_active ? 'activated' : 'deactivated';

        ActivityLogger::log($state, 'products', ucfirst($state) . " product {$product->sku}", $product);
        app(LowStockMonitor::class)->check($product->id);

        return back()->with('success', "Product \"{$product->name}\" was {$state}.");
    }

    /** "Delete" = archive (soft delete). History and reports keep working. */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        ActivityLogger::log('archived', 'products', "Archived product {$product->name} ({$product->sku})", $product);
        app(LowStockMonitor::class)->check($product->id);

        return redirect()->route('products.index')
            ->with('success', "Product \"{$product->name}\" was archived. You can restore it from the Archived filter.");
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();

        ActivityLogger::log('restored', 'products', "Restored product {$product->name} ({$product->sku})", $product);
        app(LowStockMonitor::class)->check($product->id);

        return redirect()->route('products.show', $product)->with('success', "Product \"{$product->name}\" was restored.");
    }

    /** Permanent delete: only for archived products that were never used in any transaction. */
    public function forceDestroy(Product $product): RedirectResponse
    {
        if ($product->hasTransactionHistory()) {
            return back()->with('error', "\"{$product->name}\" has stock or purchase history and cannot be permanently deleted. It stays archived.");
        }

        $image = $product->image_path;
        $product->forceDelete();

        if ($image) {
            Storage::disk('public')->delete($image);
        }

        ActivityLogger::log('deleted', 'products', "Permanently deleted product {$product->name} ({$product->sku})");

        return redirect()->route('products.index', ['status' => 'archived'])
            ->with('success', "Product \"{$product->name}\" was permanently deleted.");
    }

    /** Active categories/units, plus the product's current ones even if they were deactivated. */
    private function formOptions(?Product $product = null): array
    {
        return [
            'categories' => Category::query()
                ->where('is_active', true)
                ->when($product, fn ($q) => $q->orWhere('id', $product->category_id))
                ->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()
                ->where('is_active', true)
                ->when($product, fn ($q) => $q->orWhere('id', $product->unit_id))
                ->orderBy('name')->get(['id', 'name', 'abbreviation']),
        ];
    }
}

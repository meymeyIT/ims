<?php

namespace App\Reports;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** 2. Category Report: products, low-stock count and stock value per category. */
class CategoryReport extends Report
{
    public function key(): string { return 'categories'; }
    public function title(): string { return 'Category Report'; }
    public function description(): string { return 'Number of products, low-stock products and inventory value per category.'; }
    public function group(): string { return 'products'; }
    public function filters(): array { return ['search']; }

    public function query(ReportFilters $f): Builder
    {
        // Correlated sub-queries: one row per category, archived products excluded.
        $products = fn () => Product::query()->whereColumn('products.category_id', 'categories.id');

        return Category::query()
            ->select('categories.*')
            ->selectSub($products()->selectRaw('COUNT(*)'), 'products_total')
            ->selectSub($products()->where('is_active', true)->whereColumn('current_stock', '<=', 'minimum_stock')->selectRaw('COUNT(*)'), 'low_stock_total')
            ->selectSub($products()->selectRaw('COALESCE(SUM(current_stock * average_cost), 0)'), 'stock_value')
            ->when($f->search, fn ($q) => $q->search($f->search))
            ->orderBy('name')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Category', 'type' => 'text'],
            'status' => ['label' => 'Status', 'type' => 'text'],
            'products' => ['label' => 'Products', 'type' => 'int'],
            'low_stock' => ['label' => 'Low Stock', 'type' => 'int'],
            'stock_value' => ['label' => 'Inventory Value', 'type' => 'money'],
        ];
    }

    public function row(Model $c): array
    {
        return [
            'name' => $c->name,
            'status' => $c->is_active ? 'Active' : 'Inactive',
            'products' => (int) $c->products_total,
            'low_stock' => (int) $c->low_stock_total,
            'stock_value' => (float) $c->stock_value,
        ];
    }

    public function totals(ReportFilters $f): array
    {
        $sums = $this->query($f)->get()->reduce(fn ($carry, $c) => [
            'products' => $carry['products'] + (int) $c->products_total,
            'low_stock' => $carry['low_stock'] + (int) $c->low_stock_total,
            'stock_value' => $carry['stock_value'] + (float) $c->stock_value,
        ], ['products' => 0, 'low_stock' => 0, 'stock_value' => 0.0]);

        return $sums;
    }
}

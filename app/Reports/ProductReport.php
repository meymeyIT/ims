<?php

namespace App\Reports;

use App\Models\Product;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** 1. Product Report: the product catalogue with prices and stock. */
class ProductReport extends Report
{
    use FiltersProducts;

    public function key(): string { return 'products'; }
    public function title(): string { return 'Product Report'; }
    public function description(): string { return 'All products with category, unit, prices, current and minimum stock.'; }
    public function group(): string { return 'products'; }
    public function filters(): array { return ['search', 'category', 'stock_status']; }

    public function query(ReportFilters $f): Builder
    {
        return $this->applyProductFilters(Product::query()->with(['category', 'unit']), $f)
            ->stockStatus($f->stockStatus)
            ->orderBy('name')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'type' => 'text'],
            'name' => ['label' => 'Product', 'type' => 'text'],
            'category' => ['label' => 'Category', 'type' => 'text'],
            'unit' => ['label' => 'Unit', 'type' => 'text'],
            'purchase_price' => ['label' => 'Purchase Price', 'type' => 'money'],
            'selling_price' => ['label' => 'Selling Price', 'type' => 'money'],
            'current_stock' => ['label' => 'Current Stock', 'type' => 'qty'],
            'minimum_stock' => ['label' => 'Minimum', 'type' => 'qty'],
            'stock_status' => ['label' => 'Stock Status', 'type' => 'text'],
            'status' => ['label' => 'Status', 'type' => 'text'],
        ];
    }

    public function row(Model $p): array
    {
        return [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category->name,
            'unit' => $p->unit->abbreviation,
            'purchase_price' => (float) $p->purchase_price,
            'selling_price' => (float) $p->selling_price,
            'current_stock' => (float) $p->current_stock,
            'minimum_stock' => (float) $p->minimum_stock,
            'stock_status' => ['in_stock' => 'In stock', 'low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock'][$p->stockStatus()],
            'status' => $p->is_active ? 'Active' : 'Inactive',
        ];
    }

    public function totals(ReportFilters $f): array
    {
        return ['name' => $this->query($f)->count() . ' product(s)'];
    }
}

<?php

namespace App\Reports;

use App\Models\Product;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** 8. Low Stock Report: active products at or below their minimum, with a restock suggestion. */
class LowStockReport extends Report
{
    use FiltersProducts;

    public function key(): string { return 'low-stock'; }
    public function title(): string { return 'Low Stock Report'; }
    public function description(): string { return 'Products at or below minimum stock, with shortage and suggested order quantity.'; }
    public function group(): string { return 'products'; }
    public function filters(): array { return ['search', 'category'] ; }

    public function note(): ?string
    {
        return 'Suggested order = enough to reach twice the minimum stock.';
    }

    public function query(ReportFilters $f): Builder
    {
        return $this->applyProductFilters(Product::query()->with(['category', 'unit'])->active()->lowStock(), $f)
            ->orderByRaw('CASE WHEN minimum_stock > 0 THEN current_stock / minimum_stock ELSE 0 END')
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
            'current' => ['label' => 'Current', 'type' => 'qty'],
            'minimum' => ['label' => 'Minimum', 'type' => 'qty'],
            'shortage' => ['label' => 'Shortage', 'type' => 'qty'],
            'suggested' => ['label' => 'Suggested Order', 'type' => 'qty'],
            'state' => ['label' => 'State', 'type' => 'text'],
        ];
    }

    public function row(Model $p): array
    {
        $current = (float) $p->current_stock;
        $minimum = (float) $p->minimum_stock;

        return [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category->name,
            'unit' => $p->unit->abbreviation,
            'current' => $current,
            'minimum' => $minimum,
            'shortage' => max($minimum - $current, 0),
            'suggested' => $p->suggestedRestockQuantity() !== null ? (float) $p->suggestedRestockQuantity() : null,
            'state' => $current <= 0 ? 'Out of stock' : 'Low stock',
        ];
    }

    public function totals(ReportFilters $f): array
    {
        return ['name' => $this->query($f)->count() . ' product(s)'];
    }
}

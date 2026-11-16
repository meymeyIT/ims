<?php

namespace App\Reports;

use App\Models\Product;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * 9. Inventory Valuation Report: weighted average cost method.
 *
 *   Value of a product = current stock × weighted-average unit cost
 *   Weighted-average unit cost is recalculated on every stock in:
 *     new avg = (stock × avg + qty in × unit cost) / (stock + qty in)
 *   Stock out does not change the average.
 */
class InventoryValuationReport extends Report
{
    use FiltersProducts;

    private float $grandTotal = 0.0;

    public function key(): string { return 'inventory-valuation'; }
    public function title(): string { return 'Inventory Valuation Report'; }
    public function description(): string { return 'Value of stock on hand using the weighted average cost method.'; }
    public function group(): string { return 'valuation'; }
    public function filters(): array { return ['search', 'category'] ; }

    public function note(): ?string
    {
        return 'Method: weighted average cost. Value = quantity × average unit cost. The average is recalculated on every stock in: '
            . '(stock × average + quantity in × unit cost) ÷ (stock + quantity in). Stock out does not change it.';
    }

    public function query(ReportFilters $f): Builder
    {
        return $this->applyProductFilters(Product::query()->with(['category', 'unit'])->where('current_stock', '>', 0), $f)
            ->orderByRaw('current_stock * average_cost DESC')
            ->orderBy('id');
    }

    public function prepare(ReportFilters $f): void
    {
        $this->grandTotal = (float) $this->query($f)->reorder()->toBase()->sum(DB::raw('ROUND(current_stock * average_cost, 2)'));
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'type' => 'text'],
            'name' => ['label' => 'Product', 'type' => 'text'],
            'category' => ['label' => 'Category', 'type' => 'text'],
            'quantity' => ['label' => 'Quantity', 'type' => 'qty'],
            'unit' => ['label' => 'Unit', 'type' => 'text'],
            'average_cost' => ['label' => 'Avg Unit Cost', 'type' => 'cost'],
            'value' => ['label' => 'Value', 'type' => 'money'],
            'share' => ['label' => '% of Total', 'type' => 'percent'],
        ];
    }

    public function row(Model $p): array
    {
        $value = round((float) $p->current_stock * (float) $p->average_cost, 2);

        return [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category->name,
            'quantity' => (float) $p->current_stock,
            'unit' => $p->unit->abbreviation,
            'average_cost' => (float) $p->average_cost,
            'value' => $value,
            'share' => $this->grandTotal > 0 ? round($value / $this->grandTotal * 100, 1) : 0,
        ];
    }

    public function totals(ReportFilters $f): array
    {
        return [
            'name' => $this->query($f)->count() . ' product(s) in stock',
            'value' => $this->grandTotal,
            'share' => $this->grandTotal > 0 ? 100 : 0,
        ];
    }
}

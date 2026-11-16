<?php

namespace App\Reports;

use App\Models\Product;
use App\Models\StockMovement;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 4. Stock Balance Report.
 * Without a date: the current stock. With "As of" a past date: the balance is
 * rebuilt from the stock movement ledger (sum of all movements up to that date).
 */
class StockBalanceReport extends Report
{
    use FiltersProducts;

    public function key(): string { return 'stock-balance'; }
    public function title(): string { return 'Stock Balance Report'; }
    public function description(): string { return 'Stock on hand per product, today or as of any past date (rebuilt from the ledger).'; }
    public function group(): string { return 'stock'; }
    public function filters(): array { return ['as_of', 'search', 'category', 'product']; }

    public function note(): ?string
    {
        return 'Past balances are calculated from the stock movement history. Values use the current weighted-average cost.';
    }

    public function query(ReportFilters $f): Builder
    {
        $query = Product::query()->with(['category', 'unit'])->select('products.*');

        if ($f->asOf) {
            $query->selectSub(
                StockMovement::query()
                    ->whereColumn('stock_movements.product_id', 'products.id')
                    ->where('movement_date', '<=', $f->asOf)
                    ->selectRaw('COALESCE(SUM(quantity), 0)'),
                'balance'
            );
        } else {
            $query->selectRaw('products.current_stock AS balance');
        }

        return $this->applyProductFilters($query, $f)->orderBy('name')->orderBy('products.id');
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'type' => 'text'],
            'name' => ['label' => 'Product', 'type' => 'text'],
            'category' => ['label' => 'Category', 'type' => 'text'],
            'unit' => ['label' => 'Unit', 'type' => 'text'],
            'balance' => ['label' => 'Balance', 'type' => 'qty'],
            'minimum' => ['label' => 'Minimum', 'type' => 'qty'],
            'average_cost' => ['label' => 'Avg Cost', 'type' => 'cost'],
            'value' => ['label' => 'Value', 'type' => 'money'],
        ];
    }

    public function row(Model $p): array
    {
        $balance = (float) $p->balance;

        return [
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category->name,
            'unit' => $p->unit->abbreviation,
            'balance' => $balance,
            'minimum' => (float) $p->minimum_stock,
            'average_cost' => (float) $p->average_cost,
            'value' => round($balance * (float) $p->average_cost, 2),
        ];
    }

    public function totals(ReportFilters $f): array
    {
        $value = $this->query($f)->get()->sum(fn ($p) => round((float) $p->balance * (float) $p->average_cost, 2));

        return ['value' => $value];
    }
}

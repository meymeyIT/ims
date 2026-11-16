<?php

namespace App\Reports;

use App\Enums\MovementType;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** 5. Stock In Report: everything that entered inventory in the period (IN + RETURN_IN). */
class StockInReport extends Report
{
    use FiltersProducts;

    public function key(): string { return 'stock-in'; }
    public function title(): string { return 'Stock In Report'; }
    public function description(): string { return 'Purchase receipts, manual stock in and customer returns, with quantities and cost.'; }
    public function group(): string { return 'stock'; }
    public function filters(): array { return ['date_range', 'search', 'product', 'category', 'supplier']; }

    public function note(): ?string
    {
        return 'Reversals appear as negative lines, so totals are always net.';
    }

    protected function types(): array
    {
        return [MovementType::In->value, MovementType::ReturnIn->value];
    }

    public function query(ReportFilters $f): Builder
    {
        return $this->applyMovementFilters(
            StockMovement::query()
                ->with([
                    'product.unit', 'user', 'supplier',
                    'reference' => fn (MorphTo $m) => $m->morphWith([PurchaseReceiptItem::class => ['receipt.purchase']]),
                ])
                ->whereIn('type', $this->types()),
            $f
        )->orderBy('movement_date')->orderBy('id');
    }

    public function columns(): array
    {
        return [
            'date' => ['label' => 'Date', 'type' => 'datetime'],
            'number' => ['label' => 'Transaction #', 'type' => 'text'],
            'product' => ['label' => 'Product', 'type' => 'text'],
            'sku' => ['label' => 'SKU', 'type' => 'text'],
            'quantity' => ['label' => 'Quantity', 'type' => 'qty'],
            'unit' => ['label' => 'Unit', 'type' => 'text'],
            'unit_cost' => ['label' => 'Unit Cost', 'type' => 'cost'],
            'value' => ['label' => 'Value', 'type' => 'money'],
            'source' => ['label' => 'Source', 'type' => 'text'],
            'user' => ['label' => 'User', 'type' => 'text'],
        ];
    }

    public function row(Model $m): array
    {
        $purchase = $m->reference?->receipt?->purchase;
        $source = match (true) {
            $m->reversal_of_id !== null => 'Reversal',
            $purchase !== null => $purchase->purchase_number,
            default => ucfirst(str_replace('_', ' ', (string) $m->reason)),
        };

        return [
            'date' => $m->movement_date,
            'number' => $m->movement_number,
            'product' => $m->product->name,
            'sku' => $m->product->sku,
            'quantity' => (float) $m->quantity,
            'unit' => $m->product->unit->abbreviation,
            'unit_cost' => (float) $m->unit_cost,
            'value' => round((float) $m->quantity * (float) $m->unit_cost, 2),
            'source' => $source . ($m->supplier ? " ({$m->supplier->name})" : ''),
            'user' => $m->user->name,
        ];
    }

    public function totals(ReportFilters $f): array
    {
        $sums = $this->query($f)->reorder()->toBase()
            ->selectRaw('COUNT(*) AS line_count, COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(ROUND(quantity * COALESCE(unit_cost, 0), 2)), 0) AS value')
            ->first();

        return [
            'number' => $sums->line_count . ' line(s)',
            'quantity' => (float) $sums->qty,
            'value' => (float) $sums->value,
        ];
    }
}

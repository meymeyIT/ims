<?php

namespace App\Reports;

use App\Enums\MovementType;
use App\Enums\StockOutReason;
use App\Models\StockMovement;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 6. Stock Out Report: everything that left inventory (OUT + RETURN_OUT).
 * Quantities are shown as positive numbers; value = quantity × average cost at that moment.
 */
class StockOutReport extends Report
{
    use FiltersProducts;

    public function key(): string { return 'stock-out'; }
    public function title(): string { return 'Stock Out Report'; }
    public function description(): string { return 'Sold, damaged, expired, internal use and returns to suppliers, valued at average cost.'; }
    public function group(): string { return 'stock'; }
    public function filters(): array { return ['date_range', 'search', 'product', 'category', 'out_reason']; }

    public function note(): ?string
    {
        return 'Value = quantity × weighted-average cost at the time of the stock out. Reversals appear as negative lines.';
    }

    public function query(ReportFilters $f): Builder
    {
        return $this->applyMovementFilters(
            StockMovement::query()
                ->with(['product.unit', 'user', 'supplier'])
                ->whereIn('type', [MovementType::Out->value, MovementType::ReturnOut->value])
                ->when($f->outReason, fn ($q) => $q->where('reason', $f->outReason)),
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
            'unit_cost' => ['label' => 'Avg Cost', 'type' => 'cost'],
            'value' => ['label' => 'Value', 'type' => 'money'],
            'reason' => ['label' => 'Reason', 'type' => 'text'],
            'user' => ['label' => 'User', 'type' => 'text'],
        ];
    }

    public function row(Model $m): array
    {
        $quantity = -(float) $m->quantity;   // stored negative; shown positive
        $reason = $m->reversal_of_id !== null
            ? 'Reversal'
            : (StockOutReason::tryFrom((string) $m->reason)?->label() ?? (string) $m->reason);

        return [
            'date' => $m->movement_date,
            'number' => $m->movement_number,
            'product' => $m->product->name,
            'sku' => $m->product->sku,
            'quantity' => $quantity,
            'unit' => $m->product->unit->abbreviation,
            'unit_cost' => (float) $m->unit_cost,
            'value' => round($quantity * (float) $m->unit_cost, 2),
            'reason' => $reason . ($m->supplier ? " ({$m->supplier->name})" : ''),
            'user' => $m->user->name,
        ];
    }

    public function totals(ReportFilters $f): array
    {
        $sums = $this->query($f)->reorder()->toBase()
            ->selectRaw('COUNT(*) AS line_count, COALESCE(SUM(-quantity), 0) AS qty, COALESCE(SUM(ROUND(-quantity * COALESCE(unit_cost, 0), 2)), 0) AS value')
            ->first();

        return [
            'number' => $sums->line_count . ' line(s)',
            'quantity' => (float) $sums->qty,
            'value' => (float) $sums->value,
        ];
    }
}

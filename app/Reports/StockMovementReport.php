<?php

namespace App\Reports;

use App\Enums\MovementType;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Reports\Concerns\FiltersProducts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** 10. Stock Movement Report: the full ledger for a period, every movement type. */
class StockMovementReport extends Report
{
    use FiltersProducts;

    public function key(): string { return 'stock-movements'; }
    public function title(): string { return 'Stock Movement Report'; }
    public function description(): string { return 'Every inventory transaction (IN, OUT, ADJUSTMENT, RETURN_IN, RETURN_OUT) with balances.'; }
    public function group(): string { return 'stock'; }
    public function filters(): array { return ['date_range', 'search', 'product', 'category', 'movement_type'] ; }

    public function query(ReportFilters $f): Builder
    {
        return $this->applyMovementFilters(
            StockMovement::query()
                ->with([
                    'product.unit', 'user', 'reversalOf',
                    'reference' => fn (MorphTo $m) => $m->morphWith([PurchaseReceiptItem::class => ['receipt.purchase']]),
                ])
                ->when($f->movementType, fn ($q) => $q->where('type', $f->movementType)),
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
            'type' => ['label' => 'Type', 'type' => 'text'],
            'quantity' => ['label' => 'Quantity', 'type' => 'qty'],
            'previous' => ['label' => 'Previous', 'type' => 'qty'],
            'new' => ['label' => 'New', 'type' => 'qty'],
            'reference' => ['label' => 'Reference / Reason', 'type' => 'text'],
            'user' => ['label' => 'User', 'type' => 'text'],
        ];
    }

    public function row(Model $m): array
    {
        $reference = match (true) {
            $m->reversalOf !== null => 'Reversal of ' . $m->reversalOf->movement_number,
            $m->reference?->receipt?->purchase !== null => $m->reference->receipt->purchase->purchase_number,
            default => ucfirst(str_replace('_', ' ', (string) $m->reason)),
        };

        return [
            'date' => $m->movement_date,
            'number' => $m->movement_number,
            'product' => $m->product->name,
            'sku' => $m->product->sku,
            'type' => $m->type->label(),
            'quantity' => (float) $m->quantity,
            'previous' => (float) $m->previous_stock,
            'new' => (float) $m->new_stock,
            'reference' => $reference,
            'user' => $m->user->name,
        ];
    }

    public function totals(ReportFilters $f): array
    {
        $byType = $this->query($f)->reorder()->toBase()
            ->selectRaw('type, COUNT(*) AS line_count')
            ->groupBy('type')
            ->pluck('line_count', 'type');

        $summary = collect(MovementType::cases())
            ->filter(fn ($t) => isset($byType[$t->value]))
            ->map(fn ($t) => "{$t->label()}: {$byType[$t->value]}")
            ->implode(' · ');

        return ['number' => $byType->sum() . ' movement(s)', 'product' => $summary];
    }
}

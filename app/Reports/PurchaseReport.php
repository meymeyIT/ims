<?php

namespace App\Reports;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** 7. Purchase Report: purchase orders in the period with amounts. */
class PurchaseReport extends Report
{
    public function key(): string { return 'purchases'; }
    public function title(): string { return 'Purchase Report'; }
    public function description(): string { return 'Purchase orders with supplier, status, subtotal, discount, tax and total.'; }
    public function group(): string { return 'purchases'; }
    public function permission(): ?string { return 'purchases.view'; }
    public function filters(): array { return ['date_range', 'supplier', 'purchase_status', 'search']; }
    public function totalsLabel(): string { return 'Total (excl. drafts & cancelled)'; }

    /** Filters only (no eager loads / counts), shared by the list and the totals. */
    private function filtered(ReportFilters $f): Builder
    {
        return Purchase::query()
            ->when($f->startDate, fn ($q) => $q->where('purchase_date', '>=', $f->startDate->toDateString()))
            ->when($f->endDate, fn ($q) => $q->where('purchase_date', '<=', $f->endDate->toDateString()))
            ->when($f->supplier, fn ($q) => $q->where('supplier_id', $f->supplier))
            ->when($f->purchaseStatus, fn ($q) => $q->where('status', $f->purchaseStatus))
            ->when($f->search, fn ($q) => $q->search($f->search));
    }

    public function query(ReportFilters $f): Builder
    {
        return $this->filtered($f)
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->orderBy('purchase_date')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [
            'number' => ['label' => 'Purchase #', 'type' => 'text'],
            'date' => ['label' => 'Date', 'type' => 'date'],
            'supplier' => ['label' => 'Supplier', 'type' => 'text'],
            'status' => ['label' => 'Status', 'type' => 'text'],
            'items' => ['label' => 'Items', 'type' => 'int'],
            'subtotal' => ['label' => 'Subtotal', 'type' => 'money'],
            'discount' => ['label' => 'Discount', 'type' => 'money'],
            'tax' => ['label' => 'Tax', 'type' => 'money'],
            'total' => ['label' => 'Total', 'type' => 'money'],
            'created_by' => ['label' => 'Created By', 'type' => 'text'],
        ];
    }

    public function row(Model $p): array
    {
        return [
            'number' => $p->purchase_number,
            'date' => $p->purchase_date,
            'supplier' => $p->supplier->name,
            'status' => $p->status->label(),
            'items' => (int) $p->items_count,
            'subtotal' => (float) $p->subtotal,
            'discount' => (float) $p->discount,
            'tax' => (float) $p->tax,
            'total' => (float) $p->total_amount,
            'created_by' => $p->creator->name,
        ];
    }

    /** Drafts and cancelled orders are listed but never added to the totals. */
    public function totals(ReportFilters $f): array
    {
        $sums = $this->filtered($f)->toBase()
            ->whereNotIn('status', [PurchaseStatus::Draft->value, PurchaseStatus::Cancelled->value])
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(subtotal),0) AS subtotal, COALESCE(SUM(discount),0) AS discount, COALESCE(SUM(tax),0) AS tax, COALESCE(SUM(total_amount),0) AS total')
            ->first();

        return [
            'items' => (int) $sums->orders . ' order(s)',
            'subtotal' => (float) $sums->subtotal,
            'discount' => (float) $sums->discount,
            'tax' => (float) $sums->tax,
            'total' => (float) $sums->total,
        ];
    }
}

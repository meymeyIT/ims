<?php

namespace App\Reports;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** 3. Supplier Report: purchase activity per supplier in the period (drafts and cancelled excluded). */
class SupplierReport extends Report
{
    public function key(): string { return 'suppliers'; }
    public function title(): string { return 'Supplier Report'; }
    public function description(): string { return 'Purchase orders, amount purchased and last order date per supplier.'; }
    public function group(): string { return 'purchases'; }
    public function permission(): ?string { return 'purchases.view'; }
    public function filters(): array { return ['date_range', 'search', 'supplier']; }

    public function note(): ?string
    {
        return 'Drafts and cancelled purchase orders are not counted.';
    }

    public function query(ReportFilters $f): Builder
    {
        $purchases = fn () => Purchase::query()
            ->whereColumn('purchases.supplier_id', 'suppliers.id')
            ->whereNotIn('status', [PurchaseStatus::Draft->value, PurchaseStatus::Cancelled->value])
            ->when($f->startDate, fn ($q) => $q->where('purchase_date', '>=', $f->startDate->toDateString()))
            ->when($f->endDate, fn ($q) => $q->where('purchase_date', '<=', $f->endDate->toDateString()));

        return Supplier::query()
            ->select('suppliers.*')
            ->selectSub($purchases()->selectRaw('COUNT(*)'), 'orders_total')
            ->selectSub($purchases()->selectRaw('COALESCE(SUM(total_amount), 0)'), 'amount_total')
            ->selectSub($purchases()->selectRaw('MAX(purchase_date)'), 'last_purchase')
            ->when($f->search, fn ($q) => $q->search($f->search))
            ->when($f->supplier, fn ($q) => $q->whereKey($f->supplier))
            ->orderBy('name')
            ->orderBy('id');
    }

    public function columns(): array
    {
        return [
            'name' => ['label' => 'Supplier', 'type' => 'text'],
            'company' => ['label' => 'Company', 'type' => 'text'],
            'contact' => ['label' => 'Contact', 'type' => 'text'],
            'phone' => ['label' => 'Phone', 'type' => 'text'],
            'orders' => ['label' => 'Orders', 'type' => 'int'],
            'amount' => ['label' => 'Amount Purchased', 'type' => 'money'],
            'last_purchase' => ['label' => 'Last Order', 'type' => 'date'],
            'status' => ['label' => 'Status', 'type' => 'text'],
        ];
    }

    public function row(Model $s): array
    {
        return [
            'name' => $s->name,
            'company' => $s->company_name,
            'contact' => $s->contact_person,
            'phone' => $s->phone,
            'orders' => (int) $s->orders_total,
            'amount' => (float) $s->amount_total,
            'last_purchase' => $s->last_purchase ? \Illuminate\Support\Carbon::parse($s->last_purchase) : null,   // a date: formatted by Report::format / Excel
            'status' => $s->is_active ? 'Active' : 'Inactive',
        ];
    }

    public function totals(ReportFilters $f): array
    {
        $rows = $this->query($f)->get();

        return [
            'orders' => $rows->sum(fn ($s) => (int) $s->orders_total),
            'amount' => $rows->sum(fn ($s) => (float) $s->amount_total),
        ];
    }
}

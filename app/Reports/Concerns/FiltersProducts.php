<?php

namespace App\Reports\Concerns;

use App\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/** Shared product / category / search filters. */
trait FiltersProducts
{
    protected function applyProductFilters(Builder $query, ReportFilters $f): Builder
    {
        return $query
            ->when($f->search, fn ($q) => $q->search($f->search))
            ->when($f->product, fn ($q) => $q->whereKey($f->product))
            ->when($f->category, fn ($q) => $q->where('category_id', $f->category));
    }

    /** For stock_movements queries: filter through the related product. */
    protected function applyMovementFilters(Builder $query, ReportFilters $f): Builder
    {
        return $query
            ->when($f->startDate, fn ($q) => $q->where('movement_date', '>=', $f->startDate))
            ->when($f->endDate, fn ($q) => $q->where('movement_date', '<=', $f->endDate))
            ->when($f->product, fn ($q) => $q->where('product_id', $f->product))
            ->when($f->supplier, fn ($q) => $q->where('supplier_id', $f->supplier))
            ->when($f->category || $f->search, fn ($q) => $q->whereHas('product', fn ($p) => $p->withTrashed()
                ->when($f->category, fn ($c) => $c->where('category_id', $f->category))
                ->when($f->search, fn ($s) => $s->search($f->search))));
    }
}

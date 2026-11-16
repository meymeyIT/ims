<?php

namespace App\Reports;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Enums\StockOutReason;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validated filter values for a report request.
 * Transactional reports default to the current month.
 */
class ReportFilters
{
    /** Every filter control the report pages know how to render. */
    public const ALL = ['date_range', 'as_of', 'search', 'product', 'category', 'supplier', 'purchase_status', 'movement_type', 'out_reason', 'stock_status'];

    public function __construct(
        public readonly ?CarbonImmutable $startDate = null,
        public readonly ?CarbonImmutable $endDate = null,
        public readonly ?CarbonImmutable $asOf = null,
        public readonly ?string $search = null,
        public readonly ?int $product = null,
        public readonly ?int $category = null,
        public readonly ?int $supplier = null,
        public readonly ?string $purchaseStatus = null,
        public readonly ?string $movementType = null,
        public readonly ?string $outReason = null,
        public readonly ?string $stockStatus = null,
    ) {
    }

    public static function fromRequest(Request $request, Report $report): self
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'as_of' => ['nullable', 'date', 'before_or_equal:today'],
            'search' => ['nullable', 'string', 'max:100'],
            'product' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'supplier' => ['nullable', 'integer'],
            'purchase_status' => ['nullable', Rule::enum(PurchaseStatus::class)],
            'movement_type' => ['nullable', Rule::enum(MovementType::class)],
            'out_reason' => ['nullable', Rule::enum(StockOutReason::class)],
            'stock_status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
        ]);

        $uses = fn (string $filter) => in_array($filter, $report->filters(), true);
        $date = fn (?string $value) => $value ? CarbonImmutable::parse($value) : null;

        $start = $date($data['start_date'] ?? null);
        $end = $date($data['end_date'] ?? null);

        // Date-range reports default to this month so they never load all history by accident.
        if ($uses('date_range') && ! $request->has('start_date') && ! $request->has('end_date')) {
            $start = CarbonImmutable::now()->startOfMonth();
            $end = CarbonImmutable::today();
        }

        return new self(
            startDate: $uses('date_range') ? $start?->startOfDay() : null,
            endDate: $uses('date_range') ? $end?->endOfDay() : null,
            asOf: $uses('as_of') ? $date($data['as_of'] ?? null)?->endOfDay() : null,
            search: $uses('search') ? (trim((string) ($data['search'] ?? '')) ?: null) : null,
            product: $uses('product') ? ($data['product'] ?? null) : null,
            category: $uses('category') ? ($data['category'] ?? null) : null,
            supplier: $uses('supplier') ? ($data['supplier'] ?? null) : null,
            purchaseStatus: $uses('purchase_status') ? ($data['purchase_status'] ?? null) : null,
            movementType: $uses('movement_type') ? ($data['movement_type'] ?? null) : null,
            outReason: $uses('out_reason') ? ($data['out_reason'] ?? null) : null,
            stockStatus: $uses('stock_status') ? ($data['stock_status'] ?? null) : null,
        );
    }

    /** Values for re-filling the filter form and for building export links. */
    public function toQuery(): array
    {
        return array_filter([
            'start_date' => $this->startDate?->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
            'as_of' => $this->asOf?->toDateString(),
            'search' => $this->search,
            'product' => $this->product,
            'category' => $this->category,
            'supplier' => $this->supplier,
            'purchase_status' => $this->purchaseStatus,
            'movement_type' => $this->movementType,
            'out_reason' => $this->outReason,
            'stock_status' => $this->stockStatus,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** One line describing the active filters, printed on exports. */
    public function describe(array $names = []): string
    {
        $parts = [];
        if ($this->startDate || $this->endDate) {
            $parts[] = 'Period: ' . ($this->startDate?->toAppDate() ?? '…') . ' – ' . ($this->endDate?->toAppDate() ?? '…');
        }
        if ($this->asOf) {
            $parts[] = 'As of ' . $this->asOf->toAppDate();
        }
        foreach ($names as $label => $value) {
            if ($value) {
                $parts[] = "{$label}: {$value}";
            }
        }
        if ($this->search) {
            $parts[] = "Search: \"{$this->search}\"";
        }

        return $parts ? implode(' · ', $parts) : 'All records';
    }
}

<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Every dashboard number comes from the database here: nothing is hard-coded.
 * Quantities in different units (pcs, kg, ...) are added together where the spec
 * asks for a total, so they are labelled "units".
 */
class DashboardStats
{
    private const IN_TYPES = [MovementType::In, MovementType::ReturnIn];
    private const OUT_TYPES = [MovementType::Out, MovementType::ReturnOut];

    /** Purchases that count as real spending (not drafts, not cancelled). */
    private const COUNTED_PURCHASES = [
        PurchaseStatus::Pending, PurchaseStatus::Approved, PurchaseStatus::PartiallyReceived, PurchaseStatus::Received,
    ];

    public function cards(): array
    {
        $today = [now()->startOfDay(), now()->endOfDay()];

        // One query for today's stock in/out (net of same-day reversals).
        $flowToday = StockMovement::query()
            ->whereBetween('movement_date', $today)
            ->selectRaw('
                SUM(CASE WHEN type IN (?, ?) THEN quantity ELSE 0 END) AS in_qty,
                SUM(CASE WHEN type IN (?, ?) AND reversal_of_id IS NULL THEN 1 ELSE 0 END) AS in_count,
                SUM(CASE WHEN type IN (?, ?) THEN -quantity ELSE 0 END) AS out_qty,
                SUM(CASE WHEN type IN (?, ?) AND reversal_of_id IS NULL THEN 1 ELSE 0 END) AS out_count
            ', [...$this->values(self::IN_TYPES), ...$this->values(self::IN_TYPES), ...$this->values(self::OUT_TYPES), ...$this->values(self::OUT_TYPES)])
            ->first();

        $products = Product::query()
            ->selectRaw('COUNT(*) AS total, COALESCE(SUM(current_stock), 0) AS quantity, COALESCE(SUM(current_stock * average_cost), 0) AS value')
            ->first();

        return [
            'products' => (int) $products->total,
            'categories' => Category::count(),
            'suppliers' => Supplier::count(),
            'total_stock' => (string) $products->quantity,
            'stock_in_today' => (string) ($flowToday->in_qty ?? 0),
            'stock_in_today_count' => (int) ($flowToday->in_count ?? 0),
            'stock_out_today' => (string) ($flowToday->out_qty ?? 0),
            'stock_out_today_count' => (int) ($flowToday->out_count ?? 0),
            'low_stock' => Product::active()->lowStock()->count(),
            'inventory_value' => (string) $products->value,
            'pending_purchases' => Purchase::where('status', PurchaseStatus::Pending)->count(),
            'awaiting_delivery' => Purchase::whereIn('status', [PurchaseStatus::Approved, PurchaseStatus::PartiallyReceived])->count(),
            'purchases_this_month' => (string) Purchase::whereIn('status', self::COUNTED_PURCHASES)
                ->whereBetween('purchase_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('total_amount'),
            'purchases_this_month_count' => Purchase::whereIn('status', self::COUNTED_PURCHASES)
                ->whereBetween('purchase_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->count(),
        ];
    }

    /** Data for the five charts, ready to be JSON-encoded for Chart.js. */
    public function charts(): array
    {
        $months = collect(CarbonPeriod::create(now()->startOfMonth()->subMonths(11), '1 month', now()->startOfMonth()))
            ->map(fn ($month) => $month->format('Y-m'));
        $labels = $months->map(fn ($key) => now()->createFromFormat('Y-m-d', "{$key}-01")->format('M Y'))->values();
        $since = now()->startOfMonth()->subMonths(11)->startOfDay();

        // 1. Monthly stock in / out (quantities, net of reversals).
        $flow = StockMovement::query()
            ->where('movement_date', '>=', $since)
            ->selectRaw("DATE_FORMAT(movement_date, '%Y-%m') AS month,
                SUM(CASE WHEN type IN (?, ?) THEN quantity ELSE 0 END) AS in_qty,
                SUM(CASE WHEN type IN (?, ?) THEN -quantity ELSE 0 END) AS out_qty", [...$this->values(self::IN_TYPES), ...$this->values(self::OUT_TYPES)])
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // 3. Monthly purchase totals.
        $purchases = Purchase::query()
            ->whereIn('status', self::COUNTED_PURCHASES)
            ->where('purchase_date', '>=', $since->toDateString())
            ->selectRaw("DATE_FORMAT(purchase_date, '%Y-%m') AS month, SUM(total_amount) AS total, COUNT(*) AS orders")
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        // 2. Products per category (active, not archived).
        $categories = Category::query()
            ->withCount(['products as active_products_count' => fn ($q) => $q->withoutTrashed()->where('is_active', true)])
            ->get()
            ->filter(fn ($c) => $c->active_products_count > 0)
            ->sortByDesc('active_products_count')
            ->values();

        // 4. Top 10 most frequently issued products (stock-out transactions, last 90 days, reversals excluded).
        $top = StockMovement::query()
            ->whereIn('type', $this->values(self::OUT_TYPES))
            ->whereNull('reversal_of_id')
            ->whereDoesntHave('reversal')
            ->where('movement_date', '>=', now()->subDays(90))
            ->select('product_id', DB::raw('COUNT(*) AS transactions'), DB::raw('SUM(-quantity) AS quantity'))
            ->groupBy('product_id')
            ->orderByDesc('transactions')
            ->limit(10)
            ->with('product.unit')
            ->get();

        // 5. Low stock: stock as a % of the minimum (unit-independent), most urgent first.
        $low = Product::query()
            ->active()
            ->lowStock()
            ->where('minimum_stock', '>', 0)
            ->with('unit')
            ->orderByRaw('current_stock / minimum_stock')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'unit_id', 'current_stock', 'minimum_stock']);

        return [
            'stockFlow' => [
                'labels' => $labels,
                'in' => $months->map(fn ($m) => (float) ($flow[$m]->in_qty ?? 0))->values(),
                'out' => $months->map(fn ($m) => (float) ($flow[$m]->out_qty ?? 0))->values(),
            ],
            'purchases' => [
                'labels' => $labels,
                'totals' => $months->map(fn ($m) => (float) ($purchases[$m]->total ?? 0))->values(),
                'orders' => $months->map(fn ($m) => (int) ($purchases[$m]->orders ?? 0))->values(),
            ],
            'categories' => [
                'labels' => $categories->pluck('name')->values(),
                'counts' => $categories->pluck('active_products_count')->values(),
            ],
            'topProducts' => [
                'labels' => $top->map(fn ($row) => $row->product->name)->values(),
                'transactions' => $top->pluck('transactions')->map(fn ($v) => (int) $v)->values(),
                'quantities' => $top->map(fn ($row) => (float) $row->quantity . ' ' . $row->product->unit->abbreviation)->values(),
            ],
            'lowStock' => [
                'labels' => $low->pluck('name')->values(),
                'percent' => $low->map(fn ($p) => round((float) $p->current_stock / (float) $p->minimum_stock * 100, 1))->values(),
                'detail' => $low->map(fn ($p) => sprintf('%s / %s %s', (float) $p->current_stock, (float) $p->minimum_stock, $p->unit->abbreviation))->values(),
                'out' => $low->map(fn ($p) => (float) $p->current_stock <= 0)->values(),
            ],
        ];
    }

    private function values(array $cases): array
    {
        return array_map(fn ($case) => $case->value, $cases);
    }
}

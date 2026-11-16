<?php

namespace App\Reports;

use App\Models\User;

/** The list of available reports, grouped like the sidebar. */
class ReportRegistry
{
    public const GROUPS = [
        'products' => ['Product Reports', 'box-seam'],
        'stock' => ['Stock Reports', 'bar-chart-line'],
        'purchases' => ['Purchase Reports', 'receipt'],
        'valuation' => ['Inventory Valuation', 'cash-stack'],
    ];

    private const REPORTS = [
        ProductReport::class,
        CategoryReport::class,
        LowStockReport::class,
        StockBalanceReport::class,
        StockInReport::class,
        StockOutReport::class,
        StockMovementReport::class,
        PurchaseReport::class,
        SupplierReport::class,
        InventoryValuationReport::class,
    ];

    /** @return array<string, Report> keyed by report key */
    public static function all(): array
    {
        $reports = [];
        foreach (self::REPORTS as $class) {
            $report = new $class();
            $reports[$report->key()] = $report;
        }

        return $reports;
    }

    public static function find(string $key): ?Report
    {
        return self::all()[$key] ?? null;
    }

    public static function canView(User $user, Report $report): bool
    {
        return $user->can('reports.view') && ($report->permission() === null || $user->can($report->permission()));
    }

    /** Reports the user may open, grouped: group => [key => Report]. */
    public static function visibleGrouped(User $user, ?string $onlyGroup = null): array
    {
        $grouped = [];
        foreach (self::all() as $key => $report) {
            if (($onlyGroup === null || $report->group() === $onlyGroup) && self::canView($user, $report)) {
                $grouped[$report->group()][$key] = $report;
            }
        }

        return array_replace(array_intersect_key(array_fill_keys(array_keys(self::GROUPS), null), $grouped), $grouped);
    }
}

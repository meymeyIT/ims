<?php

namespace App\Reports;

use App\Support\Branding;
use App\Support\Currency;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every report.
 *
 * A report only describes WHAT to show (filters, query, columns, totals).
 * The same ReportController, page, Excel export, PDF and print view render
 * every report, so all 10 reports behave the same way.
 *
 * Column types: text, int, qty (3 dp), money (2 dp), cost (4 dp), percent, date, datetime.
 * row() returns RAW values (numbers stay numbers so Excel can calculate with them);
 * formatting for the screen/PDF happens in format().
 */
abstract class Report
{
    /** URL key, e.g. "stock-in". */
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /** products | stock | purchases | valuation (sidebar sections). */
    abstract public function group(): string;

    /** Filter controls this report supports (see ReportFilters::ALL). */
    abstract public function filters(): array;

    /** The filtered query. Must have a deterministic order (needed for paging and exports). */
    abstract public function query(ReportFilters $filters): Builder;

    /** key => ['label' => ..., 'type' => ...] */
    abstract public function columns(): array;

    /** One row: column key => raw value. */
    abstract public function row(Model $record): array;

    /** Totals row: column key => raw value (only for columns that have a total). */
    public function totals(ReportFilters $filters): array
    {
        return [];
    }

    /** Label shown in the first cell of the totals row. */
    public function totalsLabel(): string
    {
        return 'Total';
    }

    /** Extra permission needed besides reports.view (e.g. purchase data). */
    public function permission(): ?string
    {
        return null;
    }

    /** Called once before rows are produced (e.g. to compute a grand total for percentages). */
    public function prepare(ReportFilters $filters): void
    {
    }

    /** A short note shown under the table and in exports (e.g. the valuation method). */
    public function note(): ?string
    {
        return null;
    }

    public function orientation(): string
    {
        return count($this->columns()) > 7 ? 'landscape' : 'portrait';
    }

    /** Human-readable value for the screen, print and PDF. */
    public static function format(mixed $value, string $type, bool $plainCurrency = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'int' => number_format((int) $value),
            'qty' => Quantity::format($value),
            'money' => Currency::format($value, $plainCurrency),
            'cost' => Currency::format($value, $plainCurrency, 4),
            'percent' => number_format((float) $value, 1) . '%',
            'date' => $value instanceof \DateTimeInterface ? $value->format(Branding::dateFormat()) : (string) $value,
            'datetime' => $value instanceof \DateTimeInterface ? $value->format(Branding::dateTimeFormat()) : (string) $value,
            default => (string) $value,
        };
    }

    public static function isNumeric(string $type): bool
    {
        return in_array($type, ['int', 'qty', 'money', 'cost', 'percent'], true);
    }
}

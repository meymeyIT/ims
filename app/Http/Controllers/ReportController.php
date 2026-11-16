<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Enums\StockOutReason;
use App\Exports\ReportExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Supplier;
use App\Reports\Report;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * One controller for all 10 reports: list, view (paginated), Excel, PDF and print.
 */
class ReportController extends Controller implements HasMiddleware
{
    private const PER_PAGE = 25;

    /** PDF and print show at most this many rows; Excel has no limit. */
    private const DOCUMENT_LIMIT = 2000;

    public static function middleware(): array
    {
        return [
            new Middleware('can:reports.view'),
            new Middleware('can:reports.export', only: ['excel', 'pdf', 'print']),
        ];
    }

    public function index(Request $request): View
    {
        $group = array_key_exists((string) $request->query('group'), ReportRegistry::GROUPS) ? $request->query('group') : null;

        return view('reports.index', [
            'grouped' => ReportRegistry::visibleGrouped($request->user(), $group),
            'group' => $group,
        ]);
    }

    public function show(Request $request, string $report): View
    {
        $report = $this->resolve($request, $report);
        $filters = ReportFilters::fromRequest($request, $report);
        $report->prepare($filters);

        return view('reports.show', [
            'report' => $report,
            'filters' => $filters,
            'records' => $report->query($filters)->paginate(self::PER_PAGE)->withQueryString(),
            'totals' => $report->totals($filters),
            'options' => $this->filterOptions($report),
        ]);
    }

    public function excel(Request $request, string $report): BinaryFileResponse
    {
        $report = $this->resolve($request, $report);
        $filters = ReportFilters::fromRequest($request, $report);

        ActivityLogger::log('exported', 'reports', "Exported {$report->title()} to Excel");

        return Excel::download(
            new ReportExport($report, $filters, $filters->describe($this->filterNames($filters)), $this->businessName()),
            $this->filename($report, 'xlsx'),
        );
    }

    public function pdf(Request $request, string $report): Response
    {
        $report = $this->resolve($request, $report);
        $data = $this->documentData($request, $report);

        ActivityLogger::log('exported', 'reports', "Exported {$report->title()} to PDF");

        return Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4', $report->orientation())
            ->download($this->filename($report, 'pdf'));
    }

    public function print(Request $request, string $report): View
    {
        $report = $this->resolve($request, $report);

        return view('reports.print', $this->documentData($request, $report));
    }

    // ------------------------------------------------------------------ helpers

    private function resolve(Request $request, string $key): Report
    {
        $report = ReportRegistry::find($key);

        abort_if($report === null, 404);
        abort_unless(ReportRegistry::canView($request->user(), $report), 403);

        return $report;
    }

    /** Rows + totals for PDF and print (limited, with a "truncated" flag). */
    private function documentData(Request $request, Report $report): array
    {
        $filters = ReportFilters::fromRequest($request, $report);
        $report->prepare($filters);
        $records = $report->query($filters)->limit(self::DOCUMENT_LIMIT + 1)->get();

        return [
            'report' => $report,
            'filters' => $filters,
            'rows' => $records->take(self::DOCUMENT_LIMIT)->map(fn ($record) => $report->row($record)),
            'totals' => $report->totals($filters),
            'truncated' => $records->count() > self::DOCUMENT_LIMIT,
            'limit' => self::DOCUMENT_LIMIT,
            'filterLine' => $filters->describe($this->filterNames($filters)),
            'businessName' => $this->businessName(),
        ];
    }

    /** Only load the dropdown lists this report's filters actually use. */
    private function filterOptions(Report $report): array
    {
        $uses = fn (string $filter) => in_array($filter, $report->filters(), true);

        return [
            'products' => $uses('product') ? Product::withTrashed()->orderBy('name')->get(['id', 'name', 'sku']) : collect(),
            'categories' => $uses('category') ? Category::orderBy('name')->get(['id', 'name']) : collect(),
            'suppliers' => $uses('supplier') ? Supplier::withTrashed()->orderBy('name')->get(['id', 'name']) : collect(),
            'purchaseStatuses' => PurchaseStatus::cases(),
            'movementTypes' => MovementType::cases(),
            'outReasons' => StockOutReason::cases(),
        ];
    }

    /** Readable names of the selected filter values, printed on exports. */
    private function filterNames(ReportFilters $f): array
    {
        return [
            'Product' => $f->product ? Product::withTrashed()->whereKey($f->product)->value('name') : null,
            'Category' => $f->category ? Category::whereKey($f->category)->value('name') : null,
            'Supplier' => $f->supplier ? Supplier::withTrashed()->whereKey($f->supplier)->value('name') : null,
            'Status' => $f->purchaseStatus ? PurchaseStatus::from($f->purchaseStatus)->label() : null,
            'Type' => $f->movementType ? MovementType::from($f->movementType)->label() : null,
            'Reason' => $f->outReason ? StockOutReason::from($f->outReason)->label() : null,
            'Stock' => $f->stockStatus ? Str::headline($f->stockStatus) : null,
        ];
    }

    private function businessName(): string
    {
        return Setting::get('business_name', config('app.name'));
    }

    private function filename(Report $report, string $extension): string
    {
        return Str::slug($report->title()) . '-' . now()->format('Ymd-His') . '.' . $extension;
    }
}

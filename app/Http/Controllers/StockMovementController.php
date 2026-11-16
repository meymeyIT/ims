<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stock movement history: the complete, read-only inventory ledger.
 * Corrections are made with reverse(), which adds a compensating movement.
 */
class StockMovementController extends Controller implements HasMiddleware
{
    /** Print view shows at most this many rows (use the export for more). */
    private const PRINT_LIMIT = 1000;

    public static function middleware(): array
    {
        return [
            new Middleware('can:stock.view', except: ['reverse']),
            new Middleware('can:stock.reverse', only: ['reverse']),
        ];
    }

    public function index(Request $request): View
    {
        return view('stock-movements.index', [
            'movements' => $this->filtered($request)->paginate(25)->withQueryString(),
            'types' => MovementType::cases(),
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(StockMovement $movement): View
    {
        $movement->load([
            'product.unit', 'user', 'supplier',
            'reversalOf', 'reversal.user',
            'reference' => fn (MorphTo $morph) => $morph->morphWith([PurchaseReceiptItem::class => ['receipt.purchase']]),
        ]);

        return view('stock-movements.show', ['movement' => $movement]);
    }

    public function reverse(Request $request, StockMovement $movement, StockService $stock): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $reversal = $stock->reverse($movement, $request->user(), $data['reason']);

        return redirect()->route('stock-movements.show', $reversal)
            ->with('success', "{$movement->movement_number} was reversed by {$reversal->movement_number}. Stock is now " . Quantity::format($reversal->new_stock) . '.');
    }

    /** CSV export of the filtered history (opens directly in Excel, Khmer text included). */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->reorder()->orderBy('movement_date')->orderBy('id');
        $filename = 'stock-movements-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Khmer / accented text correctly

            fputcsv($out, ['Transaction #', 'Date', 'Product', 'SKU', 'Type', 'Quantity', 'Unit', 'Previous Stock', 'New Stock', 'Unit Cost', 'Reference', 'Reason', 'Notes', 'User', 'Reversal Of']);

            // lazy() reads 500 rows at a time, so large exports don't run out of memory.
            foreach ($query->lazy(500) as $m) {
                fputcsv($out, [
                    $m->movement_number,
                    $m->movement_date->format('Y-m-d H:i'),
                    self::safe($m->product->name),
                    self::safe($m->product->sku),
                    $m->type->label(),
                    $m->quantity,
                    $m->product->unit->abbreviation,
                    $m->previous_stock,
                    $m->new_stock,
                    $m->unit_cost,
                    self::safe($m->reference?->receipt?->purchase?->purchase_number ?? $m->reference?->receipt?->receipt_number),
                    self::safe($m->reason),
                    self::safe($m->notes),
                    self::safe($m->user->name),
                    $m->reversalOf?->movement_number,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request): View
    {
        $movements = $this->filtered($request)->limit(self::PRINT_LIMIT + 1)->get();

        return view('stock-movements.print', [
            'movements' => $movements->take(self::PRINT_LIMIT),
            'truncated' => $movements->count() > self::PRINT_LIMIT,
            'limit' => self::PRINT_LIMIT,
        ]);
    }

    /** Shared by list, export and print, so they always show the same rows. */
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(MovementType::class)],
            'user' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        return StockMovement::query()
            ->with([
                'product.unit', 'user', 'reversalOf', 'reversal',
                'reference' => fn (MorphTo $morph) => $morph->morphWith([PurchaseReceiptItem::class => ['receipt.purchase']]),
            ])
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = $request->query('search');
                $q->where(fn (Builder $w) => $w
                    ->where('movement_number', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($p) => $p->withTrashed()->search($term)));
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('date_to')))
            ->latest('movement_date')
            ->latest('id');
    }

    /** Stops spreadsheet formula injection: text cells starting with = + - @ are prefixed. */
    private static function safe(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Http\Controllers\Concerns\RunsOnce;
use App\Http\Requests\StockInRequest;
use App\Models\Product;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\StockService;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Stock In: history of everything that entered inventory (manual entries and
 * purchase receipts) + a form for manual stock in.
 *
 * Goods delivered against a purchase order must be received from the purchase
 * (Receive button), never entered here, so they are never counted twice.
 */
class StockInController extends Controller implements HasMiddleware
{
    use RunsOnce;

    public static function middleware(): array
    {
        return [new Middleware('can:stock.in')];
    }

    public function index(Request $request): View
    {
        $movements = StockMovement::query()
            ->with([
                'product.unit',
                'user',
                'supplier',
                'reversalOf',
                'reference' => fn (MorphTo $morph) => $morph->morphWith([
                    PurchaseReceiptItem::class => ['receipt.purchase'],
                ]),
            ])
            ->whereIn('type', [MovementType::In, MovementType::ReturnIn])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('product', fn ($p) => $p->withTrashed()->search($request->query('search'))))
            ->when($request->query('source') === 'purchase', fn ($q) => $q->where('reason', 'purchase_receipt'))
            ->when($request->query('source') === 'manual', fn ($q) => $q->where('reason', '!=', 'purchase_receipt'))
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('date_to')))
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock-in.index', [
            'movements' => $movements,
            'suppliers' => Supplier::withTrashed()->orderBy('name')->get(['id', 'name']),
            'reasons' => StockInRequest::REASONS + ['purchase_receipt' => 'Purchase receipt'],
        ]);
    }

    public function create(Request $request): View
    {
        return view('stock-in.create', [
            'products' => Product::active()->with('unit')->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit_id', 'current_stock', 'purchase_price', 'average_cost']),
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'reasons' => StockInRequest::REASONS,
            'token' => (string) Str::uuid(),
            'selectedProduct' => $request->integer('product') ?: null,
        ]);
    }

    public function store(StockInRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();

        $movement = $this->runOnce('stock-in', $data['form_token'], function () use ($data, $request, $stock) {
            $product = Product::findOrFail($data['product_id']);

            return $stock->move(
                product: $product,
                quantity: (string) $data['quantity'],
                type: $data['reason'] === 'customer_return' ? MovementType::ReturnIn : MovementType::In,
                user: $request->user(),
                unitCost: (string) $data['unit_cost'],
                reason: $data['reason'],
                notes: $data['notes'] ?? null,
                supplierId: $data['supplier_id'] ?? null,
                movementDate: Carbon::parse($data['movement_date']),
            );
        });

        if ($movement === null) {
            return redirect()->route('stock-in.index')->with('info', 'This stock in was already saved. Nothing was added twice.');
        }

        $product = $movement->product;

        ActivityLogger::log('stock_in', 'stock', sprintf('Stock in %s %s of %s (%s)', Quantity::format($movement->quantity), $product->unit?->abbreviation, $product->name, $movement->movement_number), $movement);

        return redirect()->route('stock-in.index')->with('success', sprintf(
            'Added %s to %s. New stock: %s. (%s)',
            Quantity::format($movement->quantity),
            $product->name,
            Quantity::format($movement->new_stock),
            $movement->movement_number,
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Enums\StockOutReason;
use App\Http\Controllers\Concerns\RunsOnce;
use App\Http\Requests\StockOutRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\StockService;
use App\Support\Decimal;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Stock Out: products leaving inventory (sold, damaged, expired, internal use,
 * returned to supplier, other). "Returned to supplier" is recorded as RETURN_OUT.
 */
class StockOutController extends Controller implements HasMiddleware
{
    use RunsOnce;

    public static function middleware(): array
    {
        return [new Middleware('can:stock.out')];
    }

    public function index(Request $request): View
    {
        $movements = StockMovement::query()
            ->with(['product.unit', 'user', 'supplier', 'reversalOf'])
            ->whereIn('type', [MovementType::Out, MovementType::ReturnOut])
            ->when($request->filled('search'), fn ($q) => $q->whereHas('product', fn ($p) => $p->withTrashed()->search($request->query('search'))))
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->query('reason')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('movement_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('movement_date', '<=', $request->date('date_to')))
            ->latest('movement_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('stock-out.index', [
            'movements' => $movements,
            'reasons' => StockOutReason::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('stock-out.create', [
            // Only products that actually have stock can be issued.
            'products' => Product::active()->where('current_stock', '>', 0)->with('unit')->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit_id', 'current_stock']),
            'suppliers' => Supplier::active()->orderBy('name')->get(['id', 'name']),
            'reasons' => StockOutReason::cases(),
            'token' => (string) Str::uuid(),
            'selectedProduct' => $request->integer('product') ?: null,
        ]);
    }

    public function store(StockOutRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();
        $reason = StockOutReason::from($data['reason']);

        $movement = $this->runOnce('stock-out', $data['form_token'], function () use ($data, $reason, $request, $stock) {
            $product = Product::findOrFail($data['product_id']);

            $movement = $stock->move(
                product: $product,
                quantity: bcmul(Decimal::normalize($data['quantity'], 3), '-1', 3),
                type: $reason === StockOutReason::ReturnedToSupplier ? MovementType::ReturnOut : MovementType::Out,
                user: $request->user(),
                reason: $reason->value,
                notes: $data['notes'] ?? null,
                supplierId: $data['supplier_id'] ?? null,
                movementDate: Carbon::parse($data['movement_date']),
            );

            ActivityLogger::log('stock_out', 'stock', sprintf('Stock out %s %s of %s (%s, %s)', Quantity::format(ltrim($movement->quantity, '-')), $product->unit?->abbreviation, $product->name, $reason->label(), $movement->movement_number), $movement);

            return $movement;
        });

        if ($movement === null) {
            return redirect()->route('stock-out.index')->with('info', 'This stock out was already saved. Nothing was removed twice.');
        }

        return redirect()->route('stock-out.index')->with('success', sprintf(
            'Removed %s of %s (%s). Remaining stock: %s. (%s)',
            Quantity::format(ltrim($movement->quantity, '-')),
            $movement->product->name,
            $reason->label(),
            Quantity::format($movement->new_stock),
            $movement->movement_number,
        ));
    }
}

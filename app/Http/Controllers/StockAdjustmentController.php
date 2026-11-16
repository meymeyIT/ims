<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RunsOnce;
use App\Http\Requests\StockAdjustmentRequest;
use App\Models\Product;
use App\Services\ActivityLogger;
use App\Services\StockService;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    use RunsOnce;

    public static function middleware(): array
    {
        return [new Middleware('can:stock.adjust')];
    }

    public function create(Request $request): View
    {
        return view('stock-adjustments.create', [
            'products' => Product::query()->with('unit')->orderBy('name')
                ->get(['id', 'name', 'sku', 'unit_id', 'current_stock', 'is_active']),
            'reasons' => StockAdjustmentRequest::REASONS,
            'token' => (string) Str::uuid(),
            'selectedProduct' => $request->integer('product') ?: null,
        ]);
    }

    public function store(StockAdjustmentRequest $request, StockService $stock): RedirectResponse
    {
        $data = $request->validated();

        $movement = $this->runOnce('stock-adjustment', $data['form_token'], function () use ($data, $request, $stock) {
            $product = Product::findOrFail($data['product_id']);
            $movement = $stock->adjustTo($product, (string) $data['counted_quantity'], $request->user(), $data['reason'], $data['notes']);

            ActivityLogger::log('adjusted', 'stock', sprintf(
                'Adjusted %s from %s to %s (%s%s, %s)',
                $product->name,
                Quantity::format($movement->previous_stock),
                Quantity::format($movement->new_stock),
                $movement->isIncrease() ? '+' : '',
                Quantity::format($movement->quantity),
                $movement->movement_number,
            ), $movement);

            return $movement;
        });

        if ($movement === null) {
            return redirect()->route('stock-movements.index')->with('info', 'This adjustment was already saved.');
        }

        return redirect()->route('stock-movements.show', $movement)->with('success', sprintf(
            'Stock adjusted from %s to %s.',
            Quantity::format($movement->previous_stock),
            Quantity::format($movement->new_stock),
        ));
    }
}

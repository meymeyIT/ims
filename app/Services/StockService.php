<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceiptItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Quantity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * THE ONLY place where products.current_stock changes.
 *
 * Every call:
 *   1. runs inside a database transaction (all-or-nothing);
 *   2. locks the product row (SELECT ... FOR UPDATE) so two users changing the
 *      same product at the same moment are processed one after the other;
 *   3. refuses to make stock negative;
 *   4. updates the weighted-average cost on stock increases that carry a cost;
 *   5. writes exactly one immutable stock_movements row (the ledger);
 *   6. sends a low-stock alert when the product reaches its minimum (once).
 */
class StockService
{
    /**
     * @param  string  $quantity  Signed: positive = stock increases, negative = stock decreases.
     * @param  string|null  $unitCost  Cost per unit for increases (used for weighted average).
     * @param  bool  $removesCostFromAverage  Only for reversing a stock-in: take that cost back out of the average.
     * @param  Model|null  $reference  Source document line, e.g. a PurchaseReceiptItem.
     */
    public function move(
        Product $product,
        string $quantity,
        MovementType $type,
        User $user,
        ?string $unitCost = null,
        ?Model $reference = null,
        ?string $reason = null,
        ?string $notes = null,
        ?int $supplierId = null,
        ?CarbonInterface $movementDate = null,
        ?StockMovement $reversalOf = null,
        bool $removesCostFromAverage = false,
    ): StockMovement {
        $quantity = Decimal::normalize($quantity, 3);

        if (bccomp($quantity, '0', 3) === 0) {
            throw new InvalidArgumentException('A stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($product, $quantity, $type, $user, $unitCost, $reference, $reason, $notes, $supplierId, $movementDate, $reversalOf, $removesCostFromAverage) {
            // Re-read the product WITH a row lock: other transactions touching this
            // product now wait until we commit, so the stock we read stays correct.
            $locked = Product::withTrashed()->with('unit')->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $previousStock = Decimal::normalize($locked->current_stock, 3);
            $newStock = bcadd($previousStock, $quantity, 3);

            if (bccomp($newStock, '0', 3) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Not enough stock for %s. Available: %s %s, requested: %s.',
                        $locked->name,
                        Quantity::format($previousStock),
                        $locked->unit?->abbreviation,
                        Quantity::format(ltrim($quantity, '-')),
                    ),
                ]);
            }

            $averageCost = Decimal::normalize($locked->average_cost, 4);
            $isIncrease = bccomp($quantity, '0', 3) > 0;

            if ($isIncrease && $unitCost !== null) {
                $averageCost = $this->weightedAverage($previousStock, $averageCost, $quantity, Decimal::normalize($unitCost, 4));
            } elseif (! $isIncrease && $removesCostFromAverage && $unitCost !== null) {
                $averageCost = $this->averageAfterRemoval($previousStock, $averageCost, ltrim($quantity, '-'), Decimal::normalize($unitCost, 4));
            }

            $locked->forceFill([
                'current_stock' => $newStock,
                'average_cost' => $averageCost,
            ])->save();

            // Keep the caller's model instance in sync with the database.
            $product->forceFill([
                'current_stock' => $locked->current_stock,
                'average_cost' => $locked->average_cost,
            ])->syncOriginal();

            $movement = StockMovement::create([
                'movement_number' => DocumentNumber::next('SM', 6),
                'product_id' => $locked->id,
                'user_id' => $user->id,
                'supplier_id' => $supplierId,
                'type' => $type,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                // Increases with a cost record what was paid; everything else records
                // the cost used (or the average cost at that moment).
                'unit_cost' => $unitCost !== null ? Decimal::normalize($unitCost, 4) : $averageCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
                'notes' => $notes,
                'reversal_of_id' => $reversalOf?->id,
                'movement_date' => $movementDate ?? now(),
            ]);

            // Same transaction: if anything fails, the alert is rolled back with the movement.
            app(LowStockMonitor::class)->check($locked->id);

            return $movement;
        });
    }

    /**
     * Inventory adjustment after a physical count: sets the stock to the counted
     * quantity by recording the difference as an ADJUSTMENT movement.
     * The difference is calculated under the row lock, so it is always correct.
     */
    public function adjustTo(Product $product, string $countedQuantity, User $user, string $reason, ?string $notes = null): StockMovement
    {
        return DB::transaction(function () use ($product, $countedQuantity, $user, $reason, $notes) {
            $locked = Product::withTrashed()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $difference = bcsub(Decimal::normalize($countedQuantity, 3), Decimal::normalize($locked->current_stock, 3), 3);

            if (bccomp($difference, '0', 3) === 0) {
                throw ValidationException::withMessages([
                    'counted_quantity' => 'The counted quantity is the same as the current stock. Nothing to adjust.',
                ]);
            }

            return $this->move(
                product: $product,
                quantity: $difference,
                type: MovementType::Adjustment,
                user: $user,
                reason: $reason,
                notes: $notes,
            );
        });
    }

    /**
     * Reverses a movement by recording an opposite (compensating) movement.
     * The original row is never changed or deleted, so history stays auditable.
     * A movement can be reversed only once (also enforced by a unique index).
     */
    public function reverse(StockMovement $movement, User $user, string $reason): StockMovement
    {
        return DB::transaction(function () use ($movement, $user, $reason) {
            $original = StockMovement::with('reference')->whereKey($movement->getKey())->lockForUpdate()->firstOrFail();

            if ($original->reversal_of_id !== null) {
                throw ValidationException::withMessages(['reason' => 'This movement is itself a reversal and cannot be reversed again.']);
            }

            if (StockMovement::where('reversal_of_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['reason' => "{$original->movement_number} was already reversed."]);
            }

            $wasIncrease = $original->isIncrease();
            $product = Product::withTrashed()->findOrFail($original->product_id);

            try {
                $reversal = $this->move(
                    product: $product,
                    quantity: bcmul((string) $original->quantity, '-1', 3),
                    type: $original->type,
                    user: $user,
                    unitCost: $original->unit_cost !== null ? (string) $original->unit_cost : null,
                    reason: 'reversal',
                    notes: "Reversal of {$original->movement_number}: {$reason}",
                    supplierId: $original->supplier_id,
                    reversalOf: $original,
                    // Undo the effect a costed stock-in had on the average cost.
                    removesCostFromAverage: $wasIncrease && in_array($original->type, [MovementType::In, MovementType::ReturnIn], true),
                );
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'reason' => 'Cannot reverse: ' . collect($e->errors())->flatten()->first() . ' Some of this stock has already left the inventory.',
                ]);
            }

            // A reversed purchase receipt also gives the quantity back to the purchase order.
            if ($original->reference instanceof PurchaseReceiptItem) {
                $this->undoPurchaseReceipt($original->reference, (string) $original->quantity);
            }

            ActivityLogger::log('reversed', 'stock', "Reversed {$original->movement_number} with {$reversal->movement_number}: {$reason}", $reversal);

            return $reversal;
        });
    }

    /**
     * Weighted average cost:
     *   (current_stock × current_avg + qty_in × unit_cost) / (current_stock + qty_in)
     * If there was no stock before, the new average is simply the incoming unit cost.
     */
    public function weightedAverage(string $stock, string $averageCost, string $quantityIn, string $unitCost): string
    {
        if (bccomp($stock, '0', 3) <= 0) {
            return Decimal::normalize($unitCost, 4);
        }

        $totalValue = bcadd(bcmul($stock, $averageCost, 10), bcmul($quantityIn, $unitCost, 10), 10);
        $totalQuantity = bcadd($stock, $quantityIn, 3);

        return Decimal::round(bcdiv($totalValue, $totalQuantity, 10), 4);
    }

    /**
     * Opposite of weightedAverage(), used when a stock-in is reversed:
     *   (current_stock × current_avg − qty_out × unit_cost) / (current_stock − qty_out)
     * Keeps the current average if nothing is left or the result would be negative.
     */
    public function averageAfterRemoval(string $stock, string $averageCost, string $quantityOut, string $unitCost): string
    {
        $remaining = bcsub($stock, $quantityOut, 3);

        if (bccomp($remaining, '0', 3) <= 0) {
            return $averageCost;
        }

        $value = bcsub(bcmul($stock, $averageCost, 10), bcmul($quantityOut, $unitCost, 10), 10);
        $average = Decimal::round(bcdiv($value, $remaining, 10), 4);

        return bccomp($average, '0', 4) < 0 ? $averageCost : $average;
    }

    private function undoPurchaseReceipt(PurchaseReceiptItem $receiptItem, string $quantity): void
    {
        $item = PurchaseItem::whereKey($receiptItem->purchase_item_id)->lockForUpdate()->firstOrFail();
        $purchase = Purchase::whereKey($item->purchase_id)->lockForUpdate()->firstOrFail();

        $item->forceFill(['quantity_received' => bcsub((string) $item->quantity_received, Decimal::normalize($quantity, 3), 3)])->save();

        $items = $purchase->items()->get();
        $anyReceived = $items->contains(fn (PurchaseItem $i) => bccomp((string) $i->quantity_received, '0', 3) > 0);

        $purchase->status = match (true) {
            $items->every(fn (PurchaseItem $i) => $i->isFullyReceived()) => PurchaseStatus::Received,
            $anyReceived => PurchaseStatus::PartiallyReceived,
            default => PurchaseStatus::Approved,
        };
        $purchase->save();
    }
}

<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\Setting;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Quantity;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase order lifecycle:
 *
 *   draft ──submit──> pending ──approve──> approved ──receive──> partially_received ──receive──> received
 *     └──────────approve──────────────────────┘                    (any number of partial deliveries)
 *   draft / pending / approved ──cancel──> cancelled
 *
 * Creating, editing or approving a purchase NEVER changes stock.
 * Only receive() increases stock, through StockService.
 */
class PurchaseService
{
    public function __construct(private readonly StockService $stock)
    {
    }

    public function create(array $data, User $user, bool $submit = false): Purchase
    {
        return DB::transaction(function () use ($data, $user, $submit) {
            $purchase = new Purchase([
                'purchase_number' => DocumentNumber::next(Setting::get('purchase_number_prefix', 'PO')),
                'supplier_id' => $data['supplier_id'],
                'created_by' => $user->id,
                'purchase_date' => $data['purchase_date'],
                'discount' => Decimal::normalize($data['discount'] ?? 0, 2),
                'tax' => Decimal::normalize($data['tax'] ?? 0, 2),
                'notes' => $data['notes'] ?? null,
            ]);
            $purchase->status = $submit ? PurchaseStatus::Pending : PurchaseStatus::Draft;
            $purchase->save();

            $this->replaceItems($purchase, $data['items']);

            ActivityLogger::log('created', 'purchases', "Created purchase {$purchase->purchase_number}" . ($submit ? ' (submitted for approval)' : ' (draft)'), $purchase);

            return $purchase;
        });
    }

    public function update(Purchase $purchase, array $data, User $user, bool $submit = false): Purchase
    {
        return DB::transaction(function () use ($purchase, $data, $submit) {
            $purchase = $this->lock($purchase);

            if (! $purchase->status->isEditable()) {
                throw ValidationException::withMessages(['purchase' => "Purchase {$purchase->purchase_number} is {$purchase->status->label()} and can no longer be edited."]);
            }

            $purchase->fill([
                'supplier_id' => $data['supplier_id'],
                'purchase_date' => $data['purchase_date'],
                'discount' => Decimal::normalize($data['discount'] ?? 0, 2),
                'tax' => Decimal::normalize($data['tax'] ?? 0, 2),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($submit) {
                $purchase->status = PurchaseStatus::Pending;
            }

            $purchase->save();
            $this->replaceItems($purchase, $data['items']);

            ActivityLogger::log('updated', 'purchases', "Updated purchase {$purchase->purchase_number}" . ($submit ? ' and submitted for approval' : ''), $purchase);

            return $purchase;
        });
    }

    public function submit(Purchase $purchase): Purchase
    {
        return $this->transition($purchase, 'submitted', function (Purchase $p) {
            if ($p->status !== PurchaseStatus::Draft) {
                return 'Only draft purchases can be submitted.';
            }
            $p->status = PurchaseStatus::Pending;

            return null;
        });
    }

    public function approve(Purchase $purchase, User $user): Purchase
    {
        return $this->transition($purchase, 'approved', function (Purchase $p) use ($user) {
            if (! $p->status->canBeApproved()) {
                return "A {$p->status->label()} purchase cannot be approved.";
            }
            $p->status = PurchaseStatus::Approved;
            $p->forceFill(['approved_by' => $user->id, 'approved_at' => now()]);

            return null;
        });
    }

    public function cancel(Purchase $purchase, User $user, string $reason): Purchase
    {
        return $this->transition($purchase, 'cancelled', function (Purchase $p) use ($user, $reason) {
            if (! $p->status->canBeCancelled()) {
                return "A {$p->status->label()} purchase cannot be cancelled" . ($p->status === PurchaseStatus::PartiallyReceived ? ' because goods were already received.' : '.');
            }
            $p->status = PurchaseStatus::Cancelled;
            $p->forceFill(['cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);

            return null;
        });
    }

    /** Only drafts can be deleted (their items are deleted with them). */
    public function delete(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $purchase = $this->lock($purchase);

            if (! $purchase->status->isEditable()) {
                throw ValidationException::withMessages(['purchase' => 'Only draft purchases can be deleted. Cancel it instead.']);
            }

            ActivityLogger::log('deleted', 'purchases', "Deleted draft purchase {$purchase->purchase_number}");
            $purchase->delete();
        });
    }

    /**
     * Receive goods (full or partial delivery).
     *
     * @param  array<int|string, string|null>  $quantities  purchase_item_id => quantity received now
     * @param  string  $token  One-time UUID from the form. Re-submitting the same form is rejected,
     *                         so a double-click or page refresh can never receive goods twice.
     */
    public function receive(
        Purchase $purchase,
        array $quantities,
        string $token,
        CarbonInterface $receivedAt,
        ?string $notes,
        User $user,
    ): PurchaseReceipt {
        return DB::transaction(function () use ($purchase, $quantities, $token, $receivedAt, $notes, $user) {
            $purchase = $this->lock($purchase);

            if (PurchaseReceipt::where('receipt_token', $token)->exists()) {
                throw ValidationException::withMessages(['receipt_token' => 'This delivery was already recorded.']);
            }

            if (! $purchase->status->canBeReceived()) {
                throw ValidationException::withMessages(['items' => "Purchase {$purchase->purchase_number} is {$purchase->status->label()} and cannot be received."]);
            }

            // Lock the order lines too, so two people receiving at once can't both
            // see the same "remaining" quantity.
            $items = $purchase->items()->with('product')->lockForUpdate()->get()->keyBy('id');
            $lines = [];

            foreach ($quantities as $itemId => $quantity) {
                $quantity = Decimal::normalize($quantity, 3);

                if (bccomp($quantity, '0', 3) === 0) {
                    continue;
                }

                $item = $items->get((int) $itemId);

                if (! $item || bccomp($quantity, '0', 3) < 0) {
                    throw ValidationException::withMessages(["items.{$itemId}" => 'Invalid line or quantity.']);
                }

                $remaining = $item->remainingQuantity();

                if (bccomp($quantity, $remaining, 3) > 0) {
                    throw ValidationException::withMessages([
                        "items.{$itemId}" => sprintf('Only %s remaining for %s; you entered %s.', Quantity::format($remaining), $item->product->name, Quantity::format($quantity)),
                    ]);
                }

                $lines[] = [$item, $quantity];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['items' => 'Enter a received quantity for at least one product.']);
            }

            $receipt = PurchaseReceipt::create([
                'receipt_number' => DocumentNumber::next(Setting::get('receipt_number_prefix', 'GRN')),
                'receipt_token' => $token,
                'purchase_id' => $purchase->id,
                'received_by' => $user->id,
                'received_at' => $receivedAt,
                'notes' => $notes,
            ]);

            foreach ($lines as [$item, $quantity]) {
                $receiptItem = $receipt->items()->create([
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'unit_cost' => $item->unit_cost,
                ]);

                // One receipt line -> exactly one stock movement (unique reference in the DB).
                $this->stock->move(
                    product: $item->product,
                    quantity: $quantity,
                    type: MovementType::In,
                    user: $user,
                    unitCost: (string) $item->unit_cost,
                    reference: $receiptItem,
                    reason: 'purchase_receipt',
                    notes: "Received on {$purchase->purchase_number} ({$receipt->receipt_number})",
                    supplierId: $purchase->supplier_id,
                    movementDate: $receivedAt,
                );

                $item->forceFill(['quantity_received' => bcadd((string) $item->quantity_received, $quantity, 3)])->save();
            }

            $purchase->status = $items->every(fn ($item) => $item->isFullyReceived())
                ? PurchaseStatus::Received
                : PurchaseStatus::PartiallyReceived;
            $purchase->save();

            ActivityLogger::log('received', 'purchases', "Received goods for {$purchase->purchase_number} ({$receipt->receipt_number}, " . count($lines) . ' product(s))', $purchase);

            return $receipt;
        });
    }

    // ------------------------------------------------------------------ internals

    /** Re-read the purchase with a row lock for the rest of the transaction. */
    private function lock(Purchase $purchase): Purchase
    {
        return Purchase::whereKey($purchase->getKey())->lockForUpdate()->firstOrFail();
    }

    /** Runs a status change in a transaction; $apply returns an error message or null. */
    private function transition(Purchase $purchase, string $action, callable $apply): Purchase
    {
        return DB::transaction(function () use ($purchase, $action, $apply) {
            $purchase = $this->lock($purchase);

            if ($error = $apply($purchase)) {
                throw ValidationException::withMessages(['purchase' => $error]);
            }

            $purchase->save();

            ActivityLogger::log($action, 'purchases', ucfirst($action) . " purchase {$purchase->purchase_number}", $purchase);

            return $purchase;
        });
    }

    /** Rebuilds the order lines and recalculates subtotal / total with exact decimals. */
    private function replaceItems(Purchase $purchase, array $items): void
    {
        $purchase->items()->delete();
        $subtotal = '0.00';

        foreach ($items as $item) {
            $quantity = Decimal::normalize($item['quantity'], 3);
            $unitCost = Decimal::normalize($item['unit_cost'], 4);
            $lineTotal = Decimal::round(bcmul($quantity, $unitCost, 10), 2);

            $purchase->items()->create([
                'product_id' => $item['product_id'],
                'quantity_ordered' => $quantity,
                'unit_cost' => $unitCost,
                'subtotal' => $lineTotal,
            ]);

            $subtotal = bcadd($subtotal, $lineTotal, 2);
        }

        $total = bcadd(bcsub($subtotal, Decimal::normalize($purchase->discount, 2), 2), Decimal::normalize($purchase->tax, 2), 2);

        if (bccomp($total, '0', 2) < 0) {
            throw ValidationException::withMessages(['discount' => 'The discount cannot be larger than the subtotal plus tax.']);
        }

        $purchase->forceFill(['subtotal' => $subtotal, 'total_amount' => $total])->save();
    }
}

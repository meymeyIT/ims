<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/**
 * Phase 5 tests: purchases, receiving and stock in.
 * Covers spec tests #7 (stock in increases stock), #10 (receiving increases stock),
 * #11 (partial receiving) and #12 (duplicate receiving is prevented),
 * plus rollback behaviour and the purchase status rules.
 */
class PurchaseAndStockInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->supplier = Supplier::factory()->create();
    }

    /** Creates and approves a purchase: [product => [qty, cost], ...] */
    private function approvedPurchase(array $lines): Purchase
    {
        $service = app(PurchaseService::class);

        $purchase = $service->create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => collect($lines)->map(fn ($line) => [
                'product_id' => $line[0]->id,
                'quantity' => $line[1],
                'unit_cost' => $line[2],
            ])->all(),
        ], $this->admin, submit: true);

        return $service->approve($purchase, $this->admin);
    }

    private function receivePayload(Purchase $purchase, array $quantities, ?string $token = null): array
    {
        return [
            'receipt_token' => $token ?? (string) Str::uuid(),
            'received_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'items' => $quantities,
        ];
    }

    private function assertLedgerMatchesStock(Product $product): void
    {
        $ledger = StockMovement::where('product_id', $product->id)->sum('quantity');
        $this->assertEquals((float) $product->fresh()->current_stock, (float) $ledger, 'Sum of stock movements must equal current stock.');
    }

    // ------------------------------------------------------------------ purchases

    public function test_creating_a_purchase_calculates_totals_and_does_not_change_stock(): void
    {
        $keyboard = Product::factory()->create();
        $mouse = Product::factory()->create();

        $this->actingAs($this->admin)->post('/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'discount' => '5.00',
            'tax' => '2.50',
            'items' => [
                ['product_id' => $keyboard->id, 'quantity' => '10', 'unit_cost' => '8.50'],
                ['product_id' => $mouse->id, 'quantity' => '4', 'unit_cost' => '3.25'],
                ['product_id' => '', 'quantity' => '', 'unit_cost' => ''],   // empty row is ignored
            ],
            'action' => 'draft',
        ])->assertSessionHasNoErrors();

        $purchase = Purchase::firstOrFail();
        $this->assertSame(PurchaseStatus::Draft, $purchase->status);
        $this->assertSame('98.00', $purchase->subtotal);        // 85.00 + 13.00
        $this->assertSame('95.50', $purchase->total_amount);    // 98 - 5 + 2.50
        $this->assertCount(2, $purchase->items);
        $this->assertSame('0.000', $keyboard->fresh()->current_stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_purchase_needs_at_least_one_item_and_positive_quantities(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post('/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [],
        ])->assertSessionHasErrors('items');

        $this->actingAs($this->admin)->post('/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => '0', 'unit_cost' => '-1']],
        ])->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_cost']);

        $this->assertSame(0, Purchase::count());
    }

    public function test_discount_larger_than_total_is_rejected_and_rolled_back(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post('/purchases', [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'discount' => '500',
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '10']],
        ])->assertSessionHasErrors('discount');

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, DB::table('purchase_items')->count());
    }

    public function test_only_draft_purchases_can_be_edited(): void
    {
        $product = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$product, '5', '2']]);

        $this->actingAs($this->admin)->get("/purchases/{$purchase->id}/edit")->assertRedirect(route('purchases.show', $purchase));
        $this->actingAs($this->admin)->put("/purchases/{$purchase->id}", [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => '99', 'unit_cost' => '2']],
        ])->assertSessionHasErrors('purchase');

        $this->assertSame('5.000', $purchase->items()->first()->quantity_ordered);
    }

    public function test_staff_cannot_create_or_approve_purchases(): void
    {
        $staff = User::factory()->role(Role::STAFF)->create();
        $product = Product::factory()->create();
        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => '1']],
        ], $this->admin);

        $this->actingAs($staff)->get('/purchases')->assertForbidden();
        $this->actingAs($staff)->patch("/purchases/{$purchase->id}/approve")->assertForbidden();
        $this->assertSame(PurchaseStatus::Draft, $purchase->fresh()->status);
    }

    // ------------------------------------------------------------------ receiving

    public function test_purchase_receiving_increases_stock(): void
    {
        $product = Product::factory()->withStock(0)->create();
        $purchase = $this->approvedPurchase([[$product, '10', '2.50']]);
        $item = $purchase->items()->first();

        $this->actingAs($this->admin)
            ->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$item->id => '10']))
            ->assertRedirect(route('purchases.show', $purchase));

        $this->assertSame('10.000', $product->fresh()->current_stock);
        $this->assertSame('2.5000', $product->fresh()->average_cost);
        $this->assertSame(PurchaseStatus::Received, $purchase->fresh()->status);

        $movement = StockMovement::firstOrFail();
        $this->assertSame(MovementType::In, $movement->type);
        $this->assertSame('0.000', $movement->previous_stock);
        $this->assertSame('10.000', $movement->new_stock);
        $this->assertSame('purchase_receipt_item', $movement->reference_type);
        $this->assertSame($this->supplier->id, $movement->supplier_id);
        $this->assertLedgerMatchesStock($product);
    }

    public function test_partial_receiving_updates_the_correct_quantity(): void
    {
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$product, '10', '1'], [$other, '4', '1']]);
        [$item, $otherItem] = $purchase->items()->orderBy('id')->get()->all();

        // First delivery: 6 of 10, nothing of the other product.
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$item->id => '6', $otherItem->id => '0']));

        $this->assertSame('6.000', $item->fresh()->quantity_received);
        $this->assertSame('6.000', $product->fresh()->current_stock);
        $this->assertSame('0.000', $other->fresh()->current_stock);
        $this->assertSame(PurchaseStatus::PartiallyReceived, $purchase->fresh()->status);

        // Second delivery: the remaining 4 + the other product.
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$item->id => '4', $otherItem->id => '4']));

        $this->assertSame('10.000', $product->fresh()->current_stock);
        $this->assertSame('4.000', $other->fresh()->current_stock);
        $this->assertSame(PurchaseStatus::Received, $purchase->fresh()->status);
        $this->assertSame(2, $purchase->receipts()->count());
        $this->assertLedgerMatchesStock($product);
    }

    public function test_cannot_receive_more_than_remaining_quantity(): void
    {
        $product = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$product, '10', '1']]);
        $item = $purchase->items()->first();

        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$item->id => '7']));
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$item->id => '4']))
            ->assertSessionHasErrors("items.{$item->id}");

        $this->assertSame('7.000', $product->fresh()->current_stock);
        $this->assertSame('7.000', $item->fresh()->quantity_received);
    }

    public function test_duplicate_purchase_receiving_is_prevented(): void
    {
        $product = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$product, '10', '1']]);
        $item = $purchase->items()->first();
        $payload = $this->receivePayload($purchase, [$item->id => '3']);

        // The same form submitted twice (double-click / refresh).
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $payload);
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $payload)->assertSessionHas('info');

        $this->assertSame('3.000', $product->fresh()->current_stock);
        $this->assertSame(1, StockMovement::count());
        $this->assertSame(1, $purchase->receipts()->count());
    }

    public function test_duplicate_token_is_also_rejected_inside_the_service(): void
    {
        $product = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$product, '10', '1']]);
        $item = $purchase->items()->first();
        $token = (string) Str::uuid();
        $service = app(PurchaseService::class);

        $service->receive($purchase, [$item->id => '2'], $token, now(), null, $this->admin);

        $this->expectException(ValidationException::class);
        $service->receive($purchase, [$item->id => '2'], $token, now(), null, $this->admin);
    }

    public function test_draft_and_cancelled_purchases_cannot_be_received(): void
    {
        $product = Product::factory()->create();
        $service = app(PurchaseService::class);
        $draft = $service->create([
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => '5', 'unit_cost' => '1']],
        ], $this->admin);
        $draftItem = $draft->items()->first();

        $this->actingAs($this->admin)->post("/purchases/{$draft->id}/receive", $this->receivePayload($draft, [$draftItem->id => '5']))
            ->assertSessionHasErrors('items');

        $cancelled = $this->approvedPurchase([[$product, '5', '1']]);
        $this->actingAs($this->admin)->patch("/purchases/{$cancelled->id}/cancel", ['reason' => 'Supplier out of stock'])->assertSessionHasNoErrors();
        $this->assertSame(PurchaseStatus::Cancelled, $cancelled->fresh()->status);

        $cancelledItem = $cancelled->items()->first();
        $this->actingAs($this->admin)->post("/purchases/{$cancelled->id}/receive", $this->receivePayload($cancelled, [$cancelledItem->id => '5']))
            ->assertSessionHasErrors('items');

        $this->assertSame('0.000', $product->fresh()->current_stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_partially_received_purchase_cannot_be_cancelled(): void
    {
        $product = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$product, '10', '1']]);
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$purchase->items()->first()->id => '2']));

        $this->actingAs($this->admin)->patch("/purchases/{$purchase->id}/cancel", ['reason' => 'test'])->assertSessionHasErrors('purchase');
        $this->assertSame(PurchaseStatus::PartiallyReceived, $purchase->fresh()->status);
    }

    public function test_failed_receipt_rolls_back_everything(): void
    {
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $purchase = $this->approvedPurchase([[$a, '5', '1'], [$b, '5', '1']]);
        [$itemA, $itemB] = $purchase->items()->orderBy('id')->get()->all();

        // Line A is valid, line B is too much: NOTHING may be saved.
        $this->actingAs($this->admin)->post("/purchases/{$purchase->id}/receive", $this->receivePayload($purchase, [$itemA->id => '5', $itemB->id => '6']))
            ->assertSessionHasErrors("items.{$itemB->id}");

        $this->assertSame('0.000', $a->fresh()->current_stock);
        $this->assertSame('0.000', $itemA->fresh()->quantity_received);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, $purchase->receipts()->count());
        $this->assertSame(PurchaseStatus::Approved, $purchase->fresh()->status);
    }

    // ------------------------------------------------------------------ stock in

    public function test_stock_in_increases_product_stock_correctly(): void
    {
        $product = Product::factory()->withStock(20, 2)->create();

        $this->actingAs($this->admin)->post('/stock-in', [
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => '10',
            'unit_cost' => '3.50',
            'reason' => 'restock',
            'movement_date' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('stock-in.index'));

        $product->refresh();
        $this->assertSame('30.000', $product->current_stock);         // 20 + 10
        $this->assertSame('2.5000', $product->average_cost);          // (20×2 + 10×3.5) / 30

        $movement = StockMovement::firstOrFail();
        $this->assertSame('20.000', $movement->previous_stock);
        $this->assertSame('30.000', $movement->new_stock);
        $this->assertSame($this->admin->id, $movement->user_id);
        $this->assertDatabaseHas('activity_logs', ['action' => 'stock_in', 'module' => 'stock']);
    }

    public function test_stock_in_requires_positive_quantity(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post('/stock-in', [
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => '0',
            'unit_cost' => '1',
            'reason' => 'restock',
            'movement_date' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, StockMovement::count());
    }

    public function test_stock_in_form_cannot_be_submitted_twice(): void
    {
        $product = Product::factory()->create();
        $payload = [
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => '5',
            'unit_cost' => '1',
            'reason' => 'opening_stock',
            'movement_date' => now()->subMinute()->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($this->admin)->post('/stock-in', $payload);
        $this->actingAs($this->admin)->post('/stock-in', $payload)->assertSessionHas('info');

        $this->assertSame('5.000', $product->fresh()->current_stock);
        $this->assertSame(1, StockMovement::count());
    }

    public function test_staff_needs_the_stock_in_permission(): void
    {
        $staff = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($staff)->get('/stock-in/create')->assertForbidden();
    }

    // ------------------------------------------------------------------ ledger rules

    public function test_stock_service_never_allows_negative_stock(): void
    {
        $product = Product::factory()->withStock(3)->create();

        try {
            app(StockService::class)->move($product, '-5', MovementType::Out, $this->admin);
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame('3.000', $product->fresh()->current_stock);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_stock_movements_are_immutable(): void
    {
        $product = Product::factory()->create();
        $movement = app(StockService::class)->move($product, '5', MovementType::In, $this->admin, unitCost: '1');

        $this->expectException(LogicException::class);
        $movement->update(['quantity' => 500]);
    }

    public function test_document_numbers_are_sequential_and_unique(): void
    {
        $product = Product::factory()->create();
        $numbers = collect(range(1, 3))->map(fn () => app(StockService::class)->move($product, '1', MovementType::In, $this->admin, unitCost: '1')->movement_number);

        $prefix = 'SM-' . now()->format('Ymd') . '-';
        $this->assertSame([$prefix . '000001', $prefix . '000002', $prefix . '000003'], $numbers->all());
    }
}

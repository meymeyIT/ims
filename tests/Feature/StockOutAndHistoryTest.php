<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 6 tests: stock out, history, adjustments and reversals.
 * Covers spec tests #8 (stock out decreases stock), #9 (stock out cannot exceed
 * available stock) and #15 (deleted records do not break history).
 */
class StockOutAndHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function stockIn(Product $product, string $qty, string $cost = '1'): StockMovement
    {
        return app(StockService::class)->move($product, $qty, MovementType::In, $this->admin, unitCost: $cost);
    }

    private function stockOutPayload(Product $product, string $qty, array $extra = []): array
    {
        return array_merge([
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => $qty,
            'reason' => 'sold',
            'movement_date' => now()->subMinute()->format('Y-m-d H:i:s'),
        ], $extra);
    }

    private function assertLedgerMatchesStock(Product $product): void
    {
        $this->assertEquals(
            (float) $product->fresh()->current_stock,
            (float) StockMovement::where('product_id', $product->id)->sum('quantity'),
            'Sum of stock movements must equal current stock.'
        );
    }

    // ------------------------------------------------------------------ stock out

    public function test_stock_out_decreases_product_stock_correctly(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '30');

        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '5'))
            ->assertRedirect(route('stock-out.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('25.000', $product->fresh()->current_stock);   // 30 - 5

        $movement = StockMovement::where('type', MovementType::Out)->firstOrFail();
        $this->assertSame('-5.000', $movement->quantity);
        $this->assertSame('30.000', $movement->previous_stock);
        $this->assertSame('25.000', $movement->new_stock);
        $this->assertSame('sold', $movement->reason);
        $this->assertLedgerMatchesStock($product);
    }

    public function test_stock_out_cannot_exceed_available_stock(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '4');

        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '5'))
            ->assertSessionHasErrors('quantity');

        $this->assertSame('4.000', $product->fresh()->current_stock);
        $this->assertSame(0, StockMovement::where('type', MovementType::Out)->count());
    }

    public function test_stock_out_of_exactly_all_stock_leaves_zero(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '2.5');

        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '2.5'))->assertSessionHasNoErrors();

        $this->assertSame('0.000', $product->fresh()->current_stock);
    }

    public function test_stock_out_quantity_must_be_positive(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '10');

        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '0'))->assertSessionHasErrors('quantity');
        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '-3'))->assertSessionHasErrors('quantity');

        $this->assertSame('10.000', $product->fresh()->current_stock);
    }

    public function test_return_to_supplier_is_recorded_as_return_out_and_needs_a_supplier(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $this->stockIn($product, '10');

        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '2', ['reason' => 'returned_to_supplier']))
            ->assertSessionHasErrors('supplier_id');

        $this->actingAs($this->admin)->post('/stock-out', $this->stockOutPayload($product, '2', [
            'reason' => 'returned_to_supplier',
            'supplier_id' => $supplier->id,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_movements', ['type' => 'return_out', 'supplier_id' => $supplier->id, 'quantity' => -2]);
        $this->assertSame('8.000', $product->fresh()->current_stock);
    }

    public function test_customer_return_is_recorded_as_return_in(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post('/stock-in', [
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'quantity' => '1',
            'unit_cost' => '2',
            'reason' => 'customer_return',
            'movement_date' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_movements', ['type' => 'return_in', 'product_id' => $product->id]);
    }

    public function test_stock_out_form_cannot_be_submitted_twice(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '10');
        $payload = $this->stockOutPayload($product, '3');

        $this->actingAs($this->admin)->post('/stock-out', $payload);
        $this->actingAs($this->admin)->post('/stock-out', $payload)->assertSessionHas('info');

        $this->assertSame('7.000', $product->fresh()->current_stock);
    }

    public function test_staff_can_record_stock_out_but_viewer_cannot(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '10');
        $staff = User::factory()->role(Role::STAFF)->create();
        $viewer = User::factory()->role(Role::VIEWER)->create();

        $this->actingAs($staff)->post('/stock-out', $this->stockOutPayload($product, '1'))->assertSessionHasNoErrors();
        $this->actingAs($viewer)->post('/stock-out', $this->stockOutPayload($product, '1'))->assertForbidden();
        $this->actingAs($viewer)->get('/stock-movements')->assertForbidden();

        $this->assertSame('9.000', $product->fresh()->current_stock);
    }

    // ------------------------------------------------------------------ reversals

    public function test_reversal_creates_a_compensating_movement_and_keeps_history(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '20');
        $out = app(StockService::class)->move($product, '-5', MovementType::Out, $this->admin, reason: 'sold');

        $this->actingAs($this->admin)->post("/stock-movements/{$out->id}/reverse", ['reason' => 'Wrong product scanned'])
            ->assertSessionHasNoErrors();

        $this->assertSame('20.000', $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', ['id' => $out->id, 'quantity' => -5]);      // original untouched
        $reversal = StockMovement::where('reversal_of_id', $out->id)->firstOrFail();
        $this->assertSame('5.000', $reversal->quantity);
        $this->assertSame(MovementType::Out, $reversal->type);
        $this->assertSame(3, StockMovement::count());
        $this->assertLedgerMatchesStock($product);
    }

    public function test_a_movement_cannot_be_reversed_twice_and_a_reversal_cannot_be_reversed(): void
    {
        $product = Product::factory()->create();
        $in = $this->stockIn($product, '10');

        $this->actingAs($this->admin)->post("/stock-movements/{$in->id}/reverse", ['reason' => 'Mistake']);
        $this->actingAs($this->admin)->post("/stock-movements/{$in->id}/reverse", ['reason' => 'Again'])->assertSessionHasErrors('reason');

        $reversal = StockMovement::where('reversal_of_id', $in->id)->firstOrFail();
        $this->actingAs($this->admin)->post("/stock-movements/{$reversal->id}/reverse", ['reason' => 'Undo'])->assertSessionHasErrors('reason');

        $this->assertSame('0.000', $product->fresh()->current_stock);
        $this->assertSame(2, StockMovement::count());
    }

    public function test_stock_in_cannot_be_reversed_after_the_stock_was_used(): void
    {
        $product = Product::factory()->create();
        $in = $this->stockIn($product, '10');
        app(StockService::class)->move($product, '-8', MovementType::Out, $this->admin);

        $this->actingAs($this->admin)->post("/stock-movements/{$in->id}/reverse", ['reason' => 'Mistake'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('2.000', $product->fresh()->current_stock);
    }

    public function test_reversing_a_stock_in_restores_the_previous_average_cost(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '20', '2');                 // avg 2.00
        $second = $this->stockIn($product, '10', '3.50');    // avg 2.50

        app(StockService::class)->reverse($second, $this->admin, 'Entered twice');

        $product->refresh();
        $this->assertSame('20.000', $product->current_stock);
        $this->assertSame('2.0000', $product->average_cost);
    }

    public function test_reversing_a_purchase_receipt_updates_the_purchase_order(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $service = app(PurchaseService::class);
        $purchase = $service->approve($service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '1']],
        ], $this->admin, true), $this->admin);
        $item = $purchase->items()->first();
        $service->receive($purchase, [$item->id => '10'], (string) Str::uuid(), now(), null, $this->admin);
        $this->assertSame(PurchaseStatus::Received, $purchase->fresh()->status);

        $receiptMovement = StockMovement::where('reference_type', 'purchase_receipt_item')->firstOrFail();
        $this->actingAs($this->admin)->post("/stock-movements/{$receiptMovement->id}/reverse", ['reason' => 'Wrong delivery'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.000', $product->fresh()->current_stock);
        $this->assertSame('0.000', $item->fresh()->quantity_received);
        $this->assertSame(PurchaseStatus::Approved, $purchase->fresh()->status);   // can be received again
    }

    public function test_staff_cannot_reverse_movements(): void
    {
        $product = Product::factory()->create();
        $in = $this->stockIn($product, '10');
        $staff = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($staff)->post("/stock-movements/{$in->id}/reverse", ['reason' => 'Nope'])->assertForbidden();
        $this->assertSame('10.000', $product->fresh()->current_stock);
    }

    // ------------------------------------------------------------------ adjustments

    public function test_adjustment_sets_stock_to_the_counted_quantity(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '50');

        $this->actingAs($this->admin)->post('/stock-adjustments', [
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'counted_quantity' => '47',
            'reason' => 'stock_count',
            'notes' => 'Monthly count',
        ])->assertSessionHasNoErrors();

        $this->assertSame('47.000', $product->fresh()->current_stock);
        $this->assertDatabaseHas('stock_movements', ['type' => 'adjustment', 'quantity' => -3, 'previous_stock' => 50, 'new_stock' => 47]);
        $this->assertLedgerMatchesStock($product);
    }

    public function test_adjustment_with_no_difference_is_rejected(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '5');

        $this->actingAs($this->admin)->post('/stock-adjustments', [
            'form_token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'counted_quantity' => '5',
            'reason' => 'stock_count',
            'notes' => 'Count',
        ])->assertSessionHasErrors('counted_quantity');

        $this->assertSame(1, StockMovement::count());
    }

    public function test_staff_cannot_make_adjustments(): void
    {
        $staff = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($staff)->get('/stock-adjustments/create')->assertForbidden();
    }

    // ------------------------------------------------------------------ history

    public function test_history_can_be_filtered_by_type(): void
    {
        $product = Product::factory()->create();
        $in = $this->stockIn($product, '10');
        $out = app(StockService::class)->move($product, '-2', MovementType::Out, $this->admin);

        $this->actingAs($this->admin)->get('/stock-movements?type=out')
            ->assertOk()
            ->assertSee($out->movement_number)
            ->assertDontSee($in->movement_number);
    }

    public function test_deleted_records_do_not_break_history(): void
    {
        $product = Product::factory()->create(['name' => 'Discontinued Widget']);
        $clerk = User::factory()->role(Role::MANAGER)->create(['name' => 'Former Employee']);
        $movement = app(StockService::class)->move($product, '3', MovementType::In, $clerk, unitCost: '1');

        $product->delete();   // archived
        $clerk->delete();     // soft-deleted user

        $this->actingAs($this->admin)->get('/stock-movements')
            ->assertOk()
            ->assertSee('Discontinued Widget')
            ->assertSee('Former Employee');

        $this->actingAs($this->admin)->get("/stock-movements/{$movement->id}")->assertOk()->assertSee('Discontinued Widget');
        $this->actingAs($this->admin)->get('/stock-movements/export')->assertOk();
    }

    public function test_export_downloads_csv_with_filtered_rows(): void
    {
        $product = Product::factory()->create(['name' => '=HYPERLINK("evil")']);
        $this->stockIn($product, '10');

        $response = $this->actingAs($this->admin)->get('/stock-movements/export');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);                  // BOM for Excel
        $this->assertStringContainsString('Transaction #', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);              // formula neutralised
    }

    public function test_print_view_renders(): void
    {
        $product = Product::factory()->create();
        $this->stockIn($product, '10');

        $this->actingAs($this->admin)->get('/stock-movements/print?type=in')->assertOk()->assertSee('Stock Movement History');
    }
}

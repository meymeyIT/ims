<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Concurrency and rollback behaviour of stock transactions (spec section 10).
 *
 * These tests use a SECOND real database connection to play "another user".
 * Data must be committed so that connection can see it, so this class uses
 * DatabaseTruncation (tables are emptied between tests) instead of RefreshDatabase.
 */
class StockConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->admin()->create();

        // Same database, separate connection = a second user's session.
        config(['database.connections.second_user' => config('database.connections.' . config('database.default'))]);
    }

    protected function tearDown(): void
    {
        DB::purge('second_user');

        // This class commits real rows. Empty the tables AFTER each test as well,
        // so the next test class (which uses RefreshDatabase) starts from a clean
        // database instead of inheriting our products, units and movements.
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_a_product_being_changed_by_another_user_is_locked_until_they_finish(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 0]);
        app(StockService::class)->move($product, '10', MovementType::In, $this->admin, unitCost: '1');

        // "Another user" starts a stock change on the same product and holds the row lock.
        $other = DB::connection('second_user');
        $other->beginTransaction();
        $other->table('products')->where('id', $product->id)->lockForUpdate()->first();

        // We must WAIT for them (here: give up after 1 second) instead of overwriting their change.
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            app(StockService::class)->move($product, '-4', MovementType::Out, $this->admin);
            $this->fail('The stock change should have waited for the row lock.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('lock wait timeout', $e->getMessage());
        } finally {
            $other->rollBack();
            DB::statement('SET SESSION innodb_lock_wait_timeout = 50');
        }

        // Nothing half-done was saved while we were blocked.
        $this->assertSame('10.000', $product->fresh()->current_stock);
        $this->assertSame(1, StockMovement::count());

        // As soon as the other user is finished, our change goes through.
        app(StockService::class)->move($product, '-4', MovementType::Out, $this->admin);
        $this->assertSame('6.000', $product->fresh()->current_stock);
    }

    public function test_stock_read_under_the_lock_is_always_the_latest_committed_value(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 0]);
        app(StockService::class)->move($product, '10', MovementType::In, $this->admin, unitCost: '1');

        // "Another user" sells 7 and commits, while our in-memory $product still says 10.
        $other = DB::connection('second_user');
        $other->transaction(function () use ($other, $product) {
            $other->table('products')->where('id', $product->id)->update(['current_stock' => 3]);
        });

        // We try to sell 5 using our stale model. StockService re-reads the row WITH a lock,
        // sees the real stock (3) and refuses, instead of trusting the stale 10.
        try {
            app(StockService::class)->move($product, '-5', MovementType::Out, $this->admin);
            $this->fail('Selling 5 when only 3 are left must be refused.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Available: 3', collect($e->errors())->flatten()->first());
        }

        $this->assertSame('3.000', $product->fresh()->current_stock);
    }

    public function test_a_failure_inside_a_larger_transaction_rolls_back_every_stock_change(): void
    {
        $a = Product::factory()->create(['minimum_stock' => 0]);
        $b = Product::factory()->create(['minimum_stock' => 0]);

        try {
            DB::transaction(function () use ($a, $b) {
                app(StockService::class)->move($a, '5', MovementType::In, $this->admin, unitCost: '1');
                app(StockService::class)->move($b, '5', MovementType::In, $this->admin, unitCost: '1');

                throw new RuntimeException('Something failed after both stock changes.');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame('0.000', $a->fresh()->current_stock);
        $this->assertSame('0.000', $b->fresh()->current_stock);
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, DB::table('sequences')->where('value', '>', 0)->count());   // numbers not consumed either
    }
}

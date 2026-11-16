<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 7 tests: low-stock alerts and the dashboard.
 * Covers spec tests #13 (low-stock alert appears when stock reaches minimum)
 * and #14 (reported numbers are calculated correctly).
 */
class LowStockAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);
        $this->admin = User::factory()->admin()->create();
        $this->manager = User::factory()->role(Role::MANAGER)->create();
        $this->viewer = User::factory()->role(Role::VIEWER)->create();
    }

    private function stock(Product $product, string $qty): void
    {
        app(StockService::class)->move($product, $qty, bccomp($qty, '0', 3) > 0 ? MovementType::In : MovementType::Out, $this->admin, unitCost: bccomp($qty, '0', 3) > 0 ? '1' : null);
    }

    private function alertsFor(Product $product)
    {
        return DatabaseNotification::where('type', 'low_stock')->where('data->product_id', $product->id);
    }

    // ------------------------------------------------------------------ low stock alerts

    public function test_low_stock_alert_appears_when_stock_reaches_minimum(): void
    {
        $product = Product::factory()->create(['name' => 'Keyboard', 'minimum_stock' => 10]);
        $this->stock($product, '20');
        $this->assertSame(0, $this->alertsFor($product)->count());

        $this->stock($product, '-15');   // 20 -> 5, at or below minimum 10

        $this->assertNotNull($product->fresh()->low_stock_alerted_at);
        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $this->manager->unreadNotifications()->count());   // manager has notifications.view
        $this->assertSame(0, $this->viewer->notifications()->count());          // viewer does not

        $message = $this->admin->unreadNotifications()->first()->data['message'];
        $this->assertStringContainsString('Keyboard is low on stock', $message);

        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Keyboard');
        $this->actingAs($this->admin)->get('/notifications')->assertOk()->assertSee('Keyboard is low on stock');
    }

    public function test_stock_exactly_at_minimum_counts_as_low(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '10');

        $this->assertSame(2, $this->alertsFor($product)->count());   // one per recipient (admin + manager)
    }

    public function test_no_duplicate_alerts_while_the_product_stays_low(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '12');
        $this->stock($product, '-3');   // 9: alert
        $this->stock($product, '-2');   // 7: still low, no new alert
        $this->stock($product, '1');    // 8: still low, no new alert

        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_restocking_resolves_the_alert_and_a_new_drop_alerts_again(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '5');      // low: alert #1
        $this->stock($product, '20');     // 25: back above minimum

        $this->assertNull($product->fresh()->low_stock_alerted_at);
        $this->assertSame(0, $this->admin->unreadNotifications()->count());   // old alert marked read automatically

        $this->stock($product, '-20');    // 5: low again -> alert #2
        $this->assertSame(2, $this->admin->notifications()->count());
        $this->assertSame(1, $this->admin->unreadNotifications()->count());
    }

    public function test_raising_the_minimum_stock_triggers_an_alert(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 2]);
        $this->stock($product, '5');
        $this->assertSame(0, $this->admin->notifications()->count());

        $this->actingAs($this->admin)->put("/products/{$product->id}", [
            'name' => $product->name,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'purchase_price' => '1',
            'selling_price' => '2',
            'minimum_stock' => '10',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_alerts_can_be_switched_off_in_settings(): void
    {
        Setting::set('low_stock_notifications', false);
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '3');

        $this->assertSame(0, DatabaseNotification::count());
        $this->assertNull($product->fresh()->low_stock_alerted_at);
    }

    public function test_scheduled_command_alerts_existing_low_stock_once(): void
    {
        Setting::set('low_stock_notifications', false);
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '3');                       // low, but alerts were off
        Setting::set('low_stock_notifications', true);

        $this->artisan('ims:check-low-stock')->assertSuccessful();
        $this->artisan('ims:check-low-stock')->assertSuccessful();   // second run: nothing new

        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_the_product(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '3');
        $notification = $this->admin->unreadNotifications()->firstOrFail();

        $this->actingAs($this->admin)->get("/notifications/{$notification->id}")
            ->assertRedirect(route('products.show', $product->id, false));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_open_someone_elses_notification(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 10]);
        $this->stock($product, '3');
        $managersNotification = $this->manager->notifications()->firstOrFail();

        $this->actingAs($this->admin)->get("/notifications/{$managersNotification->id}")->assertNotFound();
        $this->assertNull($managersNotification->fresh()->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        foreach (range(1, 3) as $i) {
            $this->stock(Product::factory()->create(['minimum_stock' => 10]), '3');
        }
        $this->assertSame(3, $this->admin->unreadNotifications()->count());

        $this->actingAs($this->admin)->post('/notifications/read-all');

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertSame(3, $this->manager->unreadNotifications()->count());   // only the current user's
    }

    public function test_low_stock_page_suggests_restock_and_prefills_a_purchase(): void
    {
        $product = Product::factory()->create(['name' => 'Coffee Beans', 'minimum_stock' => 10, 'purchase_price' => '4.20']);
        $this->stock($product, '4');   // suggested = 2 × 10 − 4 = 16

        $this->assertSame('16.000', $product->fresh()->suggestedRestockQuantity());
        $this->actingAs($this->admin)->get('/notifications')->assertOk()->assertSee('Coffee Beans')->assertSee('16');

        $this->actingAs($this->admin)->get('/purchases/create?products[]=' . $product->id)
            ->assertOk()
            ->assertSee('value="16.000"', false);
    }

    public function test_viewer_cannot_open_the_alerts_page(): void
    {
        $this->actingAs($this->viewer)->get('/notifications')->assertForbidden();
    }

    // ------------------------------------------------------------------ dashboard

    public function test_dashboard_numbers_come_from_the_database(): void
    {
        $a = Product::factory()->create(['minimum_stock' => 5]);
        $b = Product::factory()->create(['minimum_stock' => 5]);
        app(StockService::class)->move($a, '10', MovementType::In, $this->admin, unitCost: '2');     // value 20
        app(StockService::class)->move($b, '4', MovementType::In, $this->admin, unitCost: '2.50');   // value 10, low (4 <= 5)
        app(StockService::class)->move($a, '-3', MovementType::Out, $this->admin);                    // a: 7 left, value 14

        $supplier = Supplier::factory()->create();
        app(PurchaseService::class)->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $a->id, 'quantity' => '2', 'unit_cost' => '50']],
        ], $this->admin, submit: true);

        $cards = app(\App\Services\DashboardStats::class)->cards();

        $this->assertSame(2, $cards['products']);
        $this->assertEquals(11, (float) $cards['total_stock']);          // 7 + 4
        $this->assertEquals(24, (float) $cards['inventory_value']);      // 7×2 + 4×2.5
        $this->assertEquals(14, (float) $cards['stock_in_today']);       // 10 + 4
        $this->assertSame(2, $cards['stock_in_today_count']);
        $this->assertEquals(3, (float) $cards['stock_out_today']);
        $this->assertSame(1, $cards['low_stock']);
        $this->assertSame(1, $cards['pending_purchases']);
        $this->assertEquals(100, (float) $cards['purchases_this_month']);
    }

    public function test_reversals_are_netted_out_of_todays_stock_out(): void
    {
        $product = Product::factory()->create();
        app(StockService::class)->move($product, '10', MovementType::In, $this->admin, unitCost: '1');
        $out = app(StockService::class)->move($product, '-4', MovementType::Out, $this->admin);
        app(StockService::class)->reverse($out, $this->admin, 'mistake');

        $cards = app(\App\Services\DashboardStats::class)->cards();

        $this->assertEquals(0, (float) $cards['stock_out_today']);
        $this->assertSame(1, $cards['stock_out_today_count']);   // the original counts, the reversal does not add one
    }

    public function test_dashboard_renders_charts_for_every_role(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 5]);
        $this->stock($product, '8');
        $this->stock($product, '-6');

        foreach ([$this->admin, $this->manager, User::factory()->role(Role::STAFF)->create(), $this->viewer] as $user) {
            $this->actingAs($user)->get('/dashboard')
                ->assertOk()
                ->assertSee('Monthly Stock In and Stock Out')
                ->assertSee('dashboard-data', false);
        }

        // Purchase widgets only for users who can view purchases.
        $this->actingAs($this->viewer)->get('/dashboard')->assertDontSee('Monthly Purchase Trends');
        $this->actingAs($this->manager)->get('/dashboard')->assertSee('Monthly Purchase Trends');
    }

    public function test_chart_data_contains_twelve_months(): void
    {
        $charts = app(\App\Services\DashboardStats::class)->charts();

        $this->assertCount(12, $charts['stockFlow']['labels']);
        $this->assertSame(now()->format('M Y'), $charts['stockFlow']['labels']->last());
        $this->assertCount(12, $charts['purchases']['totals']);
    }
}

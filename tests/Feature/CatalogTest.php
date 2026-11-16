<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 4 tests: categories, units, products and suppliers.
 * Covers spec tests #3 (admin can create a product), #4 (duplicate SKU rejected)
 * and #5 (admin can create a category).
 */
class CatalogTest extends TestCase
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

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Logitech Keyboard K120',
            'sku' => 'kb-k120',
            'barcode' => '5099206020856',
            'category_id' => Category::factory()->create()->id,
            'unit_id' => Unit::factory()->create()->id,
            'purchase_price' => '8.50',
            'selling_price' => '12.00',
            'minimum_stock' => '10',
            'description' => 'USB keyboard',
            'is_active' => '1',
        ], $overrides);
    }

    // ------------------------------------------------------------------ categories

    public function test_admin_can_create_a_category(): void
    {
        $this->actingAs($this->admin)
            ->post('/categories', ['name' => 'Beverages', 'description' => 'Drinks', 'is_active' => '1'])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Beverages', 'is_active' => true]);
        $this->assertDatabaseHas('activity_logs', ['module' => 'categories', 'action' => 'created']);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        Category::factory()->create(['name' => 'Beverages']);

        $this->actingAs($this->admin)->post('/categories', ['name' => 'Beverages'])->assertSessionHasErrors('name');
        $this->assertSame(1, Category::where('name', 'Beverages')->count());
    }

    public function test_category_used_by_a_product_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->delete("/categories/{$product->category_id}")->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
    }

    public function test_category_used_only_by_an_archived_product_still_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $product->delete(); // archive

        $this->actingAs($this->admin)->delete("/categories/{$product->category_id}")->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $product->category_id]);
    }

    public function test_unused_category_can_be_deleted(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->delete("/categories/{$category->id}")->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // ------------------------------------------------------------------ units

    public function test_unit_used_by_a_product_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->delete("/units/{$product->unit_id}")->assertSessionHas('error');
        $this->assertDatabaseHas('units', ['id' => $product->unit_id]);
    }

    public function test_duplicate_unit_abbreviation_is_rejected(): void
    {
        Unit::factory()->create(['abbreviation' => 'kg']);

        $this->actingAs($this->admin)->post('/units', ['name' => 'Kilo', 'abbreviation' => 'kg'])
            ->assertSessionHasErrors('abbreviation');
    }

    // ------------------------------------------------------------------ products

    public function test_admin_can_create_a_product(): void
    {
        $response = $this->actingAs($this->admin)->post('/products', $this->productPayload());

        $product = Product::where('sku', 'KB-K120')->firstOrFail();   // SKU is stored upper-case
        $response->assertRedirect(route('products.show', $product));

        $this->assertSame('Logitech Keyboard K120', $product->name);
        $this->assertSame('0.000', $product->fresh()->current_stock);
        $this->assertDatabaseHas('activity_logs', ['module' => 'products', 'action' => 'created', 'subject_id' => $product->id]);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        Product::factory()->create(['sku' => 'KB-K120']);

        $this->actingAs($this->admin)->post('/products', $this->productPayload(['sku' => 'kb-k120', 'barcode' => null]))
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::withTrashed()->where('sku', 'KB-K120')->count());
    }

    public function test_sku_of_an_archived_product_cannot_be_reused(): void
    {
        Product::factory()->create(['sku' => 'OLD-001'])->delete();

        $this->actingAs($this->admin)->post('/products', $this->productPayload(['sku' => 'OLD-001', 'barcode' => null]))
            ->assertSessionHasErrors('sku');
    }

    public function test_negative_prices_and_minimum_stock_are_rejected(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productPayload([
            'purchase_price' => '-1',
            'selling_price' => '-5',
            'minimum_stock' => '-2',
        ]))->assertSessionHasErrors(['purchase_price', 'selling_price', 'minimum_stock']);

        $this->assertSame(0, Product::count());
    }

    public function test_stock_cannot_be_set_through_the_product_form(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productPayload([
            'current_stock' => '999',
            'average_cost' => '50',
        ]));

        $product = Product::where('sku', 'KB-K120')->firstOrFail();
        $this->assertSame('0.000', $product->current_stock);
        $this->assertSame('0.0000', $product->average_cost);
    }

    public function test_product_image_is_uploaded_and_replaced(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The PHP gd extension is required to generate a test image. Enable extension=gd in php.ini.');
        }

        Storage::fake('public');

        $this->actingAs($this->admin)->post('/products', $this->productPayload([
            'image' => UploadedFile::fake()->image('keyboard.jpg', 400, 400),
        ]));

        $product = Product::where('sku', 'KB-K120')->firstOrFail();
        $firstImage = $product->image_path;
        Storage::disk('public')->assertExists($firstImage);

        $this->actingAs($this->admin)->put("/products/{$product->id}", $this->productPayload([
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'image' => UploadedFile::fake()->image('keyboard2.png', 300, 300),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($firstImage);              // old file cleaned up
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->admin)->post('/products', $this->productPayload([
            'image' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
        ]))->assertSessionHasErrors('image');
    }

    public function test_products_can_be_searched_by_name_sku_and_barcode(): void
    {
        Product::factory()->create(['name' => 'Arabica Coffee Beans', 'sku' => 'COF-001', 'barcode' => '111222333']);
        Product::factory()->create(['name' => 'Green Tea', 'sku' => 'TEA-001', 'barcode' => '999888777']);

        $this->actingAs($this->admin)->get('/products?search=arabica')->assertSee('Arabica Coffee Beans')->assertDontSee('Green Tea');
        $this->actingAs($this->admin)->get('/products?search=TEA-001')->assertSee('Green Tea')->assertDontSee('Arabica');
        $this->actingAs($this->admin)->get('/products?search=111222')->assertSee('Arabica Coffee Beans');
    }

    public function test_products_can_be_filtered_by_stock_status(): void
    {
        Product::factory()->withStock(50)->create(['name' => 'Plenty Item', 'minimum_stock' => 10]);
        Product::factory()->withStock(3)->create(['name' => 'Low Item', 'minimum_stock' => 10]);
        Product::factory()->create(['name' => 'Empty Item', 'minimum_stock' => 10]);

        $this->actingAs($this->admin)->get('/products?stock=low_stock')
            ->assertSee('Low Item')->assertDontSee('Plenty Item')->assertDontSee('Empty Item');
        $this->actingAs($this->admin)->get('/products?stock=out_of_stock')
            ->assertSee('Empty Item')->assertDontSee('Low Item');
    }

    public function test_deleting_a_product_archives_it(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->delete("/products/{$product->id}")->assertRedirect(route('products.index'));

        $this->assertSoftDeleted($product);
        $this->actingAs($this->admin)->patch("/products/{$product->id}/restore");
        $this->assertNotSoftDeleted($product);
    }

    public function test_product_with_stock_history_cannot_be_permanently_deleted(): void
    {
        $product = Product::factory()->create();
        StockMovement::create([
            'movement_number' => 'SM-TEST-000001',
            'product_id' => $product->id,
            'user_id' => $this->admin->id,
            'type' => MovementType::In,
            'quantity' => 5,
            'previous_stock' => 0,
            'new_stock' => 5,
            'movement_date' => now(),
        ]);
        $product->delete();

        $this->actingAs($this->admin)->delete("/products/{$product->id}/force")->assertSessionHas('error');
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_unused_archived_product_can_be_permanently_deleted(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->actingAs($this->admin)->delete("/products/{$product->id}/force");
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // ------------------------------------------------------------------ permissions

    public function test_staff_and_viewer_can_view_but_not_change_products(): void
    {
        $product = Product::factory()->create();

        foreach ([Role::STAFF, Role::VIEWER] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user);

            $this->get('/products')->assertOk();
            $this->get("/products/{$product->id}")->assertOk();
            $this->get('/products/create')->assertForbidden();
            $this->post('/products', $this->productPayload())->assertForbidden();
            $this->delete("/products/{$product->id}")->assertForbidden();
            $this->get('/categories')->assertForbidden();
            $this->get('/suppliers')->assertForbidden();
        }
    }

    public function test_manager_can_manage_catalog(): void
    {
        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)->post('/categories', ['name' => 'Snacks'])->assertRedirect();
        $this->actingAs($manager)->post('/suppliers', ['name' => 'ABC Trading'])->assertRedirect();
        $this->actingAs($manager)->post('/products', $this->productPayload())->assertRedirect();

        $this->assertDatabaseHas('categories', ['name' => 'Snacks']);
        $this->assertDatabaseHas('suppliers', ['name' => 'ABC Trading']);
        $this->assertDatabaseHas('products', ['sku' => 'KB-K120']);
    }

    // ------------------------------------------------------------------ suppliers

    public function test_supplier_email_and_phone_are_validated(): void
    {
        $this->actingAs($this->admin)->post('/suppliers', [
            'name' => 'Bad Supplier',
            'email' => 'not-an-email',
            'phone' => 'call me',
        ])->assertSessionHasErrors(['email', 'phone']);

        $this->actingAs($this->admin)->post('/suppliers', [
            'name' => 'Good Supplier',
            'email' => 'Sales@Good.com',
            'phone' => '+855 12 345 678',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['name' => 'Good Supplier', 'email' => 'sales@good.com']);
    }

    public function test_supplier_page_shows_purchase_history_section(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->admin)->get("/suppliers/{$supplier->id}")
            ->assertOk()
            ->assertSee('Purchase history')
            ->assertSee('No purchases from this supplier yet.');
    }
}

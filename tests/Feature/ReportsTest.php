<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Reports\InventoryValuationReport;
use App\Reports\ReportFilters;
use App\Reports\ReportRegistry;
use App\Services\PurchaseService;
use App\Services\StockService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Phase 8 tests: the 10 reports, their numbers and their exports.
 * Covers spec test #14 (reports calculate correct values) and #15 (deleted records
 * do not break historical reports).
 */
class ReportsTest extends TestCase
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

    private function move(Product $product, string $qty, ?string $cost = null, $date = null, ?MovementType $type = null): \App\Models\StockMovement
    {
        $type ??= bccomp($qty, '0', 3) > 0 ? MovementType::In : MovementType::Out;

        return app(StockService::class)->move($product, $qty, $type, $this->admin, unitCost: $cost, movementDate: $date);
    }

    private function filters(string $key, array $query = []): ReportFilters
    {
        return ReportFilters::fromRequest(Request::create('/', 'GET', $query), ReportRegistry::find($key));
    }

    // ------------------------------------------------------------------ every report works

    public function test_every_report_page_print_and_pdf_renders(): void
    {
        $product = Product::factory()->create(['minimum_stock' => 50]);
        $this->move($product, '10', '2');
        $this->move($product, '-3');

        foreach (array_keys(ReportRegistry::all()) as $key) {
            $this->actingAs($this->admin)->get("/reports/{$key}")->assertOk()->assertSee(ReportRegistry::find($key)->title());
            $this->actingAs($this->admin)->get("/reports/{$key}/print")->assertOk();
        }

        $pdf = $this->actingAs($this->admin)->get('/reports/inventory-valuation/pdf');
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_reports_index_lists_the_reports_by_group(): void
    {
        $this->actingAs($this->admin)->get('/reports')->assertOk()
            ->assertSee('Inventory Valuation Report')->assertSee('Stock In Report')->assertSee('Purchase Report');

        $this->actingAs($this->admin)->get('/reports?group=stock')->assertOk()
            ->assertSee('Stock Out Report')
            ->assertDontSee(route('reports.show', 'purchases'));   // the sidebar still says "Purchase Reports", so check the card link
    }

    // ------------------------------------------------------------------ correct values (spec #14)

    public function test_inventory_valuation_uses_weighted_average_cost(): void
    {
        $a = Product::factory()->create(['name' => 'Alpha']);
        $b = Product::factory()->create(['name' => 'Beta']);
        $this->move($a, '20', '2');      // avg 2.00
        $this->move($a, '10', '3.50');   // avg (40 + 35) / 30 = 2.50
        $this->move($a, '-6');           // 24 left × 2.50 = 60.00
        $this->move($b, '4', '10');      // 4 × 10 = 40.00

        $report = new InventoryValuationReport();
        $filters = $this->filters('inventory-valuation');
        $report->prepare($filters);
        $rows = $report->query($filters)->get()->map(fn ($p) => $report->row($p))->keyBy('name');

        $this->assertEquals(60.00, $rows['Alpha']['value']);
        $this->assertEquals(2.5, $rows['Alpha']['average_cost']);
        $this->assertEquals(40.00, $rows['Beta']['value']);
        $this->assertEquals(60.0, $rows['Alpha']['share']);   // 60 of 100
        $this->assertEquals(100.00, $report->totals($filters)['value']);
    }

    public function test_stock_balance_can_be_rebuilt_for_a_past_date(): void
    {
        $product = Product::factory()->create(['name' => 'Gamma']);
        $this->move($product, '10', '1', now()->subDays(5));
        $this->move($product, '-4', null, now()->subDays(2));
        $this->move($product, '6', '1', now());

        $balance = fn (array $q) => (float) ReportRegistry::find('stock-balance')->query($this->filters('stock-balance', $q))->first()->balance;

        $this->assertEquals(10, $balance(['as_of' => now()->subDays(3)->toDateString()]));
        $this->assertEquals(6, $balance(['as_of' => now()->subDay()->toDateString()]));
        $this->assertEquals(12, $balance([]));   // today = current stock
    }

    public function test_stock_in_and_out_reports_net_out_reversals(): void
    {
        $product = Product::factory()->create();
        $this->move($product, '10', '2');
        $wrong = $this->move($product, '5', '2');
        app(StockService::class)->reverse($wrong, $this->admin, 'typo');
        $this->move($product, '-4');                         // avg 2 → value 8

        $in = ReportRegistry::find('stock-in')->totals($this->filters('stock-in'));
        $this->assertEquals(10, $in['quantity']);           // 10 + 5 − 5
        $this->assertEquals(20, $in['value']);

        $out = ReportRegistry::find('stock-out')->totals($this->filters('stock-out'));
        $this->assertEquals(4, $out['quantity']);            // shown positive
        $this->assertEquals(8, $out['value']);
    }

    public function test_purchase_report_totals_exclude_drafts_and_cancelled(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $service = app(PurchaseService::class);
        $make = fn (string $cost, bool $submit = true) => $service->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => '1', 'unit_cost' => $cost]],
        ], $this->admin, $submit);

        $make('100');                                           // pending: counted
        $service->approve($make('50'), $this->admin);           // approved: counted
        $make('999', false);                                    // draft: not counted
        $service->cancel($make('777'), $this->admin, 'no');     // cancelled: not counted

        $totals = ReportRegistry::find('purchases')->totals($this->filters('purchases'));
        $this->assertEquals(150, $totals['total']);
        $this->assertSame('2 order(s)', $totals['items']);

        $supplierRow = ReportRegistry::find('suppliers')->query($this->filters('suppliers'))->first();
        $this->assertEquals(2, $supplierRow->orders_total);
        $this->assertEquals(150, $supplierRow->amount_total);
    }

    public function test_category_report_counts_and_values(): void
    {
        $category = Category::factory()->create(['name' => 'Drinks']);
        $cola = Product::factory()->create(['category_id' => $category->id, 'minimum_stock' => 5]);
        $tea = Product::factory()->create(['category_id' => $category->id, 'minimum_stock' => 5]);
        $old = Product::factory()->create(['category_id' => $category->id]);
        $this->move($cola, '10', '1.50');   // value 15, not low
        $this->move($tea, '2', '5');        // value 10, low
        $old->delete();                     // archived: excluded

        $row = ReportRegistry::find('categories')->query($this->filters('categories', ['search' => 'Drinks']))->first();
        $this->assertEquals(2, $row->products_total);
        $this->assertEquals(1, $row->low_stock_total);
        $this->assertEquals(25, $row->stock_value);
    }

    public function test_low_stock_report_shows_shortage_and_suggestion(): void
    {
        $product = Product::factory()->create(['name' => 'Delta', 'minimum_stock' => 10]);
        $this->move($product, '4', '1');

        $report = ReportRegistry::find('low-stock');
        $row = $report->row($report->query($this->filters('low-stock'))->firstOrFail());

        $this->assertEquals(6, $row['shortage']);
        $this->assertEquals(16, $row['suggested']);   // 2 × 10 − 4
    }

    public function test_date_range_defaults_to_the_current_month(): void
    {
        $product = Product::factory()->create();
        $this->move($product, '7', '1', now()->subMonths(2));
        $this->move($product, '3', '1', now());

        $this->assertEquals(3, ReportRegistry::find('stock-in')->totals($this->filters('stock-in'))['quantity']);
        $this->assertEquals(10, ReportRegistry::find('stock-in')->totals($this->filters('stock-in', [
            'start_date' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->toDateString(),
        ]))['quantity']);
    }

    public function test_deleted_records_do_not_break_historical_reports(): void
    {
        $product = Product::factory()->create(['name' => 'Retired Item']);
        $clerk = User::factory()->role(Role::MANAGER)->create(['name' => 'Ex Staff']);
        app(StockService::class)->move($product, '5', MovementType::In, $clerk, unitCost: '1');
        $product->delete();
        $clerk->delete();

        $this->actingAs($this->admin)->get('/reports/stock-movements')->assertOk()->assertSee('Retired Item')->assertSee('Ex Staff');
        $this->actingAs($this->admin)->get('/reports/stock-in')->assertOk()->assertSee('Retired Item');
    }

    // ------------------------------------------------------------------ exports

    public function test_excel_export_has_headings_totals_and_never_creates_formulas(): void
    {
        $product = Product::factory()->create(['name' => '=HYPERLINK("http://evil.example","click")']);
        $this->move($product, '10', '2');

        $response = $this->actingAs($this->admin)->get('/reports/inventory-valuation/excel');
        $response->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();

        $this->assertSame('Inventory Valuation Report', $sheet->getCell('A1')->getValue());
        $this->assertSame('SKU', $sheet->getCell('A4')->getValue());
        $this->assertSame('Product', $sheet->getCell('B4')->getValue());

        $nameCell = $sheet->getCell('B5');
        $this->assertSame(DataType::TYPE_STRING, $nameCell->getDataType());            // text, not a formula
        $this->assertStringStartsWith('=HYPERLINK', $nameCell->getValue());

        $this->assertEquals(20, $sheet->getCell('G5')->getValue());                  // value is a real number
        $this->assertSame('Total', $sheet->getCell('A6')->getValue());
        $this->assertEquals(20, $sheet->getCell('G6')->getValue());
    }

    // ------------------------------------------------------------------ permissions

    public function test_viewer_can_view_reports_but_not_export_or_see_purchase_reports(): void
    {
        $viewer = User::factory()->role(Role::VIEWER)->create();

        $this->actingAs($viewer)->get('/reports/products')->assertOk();
        $this->actingAs($viewer)->get('/reports/inventory-valuation')->assertOk()->assertDontSee('/excel');
        $this->actingAs($viewer)->get('/reports/products/excel')->assertForbidden();
        $this->actingAs($viewer)->get('/reports/products/pdf')->assertForbidden();
        $this->actingAs($viewer)->get('/reports/purchases')->assertForbidden();
        $this->actingAs($viewer)->get('/reports')->assertOk()->assertDontSee('Supplier Report');
    }

    public function test_staff_has_no_report_access(): void
    {
        $staff = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($staff)->get('/reports')->assertForbidden();
        $this->actingAs($staff)->get('/reports/products')->assertForbidden();
    }

    public function test_unknown_report_returns_404(): void
    {
        $this->actingAs($this->admin)->get('/reports/does-not-exist')->assertNotFound();
    }
}

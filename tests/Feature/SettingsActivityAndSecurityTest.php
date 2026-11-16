<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Currency;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 9 tests: system settings, activity logs and security checks. */
class SettingsActivityAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);
        $this->admin = User::factory()->admin()->create();
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Zoom Mart',
            'business_address' => 'Street 2004, Phnom Penh',
            'business_phone' => '012 345 678',
            'business_email' => 'Info@ZoomMart.com',
            'currency' => 'USD',
            'khr_exchange_rate' => '4100',
            'date_format' => 'd/m/Y',
            'low_stock_notifications' => '1',
            'low_stock_email' => '0',
            'purchase_number_prefix' => 'po',
            'receipt_number_prefix' => 'GRN',
        ], $overrides);
    }

    // ------------------------------------------------------------------ settings

    public function test_admin_can_update_settings_and_the_change_is_logged(): void
    {
        $this->actingAs($this->admin)->put('/settings', $this->settingsPayload())
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Zoom Mart', Setting::get('business_name'));
        $this->assertSame('info@zoommart.com', Setting::get('business_email'));
        $this->assertSame('PO', Setting::get('purchase_number_prefix'));
        $this->assertSame('4100', Setting::get('khr_exchange_rate'));
        $this->assertDatabaseHas('activity_logs', ['module' => 'settings', 'action' => 'updated']);

        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Zoom Mart');
    }

    public function test_only_admin_can_open_settings_and_activity_logs(): void
    {
        $manager = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($manager)->get('/settings')->assertForbidden();
        $this->actingAs($manager)->put('/settings', $this->settingsPayload())->assertForbidden();
        $this->actingAs($manager)->get('/activity-logs')->assertForbidden();

        $this->actingAs($this->admin)->get('/settings')->assertOk()->assertSee('Business logo');
        $this->actingAs($this->admin)->get('/activity-logs')->assertOk();
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingAs($this->admin)->put('/settings', $this->settingsPayload([
            'currency' => 'EUR',
            'khr_exchange_rate' => '0',
            'date_format' => 'Y/d/m',
            'purchase_number_prefix' => 'P O!',
            'receipt_number_prefix' => '',
        ]))->assertSessionHasErrors(['currency', 'khr_exchange_rate', 'date_format', 'purchase_number_prefix', 'receipt_number_prefix']);
    }

    public function test_currency_setting_changes_money_formatting(): void
    {
        $this->assertSame('$1,234.50', Currency::format('1234.5'));
        $this->assertSame('≈ 4,000 ៛', Currency::alternate('1'));

        Setting::set('currency', 'KHR');

        $this->assertSame('40,000 ៛', Currency::format('40000'));
        $this->assertSame('40,000 KHR', Currency::format('40000', plain: true));
        $this->assertSame('≈ $10.00', Currency::alternate('40000'));
    }

    public function test_date_format_setting_is_used_everywhere(): void
    {
        $date = Carbon::create(2026, 11, 16, 14, 30);
        $this->assertSame('16/11/2026', $date->toAppDate());

        Setting::set('date_format', 'Y-m-d');
        $this->assertSame('2026-11-16 14:30', $date->toAppDateTime());

        Setting::set('date_format', 'd M Y');
        $this->assertSame('16 Nov 2026', $date->toImmutable()->toAppDate());
    }

    public function test_logo_can_be_uploaded_and_svg_is_refused(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->put('/settings', $this->settingsPayload([
            'business_logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg onload="alert(1)"></svg>'),
        ]))->assertSessionHasErrors('business_logo');

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The gd extension is needed to generate a test PNG.');
        }

        $this->actingAs($this->admin)->put('/settings', $this->settingsPayload([
            'business_logo' => UploadedFile::fake()->image('logo.png', 400, 200),
        ]))->assertSessionHasNoErrors();

        $path = Setting::get('business_logo');
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->admin)->put('/settings', $this->settingsPayload(['remove_logo' => '1']));
        $this->assertNull(Setting::get('business_logo'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_switching_alerts_back_on_sends_the_missed_alerts(): void
    {
        Setting::set('low_stock_notifications', false);
        Product::factory()->create(['minimum_stock' => 10]); // 0 stock: low, but alerts were off

        $this->actingAs($this->admin)->put('/settings', $this->settingsPayload(['low_stock_notifications' => '1']));

        $this->assertSame(1, $this->admin->notifications()->count());
    }

    // ------------------------------------------------------------------ activity logs

    public function test_activity_logs_can_be_filtered(): void
    {
        ActivityLog::create(['user_id' => $this->admin->id, 'action' => 'created', 'module' => 'products', 'description' => 'Created product Coffee']);
        ActivityLog::create(['user_id' => $this->admin->id, 'action' => 'stock_out', 'module' => 'stock', 'description' => 'Stock out Tea']);

        $this->actingAs($this->admin)->get('/activity-logs?module=products')
            ->assertOk()->assertSee('Created product Coffee')->assertDontSee('Stock out Tea');

        $this->actingAs($this->admin)->get('/activity-logs?search=Tea')
            ->assertOk()->assertSee('Stock out Tea')->assertDontSee('Created product Coffee');
    }

    public function test_failed_sign_in_is_logged_without_the_password(): void
    {
        $this->post('/login', ['login' => 'admin-guess', 'password' => 'SuperSecret!123']);

        $log = ActivityLog::where('action', 'login_failed')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('admin-guess', $log->description);
        $this->assertStringNotContainsString('SuperSecret', $log->description . json_encode($log->properties));
        $this->assertNotNull($log->ip_address);
    }

    // ------------------------------------------------------------------ security

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_every_application_route_requires_login(): void
    {
        // Login and password-reset routes are public on purpose: they carry the "guest" middleware.
        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route) => in_array('guest', $route->gatherMiddleware(), true))
            ->reject(fn ($route) => in_array($route->uri(), ['/', 'up'], true))                 // redirect / health check
            ->reject(fn ($route) => str_starts_with((string) $route->getName(), 'storage.'))  // framework file route
            ->reject(fn ($route) => str_starts_with($route->uri(), '_'))                       // framework internals
            ->reject(fn ($route) => in_array('auth', $route->gatherMiddleware(), true))
            ->map(fn ($route) => implode('|', $route->methods()) . ' ' . $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'These routes are reachable without logging in.');
    }

    public function test_guest_requests_to_protected_pages_are_redirected_to_login(): void
    {
        foreach (['/dashboard', '/products', '/purchases', '/stock-movements', '/reports', '/settings', '/activity-logs', '/users'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }

        $this->post('/stock-out', [])->assertRedirect(route('login'));
    }

    public function test_passwords_are_hashed_and_never_exposed(): void
    {
        $user = User::factory()->create(['password' => 'PlainText123']);

        $this->assertNotSame('PlainText123', $user->getRawOriginal('password'));
        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }
}

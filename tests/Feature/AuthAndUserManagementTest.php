<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 3 tests: authentication, role-based access and user management.
 * Covers spec tests #1 (admin can log in), #2 (invalid login rejected)
 * and #6 (staff cannot access unauthorized pages).
 */
class AuthAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();                       // views render without built assets
        $this->seed(RolePermissionSeeder::class);   // the 4 roles + 32 permissions
    }

    // ------------------------------------------------------------------ authentication

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post('/login', ['login' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'action' => 'login']);
    }

    public function test_user_can_log_in_with_username(): void
    {
        $user = User::factory()->role(Role::STAFF)->create(['username' => 'dara']);

        $this->post('/login', ['login' => 'Dara', 'password' => 'password'])   // case-insensitive
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/users')->assertRedirect(route('login'));
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->role(Role::STAFF)->create();
        $this->actingAs($user);

        $user->forceFill(['is_active' => false])->save();

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ authorization

    public function test_every_role_can_open_the_dashboard(): void
    {
        foreach ([Role::ADMIN, Role::MANAGER, Role::STAFF, Role::VIEWER] as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Total Products');
        }
    }

    public function test_staff_cannot_access_unauthorized_pages(): void
    {
        $staff = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($staff);
        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->get('/roles')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
    }

    public function test_viewer_cannot_create_users(): void
    {
        $viewer = User::factory()->role(Role::VIEWER)->create();

        $this->actingAs($viewer)->post('/users', [
            'name' => 'Hacker',
            'username' => 'hacker',
            'email' => 'hacker@example.com',
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'hacker@example.com']);
    }

    public function test_sidebar_hides_links_the_user_cannot_use(): void
    {
        $staff = User::factory()->role(Role::STAFF)->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($staff)->get('/dashboard')->assertDontSee(route('users.index'));
        $this->actingAs($admin)->get('/dashboard')->assertSee(route('users.index'));
    }

    // ------------------------------------------------------------------ user management

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $staffRoleId = Role::where('name', Role::STAFF)->value('id');

        $this->actingAs($admin)->post('/users', [
            'name' => 'Sok Dara',
            'username' => 'Dara',
            'email' => 'Dara@Example.com',
            'phone' => '012 345 678',
            'role_id' => $staffRoleId,
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'is_active' => '1',
        ])->assertRedirect(route('users.index'));

        $user = User::where('email', 'dara@example.com')->firstOrFail(); // email normalised to lower case
        $this->assertSame($staffRoleId, $user->role_id);
        $this->assertSame('dara', $user->username);
        $this->assertTrue(Hash::check('Secret123', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'module' => 'users', 'subject_id' => $user->id]);
    }

    public function test_weak_password_and_duplicate_email_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Duplicate',
            'username' => $admin->username,
            'email' => $admin->email,
            'role_id' => Role::where('name', Role::STAFF)->value('id'),
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertSessionHasErrors(['username', 'email', 'password']);
    }

    public function test_admin_cannot_delete_or_deactivate_themself(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->delete("/users/{$admin->id}")->assertSessionHas('error');
        $this->patch("/users/{$admin->id}/toggle-status")->assertSessionHas('error');

        $this->assertNotSoftDeleted($admin);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'username' => $admin->username,
            'email' => $admin->email,
            'role_id' => Role::where('name', Role::STAFF)->value('id'),
            'is_active' => '1',
        ])->assertSessionHasErrors('role_id');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_deleted_user_is_soft_deleted_and_cannot_log_in(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($admin)->delete("/users/{$staff->id}")->assertRedirect(route('users.index'));
        $this->assertSoftDeleted($staff);

        auth()->logout();
        $this->post('/login', ['login' => $staff->email, 'password' => 'password']);
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ roles & profile

    public function test_admin_can_grant_stock_in_to_staff(): void
    {
        $admin = User::factory()->admin()->create();
        $staffRole = Role::where('name', Role::STAFF)->firstOrFail();
        $ids = $staffRole->permissions()->pluck('permissions.id')
            ->push(Permission::where('name', 'stock.in')->value('id'))
            ->all();

        $this->actingAs($admin)->put("/roles/{$staffRole->id}", ['permissions' => $ids])
            ->assertRedirect(route('roles.index'));

        $staff = User::factory()->role(Role::STAFF)->create();
        $this->assertTrue($staff->hasPermission('stock.in'));
    }

    public function test_admin_role_cannot_be_edited(): void
    {
        $admin = User::factory()->admin()->create();
        $adminRole = Role::where('name', Role::ADMIN)->firstOrFail();

        $this->actingAs($admin)->put("/roles/{$adminRole->id}", ['permissions' => []])
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasPermission('settings.manage'));
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->role(Role::STAFF)->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
    }
}

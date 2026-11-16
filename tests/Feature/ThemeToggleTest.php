<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_has_the_theme_toggle_and_applies_the_saved_theme_early(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme-toggle', false)
            ->assertSee("localStorage.getItem('ims-theme')", false);
    }

    public function test_every_signed_in_page_has_the_theme_toggle(): void
    {
        $this->seed(RolePermissionSeeder::class);
        // fresh(): reload from the database like a real sign-in does, so every column
        // (e.g. last_login_at, shown on My Profile) is present on the model.
        $viewer = User::factory()->role('viewer')->create()->fresh();

        foreach (['dashboard', 'profile.edit'] as $route) {
            $this->actingAs($viewer)->get(route($route))
                ->assertOk()
                ->assertSee('data-theme-toggle', false)
                ->assertSee('data-bs-theme="light"', false);
        }
    }
}

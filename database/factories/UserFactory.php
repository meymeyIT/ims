<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 *
 * Used by automated tests (Phase 10):
 *   User::factory()->create();              // viewer
 *   User::factory()->role('staff')->create();
 *   User::factory()->admin()->create();
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'role_id' => fn () => $this->roleId(Role::VIEWER),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('0## ### ####'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(string $name): static
    {
        return $this->state(fn () => ['role_id' => $this->roleId($name)]);
    }

    public function admin(): static
    {
        return $this->role(Role::ADMIN);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    private function roleId(string $name): int
    {
        return Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)])->id;
    }
}

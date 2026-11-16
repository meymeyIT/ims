<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates one test account per non-admin role, for LOCAL TESTING ONLY.
 * Not called by DatabaseSeeder; run it manually:
 *   php artisan db:seed --class=DemoUserSeeder
 *
 * Password: DEMO_PASSWORD from .env, or "Password123" if not set.
 * Refuses to run in production.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoUserSeeder is disabled in production.');

            return;
        }

        $password = env('DEMO_PASSWORD', 'Password123');

        $accounts = [
            [Role::MANAGER, 'Demo Manager', 'manager', 'manager@ims.local'],
            [Role::STAFF, 'Demo Staff', 'staff', 'staff@ims.local'],
            [Role::VIEWER, 'Demo Viewer', 'viewer', 'viewer@ims.local'],
        ];

        foreach ($accounts as [$roleName, $name, $username, $email]) {
            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->fill([
                'role_id' => Role::where('name', $roleName)->value('id'),
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'is_active' => true,
            ]);
            $user->email_verified_at ??= now();
            $user->deleted_at = null;
            $user->save();

            $this->command?->info(str_pad(ucfirst($roleName), 8) . " username: {$username}  /  password: {$password}");
        }
    }
}

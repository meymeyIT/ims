<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first Admin account securely:
 * - credentials come from .env (ADMIN_EMAIL / ADMIN_PASSWORD), never hardcoded;
 * - if ADMIN_PASSWORD is empty, a strong random password is generated and shown ONCE;
 * - if the admin already exists, nothing is changed (the password is never reset).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('ims.admin.email');

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->command?->info("Admin account {$email} already exists - skipped.");

            return;
        }

        $password = config('ims.admin.password');
        $generated = blank($password);

        if ($generated) {
            $password = Str::password(16);
        }

        User::create([
            'role_id' => Role::where('name', Role::ADMIN)->value('id'),
            'name' => config('ims.admin.name'),
            'email' => $email,
            'password' => $password,          // hashed automatically by the User model cast
            'is_active' => true,
        ])->forceFill(['email_verified_at' => now()])->save();

        $this->command?->info("Admin account created: {$email}");

        if ($generated) {
            $this->command?->warn("Generated password (shown only once, save it now): {$password}");
        }
    }
}

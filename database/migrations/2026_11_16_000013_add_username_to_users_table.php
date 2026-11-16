<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds a unique username so users can sign in with "username or email".
 * Existing users get a username made from their email (admin@ims.local -> admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
        });

        // Backfill existing accounts with a unique username.
        $taken = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $base = Str::of(Str::before($user->email, '@'))->lower()->replaceMatches('/[^a-z0-9_.-]/', '')->limit(40, '')->value() ?: 'user';
            $username = $base;
            $i = 1;
            while (in_array($username, $taken, true)) {
                $username = $base . $i++;
            }
            $taken[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->change();
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};

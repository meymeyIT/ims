<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const ADMIN = 'admin';
    public const MANAGER = 'manager';
    public const STAFF = 'staff';
    public const VIEWER = 'viewer';

    protected $fillable = ['name', 'display_name', 'description'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $permission): bool
    {
        // Admin always has every permission, even ones added after seeding.
        if ($this->name === self::ADMIN) {
            return true;
        }

        return $this->permissions->contains('name', $permission);
    }
}

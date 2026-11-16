<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for user management.
 * Permission checks come from the role; the extra rules protect the system
 * from locking itself out (no self-delete, never remove the last active admin).
 */
class UserPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->hasPermission('users.view');
    }

    public function create(User $auth): bool
    {
        return $auth->hasPermission('users.create');
    }

    public function update(User $auth, User $user): bool
    {
        return $auth->hasPermission('users.update');
    }

    /** Activate / deactivate. */
    public function toggleStatus(User $auth, User $user): bool
    {
        return $auth->hasPermission('users.update')
            && ! $auth->is($user)
            && ! ($user->is_active && $user->isLastActiveAdmin());
    }

    public function delete(User $auth, User $user): bool
    {
        return $auth->hasPermission('users.delete')
            && ! $auth->is($user)
            && ! $user->isLastActiveAdmin();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Roles are fixed (Admin, Manager, Staff, Viewer); their permissions are editable.
 * Routes are protected with can:roles.view / can:roles.manage middleware.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('id')->get(),
            'totalPermissions' => Permission::count(),
        ]);
    }

    public function edit(Role $role): View|RedirectResponse
    {
        if ($role->name === Role::ADMIN) {
            return redirect()->route('roles.index')
                ->with('info', 'The Administrator role always has every permission and cannot be edited.');
        }

        $role->load('permissions');

        return view('roles.edit', [
            'role' => $role,
            'permissionsByModule' => Permission::orderBy('module')->orderBy('id')->get()->groupBy('module'),
            'granted' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === Role::ADMIN) {
            return redirect()->route('roles.index')->with('error', 'The Administrator role cannot be edited.');
        }

        $before = $role->permissions()->pluck('name')->all();
        $role->permissions()->sync($request->validated('permissions', []));
        $after = $role->permissions()->pluck('name')->all();

        ActivityLogger::log('updated', 'roles', "Updated permissions of role {$role->display_name}", $role, [
            'added' => array_values(array_diff($after, $before)),
            'removed' => array_values(array_diff($before, $after)),
        ]);

        return redirect()->route('roles.index')->with('success', "Permissions for {$role->display_name} were updated.");
    }
}

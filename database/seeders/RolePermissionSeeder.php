<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the 4 default roles and all permissions.
 * Safe to run again: it updates existing rows instead of duplicating them.
 * Note: re-running resets each role's permissions to these defaults.
 */
class RolePermissionSeeder extends Seeder
{
    /** module => [permission name => display name] */
    private const PERMISSIONS = [
        'dashboard' => [
            'dashboard.view' => 'View dashboard',
        ],
        'users' => [
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Edit users',
            'users.delete' => 'Delete / deactivate users',
        ],
        'roles' => [
            'roles.view' => 'View roles',
            'roles.manage' => 'Manage roles and permissions',
        ],
        'categories' => [
            'categories.view' => 'View categories',
            'categories.manage' => 'Create / edit / delete categories',
        ],
        'units' => [
            'units.view' => 'View units',
            'units.manage' => 'Create / edit / delete units',
        ],
        'products' => [
            'products.view' => 'View products and stock balances',
            'products.create' => 'Create products',
            'products.update' => 'Edit products',
            'products.delete' => 'Archive / delete products',
        ],
        'suppliers' => [
            'suppliers.view' => 'View suppliers',
            'suppliers.manage' => 'Create / edit / delete suppliers',
        ],
        'purchases' => [
            'purchases.view' => 'View purchases',
            'purchases.create' => 'Create and edit draft purchases',
            'purchases.approve' => 'Approve purchases',
            'purchases.receive' => 'Receive purchased products',
            'purchases.cancel' => 'Cancel purchases',
        ],
        'stock' => [
            'stock.view' => 'View stock history',
            'stock.in' => 'Record stock in',
            'stock.out' => 'Record stock out',
            'stock.adjust' => 'Make inventory adjustments',
            'stock.reverse' => 'Reverse stock transactions',
        ],
        'notifications' => [
            'notifications.view' => 'View low-stock notifications',
        ],
        'reports' => [
            'reports.view' => 'View reports',
            'reports.export' => 'Export reports (Excel / PDF)',
        ],
        'activity_logs' => [
            'activity_logs.view' => 'View activity logs',
        ],
        'settings' => [
            'settings.manage' => 'Manage system settings',
        ],
    ];

    /** role => [display name, description, permissions ('*' = all)] */
    private const ROLES = [
        Role::ADMIN => [
            'Administrator',
            'Full access to every module, users, roles and settings.',
            ['*'],
        ],
        Role::MANAGER => [
            'Manager',
            'Manages products, suppliers, purchases and inventory; generates reports.',
            [
                'dashboard.view',
                'categories.view', 'categories.manage',
                'units.view', 'units.manage',
                'products.view', 'products.create', 'products.update', 'products.delete',
                'suppliers.view', 'suppliers.manage',
                'purchases.view', 'purchases.create', 'purchases.approve', 'purchases.receive', 'purchases.cancel',
                'stock.view', 'stock.in', 'stock.out', 'stock.adjust', 'stock.reverse',
                'notifications.view',
                'reports.view', 'reports.export',
            ],
        ],
        Role::STAFF => [
            'Staff',
            'Records stock out and views products. Stock in only when an Admin grants "stock.in".',
            [
                'dashboard.view',
                'products.view',
                'stock.view', 'stock.out',
                'notifications.view',
            ],
        ],
        Role::VIEWER => [
            'Viewer',
            'Read-only: dashboard, products, stock balances and authorized reports.',
            [
                'dashboard.view',
                'products.view',
                'reports.view',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $module => $permissions) {
            foreach ($permissions as $name => $displayName) {
                Permission::updateOrCreate(
                    ['name' => $name],
                    ['display_name' => $displayName, 'module' => $module],
                );
            }
        }

        $allPermissionIds = Permission::pluck('id', 'name');

        foreach (self::ROLES as $name => [$displayName, $description, $permissions]) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                ['display_name' => $displayName, 'description' => $description],
            );

            $ids = $permissions === ['*']
                ? $allPermissionIds->values()
                : $allPermissionIds->only($permissions)->values();

            $role->permissions()->sync($ids);
        }

        $this->command?->info('Roles: ' . Role::count() . ', permissions: ' . Permission::count());
    }
}

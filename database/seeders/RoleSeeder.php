<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Roles and the capabilities each one carries.
     *
     * Capabilities are named here rather than spelled out as role-name string
     * literals at each call site. Authorization used to be expressed as
     * `hasAnyRole(['ferry_operator', 'admin'])` duplicated across two dozen
     * controller methods and six policies, which made renaming a role a silent,
     * codebase-wide authorization change.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        'visitor' => [],

        'hotel_manager' => [
            'hotels.manage',
            'bookings.manage',
            'promotions.manage',
        ],

        'ferry_operator' => [
            'fleet.manage',
            'ferry.schedules.manage',
            'ferry.tickets.handle',
            'promotions.manage',
        ],

        'themepark_staff' => [
            'park.events.manage',
            'park.tickets.handle',
            'park.reports.view',
            'promotions.manage',
        ],

        'admin' => [
            'hotels.manage',
            'bookings.manage',
            'fleet.manage',
            'ferry.schedules.manage',
            'ferry.tickets.handle',
            'park.events.manage',
            'park.tickets.handle',
            'park.reports.view',
            'promotions.manage',
            'map.manage',
            'users.manage',
        ],
    ];

    public function run(): void
    {
        foreach (array_unique(array_merge(...array_values(self::ROLE_PERMISSIONS))) as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findOrCreate($role)->syncPermissions($permissions);
        }

        // Roles and permissions are cached. A seeder run that leaves a stale cache
        // makes every check afterwards answer from the previous grants.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

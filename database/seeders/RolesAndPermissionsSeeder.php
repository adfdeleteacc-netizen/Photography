<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminPerms = [
            'manage users', 'manage subscriptions', 'configure commissions', 'handle disputes', 'verify kyc',
            'approve settlements', 'view analytics', 'manage coupons', 'export tax', 'monitor fraud',
        ];
        foreach ($adminPerms as $p) { Permission::findOrCreate($p, 'web'); }

        foreach (['customer', 'photographer', 'studio'] as $r) { Role::findOrCreate($r, 'web'); }
        Role::findOrCreate('admin', 'web')->syncPermissions($adminPerms);
    }
}

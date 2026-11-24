<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        // Bootstrap admin. Set ADMIN_EMAIL / ADMIN_PASSWORD in .env — there is deliberately no default password.
        $email = env('ADMIN_EMAIL');
        $pass = env('ADMIN_PASSWORD');
        if ($email && $pass) {
            $admin = User::firstOrCreate(['email' => $email], ['name' => 'Platform Admin', 'password' => $pass, 'referral_code' => 'ADMIN001', 'email_verified_at' => now()]);
            $admin->syncRoles('admin');
        }

        if (app()->environment('local') && class_exists(DemoSeeder::class)) {
            $this->call(DemoSeeder::class);
        }
    }
}

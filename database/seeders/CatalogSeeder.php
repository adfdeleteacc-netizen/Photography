<?php

namespace Database\Seeders;

use App\Models\{Category, SubscriptionPlan};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Wedding', 'Pre-wedding', 'Product shoot', 'Fashion', 'Corporate', 'Event'] as $c) {
            Category::firstOrCreate(['slug' => Str::slug($c)], ['name' => $c]);
        }

        // Prices in paise (₹499 = 49900). Adjust to your pricing strategy.
        $plans = [
            ['Starter', 'starter', 49900, 499000, 30, 5, 1, false, false, false, 14],
            ['Pro', 'pro', 149900, 1499000, 200, 25, 1, true, true, false, 0],
            ['Studio Enterprise', 'studio-enterprise', 499900, 4999000, null, null, 15, true, true, true, 0],
        ];
        foreach ($plans as [$name, $slug, $monthly, $annual, $uploads, $bookings, $team, $featured, $analytics, $studio, $trial]) {
            foreach (['monthly' => $monthly, 'annual' => $annual] as $interval => $price) {
                SubscriptionPlan::updateOrCreate(['slug' => "$slug-$interval"], [
                    'name' => $name, 'interval' => $interval, 'price_paise' => $price, 'trial_days' => $interval === 'monthly' ? $trial : 0,
                    'max_portfolio_uploads' => $uploads, 'max_bookings_per_month' => $bookings, 'max_team_members' => $studio ? $team : 1,
                    'featured_listing' => $featured, 'analytics_access' => $analytics, 'for_studios' => $studio, 'is_active' => true,
                ]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\{AvailabilitySlot, Category, PhotographerProfile, ServicePackage, Subscription, SubscriptionPlan, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Local-only demo data: 1 customer, 3 photographers (active subs, Mon–Sat 09:00–20:00, one package each). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::firstOrCreate(['email' => 'customer@example.test'], ['name' => 'Demo Customer', 'password' => 'Password123', 'referral_code' => 'DEMOCUST', 'email_verified_at' => now()]);
        $customer->syncRoles('customer');

        $plan = SubscriptionPlan::where('slug', 'pro-monthly')->first();
        $cats = Category::all();
        $cities = ['Mumbai', 'Delhi', 'Bengaluru'];

        foreach ($cities as $i => $city) {
            $u = User::firstOrCreate(['email' => "photographer{$i}@example.test"], [
                'name' => "Demo Photographer {$i}", 'password' => 'Password123',
                'referral_code' => 'DEMOPH' . $i, 'email_verified_at' => now(),
            ]);
            $u->syncRoles('photographer');

            $profile = PhotographerProfile::firstOrCreate(['user_id' => $u->id], [
                'display_name' => "Lens Studio {$i}", 'slug' => Str::slug("lens-studio-{$i}-{$city}"), 'bio' => 'Candid wedding and event photography.',
                'city' => $city, 'experience_years' => 5 + $i, 'starting_price_paise' => 2500000 + $i * 500000,
            ]);
            $profile->categories()->syncWithoutDetaching($cats->take(3)->pluck('id'));

            foreach ([1, 2, 3, 4, 5, 6] as $dow) {
                AvailabilitySlot::firstOrCreate(['photographer_profile_id' => $profile->id, 'day_of_week' => $dow], ['start_time' => '09:00:00', 'end_time' => '20:00:00']);
            }
            ServicePackage::firstOrCreate(['owner_type' => PhotographerProfile::class, 'owner_id' => $profile->id, 'title' => 'Full-day wedding coverage'], [
                'category_id' => $cats->first()->id, 'price_paise' => 4500000, 'duration_minutes' => 600,
            ]);
            Subscription::firstOrCreate(['user_id' => $u->id], [
                'subscription_plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth(),
            ]);
        }
    }
}

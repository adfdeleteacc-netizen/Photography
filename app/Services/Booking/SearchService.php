<?php

namespace App\Services\Booking;

use App\Models\{Booking, PhotographerProfile};
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Discovery. Filters: city, service type (category slug), budget, rating, availability, verified.
 * Only creators with a currently-active subscription are listed. Featured creators rank first.
 * Results are cached briefly per unique filter set (tag-capable store such as Redis required).
 */
class SearchService
{
    public function __construct(private AvailabilityService $availability) {}

    public function search(array $f, int $perPage = 20): LengthAwarePaginator
    {
        // Run fresh searches to avoid cached paginator serialization issues.
        return $this->query($f, $perPage);
    }

    /** Tagged cache when the store supports it (Redis/Memcached); plain cache otherwise. */
    private function cache()
    {
        return Cache::getStore() instanceof \Illuminate\Cache\TaggableStore ? Cache::tags(['search']) : Cache::store();
    }

    public static function flush(): void
    {
        Cache::getStore() instanceof \Illuminate\Cache\TaggableStore ? Cache::tags(['search'])->flush() : null;
    }

    private function query(array $f, int $perPage): LengthAwarePaginator
    {
        $q = PhotographerProfile::query()
            ->select('photographer_profiles.*')
            ->with(['categories:id,name,slug', 'studio:id,name,slug'])
            ->whereHas('user.subscriptions', fn ($s) => $s->whereIn('status', ['trialing', 'active', 'past_due'])
                ->where(fn ($w) => $w->where('ends_at', '>=', now())->orWhere('grace_ends_at', '>=', now())))
            ->when($f['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            ->when($f['service_type'] ?? null, fn ($q, $v) => $q->whereHas('categories', fn ($c) => $c->where('slug', $v)))
            ->when($f['min_budget'] ?? null, fn ($q, $v) => $q->where('starting_price_paise', '>=', $v * 100))
            ->when($f['max_budget'] ?? null, fn ($q, $v) => $q->where('starting_price_paise', '<=', $v * 100))
            ->when($f['min_rating'] ?? null, fn ($q, $v) => $q->where('rating_avg', '>=', $v))
            ->when(! empty($f['verified']), fn ($q) => $q->where('is_verified', true))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('display_name', 'like', "%{$v}%")->orWhere('bio', 'like', "%{$v}%")))
            ->when(! empty($f['date']), function ($q) use ($f) {
                $day = \Carbon\Carbon::parse($f['date']);
                // Works that weekday and isn't blocked for the entire day. The exact slot is
                // re-validated under a row lock at booking time, so this is a discovery hint only.
                $q->whereHas('availabilitySlots', fn ($s) => $s->where('is_active', true)->where('day_of_week', $day->dayOfWeek))
                  ->whereDoesntHave('blockedDates', fn ($b) => $b->where('starts_at', '<=', $day->copy()->startOfDay())->where('ends_at', '>=', $day->copy()->endOfDay()));
            })
            ->orderByDesc('is_featured')->orderByDesc('rating_avg')->orderByDesc('rating_count');

        return $q->paginate($perPage)->withQueryString();
    }
}

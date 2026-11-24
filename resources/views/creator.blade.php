@extends('layouts.marketplace')
@section('title', $creator->display_name . ' — ' . $creator->city)
@section('meta_description', \Illuminate\Support\Str::limit($creator->bio, 150))

@push('head')
<script type="application/ld+json">
{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $creator->display_name,
   'address' => ['@type' => 'PostalAddress', 'addressLocality' => $creator->city],
   'aggregateRating' => $creator->rating_count ? ['@type' => 'AggregateRating', 'ratingValue' => $creator->rating_avg, 'reviewCount' => $creator->rating_count] : null]), JSON_UNESCAPED_SLASHES) !!}
</script>
@endpush

@section('content')
@php
    $pkgs = $creator->packages->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'price' => $p->price_paise, 'mins' => $p->duration_minutes])->values();
@endphp
<div class="grid lg:grid-cols-3 gap-8"
     x-data="{
        pkg: {{ $creator->packages->first()?->id ?? 'null' }},
        starts_at: '', location: '', notes: '', coupon: '', points: 0, busy: false, done: false,
        packages: @js($pkgs),
        get sel() { return this.packages.find(p => p.id == this.pkg) },
        async submit() {
            this.busy = true;
            const r = await api('post', '/api/bookings', {
                service_package_id: this.pkg, photographer_profile_id: {{ $creator->id }}, starts_at: this.starts_at,
                location: this.location || null, notes: this.notes || null,
                coupon_code: this.coupon || null, loyalty_points: Number(this.points) || 0,
            });
            this.busy = false;
            if (r) { this.done = true; notifyOk('Request sent! The creator will respond soon.'); setTimeout(() => location.href = '{{ route('customer.bookings') }}', 1200); }
        }
     }">

    {{-- left: profile --}}
    <div class="lg:col-span-2">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">{{ $creator->display_name }}
                    @if($creator->is_verified)<span class="badge bg-emerald-100 text-emerald-700 align-middle">✓ Verified</span>@endif
                </h1>
                <p class="text-slate-500">{{ $creator->city }} · {{ $creator->experience_years }} yrs · ⭐ {{ number_format($creator->rating_avg, 1) }} ({{ $creator->rating_count }} reviews)</p>
            </div>
        </div>
        <p class="mt-4 text-slate-700">{{ $creator->bio }}</p>
        <div class="mt-3 flex flex-wrap gap-1">@foreach($creator->categories as $cat)<span class="badge bg-slate-100 text-slate-600">{{ $cat->name }}</span>@endforeach</div>

        <h2 class="mt-8 mb-3 font-semibold text-lg">Portfolio</h2>
        @php $shown = false; @endphp
        @foreach($creator->portfolios->where('is_published', true) as $pf)
            @php $thumbs = $pf->media->where('is_private', false)->whereNotNull('thumbnail_path'); @endphp
            @if($thumbs->count()) @php $shown = true; @endphp
                <h3 class="mt-4 font-medium">{{ $pf->title }} <span class="text-xs text-slate-400">{{ ucfirst(str_replace('_', ' ', $pf->type)) }}</span></h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                    @foreach($thumbs as $m)
                        <img loading="lazy" class="rounded-lg aspect-square object-cover" alt="{{ $pf->title }}"
                             src="{{ Storage::disk(config('marketplace.media.disk'))->url($m->thumbnail_path) }}">
                    @endforeach
                </div>
            @endif
        @endforeach
        @unless($shown)<p class="text-sm text-slate-400">This creator hasn't published photos yet.</p>@endunless

        <h2 class="mt-8 mb-3 font-semibold text-lg">Reviews</h2>
        @forelse($creator->reviews->where('is_visible', true) as $rv)
            <div class="card p-4 mb-2">
                <div class="flex justify-between text-sm"><span>{{ str_repeat('⭐', $rv->rating) }}</span><span class="text-slate-400">{{ $rv->customer->name }}</span></div>
                <p class="text-sm mt-1">{{ $rv->body }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-400">No reviews yet.</p>
        @endforelse
    </div>

    {{-- right: booking card --}}
    <aside class="lg:sticky lg:top-20 self-start card p-5">
        <h2 class="font-semibold text-lg mb-3">Request a booking</h2>

        @guest
            <p class="text-sm text-slate-600">Log in as a customer to book.</p>
            <a href="{{ route('login') }}" class="btn-primary w-full mt-3">Log in</a>
        @else
            @role('customer')
                <template x-if="!done">
                <div class="space-y-3">
                    <div>
                        <label class="label">Package</label>
                        <select class="input" x-model="pkg">
                            <template x-for="p in packages" :key="p.id"><option :value="p.id" x-text="p.title + ' — ' + inrPaise(p.price)"></option></template>
                        </select>
                        <p class="text-xs text-slate-400 mt-1" x-show="sel" x-text="'Duration ≈ ' + (sel.mins/60).toFixed(1) + ' hours'"></p>
                    </div>
                    <div><label class="label">Date & start time</label><input type="datetime-local" class="input" x-model="starts_at"></div>
                    <div><label class="label">Location</label><input class="input" x-model="location" placeholder="Venue / address"></div>
                    <div><label class="label">Notes</label><textarea rows="2" class="input" x-model="notes" placeholder="Anything the creator should know"></textarea></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label">Coupon</label><input class="input uppercase" x-model="coupon" placeholder="CODE"></div>
                        <div><label class="label">Loyalty points</label><input type="number" min="0" class="input" x-model="points"></div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-sm" x-show="sel">
                        <div class="flex justify-between"><span>Package price</span><b x-text="inrPaise(sel.price)"></b></div>
                        <p class="text-xs text-slate-500 mt-1">Coupon / points discounts are applied when the creator accepts. You only pay after acceptance.</p>
                    </div>
                    <button class="btn-primary w-full" :disabled="busy || !starts_at || !pkg" @click="submit()" x-text="busy ? 'Sending…' : 'Send request'"></button>
                </div>
                </template>
                <p x-show="done" class="text-emerald-700 text-sm">✅ Request sent. Redirecting to your bookings…</p>
            @else
                <p class="text-sm text-slate-500">Only customer accounts can book.</p>
            @endrole
        @endguest
    </aside>
</div>
@endsection

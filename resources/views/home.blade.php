@extends('layouts.marketplace')
@section('title', 'Find photographers & studios')

@section('content')
<section class="rounded-3xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white px-6 py-10 md:px-12 md:py-14 mb-8">
    <h1 class="text-3xl md:text-4xl font-bold">Book the perfect photographer</h1>
    <p class="mt-2 text-indigo-100 max-w-xl">Verified creators, transparent pricing, secure payments. Weddings, events, products and more.</p>

    <form method="GET" class="mt-6 grid gap-3 md:grid-cols-12 bg-white rounded-2xl p-3 text-slate-800">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search creators" class="input md:col-span-4">
        <input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="City" class="input md:col-span-3">
        <select name="service_type" class="input md:col-span-3">
            <option value="">All services</option>
            @foreach($categories as $c)
                <option value="{{ $c->slug }}" @selected(($filters['service_type'] ?? '') === $c->slug)>{{ $c->name }}</option>
            @endforeach
        </select>
        <button class="btn-primary md:col-span-2">Search</button>
        <label class="text-sm flex items-center gap-2 md:col-span-12 text-slate-600">
            <input type="checkbox" name="verified" value="1" @checked(!empty($filters['verified']))> Verified creators only
        </label>
    </form>
</section>

<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
@forelse($creators as $c)
    <a href="{{ route('creator.show', $c->slug) }}" class="card p-5 hover:shadow-md hover:-translate-y-0.5 transition">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h3 class="font-semibold">{{ $c->display_name }}</h3>
                <p class="text-sm text-slate-500">{{ $c->city }} · {{ $c->experience_years }} yrs experience</p>
            </div>
            <div class="flex flex-col items-end gap-1">
                @if($c->is_verified)<span class="badge bg-emerald-100 text-emerald-700">✓ Verified</span>@endif
                @if($c->is_featured)<span class="badge bg-amber-100 text-amber-800">Featured</span>@endif
            </div>
        </div>
        <p class="mt-3 text-sm text-slate-600 line-clamp-2">{{ $c->bio }}</p>
        <div class="mt-3 flex flex-wrap gap-1">
            @foreach($c->categories as $cat)<span class="badge bg-slate-100 text-slate-600">{{ $cat->name }}</span>@endforeach
        </div>
        <div class="mt-4 flex justify-between text-sm border-t pt-3">
            <span>⭐ {{ number_format($c->rating_avg, 1) }} <span class="text-slate-400">({{ $c->rating_count }})</span></span>
            <span class="font-medium">from ₹{{ number_format($c->starting_price_paise / 100) }}</span>
        </div>
    </a>
@empty
    <p class="col-span-full text-center text-slate-500 py-16">No creators match your filters.</p>
@endforelse
</div>
<div class="mt-8">{{ $creators->links() }}</div>
@endsection

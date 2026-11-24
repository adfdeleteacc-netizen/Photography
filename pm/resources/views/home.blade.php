@extends('layouts.marketplace')
@section('title', 'Find photographers & studios')

@section('content')
<form method="GET" class="grid gap-3 md:grid-cols-5 bg-white p-4 rounded-xl border mb-8">
    <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search creators" class="border rounded px-3 py-2 md:col-span-2">
    <input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="City" class="border rounded px-3 py-2">
    <select name="service_type" class="border rounded px-3 py-2">
        <option value="">All services</option>
        @foreach($categories as $c)
            <option value="{{ $c->slug }}" @selected(($filters['service_type'] ?? '') === $c->slug)>{{ $c->name }}</option>
        @endforeach
    </select>
    <button class="bg-indigo-600 text-white rounded px-3 py-2">Search</button>
    <label class="text-sm flex items-center gap-2 md:col-span-5"><input type="checkbox" name="verified" value="1" @checked(!empty($filters['verified']))> Verified creators only</label>
</form>

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
@forelse($creators as $c)
    <a href="{{ route('creator.show', $c->slug) }}" class="bg-white rounded-xl border p-5 hover:shadow">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold">{{ $c->display_name }}</h3>
            @if($c->is_verified)<span class="text-xs bg-emerald-100 text-emerald-700 rounded px-2 py-0.5">Verified</span>@endif
        </div>
        <p class="text-sm text-slate-500">{{ $c->city }} · {{ $c->experience_years }} yrs</p>
        <p class="mt-2 text-sm line-clamp-2">{{ $c->bio }}</p>
        <div class="mt-3 flex justify-between text-sm">
            <span>★ {{ number_format($c->rating_avg, 1) }} ({{ $c->rating_count }})</span>
            <span>from ₹{{ number_format($c->starting_price_paise / 100) }}</span>
        </div>
    </a>
@empty
    <p class="col-span-full text-center text-slate-500">No creators match your filters.</p>
@endforelse
</div>
<div class="mt-8">{{ $creators->links() }}</div>
@endsection

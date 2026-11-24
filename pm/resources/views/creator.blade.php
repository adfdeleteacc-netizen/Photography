@extends('layouts.marketplace')
@section('title', $creator->display_name . ' — ' . $creator->city)
@section('meta_description', \Illuminate\Support\Str::limit($creator->bio, 150))

@section('content')
<script type="application/ld+json">
{!! json_encode(['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $creator->display_name,
   'address' => ['@type' => 'PostalAddress', 'addressLocality' => $creator->city],
   'aggregateRating' => $creator->rating_count ? ['@type' => 'AggregateRating', 'ratingValue' => $creator->rating_avg, 'reviewCount' => $creator->rating_count] : null], JSON_UNESCAPED_SLASHES) !!}
</script>

<h1 class="text-2xl font-bold">{{ $creator->display_name }}</h1>
<p class="text-slate-500">{{ $creator->city }} · ★ {{ number_format($creator->rating_avg, 1) }} ({{ $creator->rating_count }} reviews)</p>
<p class="mt-4 max-w-2xl">{{ $creator->bio }}</p>

<h2 class="mt-8 mb-3 font-semibold">Packages</h2>
<div class="grid gap-4 md:grid-cols-3">
@foreach($creator->packages as $p)
    <div class="bg-white border rounded-xl p-4">
        <h3 class="font-medium">{{ $p->title }}</h3>
        <p class="text-sm text-slate-500">{{ intdiv($p->duration_minutes, 60) }} hrs</p>
        <p class="mt-2 font-semibold">₹{{ number_format($p->price_paise / 100) }}</p>
    </div>
@endforeach
</div>

<h2 class="mt-8 mb-3 font-semibold">Portfolio</h2>
@foreach($creator->portfolios->where('is_published', true) as $pf)
    <h3 class="mt-4 font-medium">{{ $pf->title }} <span class="text-xs text-slate-400">{{ ucfirst(str_replace('_', ' ', $pf->type)) }}</span></h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
        @foreach($pf->media->where('is_private', false)->whereNotNull('thumbnail_path') as $m)
            <img loading="lazy" class="rounded aspect-square object-cover"
                 src="{{ Storage::disk(config('marketplace.media.disk'))->url($m->thumbnail_path) }}" alt="{{ $pf->title }}">
        @endforeach
    </div>
@endforeach

<h2 class="mt-8 mb-3 font-semibold">Reviews</h2>
@forelse($creator->reviews->where('is_visible', true) as $rv)
    <div class="bg-white border rounded-lg p-3 mb-2"><span>{{ str_repeat('★', $rv->rating) }}</span> <span class="text-sm text-slate-500">{{ $rv->customer->name }}</span><p class="text-sm">{{ $rv->body }}</p></div>
@empty
    <p class="text-slate-500 text-sm">No reviews yet.</p>
@endforelse
@endsection

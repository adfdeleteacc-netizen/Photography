@extends('layouts.marketplace')
@section('title', 'My bookings')

@section('content')
<div x-data="{ rows: [] }" x-init="axios.get('/api/my-bookings').then(r => rows = r.data.data)">
    <h1 class="text-xl font-semibold mb-4">My bookings</h1>
    <ul class="space-y-2">
        <template x-for="b in rows" :key="b.id">
            <li class="bg-white border rounded p-4 flex justify-between text-sm">
                <span><b x-text="b.photographer?.display_name"></b> · <span x-text="b.package?.title"></span></span>
                <span><span x-text="new Date(b.starts_at).toLocaleDateString('en-IN')"></span> · <span class="uppercase text-xs" x-text="b.status.replace('_',' ')"></span> · <span x-text="inr(b.total_amount_paise/100)"></span></span>
            </li>
        </template>
    </ul>
</div>
@endsection

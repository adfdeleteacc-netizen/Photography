<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\AvailabilityRequest;
use App\Models\BlockedDate;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

class AvailabilityController extends Controller
{
    public function show(Request $r): JsonResponse
    {
        $p = $r->user()->photographerProfile ?? abort(404);
        return response()->json(['slots' => $p->availabilitySlots, 'blocked' => $p->blockedDates()->where('ends_at', '>=', now())->get()]);
    }

    public function store(AvailabilityRequest $r): JsonResponse
    {
        $p = $r->user()->photographerProfile ?? abort(422, 'Create your profile first.');

        DB::transaction(function () use ($r, $p) {
            if ($r->has('slots')) {
                $p->availabilitySlots()->delete();
                $p->availabilitySlots()->createMany(collect($r->slots)->map(fn ($s) => $s + ['is_active' => true])->all());
            }
            foreach ($r->input('blocked', []) as $b) {
                // Creators can't block a window already held by a booking — that would hide a conflict.
                abort_if($p->bookings()->whereIn('status', ['pending_payment', 'confirmed'])->where('starts_at', '<', $b['ends_at'])->where('ends_at', '>', $b['starts_at'])->exists(), 409, 'A booking already exists in that window.');
                $p->blockedDates()->create($b);
            }
        });
        return $this->show($r);
    }

    public function unblock(Request $r, BlockedDate $blocked): JsonResponse
    {
        abort_unless($blocked->photographer_profile_id === $r->user()->photographerProfile?->id, 403);
        abort_if($blocked->booking_id, 409, 'Dates locked by a confirmed booking cannot be unblocked.');   // "lock dates after confirmation"
        $blocked->delete();
        return response()->json(null, 204);
    }
}

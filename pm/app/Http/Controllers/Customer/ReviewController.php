<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreReviewRequest;
use App\Models\{Booking, Review};
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $r): JsonResponse
    {
        $booking = Booking::findOrFail($r->booking_id);
        $this->authorize('review', $booking);   // owner + completed + no existing review (DB unique index backs this up)

        $review = DB::transaction(function () use ($r, $booking) {
            $review = Review::create([
                'booking_id' => $booking->id, 'customer_id' => $r->user()->id,
                'photographer_profile_id' => $booking->photographer_profile_id,
                'rating' => $r->rating, 'body' => $r->body,
            ]);
            // Recompute aggregates from source of truth to avoid drift.
            $agg = Review::where('photographer_profile_id', $booking->photographer_profile_id)->where('is_visible', true)
                ->selectRaw('AVG(rating) a, COUNT(*) c')->first();
            $booking->photographer->update(['rating_avg' => round($agg->a, 2), 'rating_count' => $agg->c]);
            return $review;
        });
        \App\Services\Booking\SearchService::flush();   // ratings changed → drop cached discovery results
        return response()->json($review, 201);
    }
}

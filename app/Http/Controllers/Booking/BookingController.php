<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\{CancelBookingRequest, StoreBookingRequest};
use App\Models\Booking;
use App\Services\Booking\BookingService;
use Illuminate\Http\{JsonResponse, Request};

/** Thin: authorise → delegate to BookingService → return JSON. */
class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function store(StoreBookingRequest $r): JsonResponse
    {
        return response()->json($this->bookings->createInquiry($r->user(), $r->validated()), 201);
    }

    public function mine(Request $r): JsonResponse
    {
        return response()->json(
            Booking::with(['photographer:id,display_name,slug', 'package:id,title', 'payment', 'review:id,booking_id,rating'])
                ->where('customer_id', $r->user()->id)->latest()->paginate(20)
        );
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);
        return response()->json($booking->load('statusLogs', 'package', 'photographer', 'payment', 'conversation'));
    }

    public function accept(Request $r, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);
        return response()->json($this->bookings->accept($booking, $r->user()));
    }

    public function reject(CancelBookingRequest $r, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);
        return response()->json($this->bookings->reject($booking, $r->user(), $r->reason));
    }

    public function complete(Request $r, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);
        return response()->json($this->bookings->complete($booking, $r->user()));
    }

    public function cancel(CancelBookingRequest $r, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);
        return response()->json($this->bookings->cancel($booking, $r->user(), $r->reason));
    }

    public function assign(Request $r, Booking $booking): JsonResponse
    {
        $this->authorize('manage', $booking);
        $data = $r->validate(['photographer_profile_id' => 'required|exists:photographer_profiles,id']);
        // Studio assignment: the target must belong to the same studio.
        abort_unless($booking->studio_id && \App\Models\PhotographerProfile::whereKey($data['photographer_profile_id'])->where('studio_id', $booking->studio_id)->exists(), 422, 'Photographer is not in this studio.');
        $booking->update(['assigned_to' => \App\Models\PhotographerProfile::find($data['photographer_profile_id'])->user_id]);
        return response()->json($booking);
    }
}

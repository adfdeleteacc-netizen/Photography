<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Conversation};
use Illuminate\Http\{JsonResponse, Request};

/** Booking-scoped chat. A conversation only exists once an inquiry/booking exists. */
class MessageController extends Controller
{
    public function index(Request $r, Booking $booking): JsonResponse
    {
        $conv = $this->conversationFor($r, $booking);
        $conv->messages()->where('sender_id', '!=', $r->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json($conv->messages()->with('sender:id,name')->latest('id')->paginate(50));
    }

    public function store(Request $r, Booking $booking): JsonResponse
    {
        $conv = $this->conversationFor($r, $booking);
        abort_if(in_array($booking->status, [Booking::CANCELLED, Booking::REFUNDED], true), 403, 'This conversation is closed.');

        $data = $r->validate(['body' => 'required|string|max:2000']);
        $body = $this->maskContactDetails($data['body']);   // keep payments on-platform
        $msg = $conv->messages()->create(['sender_id' => $r->user()->id, 'body' => $body]);
        $conv->update(['last_message_at' => now()]);

        // broadcast(new \App\Events\MessageSent($msg))->toOthers();   // enable with Echo + Pusher
        return response()->json($msg, 201);
    }

    private function conversationFor(Request $r, Booking $booking): Conversation
    {
        $this->authorize('view', $booking);
        return Conversation::where('booking_id', $booking->id)->firstOrFail();
    }

    private function maskContactDetails(string $text): string
    {
        $text = preg_replace('/\b[\w.+-]+@[\w-]+\.[\w.]+\b/', '[email hidden]', $text);
        return preg_replace('/(?:\+?91[\s-]?)?[6-9]\d{4}[\s-]?\d{5}\b/', '[phone hidden]', $text);
    }
}

<?php

namespace App\Services\Notification;

use App\Models\Booking;
use App\Notifications\BookingNotification;

class NotificationService
{
    public function bookingRequested(Booking $b): void
    {
        $b->loadMissing('photographer.user');
        $b->photographer->user->notify(new BookingNotification($b, 'New booking request', "You have a new request for {$b->starts_at->format('d M Y, h:i A')}."));
    }

    public function bookingAccepted(Booking $b): void
    {
        $b->customer->notify(new BookingNotification($b, 'Request accepted — pay to confirm', 'Complete payment within ' . config('marketplace.payment_hold_minutes') . ' minutes to lock your date.'));
    }

    public function bookingCancelled(Booking $b): void
    {
        $b->loadMissing('photographer.user');
        foreach ([$b->customer, $b->photographer->user] as $u) {
            $u->notify(new BookingNotification($b, 'Booking cancelled', "Booking {$b->reference} was cancelled."));
        }
    }
}

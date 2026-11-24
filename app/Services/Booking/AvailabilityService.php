<?php

namespace App\Services\Booking;

use App\Exceptions\MarketplaceException;
use App\Models\{AvailabilitySlot, BlockedDate, Booking, PhotographerProfile};
use Carbon\CarbonInterface;

class AvailabilityService
{
    /** Booking statuses that occupy the calendar. */
    private const HOLDING = [Booking::PENDING_PAYMENT, Booking::CONFIRMED];

    public function isAvailable(int $profileId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreBookingId = null): bool
    {
        return $this->withinWorkingHours($profileId, $start, $end)
            && ! $this->overlapsBlock($profileId, $start, $end)
            && ! $this->overlapsBooking($profileId, $start, $end, $ignoreBookingId);
    }

    /**
     * Call INSIDE a DB transaction. Locks the photographer row so two concurrent
     * requests for the same creator serialize and cannot both pass the overlap check.
     */
    public function assertAvailableAndLock(int $profileId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreBookingId = null): PhotographerProfile
    {
        $profile = PhotographerProfile::whereKey($profileId)->lockForUpdate()->firstOrFail();

        if (! $this->withinWorkingHours($profileId, $start, $end)) {
            throw new MarketplaceException('The creator does not work during the requested time.', 'outside_working_hours', 422);
        }
        if ($this->overlapsBlock($profileId, $start, $end) || $this->overlapsBooking($profileId, $start, $end, $ignoreBookingId)) {
            throw new MarketplaceException('That slot is no longer available.', 'slot_unavailable', 409);
        }
        return $profile;
    }

    /** Lock the dates once a booking is confirmed so manual edits can't free them silently. */
    public function lockForBooking(Booking $b): void
    {
        BlockedDate::create([
            'photographer_profile_id' => $b->photographer_profile_id,
            'starts_at' => $b->starts_at, 'ends_at' => $b->ends_at,
            'reason' => 'Booking ' . $b->reference, 'booking_id' => $b->id,
        ]);
    }

    public function unlockForBooking(Booking $b): void
    {
        BlockedDate::where('booking_id', $b->id)->delete();
    }

    private function withinWorkingHours(int $profileId, CarbonInterface $start, CarbonInterface $end): bool
    {
        if (! $start->isSameDay($end)) {
            return false; // multi-day shoots: model as consecutive bookings or extend slots
        }
        $slots = AvailabilitySlot::where('photographer_profile_id', $profileId)
            ->where('is_active', true)->where('day_of_week', $start->dayOfWeek)->get();

        if ($slots->isEmpty()) {
            return false;
        }
        $s = $start->format('H:i:s');
        $e = $end->format('H:i:s');
        return $slots->contains(fn ($slot) => $slot->start_time <= $s && $slot->end_time >= $e);
    }

    private function overlapsBlock(int $profileId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return BlockedDate::where('photographer_profile_id', $profileId)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
    }

    private function overlapsBooking(int $profileId, CarbonInterface $start, CarbonInterface $end, ?int $ignore): bool
    {
        return Booking::where('photographer_profile_id', $profileId)
            ->whereIn('status', self::HOLDING)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->where(function ($q) {
                // an unpaid hold that has expired no longer blocks the calendar
                $q->where('status', Booking::CONFIRMED)->orWhere('payment_due_at', '>', now());
            })
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
    }
}

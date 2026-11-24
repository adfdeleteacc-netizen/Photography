<?php

namespace App\Policies;

use App\Models\{Booking, User};

class BookingPolicy
{
    public function before(User $u): ?bool { return $u->hasRole('admin') ? true : null; }

    public function view(User $u, Booking $b): bool
    {
        return $b->customer_id === $u->id || $this->isCreatorSide($u, $b);
    }

    /** accept / reject / complete: only the creator side. */
    public function manage(User $u, Booking $b): bool { return $this->isCreatorSide($u, $b); }

    public function cancel(User $u, Booking $b): bool { return $this->view($u, $b); }

    public function pay(User $u, Booking $b): bool { return $b->customer_id === $u->id; }

    public function review(User $u, Booking $b): bool
    {
        return $b->customer_id === $u->id && $b->status === Booking::COMPLETED && ! $b->review()->exists();
    }

    private function isCreatorSide(User $u, Booking $b): bool
    {
        $b->loadMissing('photographer', 'studio');
        return $b->photographer->user_id === $u->id
            || $b->studio?->owner_id === $u->id
            || $b->assigned_to === $u->id
            || ($b->studio && $b->studio->members()->where('user_id', $u->id)->where('role', 'manager')->where('is_active', true)->exists());
    }
}

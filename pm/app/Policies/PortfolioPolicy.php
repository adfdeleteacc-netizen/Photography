<?php

namespace App\Policies;

use App\Models\{Portfolio, User};

class PortfolioPolicy
{
    public function before(User $u): ?bool { return $u->hasRole('admin') ? true : null; }

    public function update(User $u, Portfolio $p): bool
    {
        $owner = $p->owner;
        return ($owner instanceof \App\Models\PhotographerProfile && $owner->user_id === $u->id)
            || ($owner instanceof \App\Models\Studio && $owner->owner_id === $u->id);
    }

    public function delete(User $u, Portfolio $p): bool { return $this->update($u, $p); }
}

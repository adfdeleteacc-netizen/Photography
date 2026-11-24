<?php

namespace App\Policies;

use App\Models\{Studio, User};

class StudioPolicy
{
    public function manage(User $u, Studio $s): bool
    {
        return $u->hasRole('admin') || $s->owner_id === $u->id
            || $s->members()->where('user_id', $u->id)->where('role', 'manager')->where('is_active', true)->exists();
    }
}

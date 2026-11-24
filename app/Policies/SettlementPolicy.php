<?php

namespace App\Policies;

use App\Models\{Settlement, User};

class SettlementPolicy
{
    public function view(User $u, Settlement $s): bool { return $u->hasRole('admin') || $s->user_id === $u->id; }
    public function approve(User $u, Settlement $s): bool { return $u->can('approve settlements'); }
}

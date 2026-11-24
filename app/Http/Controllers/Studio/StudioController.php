<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\{PhotographerProfile, Studio, StudioMember, User};
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\{JsonResponse, Request};

class StudioController extends Controller
{
    public function addMember(Request $r, Studio $studio): JsonResponse
    {
        $this->authorize('manage', $studio);
        $data = $r->validate(['email' => 'required|email|exists:users,email', 'role' => 'required|in:manager,photographer,assistant']);

        $plan = $studio->owner->activeSubscription()?->plan;
        abort_if($plan?->max_team_members !== null && $studio->members()->where('is_active', true)->count() >= $plan->max_team_members, 402, 'Team member limit reached for your plan.');

        $user = User::where('email', $data['email'])->firstOrFail();
        $member = StudioMember::updateOrCreate(['studio_id' => $studio->id, 'user_id' => $user->id], ['role' => $data['role'], 'is_active' => true]);

        if ($data['role'] === 'photographer' && ($p = $user->photographerProfile)) {
            $p->update(['studio_id' => $studio->id]);
        }
        return response()->json($member, 201);
    }

    public function removeMember(Studio $studio, StudioMember $member): JsonResponse
    {
        $this->authorize('manage', $studio);
        abort_unless($member->studio_id === $studio->id, 404);
        PhotographerProfile::where('user_id', $member->user_id)->where('studio_id', $studio->id)->update(['studio_id' => null]);
        $member->update(['is_active' => false]);
        return response()->json(null, 204);
    }

    public function team(Studio $studio): JsonResponse
    {
        $this->authorize('manage', $studio);
        return response()->json($studio->members()->with('user:id,name,email')->get());
    }
}

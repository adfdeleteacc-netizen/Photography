<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks creator features without an active (trial / paid / in-grace) subscription.
 * Applied to booking-related creator routes; subscription & billing routes stay reachable
 * so a lapsed creator can always pay.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sub = $user?->activeSubscription();

        if (! $sub) {
            return $request->expectsJson()
                ? response()->json(['message' => 'An active subscription is required.', 'code' => 'subscription_required'], 402)
                : redirect()->route('subscription.plans')->with('error', 'Please subscribe to continue.');
        }

        // Surface grace-period state so the UI can nag.
        if ($sub->status === 'past_due') {
            $request->attributes->set('subscription_in_grace', true);
            $response = $next($request);
            $response->headers->set('X-Subscription-Grace-Ends', $sub->grace_ends_at?->toIso8601String());
            return $response;
        }
        return $next($request);
    }
}

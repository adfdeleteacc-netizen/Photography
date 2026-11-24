<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isKycVerified()) {
            return response()->json(['message' => 'KYC verification is required.', 'code' => 'kyc_required'], 403);
        }
        return $next($request);
    }
}

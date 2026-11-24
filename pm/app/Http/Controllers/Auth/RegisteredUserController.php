<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Replaces Breeze's controller so web sign-ups get a role, referral code, loyalty wallet and referral link. */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, AccountService $accounts): RedirectResponse
    {
        $user = $accounts->register($request->validated());
        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\{KycRequest, LoginRequest, RegisterRequest};
use App\Models\User;
use App\Services\Auth\AccountService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Hash};
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->accounts->register($request->validated());
        return response()->json(['user' => $user->only('id', 'name', 'email'), 'token' => $user->createToken('api')->plainTextToken, 'next' => 'verify_email'], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();
        // Same message for unknown email and wrong password (no user enumeration).
        if (! $user || ! Hash::check($request->password, $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        return response()->json(['token' => $user->createToken('api')->plainTextToken, 'roles' => $user->getRoleNames()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['ok' => true]);
    }

    public function submitKyc(KycRequest $request): JsonResponse
    {
        $this->accounts->submitKyc($request->user(), $request->validated(), $request->file('pan_file'), $request->file('identity_file'));
        return response()->json(['status' => 'pending'], 202);
    }
}

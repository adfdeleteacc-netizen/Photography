<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^[6-9][0-9]{9}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()],
            'role' => ['required', 'in:customer,photographer,studio'],   // admin can never self-register
            'referral_code' => ['nullable', 'exists:users,referral_code'],
        ];
    }
}

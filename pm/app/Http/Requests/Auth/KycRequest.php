<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class KycRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole(['photographer', 'studio']) ?? false; }

    public function rules(): array
    {
        return [
            'pan' => ['required', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'pan_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'identity_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'bank.account_holder' => ['required', 'string', 'max:120'],
            'bank.account_number' => ['required', 'digits_between:9,18'],
            'bank.ifsc' => ['required', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
        ];
    }
}

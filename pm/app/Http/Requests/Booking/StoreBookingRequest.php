<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRole('customer') ?? false; }

    public function rules(): array
    {
        return [
            'service_package_id' => ['required', 'exists:service_packages,id'],
            'photographer_profile_id' => ['required', 'exists:photographer_profiles,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

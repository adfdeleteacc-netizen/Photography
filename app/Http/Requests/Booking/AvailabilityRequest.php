<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasAnyRole(['photographer']) ?? false; }

    public function rules(): array
    {
        return [
            'slots' => ['sometimes', 'array', 'max:50'],
            'slots.*.day_of_week' => ['required_with:slots', 'integer', 'between:0,6'],
            'slots.*.start_time' => ['required_with:slots', 'date_format:H:i'],
            'slots.*.end_time' => ['required_with:slots', 'date_format:H:i', 'after:slots.*.start_time'],
            'blocked' => ['sometimes', 'array', 'max:100'],
            'blocked.*.starts_at' => ['required_with:blocked', 'date'],
            'blocked.*.ends_at' => ['required_with:blocked', 'date', 'after:blocked.*.starts_at'],
            'blocked.*.reason' => ['nullable', 'string', 'max:190'],
        ];
    }
}

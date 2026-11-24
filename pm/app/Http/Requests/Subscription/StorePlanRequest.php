<?php

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage subscriptions') ?? false; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'alpha_dash', 'unique:subscription_plans,slug'],
            'interval' => ['required', 'in:monthly,annual'],
            'price_paise' => ['required', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'between:0,60'],
            'max_portfolio_uploads' => ['nullable', 'integer', 'min:1'],
            'max_bookings_per_month' => ['nullable', 'integer', 'min:1'],
            'max_team_members' => ['nullable', 'integer', 'min:1'],
            'featured_listing' => ['boolean'], 'analytics_access' => ['boolean'], 'for_studios' => ['boolean'],
        ];
    }
}

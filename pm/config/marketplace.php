<?php

return [
    // All money is stored as integer paise (₹1 = 100 paise) to avoid float errors.
    'currency' => 'INR',

    // Default commission %, overridable by admin via settings table (see CommissionSetting).
    'commission_percent' => env('MARKETPLACE_COMMISSION_PERCENT', 20),

    'gst_percent' => env('MARKETPLACE_GST_PERCENT', 18),   // GST on platform commission
    'platform_state_code' => env('MARKETPLACE_STATE_CODE', '27'), // for CGST/SGST vs IGST split
    'platform_gstin' => env('MARKETPLACE_GSTIN'),
    'payment_hold_minutes' => env('PAYMENT_HOLD_MINUTES', 30),
    'tcs_percent' => env('MARKETPLACE_TCS_PERCENT', 0.5),  // TCS on net booking value

    'subscription' => [
        'grace_days' => env('SUBSCRIPTION_GRACE_DAYS', 5),
        'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 14),
        'reminder_days' => [7, 3, 1],
    ],

    'settlement' => [
        'default_frequency' => 'weekly', // daily | weekly | manual
        'hold_days_after_completion' => 2,
        'minimum_payout_paise' => 10000,
    ],

    'loyalty' => [
        'points_per_100_rupees' => 1,
        'paise_per_point' => 100,        // 1 point = ₹1
        'max_redeem_percent' => 20,      // of booking amount
        'referral_reward_points' => 200,
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', 's3'),
        'max_image_kb' => 10240,
        'max_video_kb' => 204800,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'video_mimes' => ['mp4', 'mov'],
        'signed_url_minutes' => 15,
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'x_account_number' => env('RAZORPAYX_ACCOUNT_NUMBER'),
    ],
];

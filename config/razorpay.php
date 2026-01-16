<?php

return [
    'key_id' => env('RAZORPAY_KEY_ID'),
    'key_secret' => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),

    // Razorpay X Payout Account
    'payout_account' => env('RAZORPAY_PAYOUT_ACCOUNT'),
];

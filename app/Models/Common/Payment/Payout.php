<?php

namespace App\Models\Common\Payment;

use App\Models\BaseModel;
use App\Models\Common\User\Legal\UserBank;
use App\Models\User;

class Payout extends BaseModel
{
    //

    protected $fillable = [
        'payout_code',

        'user_id',
        'user_bank_id',

        'amount',

        'razorpay_payout_id',
        'status',

        'requested_by',
        'requested_ip',
        'approved_by',
        'approved_at',

        'payout_mode', // manual or razorpay
        'paid_at',
        'reference',

        'remark',
    ];

    // casts
    protected $casts = [
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // relationships


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userBank()
    {
        return $this->belongsTo(UserBank::class, 'user_bank_id');
    }
}

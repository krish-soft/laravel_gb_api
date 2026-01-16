<?php

namespace App\Models\Common\Wallet;

use App\Models\BaseModel;
use App\Models\Common\User\Legal\UserBank;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class WalletPayout extends BaseModel
{
    //

    protected $fillable = [
        'payout_code',
        'wallet_id',
        'user_id',
        'user_bank_id',
        'amount',
        'razorpay_payout_id',
        'status',
        'requested_by',
        'requested_ip',
        'approved_by',
        'approved_at',
        'remark',
    ];

    // casts 
    protected $casts = [
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // relationships
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userBank()
    {
        return $this->belongsTo(UserBank::class, 'user_bank_id');
    }

    


}

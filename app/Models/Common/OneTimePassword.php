<?php

namespace App\Models\Common;

use App\Models\BaseModel;
use App\Models\User;

class OneTimePassword extends BaseModel
{
    //

    protected $fillable = [
        'user_id',
        'request_id',

        'purpose',
        'channel',

        'dial_code',
        'phone_number',
        'email',

        'otp_code',
        'expires_at',

        'verified_at',
        'attempts',

        'ip_address',
        'user_agent',
    ];



    // Relationships


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

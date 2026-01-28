<?php

namespace App\Models\Common\User\Legal;

use App\Models\BaseModel;
use App\Models\Common\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserKyc extends BaseModel
{

    use SoftDeletes;


    protected $fillable = [
        'user_id',
        'kyc_code',

        'legal_name',
        'other_legal_name',
        'father_name',
        'mother_name',

        'pan_card_number',
        'aadhaar_last4',
        'aadhaar_vid_last4',

        'dob',
        'gender',

        // Verification fields
        'status',


        'is_verified',
        'verification_mode',
        'verified_at',
        'verified_by',
        'verified_user_id',

        'review_comment',

        'is_expired',
        'expired_at',

        'addr_code',

        'custchar1',
        'custchar2',

        'remarks',
    ];


    protected $guarded = [
        'kyc_code',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        // 'pan_card_number'    => 'encrypted',   // PAN encrypted
        'verified_at' => 'datetime',
        'expired_at'  => 'datetime',
        'is_expired'  => 'boolean',
    ];

    protected $hidden = [
        'pan_card_number',
        'picture',
        // Verification fields
        // 'status',
        // 'verification_mode',
        // 'verified_at',
        // 'verified_by',
        // 'verified_user_id',
        // 'review_comment',
    ];


    /**
     * Auto-generate unique KYC code
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->kyc_code)) {
                $model->kyc_code = self::generateUniqueKycCode();
            }
        });
    }

    /**
     * Generate unique alphanumeric KYC code
     * Example: KYC-A9F3XQ82
     */
    public static function generateUniqueKycCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no O I L 0 1
        $length = 10;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('kyc_code', $code)->exists());

        return $code;
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_user_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'addr_code', 'addr_code');
    }


    public function legalDocuments()
    {
        return $this->hasMany(UserLegalDocument::class, 'user_kyc_id', 'id');
    }



    //
}

<?php

namespace App\Models\User\Legal;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserBank extends BaseModel
{
    use SoftDeletes;
    //

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',
        'bank_code',

        'account_holder_name',

        'account_number_encrypted',
        'account_number_last4',

        'ifsc_code',
        'bank_name',
        'branch_name',

        'account_type',

        'status',

        'verification_mode',

        'test_deposit_required',
        'test_deposit_amount',
        'test_deposit_ref',
        'test_deposit_verified_at',

        'verified_at',
        'verified_by',
        'verified_user_id',

        'review_comment',

        'is_primary',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'account_number_encrypted' => 'encrypted', // auto encrypt/decrypt
        'verified_at'              => 'datetime',
        'is_primary'               => 'boolean',
    ];

    /**
     * Auto-generate unique, human-readable bank_code
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->bank_code)) {
                $model->bank_code = self::generateUniqueBankCode();
            }
        });
    }

    /**
     * Generate human-readable unique bank code
     * (No O, I, L, 0, 1)
     * Example: B8QZ7M4R9A
     */
    public static function generateUniqueBankCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 10;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('bank_code', $code)->exists());

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
}

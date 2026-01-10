<?php

namespace App\Models\User\Legal;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserLegalDocument extends BaseModel
{
    //

    use SoftDeletes;

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',
        'user_kyc_id',

        'legal_doc_code',
        'document_type',

        'document_number_encrypted',
        'document_number_last4',

        'issued_at',
        'expires_at',

        'document_path',

        'status',
        'review_comment',

        'verification_mode',
        'verified_at',
        'verified_by',
        'verified_user_id',
    ];

    protected $guarded = [
        'legal_doc_code',
    ];

    protected $hidden = [
        'document_number_encrypted',
    ];

    /**
     * Attribute casting
     */
    protected $casts = [
        'document_number_encrypted' => 'encrypted', // auto encrypt/decrypt
        'issued_at'   => 'date',
        'expires_at'  => 'date',
        'verified_at' => 'datetime',
    ];

    /**
     * Auto-generate unique, human-readable legal_doc_code
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->legal_doc_code)) {
                $model->legal_doc_code = self::generateUniqueDocumentCode();
            }
        });
    }

    /**
     * Human-readable, user-safe document code
     * No O I L 0 1
     */
    public static function generateUniqueDocumentCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 10;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::where('legal_doc_code', $code)->exists());

        return $code;
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kyc()
    {
        return $this->belongsTo(UserKyc::class, 'user_kyc_id');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_user_id');
    }
}

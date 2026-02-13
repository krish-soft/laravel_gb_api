<?php

namespace App\Models\Common\User\Legal;

use App\Models\BaseModel;
use App\Models\Master\Vehicle\MstVehicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleKyc extends BaseModel
{
    //
    use SoftDeletes;


    protected $fillable = [
        'user_id',
        'mst_vehicle_id',

        'user_code',
        'picture',

        'vehicle_kyc_code',
        'license_plate_number',
        'driving_license_number',
        'registration_number',
        'insurance_policy_number',
        'vehicle_color',

        'status',

        'is_verified',
        'verification_mode',
        'verified_at',
        'verified_by',
        'verified_user_id',

        'review_comment',

        'is_expired',
        'expired_at',
    ];

    protected $guarded = [
        'vehicle_kyc_code',
    ];

    // casts
    protected $casts = [
        'verified_at' => 'datetime',
        'expired_at'  => 'datetime',
        'is_expired'  => 'boolean',
    ];

    // relationships

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->vehicle_kyc_code)) {
                $model->vehicle_kyc_code = self::generateUniqueKycCode();
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
        } while (self::where('vehicle_kyc_code', $code)->exists());

        return $code;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mstVehicle()
    {
        return $this->belongsTo(MstVehicle::class, 'mst_vehicle_id');
    }

    public function verifiedByUser()
    {
        return $this->belongsTo(User::class, 'verified_user_id');
    }

    public function legalDocuments()
    {
        return $this->hasMany(UserLegalDocument::class, 'user_kyc_id', 'id');
    }
}

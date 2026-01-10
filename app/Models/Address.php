<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($address) {
            if (empty($address->addr_code)) {
                $address->addr_code = self::generateUniqueAddrCode();
            }
        });
    }

    private static function generateUniqueAddrCode(): string
    {
        do {
            // 8 digits, India-scale safe
            $code = (string) random_int(10000000, 99999999);
        } while (self::withTrashed()->where('addr_code', $code)->exists());

        return $code;
    }




    protected $fillable = [
        'addr_code',
        'addr_name',
        'addr_type',

        'address_line1',
        'address_line2',
        'landmark',
        'village',
        'taluka',
        'district',
        'city',
        'state',
        'state_iso',
        'postal_code',
        'country',
        'country_iso',

        'contact_name',
        'dial_code',
        'phone_number',
        'email',

        'latitude',
        'longitude',

        'is_active',

        'remark',

        'custchar1',
        'custchar2',
    ];

    //  
    protected $guarded = ['addr_code'];


    // casts
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // scope

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

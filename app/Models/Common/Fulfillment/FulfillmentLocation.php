<?php

namespace App\Models\Common\Fulfillment;

use App\Models\BaseModel;
use App\Models\Common\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class FulfillmentLocation extends BaseModel
{
    //
    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->fl_code)) {
                $model->fl_code = self::generateUniqueFulfillmentLocationCode();
            }
        });
    }


    protected $fillable = [
        'user_id',
        'picture',
        'fl_code',
        'name',
        'type',
        'addr_code',
        'is_active',
        'inactive_reason',
        'remarks',

        // Verification audit
        'status',
        'verification_mode',
        'verified_at',
        'verified_by',
        'verified_user_id',
        'review_comment',
    ];

    protected $guarded = ['user_id', 'fl_code'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        // Verification audit
        // 'status',
        // 'verification_mode',
        // 'verified_at',
        // 'verified_by',
        // 'verified_user_id',
        // 'review_comment',
    ];

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function address()
    {
        return $this->hasOne(Address::class, 'addr_code', 'addr_code');
    }

    public function depots()
    {
        return $this->hasMany(FulfillmentLocationDepot::class, 'fulfillment_location_id', 'id');
    }


    // Helper Methods
    public static function generateUniqueFulfillmentLocationCode()
    {
        do {
            $code =  strtoupper(uniqid('FL-'));
        } while (self::where('fl_code', $code)->exists());

        return $code;
    }


    //
}

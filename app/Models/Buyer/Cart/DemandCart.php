<?php

namespace App\Models\Buyer\Cart;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DemandCart extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->cart_uuid)) {
                $model->cart_uuid = (string) Str::uuid();
            }
        });
    }
    protected $fillable = [
        'buyer_id',
        'fulfillment_location_id',
        'cart_uuid',
        'status',
        'locked_at',
        'converted_at',
        'expired_at',
        'meta',
    ];


    // Casts
    protected $casts = [
        'locked_at' => 'datetime',
        'converted_at' => 'datetime',
        'expired_at' => 'datetime',
        'meta' => 'array',
    ];

    // Relationships

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'fulfillment_location_id');
    }

    public function demandCartItems()
    {
        return $this->hasMany(DemandCartItem::class, 'demand_cart_id');
    }


    // Calculated Attributes
    // total cart amount
    public function getTotalCartItemAmount()
    {
        // Charges for all cart items
        return $this->demandCartItems->sum(function ($item) {
            return $item->total_price;
        });
    }
}

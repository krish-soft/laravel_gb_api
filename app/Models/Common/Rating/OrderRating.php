<?php

namespace App\Models\Common\Rating;

use App\Models\BaseModel;
use App\Models\Buyer\Order\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderRating extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'rating',
        'review',
    ];

    // casts
    protected $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
    ];

    // relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    // Only buyer
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

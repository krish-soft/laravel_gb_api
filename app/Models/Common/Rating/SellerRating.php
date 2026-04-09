<?php

namespace App\Models\Common\Rating;

use App\Models\BaseModel;
use App\Models\Seller\Product\ProductListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellerRating extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'product_listing_id',
        'seller_id',
        'user_id',
        'rating',
        'review',
    ];

    // casts
    protected $casts = [
        'seller_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
    ];

    // relationships

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class, 'product_listing_id', 'id');
    }


    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id')->safe();
    }

    // Mainly given by buyer & Driver
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->safe();
    }
}

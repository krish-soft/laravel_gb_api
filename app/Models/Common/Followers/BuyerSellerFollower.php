<?php

namespace App\Models\Common\Followers;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuyerSellerFollower extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'buyer_id',
        'seller_id',

        'is_following',
        'followed_at',
        'unfollowed_at',
        'follow_source',

        'is_notification_enabled'
    ];

    // casts
    protected $casts = [
        'buyer_id' => 'integer',
        'seller_id' => 'integer',
        'is_following' => 'boolean',
        'is_notification_enabled' => 'boolean',
        'followed_at' => 'datetime',
        'unfollowed_at' => 'datetime',
    ];

    // scope
    public function scopeActiveFollowers($query)
    {
        return $query->where('is_following', true);
    }

    // relationships
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }


    public function follow()
    {
        $this->update([
            'is_following' => true,
            'followed_at' => now(),
            'unfollowed_at' => null
        ]);
    }

    public function unfollow()
    {
        $this->update([
            'is_following' => false,
            'unfollowed_at' => now()
        ]);
    }

    public function followedSellers()
    {
        return $this->belongsToMany(
            User::class,
            'buyer_seller_followers',
            'buyer_id',
            'seller_id'
        )
            ->withPivot([
                'is_following',
                'followed_at',
                'unfollowed_at',
                'follow_source',
                'is_notification_enabled'
            ])
            ->wherePivot('is_following', true);
    }


    //
}

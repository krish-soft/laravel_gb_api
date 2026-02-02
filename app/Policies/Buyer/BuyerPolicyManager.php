<?php

namespace App\Policies\Buyer;

use App\Models\Common\User\UserDepot;
use App\Models\Seller\Product\ProductListing;
use App\Models\User;

class BuyerPolicyManager
{


    /// 
    public static function canBuyerSeeProductListing(User $buyer, ProductListing $productListing)
    {
        return UserDepot::where('user_id', $buyer->id)
            ->whereIn(
                'depot_id',
                UserDepot::where('user_id', $productListing->seller_id)
                    ->pluck('depot_id')
            )
            ->exists();
    }








    //
}

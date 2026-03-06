<?php

namespace App\Http\Controllers\Api\v1\User\Common;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Followers\BuyerSellerFollower;
use Illuminate\Http\Request;

class BuyerSellerFollowerApiController extends ApiResponseWithAuthController
{
    //



    public function followSeller(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'seller_id' => 'required|integer|exists:users,id',
        ]);

        if ($user->id == $request->seller_id) {
            return $this->showErrorMessage(__('messages.error_messages.cannot_follow_yourself'));
        }

        $follower = BuyerSellerFollower::firstOrCreate(
            [
                'buyer_id' => $user->id,
                'seller_id' => $request->seller_id
            ]
        );

        $follower->follow();

        return $this->showSuccessMessage(
            __('messages.success_messages.followed_successfully')
        );
    }

    public function unfollowSeller(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'seller_id' => 'required|integer|exists:users,id',
        ]);

        $follower = BuyerSellerFollower::where('buyer_id', $user->id)
            ->where('seller_id', $request->seller_id)
            ->first();

        if (!$follower) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'));
        }

        $follower->unfollow();

        return $this->showSuccessMessage(
            __('messages.success_messages.unfollowed_successfully')
        );
    }


    // list

    public function listFollowedSellers(Request $request)
    {
        $user = $request->user();

        $sellers = BuyerSellerFollower::with('seller:id,user_code,nickname')
            ->where('buyer_id', $user->id)
            ->active()
            ->get()
            ->pluck('seller');

        return $this->showSuccessData($sellers);
    }
}

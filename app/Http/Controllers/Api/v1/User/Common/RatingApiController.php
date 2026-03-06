<?php

namespace App\Http\Controllers\Api\v1\User\Common;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Rating\BuyerRating;
use App\Models\Common\Rating\DriverRating;
use App\Models\Common\Rating\OrderRating;
use App\Models\Common\Rating\SellerRating;
use App\Models\Delivery\DriverShipment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RatingApiController extends ApiResponseWithAuthController
{
    //

    public function giveOrderRating(Request $request)
    {
        //

        $user = $request->user();

        $request->validate([
            'order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);


        OrderRating::create([
            'order_id' => $request->order_id,
            'user_id' => $user->id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.thanks_for_your_feedback'));


        //
    }


    public function giveDriverRating(Request $request)
    {
        //
        $user = $request->user();

        $request->validate([
            'driver_shipment_id' => 'required|integer|exists:driver_shipments,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        $driverShipment = DriverShipment::with('shipment')->findOrFail($request->driver_shipment_id);

        if ($user->isBuyer() && $driverShipment->shipment->buyer_id != $user->id) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        if ($user->isSeller() && $driverShipment->shipment->seller_id != $user->id) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }


        if ($user->isDelivery() && $driverShipment->driver_id != $user->id) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        DriverRating::create([
            'driver_shipment_id' => $request->driver_shipment_id,
            'driver_id' => $driverShipment->driver_id,
            'user_id' => $user->id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.thanks_for_your_feedback'));
    }



    public function giveSellerRating(Request $request)
    {
        //
        $user = $request->user();

        $request->validate([
            'seller_id' => 'required|integer|exists:users,id', // will given by buyer & driver
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);




        if ($user->isBuyer()) {
            $hasTransaction = Order::where('buyer_id', $user->id)
                ->where('seller_id', $request->seller_id)
                ->exists();

            if (!$hasTransaction) {
                return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
            }
        }


        if ($user->isDelivery()) {
            $hasTransaction = DriverShipment::where('driver_id', $user->id)
                ->whereHas('shipment', function ($query) use ($request) {
                    $query->where('seller_id', $request->seller_id);
                })
                ->exists();

            if (!$hasTransaction) {
                return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
            }
        }


        SellerRating::create([
            'seller_id' => $request->seller_id,
            'user_id' => $user->id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.thanks_for_your_feedback'));
    }


    public function giveBuyerRating(Request $request)
    {
        //
        $user = $request->user();
        $request->validate([
            'buyer_id' => 'required|integer|exists:users,id', // will given by driver
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        if ($user->isDelivery()) {
            $hasTransaction = DriverShipment::where('driver_id', $user->id)
                ->whereHas('shipment', function ($query) use ($request) {
                    $query->where('buyer_id', $request->buyer_id);
                })
                ->exists();

            if (!$hasTransaction) {
                return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
            }
        }

        BuyerRating::create([
            'buyer_id' => $request->buyer_id,
            'user_id' => $user->id,
            'rating' => $request->rating,
            'review' => $request->review,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.thanks_for_your_feedback'));
    }



    public function getRatings(Request $request)
    {
        //
        $user = $request->user();

        $orderRatings = OrderRating::whereHas('order', function ($query) use ($user) {
            $query->where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id);
        })->with('order')->get();

        $driverRatings = DriverRating::whereHas('driverShipment.shipment', function ($query) use ($user) {
            $query->where('buyer_id', $user->id)
                ->orWhere('seller_id', $user->id);
        })->with('driverShipment.shipment')->get();

        $sellerRatings = SellerRating::where('seller_id', $user->id)->get();

        $buyerRatings = BuyerRating::where('buyer_id', $user->id)->get();

        return response()->json([
            'order_ratings' => $orderRatings,
            'driver_ratings' => $driverRatings,
            'seller_ratings' => $sellerRatings,
            'buyer_ratings' => $buyerRatings,
        ]);
    }




    //
}

<?php

namespace App\Http\Controllers\Api\v1\User;

use App\Enum\Common\OtpPurposeEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\OneTimePassword;
use App\Models\Delivery\DriverShipment;
use App\Models\User;
use App\Services\Common\Auth\OneTimePasswordService;
use Illuminate\Http\Request;

class DeliveryOtpActionApiController extends ApiResponseWithAuthController
{
    //
    // This controller to generate OTP base on requirements
    // For now we need OTP to confirm shipment delivery by driver, and also for buyer to confirm order cancellation or return request
    // So we can have a common OTP generation and verification logic here, and then use it in the respective controllers

    // Driver will request OTP to confirm delivery, and Buyer will request OTP to confirm cancellation or return request
    public function requestDeliveryConfirmationOtp(Request $request, OneTimePasswordService $otpService)
    {
        $user = $request->user();

        $request->validate([
            'driver_shipment_id' => 'required|integer|exists:driver_shipments,id',
        ]);

        $driverShipment = DriverShipment::with([
            'shipment.buyer' // right now only buyer needs to receive OTP, if in future we need to send OTP to seller then we can also load seller relationship here
        ])
            ->findOrFail($request->driver_shipment_id);

        // buyer not found then throw error
        if (!$driverShipment->shipment || !$driverShipment->shipment->buyer) {
            return $this->showErrorMessage('Associated shipment or buyer not found.', 404);
        }

        $shipment = $driverShipment->shipment;
        $buyer = User::findOrFail($shipment->buyer_id);

        // Generate OTP
        $otpService->generate(
            $buyer,
            OtpPurposeEnum::DELIVERY_CONFIRMATION->value,
            'sms',
            [
                'phone_number' => $buyer->phone_number,
            ]

        );

        if (!$otpService->send()) {
            return $this->showErrorMessage(
                'Failed to send OTP. Please try again later.',
                500
            );
        }

        return $this->successResponse(
            'OTP sent successfully',
            ['request_id' => $otpService->requestId()]
        );

        //
    }















    //
}

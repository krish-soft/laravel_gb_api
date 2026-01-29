<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Payment;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Payment\Payment;
use Illuminate\Http\Request;

class AdminPaymentApiController extends ApiResponseWithAdminAuthController
{
    //

    public function getPaymentsList(Request $request)
    {

        $paymentQuery = Payment::latest();

        if ($request->has('status')) {
            $paymentQuery->where('status', $request->input('status'));
        }

        $payments = $paymentQuery->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $payments, 200);
    }


    public function getPaymentDetails($paymentId)
    {
        //

        $payment = Payment::where('id', $paymentId)->firstOrfail();

        return $this->successResponse(__('messages.success_messages.success_get'), $payment, 200);
    }
}

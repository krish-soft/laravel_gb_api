<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Payment;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Payment\Payment;
use Illuminate\Http\Request;

class PaymentApiController extends ApiResponseWithAdminAuthController
{
    //

    public function index(Request $request)
    {

        $paymentQuery = Payment::latest();


      
    }
}

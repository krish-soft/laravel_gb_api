<?php

namespace App\Enum\Common\Payment;

enum PaymentGatewayEnum: string
{
    //

    case RAZORPAY = 'razorpay';
    case MANUAL = 'manual';
}

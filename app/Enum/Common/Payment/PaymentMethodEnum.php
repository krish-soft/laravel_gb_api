<?php

namespace App\Enum\Common\Payment;

enum PaymentMethodEnum: string
{
    //
    case COD = 'cod';
    case RAZORPAY = 'razorpay';
    case MANUAL = 'manual';
}

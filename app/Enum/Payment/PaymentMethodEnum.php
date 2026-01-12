<?php

namespace App\Enum\Payment;

enum PaymentMethodEnum: string
{
    //
    case COD = 'cod';
    case RAZORPAY = 'razorpay';
    case MANUAL = 'manual';
}

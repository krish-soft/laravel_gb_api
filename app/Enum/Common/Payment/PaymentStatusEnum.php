<?php

namespace App\Enum\Common\Payment;

enum PaymentStatusEnum : string
{
    //
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
}

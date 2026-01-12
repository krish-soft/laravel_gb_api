<?php

namespace App\Enum\Payment;

enum PaymentStatusEnum : string
{
    //
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
}

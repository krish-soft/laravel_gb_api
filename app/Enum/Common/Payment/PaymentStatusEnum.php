<?php

namespace App\Enum\Common\Payment;

enum PaymentStatusEnum: string
{
    //
    case INITIATED = 'initiated';

    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';


        // Razorpay specific
    case CAPTURED = 'captured';
    
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case AUTHORIZED = 'authorized';
    case ATTEMPTED = 'attempted';

    //
}

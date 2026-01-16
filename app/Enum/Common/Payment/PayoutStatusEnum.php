<?php

namespace App\Enum\Common\Payment;

enum PayoutStatusEnum: string
{
    //
    case REQUESTED = 'requested';
    case PROCESSING = 'processing';

    case PAID = 'paid';
    case FAILED = 'failed';

// Razorpay specific
    case PROCESSED = 'processed';
    case REJECTED = 'rejected';


}

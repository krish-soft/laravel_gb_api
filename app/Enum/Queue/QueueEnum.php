<?php

namespace App\Enum\Queue;

enum QueueEnum: string
{
    //

    // For scheduled batch processing
    case SELLER_CUTOFF   = 'seller_cutoff';
    case BUYER_CUTOFF   = 'buyer_cutoff';
    case LISTING_CUTOFF   = 'listing_cutoff';

    case ACCOUNTING_CUTOFF   = 'accounting_cutoff';
    case INVOICING_CUTOFF   = 'invoicing_cutoff';

        // For real time processing
    case ACCOUNTING   = 'accounting';
    case INVOICING   = 'invoicing';
}

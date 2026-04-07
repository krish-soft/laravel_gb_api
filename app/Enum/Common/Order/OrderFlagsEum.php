<?php

namespace App\Enum\Common\Order;

enum OrderFlagsEum: string
{
    //


    case CUTOFF_ERROR = 'cutoff_error';
    case ACCOUNTING_ERROR = 'accounting_error';
    case INVOICING_ERROR = 'invoicing_error';
    case INVOICE_ACCOUNTING_ERROR = 'invoice_accounting_error';


    //
}

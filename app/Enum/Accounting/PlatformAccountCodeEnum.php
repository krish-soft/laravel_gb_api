<?php

namespace App\Enum\Accounting;

enum PlatformAccountCodeEnum :string
{
    //

    case PLATFORM_REVENUE = 'PLATFORM_REVENUE';
    case PLATFORM_CLEARING = 'PLATFORM_CLEARING';
    case PLATFORM_TAX = 'PLATFORM_TAX';

  


//Clearing holds buyer money
// Revenue keeps platform earnings
// Tax keeps GST
// Ledger rows show movement
// Status shows availability

# Sample 

// LEDGER ENTRIES (ORDER CONFIRMED)
// | # | owner_type | account_code      | Credit | Debit | entry_type              | status    |
// | - | ---------- | ----------------- | ------ | ----- | ----------------------- | --------- |
// | 1 | PLATFORM   | PLATFORM_CLEARING | 1061.8 | 0     | order_payment_received  | AVAILABLE |
// | 2 | SELLER     | SELLER_MAIN       | 1000   | 0     | order_base_amount       | PENDING   |
// | 3 | PLATFORM   | PLATFORM_REVENUE  | 10     | 0     | order_platform_fee_base | AVAILABLE |
// | 4 | PLATFORM   | PLATFORM_TAX      | 51.8   | 0     | order_tax_amount        | AVAILABLE |

// AFTER DELIVERY (STATUS UPDATE ONLY)
// | owner_type | account_code | Old status | New status |
// | ---------- | ------------ | ---------- | ---------- |
// | SELLER     | SELLER_MAIN  | PENDING    | AVAILABLE  |

// PAYOUT TO SELLER
// | # | owner_type | account_code      | Credit | Debit | entry_type | status  |
// | - | ---------- | ----------------- | ------ | ----- | ---------- | ------- |
// | 5 | SELLER     | SELLER_MAIN       | 0      | 1000  | payout     | SETTLED |
// | 6 | PLATFORM   | PLATFORM_CLEARING | 0      | 1000  | payout     | SETTLED |

// GST PAYMENT
// | # | owner_type | account_code      | Credit | Debit | entry_type  | status  |
// | - | ---------- | ----------------- | ------ | ----- | ----------- | ------- |
// | 7 | PLATFORM   | PLATFORM_TAX      | 0      | 51.8  | tax_payment | SETTLED |
// | 8 | PLATFORM   | PLATFORM_CLEARING | 0      | 51.8  | tax_payment | SETTLED |

// FINAL NET RESULT
// | owner_type | account_code      | Net |
// | ---------- | ----------------- | --- |
// | SELLER     | SELLER_MAIN       | 0   |
// | PLATFORM   | PLATFORM_REVENUE  | +10 |
// | PLATFORM   | PLATFORM_TAX      | 0   |
// | PLATFORM   | PLATFORM_CLEARING | 0   |


}

<?php

namespace App\Models\Common\Accounting\Settlement;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementAccountLedger extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'settlement_batch_id',
        'settlement_account_id',
        'account_ledger_id',
        'credit',
        'debit',
       
    ];
}

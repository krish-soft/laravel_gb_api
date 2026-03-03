<?php

namespace App\Models\Common\Accounting\Settlement;

use App\Models\BaseModel;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Payment\Payout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementAccount extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'settlement_batch_id',
        'finance_year_id',
        'user_account_id',
        'platform_account_id',
        'payout_id',
        'amount',
        'status',
        'remarks',
    ];

    // casts
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // relationships

    public function settlementBatch()
    {
        return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id');
    }

    public function settlementAccountLedgers()
    {
        return $this->hasMany(SettlementAccountLedger::class, 'settlement_account_id');
    }

    public function userAccount()
    {
        return $this->belongsTo(Account::class, 'user_account_id', 'id');
    }

    public function platformAccount()
    {
        return $this->belongsTo(Account::class, 'platform_account_id', 'id');
    }

    public function payout()
    {
        return $this->belongsTo(Payout::class, 'payout_id', 'id');
    }


    //
}

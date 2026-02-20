<?php

namespace App\Models\Common\Accounting\Settlement;

use App\Models\BaseModel;
use App\Models\Common\Accounting\AccountLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementAccountLedger extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'settlement_ledger_txn_code',
        'settlement_batch_id',
        'settlement_account_id',
        'account_ledger_id',
        'credit',
        'debit',

    ];

    // casts
    protected $casts = [
        'credit' => 'decimal:2',
        'debit' => 'decimal:2',
    ];

    // relationships
    public function settlementBatch()
    {
        return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id');
    }

    public function settlementAccount()
    {
        return $this->belongsTo(SettlementAccount::class, 'settlement_account_id');
    }

    public function accountLedger()
    {
        return $this->belongsTo(AccountLedger::class, 'account_ledger_id');
    }


    // booted
    protected static function booted()
    {
        // settlement_ledger_txn_code auto generation
        static::creating(function ($model) {
            if (empty($model->settlement_ledger_txn_code)) {
                $model->settlement_ledger_txn_code = 'SLTXN_' . strtoupper(uniqid());
            }
        });
    }
}

<?php

namespace App\Models\Common\Accounting\Settlement;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SettlementBatch extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'finance_year_id',
        'batch_no',
        'batch_date',
        'cutoff_date',
        'remarks',
        'status',

    ];


    // Relationships

    public function settlementAccounts()
    {
        return $this->hasMany(SettlementAccount::class, 'settlement_batch_id');
    }

    public function settlementAccountLedgers()
    {
        return $this->hasMany(SettlementAccountLedger::class, 'settlement_batch_id');
    }

    // booted method to generate batch number
    protected static function booted()
    {

        // only 6 digits sequential batch number, no date prefix, for simplicity
        static::creating(function ($batch) {
            $latestBatch = self::latest('id')->first();
            $latestBatchNo = $latestBatch ? (int)$latestBatch->batch_no : 0;
            $batch->batch_no = str_pad($latestBatchNo + 1, 6, '0', STR_PAD_LEFT);
        });
    }
}

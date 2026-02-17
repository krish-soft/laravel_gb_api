<?php

namespace App\Models\Common\Accounting;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AccountLedger extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->ledger_txn_code)) {
                $model->ledger_txn_code = self::generateLedgerTxnCode();
            }
        });
    }

    protected $fillable = [
        'account_id',
        'finance_year_id',

        'description',

        'credit',
        'debit',

        'ledger_date',
        'entry_type',

        'source_type',
        'source_id',
        'source_code',

        'reference',
        'payment_reference',
        'common_reference',

        'parent_ledger_id',

        'status',

        'settled_at',

        'is_tax',
        'is_open_balance',

        'remarks',
    ];

    // casts
    protected $casts = [
        'credit' => 'decimal:2',
        'debit' => 'decimal:2',
        'is_tax' => 'boolean',
        'is_open_balance' => 'boolean',
    ];

    // relationships

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    public function parentLedger()
    {
        return $this->belongsTo(AccountLedger::class, 'parent_ledger_id', 'id');
    }

    // Genrate unique ledger transaction code
    public static function generateLedgerTxnCode(): string
    {
        do {
            $code =  'LDG-' . date('Ymd') . '-' . Str::upper(Str::random(6));
        } while (self::withTrashed()->where('ledger_txn_code', $code)->exists());

        return $code;
    }

    // morph source relation for order, 

    // morph source relation
    public function source()
    {
        return $this->morphTo(
            name: 'source',
            type: 'source_type',
            id: 'source_id'
        );
    }

    // appends source
    protected $appends = ['source'];

    public function getSourceAttribute()
    {
        if (empty($this->source_type) || empty($this->source_id)) {
            return null;
        }

        return $this->getRelationValue('source');
    }
    //
}

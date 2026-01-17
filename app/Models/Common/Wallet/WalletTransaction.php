<?php

namespace App\Models\Common\Wallet;

use App\Models\BaseModel;
use Illuminate\Support\Str;

class WalletTransaction extends BaseModel
{

    protected $fillable = [
        'wallet_id',
        'user_code',
        'wallet_txn_code',

        'amount',
        'type',
        'status',
        'description',

        'source_type',
        'source_id',
        'source_code',

        'reference', // Internal reference
        'gateway', // Payment gateway used
        'payment_reference', // Payment gateway reference

        'remark',

        // Wallet own id in case we have to deduc from seller and give to buyer or vice versa
        'related_wallet_txn_id',
        'related_wallet_txn_code',

        'is_affecting_balance',

    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_affecting_balance' => 'boolean',

    ];

    /* =========================
     | Relationships
     =========================*/

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function ledgers()
    {
        return $this->hasMany(WalletLedger::class);
    }

    /* =========================
     | Boot
     =========================*/

    protected static function booted()
    {
        static::creating(function ($txn) {
            if (empty($txn->wallet_txn_code)) {
                $txn->wallet_txn_code = self::generateTxnCode();
            }
        });
    }

    public static function generateTxnCode(): string
    {
        return 'WTX-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
    }
}

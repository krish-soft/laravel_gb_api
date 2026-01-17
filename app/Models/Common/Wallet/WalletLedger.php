<?php

namespace App\Models\Common\Wallet;

use App\Models\BaseModel;

class WalletLedger extends BaseModel
{

    protected $fillable = [
        'wallet_id',
        'wallet_transaction_id',
        'settlement_id ',

        'credit',
        'debit',

        'action',
        'description',

        'ref_type',

    ];

    protected $casts = [
        'credit' => 'decimal:2',
        'debit'  => 'decimal:2',
    ];

    /* =========================
     | Relationships
     =========================*/

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction()
    {
        return $this->belongsTo(
            WalletTransaction::class,
            'wallet_transaction_id'
        );
    }
}

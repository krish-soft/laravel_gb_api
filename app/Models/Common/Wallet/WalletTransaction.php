<?php

namespace App\Models\Common\Wallet;

use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Models\BaseModel;
use Illuminate\Support\Str;

class WalletTransaction extends BaseModel
{

    protected $fillable = [
        'wallet_id',
        'user_code',
        'wallet_txn_code',

        // Who is doing to whom to get how mcuh remain lastly
        'from_entity',
        'from_entity_id',

        'to_entity',
        'to_entity_id',

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




    /* =====================================================
     | TOTAL AMOUNT OWED TO AN ENTITY
     | Example: How much PLATFORM owes SELLERS
     =====================================================*/
    public static function totalOwedTo(
        string $toEntity,
        ?int $toEntityId = null
    ): float {
        return (float) self::where('to_entity', $toEntity)
            ->when(
                $toEntityId,
                fn($q) =>
                $q->where('to_entity_id', $toEntityId)
            )
            ->where('status', '!=', WalletStatusEnum::COMPLETED->value)
            ->sum('amount');
    }

    /* =====================================================
     | TOTAL AMOUNT AN ENTITY OWES
     | Example: How much SELLER owes PLATFORM
     =====================================================*/
    public static function totalOwedBy(
        string $fromEntity,
        ?int $fromEntityId = null
    ): float {
        return (float) self::where('from_entity', $fromEntity)
            ->when(
                $fromEntityId,
                fn($q) =>
                $q->where('from_entity_id', $fromEntityId)
            )
            ->where('status', '!=', WalletStatusEnum::COMPLETED->value)
            ->sum('amount');
    }

    /* =====================================================
     | NET POSITION (MOST IMPORTANT)
     | +ve → platform owes user
     | -ve → user owes platform
     =====================================================*/
    public static function netAmountFor(
        string $entity,
        int $entityId
    ): float {

        $owedTo = self::totalOwedTo($entity, $entityId);
        $owedBy = self::totalOwedBy($entity, $entityId);

        return round($owedTo - $owedBy, 2);
    }

    /* =====================================================
     | TOTAL UNSETTLED AMOUNT (SYSTEM LEVEL)
     | Example: Finance dashboard number
     =====================================================*/
    public static function totalUnsettledAmount(): float
    {
        return (float) self::where('status', '!=', WalletStatusEnum::COMPLETED->value)
            ->sum('amount');
    }

    /* =====================================================
     | TOTAL SETTLED AMOUNT
     =====================================================*/
    public static function totalSettledAmount(): float
    {
        return (float) self::where('status', WalletStatusEnum::COMPLETED->value)
            ->sum('amount');
    }

    /* =====================================================
     | CHECK IF SINGLE TRANSACTION IS SETTLED
     =====================================================*/
    public function isSettled(): bool
    {
        return $this->status === WalletStatusEnum::COMPLETED->value
            && $this->ledgers()->exists();
    }
}

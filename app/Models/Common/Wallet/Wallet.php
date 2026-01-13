<?php

namespace App\Models\Common\Wallet;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Wallet extends BaseModel
{

    protected $fillable = [
        'user_id',
        'user_code',
        'wallet_number',

        'available_balance',
        'hold_balance',

        'credit_limit',
        'daily_amount_limit',

        'currency',
        'is_active',

        'last_transaction_at',
        'last_ledger_at',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'hold_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'daily_amount_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'last_transaction_at' => 'datetime',
        'last_ledger_at' => 'datetime',
    ];

    // scope

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
   // Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function ledgers()
    {
        return $this->hasMany(WalletLedger::class);
    }

    /* =========================
     | Calculations
     =========================*/

    public function totalBalance(): float
    {
        return (float)($this->available_balance + $this->hold_balance);
    }

    public function canDebit(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }

    public function todayTotalAmount(): float
    {
        return (float)$this->transactions()
            ->whereDate('created_at', now())
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function exceedsDailyLimit(float $amount): bool
    {
        if ($this->daily_amount_limit <= 0) {
            return false;
        }

        return ($this->todayTotalAmount() + $amount) > $this->daily_amount_limit;
    }
}

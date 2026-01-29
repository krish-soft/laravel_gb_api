<?php

namespace App\Models\Common\Accounting;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends BaseModel
{
    //
    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->accnt_code)) {
                $model->accnt_code = self::generateAccountCode();
            }
        });
    }

    protected $fillable = [

        'accnt_code',
        'name',

        'owner_type',
        'owner_id',

        'currency',
        'type',

        'available_balance',
        'hold_balance',
        
        'total_credit',
        'total_debit',

        'is_active',
        'inactive_reason',

        'remarks',
    ];

    // casts
    protected $casts = [
        'available_balance' => 'decimal:2',
        'hold_balance' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // relationships

    public function ledgers()
    {
        return $this->hasMany(AccountLedger::class, 'account_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    // generate unique account code
    public static function generateAccountCode(): string
    {
        do {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $exists = self::withTrashed()->where('accnt_code', $code)->exists();
        } while ($exists);

        return $code;
    }
}

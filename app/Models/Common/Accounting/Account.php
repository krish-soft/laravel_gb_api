<?php

namespace App\Models\Common\Accounting;

use App\Enum\Accounting\AccountOwnerTypeEnum;
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

    // scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    public static function getOrCreateByOwner(
        string $ownerType,
        ?int $ownerId = null,
        ?string $accountCode = null
    ): self {
        // $existModel = null;

        if ($ownerType == AccountOwnerTypeEnum::PLATFORM->value) {
            $existModel = self::where('owner_type', $ownerType)
                // ->whereNotNull('accnt_code')
                ->where('accnt_code', $accountCode)
                ->first();
        } else {
            $existModel = self::where('owner_type', $ownerType)
                // ->whereNotNull('owner_id')
                ->where('owner_id', $ownerId)
                ->first();
        }

        // if found return
        if ($existModel) {
            return $existModel;
        }

        // else create new
        return self::create([
            'name' => $accountCode ?? $ownerType . ' Account for settlements',
            'owner_type' => $ownerType,
            'owner_id'   => $ownerId,
            'accnt_code' => $accountCode ?? self::generateAccountCode(),

            'total_credit'      => 0,
            'total_debit'       => 0,

            'hold_balance'      => 0,
            'available_balance' => 0,

            'is_active'        => true,
        ]);
    }


    public static function getOwnerTypeByUser(User $user): string
    {
        $ownerType = null;

        if ($user->isSeller()) {
            $ownerType = AccountOwnerTypeEnum::SELLER->value;
        } else if ($user->isBuyer()) {
            $ownerType = AccountOwnerTypeEnum::BUYER->value;
        } else if ($user->isDelivery()) {
            $ownerType = AccountOwnerTypeEnum::DELIVERY->value;
        }

        return $ownerType;
    }



    // public static function getOrCreateByOwner(
    //     string $ownerType,
    //     ?int $ownerId = null,
    //     ?string $accountCode = null
    // ): self {
    //     return self::firstOrCreate(
    //         [
    //             'owner_type' => $ownerType,
    //             'owner_id'   => $ownerId, // ✅ nullable
    //             'accnt_code'         => $accountCode, // nullable
    //         ],
    //         [
    //             'total_credit'      => 0,
    //             'total_debit'       => 0,

    //             'hold_balance'      => 0,
    //             'available_balance' => 0,

    //             'is_active'        => true,

    //         ]
    //     );
    // }





    // relationships

    public function ledgers()
    {
        return $this->hasMany(AccountLedger::class, 'account_id', 'id')
            ->latest('ledger_date')     // primary ordering
            ->latest('id');             // tie-breaker
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id')->select('id', 'name', 'user_code', 'nickname', 'charge_level_code');
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

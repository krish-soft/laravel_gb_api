<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enum\Admin\AdminRoleEnum;
use App\Enum\Common\Legal\BankStatusEnum;
use App\Enum\Common\Legal\KycStatusEnum;
use App\Enum\User\UserRoleEnum;
use App\Models\Buyer\Cart\Cart;
use App\Models\Common\Address;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\User\Legal\UserBank;
use App\Models\Common\User\Legal\UserKyc;
use App\Models\Common\User\Legal\UserLegalDocument;
use App\Models\Common\User\UserDepot;
use App\Models\Seller\Product\ProductListing;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;
    use Auditable;


    protected static function booted(): void
    {
        static::creating(function ($user) {
            $user->user_code = self::generateUniqueUserCode();
            $user->user_key = self::generateUniqueUserKey();
            $user->nickname = self::generateUniqueNickName($user->user_type);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'picture',
        'user_code',

        'name',
        'nickname',

        'dial_code',
        'phone_number',
        'phone_number_verified_at',

        'email',
        'email_verified_at',
        'password',

        'role',
        'user_type',
        'user_key',

        'is_test_user',
        'is_important',

        'is_active',
        'inactive_reason',

        'price_level_code',
        'kyc_code',
        'sales_rep', // To Identify who get onboard this user

        'bill_addr_code',
        'addr_code',

        'last_login_at',
        'last_login_ip',

        'access_modules',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'user_key',
        'kyc_code',

        // relations to hide
        'kyc', // to prevent N+1 issue
        'bank', // to prevent N+1 issue
        'legalDocuments', // to prevent N+1 issue
        'depots', // to prevent N+1 issue
        'fulfillmentLocations', // to prevent N+1 issue
        'buyerCart',
        'sellerProductListings',
        'wallets',

    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone_number_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_test_user' => 'boolean',
            'is_sales_rep' => 'boolean',
            'is_important' => 'boolean',
            'access_modules' => 'array',

        ];
    }


    protected static function generateUniqueUserCode()
    {
        do {
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $exists = self::withTrashed()->where('user_code', $code)->exists();
        } while ($exists);

        return $code;
    }

    protected static function generateUniqueUserKey()
    {
        do {
            $key = Str::random(36);
            $exists = self::withTrashed()->where('user_key', $key)->exists();
        } while ($exists);

        return $key;
    }


    // protected static function generateUniqueNickName(string $userType): string
    // {
    //     $prefix = match ($userType) {
    //         UserTypeEnum::FARMER->value   => 'F-',
    //         UserTypeEnum::TRADER->value   => 'T-',
    //         UserTypeEnum::DELIVERY->value => 'D-',
    //         default                       => 'U-',
    //     };

    //     do {
    //         // 8-digit number = 90 million combinations per role
    //         $number = random_int(10_000_000, 99_999_999);
    //         $nickname = $prefix . $number;
    //     } while (
    //         self::withTrashed()->where('nickname', $nickname)->exists()
    //     );

    //     return $nickname;
    // }
    protected static function generateUniqueNickName($userType = null): string
    {
        do {
            $number = self::withTrashed()->count() + 1;
            $formatted = str_pad($number, 8, '0', STR_PAD_LEFT); // 00000001
            $nickname = substr($formatted, 0, 4) . '-' . substr($formatted, 4, 4); // 0000-0001
        } while (self::withTrashed()->where('nickname', $nickname)->exists());

        return $nickname;
    }


    // scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships

    public function kyc()
    {
        return $this->hasOne(UserKyc::class, 'user_id', 'id');
    }

    public function bank()
    {
        return $this->hasOne(UserBank::class, 'user_id', 'id'); // Limiting only one Bank
    }

    public function legalDocuments()
    {
        return $this->hasMany(UserLegalDocument::class, 'user_id', 'id');
    }

    public function billAddress()
    {
        return $this->belongsTo(Address::class, 'bill_addr_code', 'addr_code');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'addr_code', 'addr_code');
    }

    public function depots()
    {
        return $this->hasMany(UserDepot::class, 'user_id', 'id');
    }

    public function primaryDepot()
    {
        return $this->hasOne(UserDepot::class, 'user_id', 'id')->where('is_primary', true);
    }

    public function buyerCart()
    {
        return $this->hasMany(Cart::class, 'buyer_id', 'id');
    }

    public function sellerProductListings()
    {
        return $this->hasMany(ProductListing::class, 'seller_id', 'id');
    }

    public function fulfillmentLocations()
    {
        return $this->hasMany(FulfillmentLocation::class, 'user_id', 'id');
    }


    /* ---------------- BOOLEAN METHODS (AUTH / LOGIC) ---------------- */

    public function isBuyer(): bool
    {
        return $this->role === UserRoleEnum::BUYER->value;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRoleEnum::SELLER->value;
    }

    public function isDelivery(): bool
    {
        return $this->role === UserRoleEnum::DELIVERY->value;
    }

    public function isAdminManagement(): bool
    {
        return in_array($this->role, AdminRoleEnum::casesAsValues(), true);
    }

    public function isSuperAdminGroup(): bool
    {
        return in_array($this->role, [AdminRoleEnum::SUPERADMIN->value, AdminRoleEnum::ADMIN->value], true);
    }

    public function hasModuleAccess(int $moduleCode): bool
    {
        if (empty($this->access_modules) || !is_array($this->access_modules)) {
            return false;
        }

        return in_array($moduleCode, $this->access_modules, true);
    }


    public function isKycApproved(): bool
    {
        return $this->kyc && $this->kyc->status === KycStatusEnum::APPROVED->value;
    }

    public function isBankVerified(): bool
    {
        return $this->bank && $this->bank->status === BankStatusEnum::VERIFIED->value;
    }


    // Check That they are ready for transactions
    public function isUserReadyForOrderManagement(): bool
    {
        //
        return $this->isKycApproved()
            //            && $this->isBankVerified() // Optional when money need they will do
            && $this->depots()->exists()
            && $this->fulfillmentLocations()->exists();
    }




    // Appends


    protected $appends = [
        'is_kyc_approved',
        'kyc_review_comment',


        'is_bank_verified',
        'is_user_ready_for_order_management',

        'is_depot_assigned',
        'is_fulfillment_location_exist',

        //
    ];

    public function getIsKycApprovedAttribute(): bool
    {
        return $this->isKycApproved();
    }

    public function getKycReviewCommentAttribute(): string
    {
        return !$this->isKycApproved() ? $this->kyc->review_comment ?? '' : '';
    }

    public function getIsBankVerifiedAttribute(): bool
    {
        return $this->isBankVerified();
    }

    public function getIsUserReadyForOrderManagementAttribute(): bool
    {
        return $this->isUserReadyForOrderManagement();
    }

    public function getIsDepotAssignedAttribute(): bool
    {
        return $this->depots()->exists();
    }

    public function getIsFulfillmentLocationExistAttribute(): bool
    {
        return $this->fulfillmentLocations()->exists();
    }


    //
}

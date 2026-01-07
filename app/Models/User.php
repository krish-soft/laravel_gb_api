<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enum\User\AdminRoleEnum;
use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
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

    protected static function booted(): void
    {
        static::creating(function ($user) {
            $user->username = self::generateUniqueUserCode();
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
        'is_sales_rep',
        'is_important',

        'is_active',
        'inactive_reason',

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
        return in_array(
            $this->role,
            array_map(fn($c) => $c->value, AdminRoleEnum::cases()),
            true
        );
    }

    public function hasModuleAccess(int $moduleCode): bool
    {
        if (empty($this->access_modules) || !is_array($this->access_modules)) {
            return false;
        }

        return in_array($moduleCode, $this->access_modules, true);
    }


    //
}

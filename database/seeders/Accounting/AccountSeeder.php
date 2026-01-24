<?php

namespace Database\Seeders\Accounting;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Models\Common\Accounting\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        Account::create([
            'name' => 'Platform Revenue Account',
            'accnt_code' => PlatformAccountCodeEnum::PLATFORM_REVENUE->value, // 'PLATFORM_REVENUE',
            'owner_type' => AccountOwnerTypeEnum::PLATFORM->value,
            'owner_id' => null,
            'currency' => 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remark' => 'Tracks platform earned income (fees, penalties)',
        ]);



        Account::create([
            'name' => 'Platform Clearing Account',
            'accnt_code' => PlatformAccountCodeEnum::PLATFORM_CLEARING->value, // 'PLATFORM_CLEARING',
            'owner_type' => AccountOwnerTypeEnum::PLATFORM->value,
            'owner_id' => null,
            'currency' => 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remark' => 'Temporary holding of buyer payments before settlement',
        ]);



        Account::create([
            'name' => 'Platform Tax Liability Account',
            'accnt_code' => PlatformAccountCodeEnum::PLATFORM_TAX->value, // 'PLATFORM_TAX',
            'owner_type' => AccountOwnerTypeEnum::PLATFORM->value,
            'owner_id' => null,
            'currency' => 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remark' => 'Tracks GST collected and payable to government',
        ]);





        //
    }
}

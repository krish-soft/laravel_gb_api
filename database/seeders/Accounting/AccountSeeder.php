<?php

namespace Database\Seeders\Accounting;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Models\Common\Accounting\Account;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
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
            'name' => 'Platform Clearing Account',
            'accnt_code' => PlatformAccountCodeEnum::PLATFORM_CLEARING->value, // 'PLATFORM_CLEARING',
            'owner_type' => AccountOwnerTypeEnum::PLATFORM->value,
            'owner_id' => null,
            'currency' => MstFinanceSetting::currency() ?? 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remarks' => 'Temporary holding of buyer payments before settlement',
        ]);

        // Account::create([
        //     'name' => 'Platform Revenue Account',
        //     'accnt_code' => PlatformAccountCodeEnum::PLATFORM_REVENUE->value, // 'PLATFORM_REVENUE',
        //     'owner_type' => AccountOwnerTypeEnum::PLATFORM->value,
        //     'owner_id' => null,
        //     'currency' => MstFinanceSetting::currency() ?? 'INR',
        //     'available_balance' => 0.00,
        //     'hold_balance' => 0.00,
        //     'total_credit' => 0.00,
        //     'total_debit' => 0.00,
        //     'is_active' => true,
        //     'remarks' => 'Tracks platform earned income (fees, penalties)',
        // ]);

        // Account::create([
        //     'name' => 'Platform Tax Liability Account',
        //     'accnt_code' => PlatformAccountCodeEnum::PLATFORM_TAX->value, // 'PLATFORM_TAX',
        //     'owner_type' => AccountOwnerTypeEnum::GOVERNMENT->value,
        //     'owner_id' => null,
        //     'currency' => MstFinanceSetting::currency() ?? 'INR',
        //     'available_balance' => 0.00,
        //     'hold_balance' => 0.00,
        //     'total_credit' => 0.00,
        //     'total_debit' => 0.00,
        //     'is_active' => true,
        //     'remarks' => 'Tracks GST collected and payable to government',
        // ]);

        Account::create([
            'name' => 'Platform Market Liability Account',
            'accnt_code' => PlatformAccountCodeEnum::PLATFORM_MARKET->value, // 'PLATFORM_MARKET',
            'owner_type' => AccountOwnerTypeEnum::PLATFORM->value,
            'owner_id' => null,
            'currency' => MstFinanceSetting::currency() ?? 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remarks' => 'Tracks GST collected and payable to government',
        ]);


        Account::create([
            'name' => 'Cash Account',
            'accnt_code' => PlatformAccountCodeEnum::CASH->value, // 'PLATFORM_MARKET',
            'owner_type' => AccountOwnerTypeEnum::CASH->value,
            'owner_id' => null,
            'currency' => MstFinanceSetting::currency() ?? 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remarks' => 'Represents cash transactions (if any) outside of bank accounts',
        ]);


        Account::create([
            'name' => 'Bank Account 01',
            'accnt_code' => PlatformAccountCodeEnum::BANK_01->value, // 'PLATFORM_MARKET',
            'owner_type' => AccountOwnerTypeEnum::BANK->value,
            'owner_id' => null,
            'currency' => MstFinanceSetting::currency() ?? 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remarks' => 'Represents actual bank account for settlements and payouts',
        ]);


        Account::create([
            'name' => 'Bank Account 02',
            'accnt_code' => PlatformAccountCodeEnum::BANK_02->value, // 'PLATFORM_MARKET',
            'owner_type' => AccountOwnerTypeEnum::BANK->value,
            'owner_id' => null,
            'currency' => MstFinanceSetting::currency() ?? 'INR',
            'available_balance' => 0.00,
            'hold_balance' => 0.00,
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'is_active' => true,
            'remarks' => 'Represents actual bank account for settlements and payouts',
        ]);







        //
    }
}

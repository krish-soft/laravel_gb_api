<?php

namespace App\Http\Controllers\Api\v1\Utils;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Admin\AdminRoleEnum;
use App\Enum\Admin\AdminUserTypeEnum;
use App\Enum\Common\Legal\BankStatusEnum;
use App\Enum\Common\Legal\KycReviewEnum;
use App\Enum\Common\Legal\KycStatusEnum;
use App\Enum\Common\Legal\LegalDocumentTypeEnum;
use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\Master\Market\MstMarket;
use App\Models\Master\MstFinancialYear;
use App\Models\Master\MstPackType;
use App\Models\Master\MstState;
use App\Models\Master\MstUnit;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Setting\AppSetting;
use Illuminate\Http\Request;

class UtilsApiController extends ApiResponseController
{
    // No Auth only common things

    public function getStateList()
    {
        $list = MstState::active()->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $list);
    }

    public function getUnitList()
    {
        $list = MstUnit::active()->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $list);
    }

    public function getPackTypeUnitList()
    {
        $list = MstPackType::active()->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $list);
    }


    public function getAppMetaInfo()
    {

        $data = [
            'app_name' => MstAppSetting::getOrCreate()->app_name,
            'current_financial_year_code' => currentFy()->code,
        ];

        return $this->successResponse(__('messages.success_messages.success_get'), $data);
    }


    public function getAlLEnums()
    {
        $commonData = [
            'user_roles' => UserRoleEnum::casesAsValues(),
            'user_types' =>  UserTypeEnum::casesAsValues(),
        ];

        $adminData = [];


        if (request()->user() && request()->user()->isAdminManagement()) {
            // Add more enums for admin users if needed
            $adminData = [
                // roles
                'admin_roles' => AdminRoleEnum::casesAsValues(),
                'admin_user_types' => AdminUserTypeEnum::casesAsValues(),

                // Legal Enums
                'kyc_statuses' => KycStatusEnum::casesAsValues(),
                'kyc_review_options' => KycReviewEnum::casesAsValues(),
                'legal_document_types' => LegalDocumentTypeEnum::casesAsValues(),
                'bank_statuses' => BankStatusEnum::casesAsValues(),

                // accounting/enums
                'accounting_entry_types' => AccountEntryTypeEnum::casesAsValues(),
                'accounting_owner_types' => AccountOwnerTypeEnum::casesAsValues(),
                'ledger_statuses' => LedgerStatusEnum::casesAsValues(),
                'platform_accounts' => PlatformAccountCodeEnum::casesAsValues(),

                'financial_years' => MstFinancialYear::active()->pluck('id')->toArray(),



                // 

            ];
        }

        $dataList = array_merge($commonData, $adminData);

        $processData = [];
        // now in this per array can you assign key as enum name and value as cases
        foreach ($dataList as $enumName => $values) {
            $processData[$enumName] = [];

            foreach ($values as $value) {
                $processData[$enumName][] = [
                    'id' => $value,
                    'value'  => $value,
                    'label' => ucfirst(strtolower((string) $value)),
                ];
            }
        }



        return $this->successResponse(__('messages.success_messages.success_get'), $processData);
    }



    public function getMarketList()
    {
        $list = [];
        if (request()->user()) {
            $list = MstMarket::active()->get();
        }
        return $this->successResponse(__('messages.success_messages.success_get'), $list);
    }


    public function getProducts()
    {
        $list = [];
        if (request()->user()) {
            $list = MstProduct::with('category', 'variants', 'packagings')->active()->get();
        }

        return $this->successResponse(__('messages.success_messages.success_get'), $list);
    }

    public function getProductVariants($productId)
    {
        $variants = [];

        if (request()->user()) {
            $product = MstProduct::findOrFail($productId);
            $variants = $product->variants()->active()->get();
        }
        return $this->successResponse(__('messages.success_messages.success_get'), $variants);
    }

    public function getProductPackagings($productId)
    {
        $packagings = [];
        if (request()->user()) {
            $product = MstProduct::findOrFail($productId);
            $packagings = $product->packagings()->active()->get();
        }
        return $this->successResponse(__('messages.success_messages.success_get'), $packagings);
    }


    //
}

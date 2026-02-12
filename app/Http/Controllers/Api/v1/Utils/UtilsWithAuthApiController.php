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
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\MstFinancialYear;
use App\Models\Master\MstPackType;
use App\Models\Master\MstState;
use App\Models\Master\MstUnit;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Setting\AppSetting;
use Illuminate\Http\Request;

class UtilsWithAuthApiController extends ApiResponseWithAuthController
{
    // No Auth only common things



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

    public function getProducts()
    {
        $list = MstProduct::with('variants', 'packagings')->active()->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $list);
    }

    public function getProductVariants($productId)
    {
        $product = MstProduct::findOrFail($productId);


        $variants = $product->variants()->active()->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $variants);
    }

    public function getProductPackagings($productId)
    {
        $product = MstProduct::findOrFail($productId);

        $packagings = $product->packagings()->active()->get();
        return $this->successResponse(__('messages.success_messages.success_get'), $packagings);
    }


    //
}

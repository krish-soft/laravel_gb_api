<?php

namespace App\Http\Controllers\Api\v1\Utils;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\Master\MstPackType;
use App\Models\Master\MstState;
use App\Models\Master\MstUnit;
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
            'fy_code' => currentFy()->code,


        ];
    }


    //
}

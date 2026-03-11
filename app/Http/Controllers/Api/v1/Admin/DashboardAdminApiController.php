<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Enum\Admin\AdminRoleEnum;
use App\Enum\User\UserRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class DashboardAdminApiController extends ApiResponseWithAdminAuthController
{
    //



    public function getDashboardData()
    {
        $data = Cache::remember('dashboard_data', 600, function () {

            return [

                'users_summary' => [
                    'total_users' => User::count(),
                    'total_admins' => User::whereIn('role', AdminRoleEnum::casesAsValues())->count(),
                    'total_buyers' => User::where('role', UserRoleEnum::BUYER->value)->count(),
                    'total_sellers' => User::where('role', UserRoleEnum::SELLER->value)->count(),
                    'total_drivers' => User::where('role', UserRoleEnum::DELIVERY->value)->count(),
                ],

                'app' => [
                    'financial_year' => MstFinanceSetting::getOrCreate()->financialYear->code,
                    'is_maintenance_mode' => MstAppSetting::isMaintenanceMode(),
                ]
            ];
        });

        return $this->successResponse(__('messages.success_messages.success_get'), $data, 200);
    }



    //
}

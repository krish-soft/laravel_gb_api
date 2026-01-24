<?php

namespace App\Http\Middleware;

use App\Enum\Common\ActionCodeEnum;
use App\Enum\Common\Setting\LocaleEnum;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Setting\AppSetting;
use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AppChecker
{
    use ApiResponserTrait;

    public function handle(Request $request, Closure $next): Response
    {

        if ($request->hasHeader('Accept-Language')) {

            $locale = strtolower(trim($request->header('Accept-Language')));

            if (!in_array($locale, LocaleEnum::casesAsValues(), true)) {
                return $this->showErrorMessage(
                    __('messages.error_messages.invalid_locale'),
                    400
                );
                // $locale = config('app.fallback_locale');
            }

            App::setLocale($locale);
        }

        $appSetting = null;

        $appSetting = MstAppSetting::getOrCreate();


        //        if (!$appSetting) {
        //            return $this->showErrorMessageWithAction(
        //                'Service unavailable',
        //                503,
        //                ActionCodeEnum::FORCE_MAINTENANCE,
        //
        //            );
        //        }

        if ($appSetting->isMaintenanceMode()) {
            return $this->showErrorMessageWithAction(
                __('messages.error_messages.maintenance_mode') . "\n\n" . $appSetting->getMaintenanceMessage(),
                503,
                ActionCodeEnum::FORCE_MAINTENANCE,

            );
        }

        $platform   = strtolower((string) $request->header('X-Platform'));
        $appVersion = (string) $request->header('X-App-Version');

        if ($platform === 'android' && $appSetting->isForceAndroidUpdate()) {
            $latestVersion = $appSetting->getAndroidAppVersion();

            if ($latestVersion && version_compare($appVersion, $latestVersion, '<')) {
                return $this->showErrorMessageWithAction(
                    __('messages.error_messages.force_app_update', ['version' => $latestVersion]),
                    426,
                    ActionCodeEnum::FORCE_APP_UPDATE,

                );
            }
        }

        if ($platform === 'ios' && $appSetting->isForceIosUpdate()) {
            $latestVersion = $appSetting->getIosAppVersion();

            if ($latestVersion && version_compare($appVersion, $latestVersion, '<')) {
                return $this->showErrorMessageWithAction(
                    __('messages.error_messages.force_app_update', ['version' => $latestVersion]),
                    426,
                    ActionCodeEnum::FORCE_APP_UPDATE,

                );
            }
        }

        return $next($request);
    }
}

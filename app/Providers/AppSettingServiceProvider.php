<?php

namespace App\Providers;

use App\Models\Master\Setting\MstAppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppSettingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //

        // Avoid crash during migrate / fresh install
        if (!Schema::hasTable('mst_app_settings')) {
            return;
        }
        $settings = null;
//        $settings = Cache::rememberForever('mst_app_settings', function () {
//            return MstAppSetting::whereNull('deleted_at')->first() ?? null;
//        });

        $settings = MstAppSetting::getOrCreate();


        if (!$settings) {
            return;
        }

        // App identity
        Config::set('app.name', $settings->app_name);

        // Localization
        Config::set('app.locale', $settings->locale ?? 'en');
        Config::set('app.fallback_locale', $settings->fallback_locale ?? 'en');
        Config::set('app.timezone', $settings->timezone ??  'Asia/Kolkata');

        // Debug (SAFE override)
        Config::set('app.debug', false);

        // Runtime timezone
        date_default_timezone_set($settings->timezone ?? 'Asia/Kolkata');
    }
}

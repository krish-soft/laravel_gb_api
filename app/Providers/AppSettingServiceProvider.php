<?php

namespace App\Providers;

use App\Models\Setting\AppSetting;
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
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        $settings = Cache::rememberForever('app_settings', function () {
            return AppSetting::whereNull('deleted_at')->first();
        });

        if (!$settings) {
            return;
        }

        // App identity
        Config::set('app.name', $settings->app_name);

        // Localization
        Config::set('app.locale', $settings->locale);
        Config::set('app.fallback_locale', $settings->fallback_locale);
        Config::set('app.timezone', $settings->timezone);

        // Debug (SAFE override)
        Config::set('app.debug', $settings->debug_enabled);

        // Runtime timezone
        date_default_timezone_set($settings->timezone);
    }
}

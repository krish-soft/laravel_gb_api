<?php

use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserLoginApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserLogoutApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserRegisterApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserResetPasswordApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\PaymentReconcileApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\WalletPayoutApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Setting\AppSettingApiController;
use App\Http\Controllers\Api\v1\Admin\Common\User\AdminRegularUserApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\MstChargeApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\MstChargeLevelApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\Rule\MstDeliveryChargeRuleApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\Rule\MstMinimumOrderChargeRuleApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Depot\MstDepotApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Depot\MstZoneApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstPackTypeApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstStateApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstUnitApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductCategoryApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductPackagingApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductVariantApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Vehicle\MstVehicleApiController;
use App\Http\Controllers\Api\v1\Admin\Seller\Product\AdminProductListingApiController;
use App\Http\Controllers\Api\v1\User\Buyer\CartApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserLoginApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserLogoutApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserRegisterApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserResetPasswordApiController;
use App\Http\Controllers\Api\v1\User\Common\Fulfillment\FulfillmentLocationApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserBankApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserKycApiController;
use App\Http\Controllers\Api\v1\User\Seller\Product\ProductListingApiController;
use App\Http\Controllers\Web\Webhooks\RazorpayBankVerificationWebhookHandler;
use App\Http\Controllers\Web\Webhooks\RazorpayPayoutWebhookController;
use App\Http\Controllers\Web\Webhooks\RazorpayWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


// Public Payment Route
Route::post('/webhooks/razorpay/payments', [RazorpayWebhookController::class, 'handle']);
Route::post('/webhooks/razorpay/payouts', [RazorpayPayoutWebhookController::class, 'handle']);
Route::post('/webhooks/razorpay/bankAccount', [RazorpayBankVerificationWebhookHandler::class, 'handle']);



Route::group([
    'prefix' => 'v1',
    'middleware' => [
        'app-checker', // Custom Middleware to check app status
        'ms-api-key-checker', // Custom Middleware to check microservice API key

    ]
], function () {

    ## Public Routes

    // Signup
    Route::post('/signup/otp/send', [UserRegisterApiController::class, 'sendRegistrationOtp']);
    Route::post('/signup/register', [UserRegisterApiController::class, 'verifyOtpAndRegister']);

    // Login
    Route::post('/signin', [UserLoginApiController::class, 'login']);

    // Forget Password
    Route::post('/forget/otp/send', [UserResetPasswordApiController::class, 'sendForgotPasswordOtp']);
    Route::post('/forget/reset', [UserResetPasswordApiController::class, 'resetPassword']);

    /**
     *  Regular User Auth Protected Routes
     */

    ## Auth Protected Routes
    ## Regular User Auth Protected Routes
    Route::group([
        'middleware' => [
            'auth:sanctum', // Sanctum Authentication
            'user-checker' // Custom Middleware to check token expiry
        ]
    ], function () {


        // Logout
        Route::post('/signout', [UserLogoutApiController::class, 'logout']);
        Route::post('/signout/all', [UserLogoutApiController::class, 'logoutAllDevices']);

        // KYC Routes
        Route::post('/kyc', [UserKycApiController::class, 'storeKyc']); // Add KYC
        Route::put('/kyc/update', [UserKycApiController::class, 'updateKyc']); // Update / Re-KYC

        // Bank Routes
        Route::apiResource('userBank', UserBankApiController::class);


        // Whihc Required KYC Approved User Only
        Route::group([
            'middleware' => [
                // 'user-legal-checker' // Custom Middleware to check user legal (KYC) status // Testing Removed
            ]
        ], function () {

            Route::get('user-profile', function (Request $request) {
                return $request->user();
            });

            // Fulfillment Location Routes
            Route::apiResource('fulfillmentLocation', FulfillmentLocationApiController::class);
            Route::post('fulfillmentLocation/address', [FulfillmentLocationApiController::class, 'addAddress']);
            Route::put('fulfillmentLocation/{fulfillmentLocation}/updateAddress', [FulfillmentLocationApiController::class, 'updateAddress']);


            // Product Listing Routes
            Route::prefix('product-listing')->group(function () {
                Route::post('listing/preview', [ProductListingApiController::class, 'previewWithCharges']);
                Route::post('listing/confirm', [ProductListingApiController::class, 'confirmListing']);
                Route::post('listing/{listingId}/cancel', [ProductListingApiController::class, 'cancelListing']);

                Route::put('packages/{packageId}', [ProductListingApiController::class, 'updatePackage']);
                Route::post('packages/{packageId}/cancel', [ProductListingApiController::class, 'deletePackage']);
            });

            // Cart Routes
            Route::prefix('cart')->group(function () {
                Route::get('active', [CartApiController::class, 'getActiveCart']);
                Route::post('item', [CartApiController::class, 'addItem']);
                Route::put('item/{cartItemId}', [CartApiController::class, 'updateItem']);
                Route::delete('item/{cartItemId}', [CartApiController::class, 'removeItem']);
                Route::delete('clear', [CartApiController::class, 'clearCart']);
            });

            //
        });

        //
    });


    ## Admin User Auth Protected Routes
    Route::group([
        'prefix' => 'admin',
    ], function () {

        ## Admin User Public Routes

        Route::post('/signin', [AdminUserLoginApiController::class, 'login']);

        Route::post('/forget/otp/send', [AdminUserResetPasswordApiController::class, 'sendForgotPasswordOtp']);
        Route::post('/forget/reset', [AdminUserResetPasswordApiController::class, 'resetPassword']);


        Route::group([
            'middleware' => [
                'auth:sanctum', // Sanctum Authentication
                'admin-user-checker' // Custom Middleware to check admin things
            ]
        ], function () {

            Route::post('/register', [AdminUserRegisterApiController::class, 'register']); // Admin User Registration
            Route::post('/signout', [AdminUserLogoutApiController::class, 'logout']); // Admin User Logout


            Route::get('user-profile', function (Request $request) {
                return $request->user();
            });


            // Product Listing Routes
            Route::post('/product-listing', [AdminProductListingApiController::class, 'store']);
            Route::post('/product-listing/{listingId}/cancel', [AdminProductListingApiController::class, 'cancelListing']);
            Route::put('/product-listing-packages/{packageId}', [AdminProductListingApiController::class, 'updatePackage']);
            Route::post('/product-listing-packages/{packageId}/cancel', [AdminProductListingApiController::class, 'deletePackage']);


            // Payments
            Route::post('/payments/{payment_code}/reconcile', [PaymentReconcileApiController::class, 'reconcile']);

            Route::prefix('wallet-payouts')->group(function () {
                Route::get('/', [WalletPayoutApiController::class, 'index']);
                Route::post('{payout}/approve', [WalletPayoutApiController::class, 'approve']);
                Route::post('{payout}/fail', [WalletPayoutApiController::class, 'fail']);
                Route::post('{payout}/reconcile', [WalletPayoutApiController::class, 'reconcile']);
            });



            // Regular User Management
            Route::apiResource('regular-user', AdminRegularUserApiController::class); // Manage Regular user
            Route::post('regular-user/{user}/addDepot', [AdminRegularUserApiController::class, 'addDepot']);
            Route::delete('regular-user/{user}/removeDepot', [AdminRegularUserApiController::class, 'removeDepot']);


            // Settings
            Route::get('setting/app', [AppSettingApiController::class, 'getSetting']);
            Route::put('setting/app', [AppSettingApiController::class, 'updateSetting']);


            ##  Master  Routes
            Route::apiResource('mstUnit', MstUnitApiController::class);
            Route::apiResource('mstPackType', MstPackTypeApiController::class);

            Route::apiResource('mstVehicle', MstVehicleApiController::class);

            Route::apiResource('mstState', MstStateApiController::class);
            Route::apiResource('mstZone', MstZoneApiController::class);

            // Depot Routes
            Route::apiResource('mstDepot', MstDepotApiController::class);
            Route::post('mstDepot/addAddress', [MstDepotApiController::class, 'addAddress']);
            Route::put('mstDepot/{mstDepot}/updateAddress', [MstDepotApiController::class, 'updateAddress']);

            // Product
            Route::apiResource('mstProductCategory', MstProductCategoryApiController::class);
            Route::apiResource('mstProduct', MstProductApiController::class);
            Route::apiResource('mstProductVariant', MstProductVariantApiController::class);
            Route::apiResource('mstProductPackaging', MstProductPackagingApiController::class);

            // Charges
            Route::apiResource('mstCharge', MstChargeApiController::class);
            Route::apiResource('mstChargeLevel', MstChargeLevelApiController::class);
            Route::apiResource('mstDeliveryChargeRule', MstDeliveryChargeRuleApiController::class);
            Route::apiResource('mstMinimumOrderChargeRule', MstMinimumOrderChargeRuleApiController::class);

            //
        });


        //
    });


    //
});

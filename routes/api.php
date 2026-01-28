<?php

use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserLoginApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserLogoutApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserRegisterApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserResetPasswordApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Fulfillment\AdminFulfillmentLocationApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\PaymentReconcileApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\PayoutApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Customer\CustomerApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Customer\CustomerLegalActionApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\MstChargeApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\MstChargeLevelApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\Rule\MstDeliveryChargeRuleApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Charge\Rule\MstMinimumOrderChargeRuleApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Depot\MstDepotApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Depot\MstZoneApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstFinancialYearApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstPackTypeApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstStateApiController;
use App\Http\Controllers\Api\v1\Admin\Master\MstUnitApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductCategoryApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductPackagingApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Product\MstProductVariantApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Setting\MstAppSettingApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Setting\MstBusinessSettingApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Setting\MstFinanceSettingApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Setting\MstPaymentSettingApiController;
use App\Http\Controllers\Api\v1\Admin\Master\Vehicle\MstVehicleApiController;
use App\Http\Controllers\Api\v1\Admin\Seller\Product\AdminProductListingApiController;
use App\Http\Controllers\Api\v1\User\Buyer\CartApiController;
use App\Http\Controllers\Api\v1\User\Buyer\CheckoutApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserLoginApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserLogoutApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserRegisterApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserResetPasswordApiController;
use App\Http\Controllers\Api\v1\User\Common\Fulfillment\FulfillmentLocationApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserBankApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserKycApiController;
use App\Http\Controllers\Api\v1\User\Seller\Product\ProductListingApiController;
use App\Http\Controllers\Api\v1\User\UserProfileApiController;
use App\Http\Controllers\Api\v1\Utils\UtilsApiController;
use App\Http\Controllers\Web\Webhooks\RazorpayBankVerificationWebhookHandler;
use App\Http\Controllers\Web\Webhooks\RazorpayPayoutWebhookController;
use App\Http\Controllers\Web\Webhooks\RazorpayWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Public Payment Route
Route::post('/webhooks/razorpay/payments', [RazorpayWebhookController::class, 'handle']);
Route::post('/webhooks/razorpay/payouts', [RazorpayPayoutWebhookController::class, 'handle']);
Route::post('/webhooks/razorpay/bankAccount', [RazorpayBankVerificationWebhookHandler::class, 'handle']);

// Serve Private Files
Route::get('/files/{path}', function ($path) {
    abort_unless(Storage::disk('private')->exists($path), 404);

    return response()->file(
        Storage::disk('private')->path($path)
    );
})
    ->where('path', '.*')
    ->name('files.view')
    ->middleware('signed');


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


    // Utils
    Route::prefix('utils')->group(function () {
        Route::get('states', [UtilsApiController::class, 'getStateList']);
        Route::get('units', [UtilsApiController::class, 'getUnitList']);
        Route::get('pack-type-units', [UtilsApiController::class, 'getPackTypeUnitList']);

        Route::get('app-meta', [UtilsApiController::class, 'getAppMetaInfo']);
        Route::get('enums', [UtilsApiController::class, 'getAlLEnums']);
    });


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


        Route::prefix('user')->group(function () {

            Route::get('/meta', [UserProfileApiController::class, 'metaDetails']);

            Route::get('/profile', [UserProfileApiController::class, 'getProfile']);
            Route::put('/profile', [UserProfileApiController::class, 'updateProfile']);
            Route::put('/profile/password', [UserProfileApiController::class, 'updatePassword']);

            // address
            Route::post('/profile/address', [UserProfileApiController::class, 'saveAddress']);
            Route::post('/profile/billingAddress', [UserProfileApiController::class, 'saveBillingAddress']);

            //
        });

        // KYC Routes
        Route::post('/kyc', [UserKycApiController::class, 'storeKyc']); // Add KYC
        Route::put('/kyc/update', [UserKycApiController::class, 'updateKyc']); // Update / Re-KYC

        // Bank Routes
        Route::apiResource('userBank', UserBankApiController::class);


        // Fulfillment Location Routes
        Route::apiResource('fulfillmentLocation', FulfillmentLocationApiController::class);
        Route::post('fulfillmentLocation/address/{fulfillmentLocation}', [FulfillmentLocationApiController::class, 'saveAddress']);


        // Which Required KYC Approved User Only
        Route::group([
            'middleware' => [
                'user-legal-checker' // Custom Middleware to check user legal (KYC) status // Testing Removed
            ]
        ], function () {



            // Product Listing Routes
            Route::prefix('listing')->group(function () {
                Route::post('create', [ProductListingApiController::class, 'createListing']);
                Route::post('preview-charge', [ProductListingApiController::class, 'previewWithCharges']);
                Route::post('cancel/{listingId}', [ProductListingApiController::class, 'cancelListing']);

                Route::put('packages/{packageId}', [ProductListingApiController::class, 'updatePackage']);
                Route::post('packages/cancel/{packageId}', [ProductListingApiController::class, 'deletePackage']);
            });

            // Cart Routes
            Route::prefix('cart')->group(function () {
                Route::get('active', [CartApiController::class, 'getActiveCart']);
                Route::post('item', [CartApiController::class, 'addItem']);
                Route::put('item/{cartItemId}', [CartApiController::class, 'updateItem']);
                Route::delete('item/{cartItemId}', [CartApiController::class, 'removeItem']);
                Route::delete('clear', [CartApiController::class, 'clearCart']);
            });


            Route::prefix('checkout')->group(function () {
                Route::get('preview', [CheckoutApiController::class, 'preview']);
                Route::get('confirm', [CheckoutApiController::class, 'confirm']);
            });



            //

            //
        });

        //
    });

    /**
     *  ADMIN
     */


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
            Route::prefix('listing')->group(function () {

                Route::get('/', [AdminProductListingApiController::class, 'getListings']);
                Route::get('/{id}', [AdminProductListingApiController::class, 'getListingDetails']);

                Route::post('create', [AdminProductListingApiController::class, 'createListing']);
                Route::post('cancel/{listingId}', [AdminProductListingApiController::class, 'cancelListing']);

                Route::put('packages/{packageId}', [AdminProductListingApiController::class, 'updatePackage']);
                Route::delete('packages/delete/{packageId}', [AdminProductListingApiController::class, 'deletePackage']);
            });


            // Payments
            Route::post('/payments/{payment_code}/reconcile', [PaymentReconcileApiController::class, 'reconcile']);
            Route::prefix('payouts')->group(function () {
                Route::get('/', [PayoutApiController::class, 'index']);
                Route::post('{payout}/approve', [PayoutApiController::class, 'approve']);
                Route::post('{payout}/fail', [PayoutApiController::class, 'fail']);
                Route::post('{payout}/reconcile', [PayoutApiController::class, 'reconcile']);
            });


            // Regular User Management
            Route::prefix('customer')->group(function () {
                Route::apiResource('customer', CustomerApiController::class); // Manage Regular user

                ## Customers Actions 
                Route::post('addDepot', [CustomerApiController::class, 'addDepot']);
                Route::delete('removeDepot/{userDepot}', [CustomerApiController::class, 'removeDepot']);
                //
            });

            ## Legal Actions
            Route::prefix('legal')->group(function () {

                Route::get('kyc', [CustomerLegalActionApiController::class, 'getKycList']);
                Route::get('kyc/{id}', [CustomerLegalActionApiController::class, 'getKycDetails']);
                Route::put('kyc/status/{id}', [CustomerLegalActionApiController::class, 'updateKycStatus']);

                // Route::get('legaldoc/list', [CustomerLegalActionApiController::class, 'getLegalDocumentList']);
                // Route::delete('legaldoc/delete/{documentId}', [CustomerLegalActionApiController::class, 'deleteLegalDocument']);
            });


            // Fulfillment Location Routes
            Route::apiResource('fulfillmentLocation', AdminFulfillmentLocationApiController::class);
            Route::post('fulfillmentLocation/addDepot', [AdminFulfillmentLocationApiController::class, 'addDepot']);
            Route::delete('fulfillmentLocation/removeDepot/{fulfillmentLocationDepot}', [AdminFulfillmentLocationApiController::class, 'removeDepot']);

            ###
            ##### Master  Routes
            Route::prefix('master')->group(function () {

                Route::apiResource('mstUnit', MstUnitApiController::class);
                Route::apiResource('mstPackType', MstPackTypeApiController::class);

                Route::apiResource('mstVehicle', MstVehicleApiController::class);

                Route::apiResource('mstState', MstStateApiController::class);
                Route::apiResource('mstZone', MstZoneApiController::class);

                // Depot Routes
                Route::apiResource('mstDepot', MstDepotApiController::class);
                Route::post('mstDepot/{depot}/address', [MstDepotApiController::class, 'saveAddress']); // Save address for depot
                Route::post('mstDepot/{depot}/uploadPicture', [MstDepotApiController::class, 'uploadPhoto']); // Upload photo for depot
                Route::delete('mstDepot/{depot}/deletePicture', [MstDepotApiController::class, 'deletePhoto']); // Delete photo for depot


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


                // Settings
                Route::prefix('setting')->group(function () {
                    // App Settings
                    Route::get('mstAppSetting', [MstAppSettingApiController::class, 'getSetting']);
                    Route::put('mstAppSetting', [MstAppSettingApiController::class, 'updateSetting']);

                    // Finance Setting
                    Route::get('mstFinanceSetting', [MstFinanceSettingApiController::class, 'getSetting']);
                    Route::put('mstFinanceSetting', [MstFinanceSettingApiController::class, 'updateSetting']);

                    // Payment Setting
                    Route::get('mstPaymentSetting', [MstPaymentSettingApiController::class, 'getSetting']);
                    Route::put('mstPaymentSetting', [MstPaymentSettingApiController::class, 'updateSetting']);

                    // Business Setting
                    Route::get('mstBusinessSetting', [MstBusinessSettingApiController::class, 'getSetting']);
                    Route::put('mstBusinessSetting', [MstBusinessSettingApiController::class, 'updateSetting']);

                    Route::post('mstBusinessSetting/updateBillAddress', [MstBusinessSettingApiController::class, 'saveBillAddress']); // Save address for business setting
                    Route::post('mstBusinessSetting/uploadPicture', [MstBusinessSettingApiController::class, 'uploadPhoto']); // Upload photo for business setting
                    Route::delete('mstBusinessSetting/deletePicture', [MstBusinessSettingApiController::class, 'deletePhoto']); // Delete photo for business setting

                    // Financial year Setting
                    Route::apiResource('mstFinancialYear', MstFinancialYearApiController::class);
                });
                //
            });


            //
        });


        //
    });


    //
});

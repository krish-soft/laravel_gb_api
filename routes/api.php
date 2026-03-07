<?php

use App\Http\Controllers\Api\v1\Admin\Buyer\Order\AdminOrderApiController;
use App\Http\Controllers\Api\v1\Admin\CmdAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Accounting\AccountAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Accounting\AccountLedgerAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserLoginApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserLogoutApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserRegisterApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Auth\AdminUserResetPasswordApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Fulfillment\AdminFulfillmentLocationApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\PaymentReconcileApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\PayoutApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Customer\CustomerApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Customer\CustomerLegalActionApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Invoice\InvoiceAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Common\Payment\AdminPaymentApiController;
use App\Http\Controllers\Api\v1\Admin\Market\MarketOrderAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Shipment\ShipmentPackageAdminApiController;
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
use App\Http\Controllers\Api\v1\Admin\Report\Order\OrderReportAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Seller\Product\AdminProductListingApiController;
use App\Http\Controllers\Api\v1\Admin\Settlement\SettlementAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Settlement\SettlementBatchAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Shipment\DriverShipmentAdminApiController;
use App\Http\Controllers\Api\v1\Admin\Shipment\ShipmentAdminApiController;
use App\Http\Controllers\Api\v1\User\Buyer\BuyerOrderApiController;
use App\Http\Controllers\Api\v1\User\Buyer\BuyerProductListingApiController;
use App\Http\Controllers\Api\v1\User\Buyer\CartApiController;
use App\Http\Controllers\Api\v1\User\Buyer\CheckoutApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserLoginApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserLogoutApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserRegisterApiController;
use App\Http\Controllers\Api\v1\User\Common\Auth\UserResetPasswordApiController;
use App\Http\Controllers\Api\v1\User\Common\BuyerSellerFollowerApiController;
use App\Http\Controllers\Api\v1\User\Common\EarningApiController;
use App\Http\Controllers\Api\v1\User\Common\Fulfillment\FulfillmentLocationApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserBankApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserKycApiController;
use App\Http\Controllers\Api\v1\User\Common\Legal\UserVehicleKycApiController;
use App\Http\Controllers\Api\v1\User\Common\RatingApiController;
use App\Http\Controllers\Api\v1\User\Common\Shipment\DriverShipmentApiController;
use App\Http\Controllers\Api\v1\User\Common\DriverApiController;
use App\Http\Controllers\Api\v1\User\Seller\Product\ProductListingApiController;
use App\Http\Controllers\Api\v1\User\UserProfileApiController;
use App\Http\Controllers\Api\v1\Utils\UtilsApiController;
use App\Http\Controllers\Api\v1\Utils\UtilsWithAuthApiController;
use App\Http\Controllers\Web\Webhooks\RazorpayBankVerificationWebhookHandler;
use App\Http\Controllers\Web\Webhooks\RazorpayPayoutWebhookController;
use App\Http\Controllers\Web\Webhooks\RazorpayWebhookController;
use App\Models\Common\Payment\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Public Payment Route
Route::post('/webhooks/razorpay/payments', [RazorpayWebhookController::class, 'handle']);
Route::post('/webhooks/razorpay/payouts', [RazorpayPayoutWebhookController::class, 'handle']);
Route::post('/webhooks/razorpay/bankAccount', [RazorpayBankVerificationWebhookHandler::class, 'handle']);

// Serve Private Files
// Route::get('/files/{path}', function ($path) {
//     abort_unless(Storage::disk('private')->exists($path), 404);

//     return response()->file(
//         Storage::disk('private')->path($path)
//     );
// })
//     ->where('path', '.*')
//     ->name('files.view')
//     ->middleware('signed');

Route::get('/files/{path}', function ($path) {

    abort_unless(Storage::disk('private')->exists($path), 404);

    $file = Storage::disk('private')->path($path);

    if (request()->boolean('download')) {
        return response()->download($file);
    }

    return response()->file($file);
})
    ->where('path', '.*')
    ->name('files.view')
    ->middleware('signed');


// routes/web.php or api.php
Route::get('/payment/status/{payment}', function (Request $request, string $payment) {

    if (!$request->hasValidSignature()) {
        abort(403);
    }

    $payment = Payment::where('payment_code', $payment)->firstOrFail();

    return response()->json([
        'status'       => $payment->status,       // initiated | paid | failed
        'order_number' => optional($payment->source())->order_number,
    ]);
})->name('payment.status');

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
    Route::post('/signin', [UserLoginApiController::class, 'login'])->name('login');

    // Forget Password
    Route::post('/forget/otp/send', [UserResetPasswordApiController::class, 'sendForgotPasswordOtp']);
    Route::post('/forget/reset', [UserResetPasswordApiController::class, 'resetPassword']);


    // Utils
    Route::prefix('utils')->group(function () {
        Route::get('states', [UtilsApiController::class, 'getStateList']);
        Route::get('app-meta', [UtilsApiController::class, 'getAppMetaInfo']);
        Route::get('enums', [UtilsApiController::class, 'getAlLEnums']);


        Route::get('markets', [UtilsApiController::class, 'getMarketList']);
        Route::get('units', [UtilsApiController::class, 'getUnitList']);
        Route::get('pack-type-units', [UtilsApiController::class, 'getPackTypeUnitList']);

        Route::get('products', [UtilsApiController::class, 'getProducts']);
        Route::get('products/variants/{productId}', [UtilsApiController::class, 'getProductVariants']);
        Route::get('products/packagings/{productId}', [UtilsApiController::class, 'getProductPackagings']);

        Route::get('platform-accounts', [UtilsApiController::class, 'getPlatformAccountsList']);
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
        Route::get('/kyc/signed-url', [UserKycApiController::class, 'signedUserKycUrl']); // Get signed URL for KYC form

        Route::post('/kyc/vehicle', [UserVehicleKycApiController::class, 'storeVehicleKyc'])->middleware('delivery-checker'); // Add Vehicle KYC
        Route::put('/kyc/vehicle/update', [UserVehicleKycApiController::class, 'updateVehicleKyc'])->middleware('delivery-checker'); // Update / Re-Vehicle KYC
        Route::get('/kyc/vehicle/signed-url', [UserVehicleKycApiController::class, 'signedVehicleKycUrl'])->middleware('delivery-checker'); // Get signed URL for Vehicle KYC form

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

            // Seller/Farmer Routes

            Route::prefix('seller')
                ->middleware([
                    'seller-checker' // Custom Middleware to check if user is seller
                ])
                ->group(function () {

                    // Product Listing Routes
                    Route::prefix('listing')->group(function () {
                        Route::get('/', [ProductListingApiController::class, 'getProductListing']);
                        Route::post('create', [ProductListingApiController::class, 'createListing']);
                        Route::post('preview-charge', [ProductListingApiController::class, 'previewWithCharges']);
                        Route::post('cancel/{listingId}', [ProductListingApiController::class, 'cancelListing']);

                        Route::put('packages/{packageId}', [ProductListingApiController::class, 'updatePackage']);
                        Route::post('packages/cancel/{packageId}', [ProductListingApiController::class, 'deletePackage']);
                    });

                    //
                });


            // Buyer/Trader Routes
            Route::prefix('buyer')->middleware([
                'buyer-checker' // Custom Middleware to check if user is buyer
            ])->group(function () {

                Route::get('products', [BuyerProductListingApiController::class, 'getBuyerProductSummary']);
                Route::get('products/package/details/{productId}', [BuyerProductListingApiController::class, 'getBuyerProductPackages']);

                // Cart Routes
                Route::prefix('cart')->group(function () {
                    Route::get('active', [CartApiController::class, 'getActiveCart']);
                    Route::post('item', [CartApiController::class, 'addItem']);
                    Route::put('item/{cartItemId}', [CartApiController::class, 'updateItem']);
                    Route::delete('item/{cartItemId}', [CartApiController::class, 'removeItem']);
                    Route::delete('clear', [CartApiController::class, 'clearCart']);
                });

                // Checkout Routes
                Route::prefix('checkout')->group(function () {
                    Route::get('preview', [CheckoutApiController::class, 'preview']);
                    Route::post('confirm', [CheckoutApiController::class, 'confirm']);
                });

                Route::prefix('order')->group(function () {
                    Route::get('list', [BuyerOrderApiController::class, 'getBuyerOrders']);
                    Route::get('details/{orderId}', [BuyerOrderApiController::class, 'getBuyerOrderDetails']);
                    Route::get('shipment-packages/{orderId}', [BuyerOrderApiController::class, 'getOrderShipmentPackages']);
                });

                //
            });

            Route::prefix('delivery')->middleware([
                'delivery-checker' // Custom Middleware to check if user is delivery
            ])->group(function () {

                // Vehicle addition pending & images
                // make driver online offlie
                Route::prefix('driver')->group(function () {
                    Route::get('online-status', [DriverApiController::class, 'getDriverOnlineOfflineStatus']);
                    Route::post('online-status/update', [DriverApiController::class, 'updateDriverOnlineOffline']);
                });

                Route::prefix('shipment')->group(function () {
                    // Driver Shipment Routes
                    Route::get('list/need-to-deliver', [DriverShipmentApiController::class, 'getDeliverShipments']);
                    Route::get('list/all', [DriverShipmentApiController::class, 'getAllShipments']);
                    Route::get('details/{driverShipment}', [DriverShipmentApiController::class, 'shipmentDetails']);

                    Route::post('accept/{driverShipment}', [DriverShipmentApiController::class, 'accept']);
                    Route::post('reject/{driverShipment}', [DriverShipmentApiController::class, 'reject']);
                    Route::post('start/{driverShipment}', [DriverShipmentApiController::class, 'start']);
                    Route::post('complete/{driverShipment}', [DriverShipmentApiController::class, 'complete']);

                    // Route::post('update/shipment-Package/status', [DriverShipmentApiController::class, 'updateShipmentPackageStatus']);            
                    Route::post('package/update-status/buyer', [DriverShipmentApiController::class, 'updateShipmentPackageBuyerStatus']);
                    Route::post('package/update-status/seller', [DriverShipmentApiController::class, 'updateShipmentPackageSellerStatus']);
                    Route::post('package/update-status/transfer', [DriverShipmentApiController::class, 'updateShipmentPackageTransferStatus']);
                });

                //
            });

            // Common for users 
            Route::prefix('earnings')->group(function () {

                //
                Route::get('/', [EarningApiController::class, 'getEarningsData']);
            });


            Route::prefix('ratings')->group(function () {

                // Route::get('/', [RatingApiController::class, 'getRatings']); // not used yet
                Route::post('/order', [RatingApiController::class, 'giveOrderRating']);
                Route::post('/driver', [RatingApiController::class, 'giveDriverRating']);
                Route::post('/seller', [RatingApiController::class, 'giveSellerRating']);
                Route::post('/buyer', [RatingApiController::class, 'giveBuyerRating']);
                //
            });


            Route::prefix('followers')->group(function () {

                Route::post('/follow', [BuyerSellerFollowerApiController::class, 'followSeller']);
                Route::post('/unfollow', [BuyerSellerFollowerApiController::class, 'unfollowSeller']);

                Route::get('/list', [BuyerSellerFollowerApiController::class, 'listFollowedSellers']);
            });

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

            Route::prefix('order')->group(function () {
                Route::get('/', [AdminOrderApiController::class, 'getOrdersList']);
                Route::get('/{orderId}', [AdminOrderApiController::class, 'getOrderDetails']);
                //
            });


            Route::prefix('market-order')->group(function () {
                Route::get('/', [MarketOrderAdminApiController::class, 'getOrdersList']);
                Route::get('/{orderId}', [MarketOrderAdminApiController::class, 'getOrderDetails']);

                Route::put('/status/{orderId}', [MarketOrderAdminApiController::class, 'updateOrderStatus']);
                Route::put('/order-amount/{orderId}', [MarketOrderAdminApiController::class, 'updateOrderAmountData']);

                Route::post('/upload-document/{orderId}', [MarketOrderAdminApiController::class, 'uploadOrderDocument']);
                Route::delete('/delete-document/{documentId}', [MarketOrderAdminApiController::class, 'deleteOrderDocument']);

                //
            });

            Route::apiResource('invoice', InvoiceAdminApiController::class);


            // Razorpay Payments  
            Route::prefix('payment')->group(function () {
                Route::get('/', [AdminPaymentApiController::class, 'getPaymentsList']);
                Route::get('/{paymentId}', [AdminPaymentApiController::class, 'getPaymentDetails']);
                Route::post('/reconcile', [PaymentReconcileApiController::class, 'reconcile']);
            });


            // Razorpay Payouts
            Route::prefix('payouts')->group(function () {
                Route::get('/', [PayoutApiController::class, 'index']);
                Route::post('{payout}/approve', [PayoutApiController::class, 'approve']);
                Route::post('{payout}/fail', [PayoutApiController::class, 'fail']);
                Route::post('{payout}/reconcile', [PayoutApiController::class, 'reconcile']);
            });

            // Accounting 
            Route::prefix('accounting')->group(function () {

                Route::get('summary', [AccountAdminApiController::class, 'summary']);
                Route::apiResource('account', AccountAdminApiController::class);
                Route::apiResource('ledger', AccountLedgerAdminApiController::class);
                Route::get('ledger/reverse/{ledgerId}', [AccountLedgerAdminApiController::class, 'reverseLedger']);
                Route::get('ledger/status/settle/{ledgerId}', [AccountLedgerAdminApiController::class, 'markSettled']);

                // Settlement Preview
                Route::prefix('settlement')->group(function () {
                    // Settlement Batch Creation 
                    Route::get('preview', [SettlementAdminApiController::class, 'getPayoutSettlementPreview']);
                    Route::post('create-batch', [SettlementAdminApiController::class, 'createSettlementBatch']);

                    // Settlement Batch Management
                    Route::get('batch', [SettlementBatchAdminApiController::class, 'getSettlementBatchList']);
                    Route::get('batch/{id}', [SettlementBatchAdminApiController::class, 'getSettlementBatchDetails']);
                    Route::get('account-bank-details/{settlementAccountId}', [SettlementBatchAdminApiController::class, 'getAccountBankDetails']);
                    Route::put('account/status/{settlementAccountId}', [SettlementBatchAdminApiController::class, 'changeSettlementAccountStatus']);
                });
            });


            // Regular User Management
            Route::prefix('customer')->group(function () {
                Route::apiResource('customer', CustomerApiController::class); // Manage Regular user

                Route::get('/search', [CustomerApiController::class, 'searchCustomerAutocomplete']); // Manage Regular user

                Route::post('/address', [CustomerApiController::class, 'saveAddress']); // Manage Regular user
                Route::post('/billingAddress', [CustomerApiController::class, 'saveBillingAddress']); // 

                ## Customers Actions 
                Route::post('addDepot', [CustomerApiController::class, 'addDepot']);
                Route::delete('removeDepot/{userDepot}', [CustomerApiController::class, 'removeDepot']);


                ## Others for searching
                Route::post('/details-by-code', [CustomerApiController::class, 'getUserDataByCode']);

                //
            });

            ## Legal Actions
            Route::prefix('legal')->group(function () {

                Route::get('kyc', [CustomerLegalActionApiController::class, 'getKycList']);
                Route::post('kyc/create', [CustomerLegalActionApiController::class, 'addNewKyc']);
                Route::get('kyc/{id}', [CustomerLegalActionApiController::class, 'getKycDetails']);
                Route::put('kyc/status/{id}', [CustomerLegalActionApiController::class, 'updateKycStatus']);

                // Route::get('legaldoc/list', [CustomerLegalActionApiController::class, 'getLegalDocumentList']);
                // Route::delete('legaldoc/delete/{documentId}', [CustomerLegalActionApiController::class, 'deleteLegalDocument']);

                Route::get('kyc/vehicle/list', [CustomerLegalActionApiController::class, 'getVehicleKycList']);
                Route::get('kyc/vehicle/details/{id}', [CustomerLegalActionApiController::class, 'getVehicleKycDetails']);
                Route::put('kyc/vehicle/status/{id}', [CustomerLegalActionApiController::class, 'updateVehicleKycStatus']);
            });


            // Fulfillment Location Routes
            Route::apiResource('fulfillmentLocation', AdminFulfillmentLocationApiController::class);
            Route::post('fulfillmentLocation/addDepot', [AdminFulfillmentLocationApiController::class, 'addDepot']);
            Route::delete('fulfillmentLocation/removeDepot/{fulfillmentLocationDepot}', [AdminFulfillmentLocationApiController::class, 'removeDepot']);

            Route::prefix('shipping')->group(function () {

                Route::get('summary', [ShipmentPackageAdminApiController::class, 'summaryReport']);
                Route::apiResource('shipmentPackage', ShipmentPackageAdminApiController::class)->only(['index', 'show']);
                Route::put('shipmentPackage/status/{shipmentPackage}', [ShipmentPackageAdminApiController::class, 'updateStatus']);

                // Shipment and Groups Management
                Route::post('shipment-generate-package-groups', [ShipmentAdminApiController::class, 'generateShipmentAndGroups']);
                Route::apiResource('shipment', ShipmentAdminApiController::class)->only(['index', 'show']);

                Route::post('shipments/split-group', [ShipmentAdminApiController::class, 'splitGroup']);
                Route::post('shipments/move-package', [ShipmentAdminApiController::class, 'movePackage']);

                Route::post('shipments/merge-groups', [ShipmentAdminApiController::class, 'mergeGroups']);
                Route::post('shipments/merge-shipments', [ShipmentAdminApiController::class, 'mergeShipments']);

                Route::post('shipments/rebuild/{shipment}', [ShipmentAdminApiController::class, 'rebuildShipment']);
                Route::get('shipment-groups/{groupNumber}', [ShipmentAdminApiController::class, 'getGroupPackages']);

                // Driver and Vehicle Assignment
                Route::post('assign-shipment-to-driver', [DriverShipmentAdminApiController::class, 'assignDriver']);
                Route::post('change-driver-shipment/{driverShipment}', [DriverShipmentAdminApiController::class, 'changeDriver']);
                Route::post('cancel-driver-shipment/{driverShipment}', [DriverShipmentAdminApiController::class, 'cancel']);
                Route::get('drivers', [DriverShipmentAdminApiController::class, 'getDriversWithAvailableVehicles']);
                Route::get('driver-shipments', [DriverShipmentAdminApiController::class, 'getDriverShipments']);

                //  
            });

            Route::prefix('cmd')->group(function () {

                // Cutoff Command
                Route::post('cutoff/product-listing', [CmdAdminApiController::class, 'cmdCutoffProductListing']);

                // accounting commands        
                Route::post('accounting/order', [CmdAdminApiController::class, 'cmdAccountingOrder']);
                Route::post('accounting/market-order', [CmdAdminApiController::class, 'cmdAccountingMarketOrder']);
                Route::post('accounting/invoice', [CmdAdminApiController::class, 'cmdAccountingInvoice']);

                // Invoice Generation Commands
                Route::post('invoice/product-listing', [CmdAdminApiController::class, 'cmdProductListingInvoiceGeneration']);
                Route::post('invoice/buyer-order', [CmdAdminApiController::class, 'cmdBuyerOrderInvoiceGeneration']);

                //
            });

            ## Report
            Route::prefix('report')->group(function () {

                Route::get('orders-by-depot', [OrderReportAdminApiController::class, 'getOrdersReportByDepot']);



                //

                //
            });

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

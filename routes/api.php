<?php

use App\Http\Controllers\Api\v1\Admin\Auth\AdminUserLoginApiController;
use App\Http\Controllers\Api\v1\Admin\Auth\AdminUserLogoutApiController;
use App\Http\Controllers\Api\v1\Admin\Auth\AdminUserRegisterApiController;
use App\Http\Controllers\Api\v1\Admin\Auth\AdminUserResetPasswordApiController;
use App\Http\Controllers\Api\v1\User\Auth\UserLoginApiController;
use App\Http\Controllers\Api\v1\User\Auth\UserLogoutApiController;
use App\Http\Controllers\Api\v1\User\Auth\UserRegisterApiController;
use App\Http\Controllers\Api\v1\User\Auth\UserResetPasswordApiController;
use App\Http\Controllers\Api\v1\User\Legal\UserKycApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::group([
    'prefix' => 'v1',
    'middleware' => [
        'ms-api-key-checker', // Custom Middleware to check microservice API key
        'app-checker', // Custom Middleware to check app status     
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


        // Whihc Required KYC Approved User Only
        Route::group([
            'middleware' => [
                'user-legal-checker' // Custom Middleware to check user legal (KYC) status

            ]
        ], function () {



            Route::get('user', function (Request $request) {
                return $request->user();
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


            Route::get('user', function (Request $request) {
                return $request->user();
            });
            //
        });


        //
    });




    //
});

<?php

use App\Http\Controllers\Web\Payment\PaymentPageController;
use App\Http\Controllers\Web\User\UserKycWebController;
use App\Http\Controllers\Web\User\UserVehicleKycWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/pay/{payment_code}', [PaymentPageController::class, 'pay'])
    ->name('payment.page')
    ->middleware('signed');


// User KYC Routes
Route::get(
    '/user-kyc/form',
    [UserKycWebController::class, 'showForm']
)->middleware('signed')->name('user.kyc.form');

Route::post(
    '/user-kyc/submit',
    [UserKycWebController::class, 'submitForm']
)->name('user.kyc.submit');


// Vehicle KYC Routes
Route::get(
    '/vehicle-kyc/form',
    [UserVehicleKycWebController::class, 'showForm']
)->middleware('signed')->name('vehicle.kyc.form');


Route::post(
    '/vehicle-kyc/submit',
    [UserVehicleKycWebController::class, 'submitForm']
)->name('vehicle.kyc.submit');

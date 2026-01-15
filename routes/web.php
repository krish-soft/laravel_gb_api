<?php

use App\Http\Controllers\Web\Payment\PaymentPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/pay/{payment_code}', [PaymentPageController::class, 'pay'])
    ->name('payment.page')
    ->middleware('signed');

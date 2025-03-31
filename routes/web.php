<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CheckOutController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/zarinpal-payment-callback', [CheckOutController::class, 'zarinpal_payment_callback'])->name('zarinpal-payment-callback');
Route::get('/zarinpal-success-payment/{referenceId}', [CheckOutController::class, 'zarinpal_success_payment'])->name('zarinpal-success-payment');
Route::get('/zarinpal-unsuccess-payment/{exception}', [CheckOutController::class, 'zarinpal_unsuccess_payment'])->name('zarinpal-unsuccess-payment');
Route::get('/bad-request', [CheckOutController::class, 'bad_request'])->name('bad-request');

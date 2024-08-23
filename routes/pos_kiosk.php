<?php

use App\Http\Controllers\Api\Device\DeviceApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//Mobile API splash11
Route::post('splash', '\App\Http\Controllers\Api\Device\DeviceApiController@splash');
Route::post('devicelogin', '\App\Http\Controllers\Api\Device\DeviceApiController@login');

Route::post('operator_login', '\App\Http\Controllers\Api\Device\DeviceApiController@operator_login');
Route::post('splash2', '\App\Http\Controllers\Api\Device\DeviceApiController@splash2');

Route::group(['middleware'=>'auth:operator','encryption'],function (){

    Route::post('otp_verification', '\App\Http\Controllers\Api\Device\DeviceApiController@otp_verification');
    Route::post('otp_verification', '\App\Http\Controllers\Api\Device\DeviceApiController@otp_verification');
    Route::get('automotora_prices', '\App\Http\Controllers\Api\Device\DeviceApiController@automotora_prices');
    Route::get('normal_prices', '\App\Http\Controllers\Api\Device\DeviceApiController@normal_prices');
    Route::post('scanning', '\App\Http\Controllers\Api\Device\DeviceApiController@scanning');
    Route::post('changepassword', '\App\Http\Controllers\Api\Device\DeviceApiController@changepassword');
    Route::post('online_transaction', '\App\Http\Controllers\Api\Device\DeviceApiController@online_transaction');
    Route::post('online_card_transaction', '\App\Http\Controllers\Api\Device\DeviceApiController@online_card_transaction');
    Route::post('summary_verification', '\App\Http\Controllers\Api\Device\DeviceApiController@summary_verification');
    Route::post('reversal_transactions', '\App\Http\Controllers\Api\Device\DeviceApiController@reversal_transactions');
    Route::post('insert_summary_logs', '\App\Http\Controllers\Api\Device\DeviceApiController@insert_summary_logs');
    Route::post('offline_transactions', '\App\Http\Controllers\Api\Device\DeviceApiController@offline_transaction');
    Route::get('transaction_details', '\App\Http\Controllers\Api\Device\DeviceApiController@transaction_details');
    Route::get('transaction_topup_details', '\App\Http\Controllers\Api\Device\DeviceApiController@transaction_topup_details');
    Route::post('operator_transaction_scanning', '\App\Http\Controllers\Api\Device\DeviceApiController@operator_transaction_scanning');
    Route::post('normal_transaction', '\App\Http\Controllers\Api\Device\DeviceApiController@normal_transaction');
    Route::post('operator_summary', '\App\Http\Controllers\Api\Device\DeviceApiController@operator_summary');
    Route::post('customer_registration', '\App\Http\Controllers\Api\Device\DeviceApiController@customer_registration');
    Route::post('report_incident', '\App\Http\Controllers\Api\Device\DeviceApiController@report_incident');
    Route::post('operator_collection', '\App\Http\Controllers\Api\Device\DeviceApiController@operator_collection');
    Route::post('card_topup', '\App\Http\Controllers\Api\Device\DeviceApiController@card_topup');
//    Route::resource('automotora', 'AutomotoraPriceController');
//    Route::resource('normalprice', 'NormalPriceController');
    Route::post('authentication', '\App\Http\Controllers\Api\Device\DeviceApiController@authentication');

//    Route::post('allocate_seat', 'Api\Operator\OperatorAllocationController@allocate_seat');
    Route::post('train_layout', '\App\Http\Controllers\Api\Device\DeviceApiController@train_layout');
    Route::post('card_transactions', '\App\Http\Controllers\Api\Device\DeviceApiController@card_transactions');
    Route::post('packages', '\App\Http\Controllers\Api\Device\DeviceApiController@packages');

    Route::post('deviceLogout', [DeviceApiController::class, 'deviceLogout']);



});




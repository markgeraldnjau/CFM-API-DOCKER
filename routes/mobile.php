<?php

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
Route::prefix('mobile')->group(function () {
    Route::post('/login', [\App\Http\Controllers\App\Auth\AuthController::class, 'login'])
        ->name('login');

    Route::middleware(['mobile.auth'])->group(function () {
        Route::post('/twoFactorAuth', [\App\Http\Controllers\App\Auth\TwoFactorAuthController::class, 'confirm'])
            ->name('twoFactorAuth.confirm');
        Route::get('/twoFactorAuth/resend', [\App\Http\Controllers\App\Auth\TwoFactorAuthController::class, 'resend'])
            ->name('twoFactorAuth.resend');
        Route::post('/change/pin', [\App\Http\Controllers\App\Auth\TwoFactorAuthController::class, 'changePin'])
            ->name('change.pin');
        Route::get('/logout', [\App\Http\Controllers\App\Auth\TwoFactorAuthController::class, 'logout'])
            ->name('logout');
    });

    Route::middleware(['mobile.auth', '2fa'])->group(function () {

        //Customer Card
        Route::get('/customer/card', [\App\Http\Controllers\App\Cards\CardController::class, 'getCardDetails'])
            ->name('customer.card');

        //Customer Tickets
        Route::get('/customer/tickets', [\App\Http\Controllers\App\Tickets\TicketController::class, 'getTicketsDetails'])
            ->name('customer.tickets');
        Route::get('/customer/metrics', [\App\Http\Controllers\App\Tickets\TicketController::class, 'getCustomerMetrics'])
            ->name('customer.metrics');

        //Train Lines
        Route::get('/train/lines', [\App\Http\Controllers\App\Trains\TrainDetailsController::class, 'getTrainLines']);
        Route::get('/train/routes', [\App\Http\Controllers\App\Trains\TrainDetailsController::class, 'getTrainRoutes']);
        Route::get('/train/directions', [\App\Http\Controllers\App\Trains\TrainDetailsController::class, 'getTrainDirections']);
        Route::get('/train/line/stops', [\App\Http\Controllers\App\Trains\TrainDetailsController::class, 'getTrainLineStops']);
        Route::get('/available/trains', [\App\Http\Controllers\App\Trains\TrainDetailsController::class, 'getAvailableTrains']);


        //News and Updates
        Route::get('/news-updates', [\App\Http\Controllers\App\News\NewsController::class, 'getAllNews']);

        // Push notifications
        Route::get('/notifications', [\App\Http\Controllers\App\FireBase\NotificationController::class, 'getCustomerNotifications']);
        Route::get('/notification/details/{token}', [\App\Http\Controllers\App\FireBase\NotificationController::class, 'getNotificationInfo']);

        //Information
        Route::get('/about-us', [\App\Http\Controllers\App\News\NewsController::class, 'aboutUs']);
        Route::get('/contact-us', [\App\Http\Controllers\App\News\NewsController::class, 'contactUs']);
        Route::get('/services-page', [\App\Http\Controllers\App\News\NewsController::class, 'services']);


        Route::get('/test/push/notification', [\App\Http\Controllers\App\FireBase\NotificationController::class, 'sendPushNotification'])->name('test.push.notification');

        Route::get('train_layout', '\App\Http\Controllers\Api\Device\DeviceApiController@train_layout');
        Route::get('card_transactions', '\App\Http\Controllers\Api\Device\DeviceApiController@card_transactions');
        Route::get('packages', '\App\Http\Controllers\Api\Device\DeviceApiController@packages');

        Route::post('online_mobile_transaction', '\App\Http\Controllers\Api\Device\DeviceApiController@online_mobile_transaction');
        Route::post('pin_validation', '\App\Http\Controllers\Api\Device\DeviceApiController@pin_validation');

        Route::get('transfer_verification', '\App\Http\Controllers\Api\Device\DeviceApiController@transfer_verification');

        Route::post('balance_transfer', '\App\Http\Controllers\Api\Device\DeviceApiController@balance_transfer');

        Route::post('customer_registration', '\App\Http\Controllers\Api\Device\DeviceApiController@mobile_customer_registration');

    });

});




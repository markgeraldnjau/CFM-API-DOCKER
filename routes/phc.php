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
Route::prefix('phc')->group(function () {
    Route::post('/login', [\App\Http\Controllers\PHC\AuthenticationController::class, 'login'])->name('login');

    Route::middleware(['auth:api_user'])->group(function () {
        Route::get('/me', [\App\Http\Controllers\PHC\AuthenticationController::class, 'me'])->name('me');
        Route::post('/refresh/session', [\App\Http\Controllers\PHC\AuthenticationController::class, 'refresh'])->name('refresh');
        Route::post('/logout', [\App\Http\Controllers\PHC\AuthenticationController::class, 'logout'])->name('logout');

        Route::post('/ticket_transactions', [\App\Http\Controllers\PHC\TransactionController::class, 'ticketTransactions'])->name('ticket.transactions');
        Route::post('/cargo_transactions', [\App\Http\Controllers\PHC\TransactionController::class, 'cargoTransactions'])->name('cargo.transactions');
        Route::post('/summary_details', [\App\Http\Controllers\PHC\TransactionController::class, 'summaryDetails'])->name('summary.details');
    });

});




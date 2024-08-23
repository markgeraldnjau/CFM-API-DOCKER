<?php

use Illuminate\Http\Request;
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

//App Login Apis

//Route::post('login', [App\Http\Controllers\Api\Auth\AuthenticatedSessionController::class, 'login']);

Route::post('captcha/generate', [\App\Http\Controllers\Api\CaptchaController::class, 'generateCaptcha']);
Route::post('captcha/validate', [\App\Http\Controllers\Api\CaptchaController::class, 'validateCaptcha']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();

});




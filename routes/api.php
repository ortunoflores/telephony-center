<?php

use App\Constants\RouteNames;
use App\Http\Controllers\Api\Twilio\CallController;
use App\Http\Middleware\Api\ValidateTwilioRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/**
 * Incoming calls Telephony Support
 */
Route::prefix('call')->middleware(ValidateTwilioRequest::class)->group(function(Router $route) {
    $route->any('incoming-call', [CallController::class, 'handleIncomingCall'])->name(RouteNames::API_TWILIO_CALL_INCOMING);
});

<?php

use App\Constants\RouteNames;
use App\Http\Controllers\Api\Twilio\CallController;
use App\Http\Middleware\Api\ValidateTwilioRequest;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

/**
 * Incoming calls Telephony Support
 */
Route::prefix('call')->middleware(ValidateTwilioRequest::class)->group(function(Router $route) {
    $route->any('/incoming', [CallController::class, 'handleIncomingCall'])->name(RouteNames::API_TWILIO_CALL_INCOMING);
    $route->any('/keypress', [CallController::class, 'handleKeypress'])->name(RouteNames::API_TWILIO_CALL_KEYPRESS);
    $route->any('/voicemail', [CallController::class, 'handleVoicemail'])->name(RouteNames::API_TWILIO_CALL_VOICEMAIL);
    $route->any('/no-answer', [CallController::class, 'handleNoAnswer'])->name(RouteNames::API_TWILIO_CALL_NO_ANSWER);
});




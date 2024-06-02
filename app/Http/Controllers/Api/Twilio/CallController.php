<?php

namespace App\Http\Controllers\Api\Twilio;

use App;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Twilio\TwiML\VoiceResponse;

class CallController extends Controller
{
    private string $description = 'Success call';
    const VOICE_MAIL_BILLING = 'https://fernando.s3.amazonaws.com/audio/voicemail/sound.mp3';

    /**
     * @param Request $request
     * @return VoiceResponse
     */
    public function handleIncomingCall(Request $request)
    {
        $toNumber = $request->input('To');
        $toNumber = self::clean_number($toNumber);

        $response = new VoiceResponse();
        $dial = $response->dial("70364107", [
            'record' => true,
            'trim' => 'trim-silence',
            'timeout' => 50,
            'action' => Config::get('app.URL_APP_PROD'). BASE_URL_COMUNICATIONS_CALL . 'incoming_call_voicemail_support',
        ]);

        $dial->setTimeout(0);
        $dial->number();

        return $response;
    }


}

<?php

namespace App\Http\Controllers\Api\Twilio;

use App;
use App\Constants\RouteNames;
use App\Http\Controllers\Controller;
use App\Services\Twilio\RestClient;
use Illuminate\Http\Request;
use Twilio\Exceptions\TwilioException;
use Twilio\TwiML\VoiceResponse;

class CallController extends Controller
{
    private int        $cleanAgentNumber  = 3029300705;
    private int        $cleanTwilioNumber = 4235893616;
    private RestClient $client;

    public function __construct(RestClient $client)
    {
        $this->client = $client;
    }

    public function handleIncomingCall()
    {
        $response = new VoiceResponse();
        $gather   = $response->gather([
            'numDigits' => 1,
            'action'    => route(RouteNames::API_TWILIO_CALL_KEYPRESS),
            'method'    => 'POST',
        ]);
        $gather->say('Press 1 to speak to an agent, or press 2 to leave a voicemail.');

        return response($response, 200)->header('Content-Type', 'text/xml');
    }

    private function processCall(VoiceResponse $voiceResponse): void
    {
        $dial = $voiceResponse->dial('', [
            'record'  => 'record-from-answer-dual',
            'trim'    => 'trim-silence',
            'timeout' => 15,
            'action'  => route(RouteNames::API_TWILIO_CALL_NO_ANSWER),
        ]);

        $dial->number($this->cleanAgentNumber);
    }

    private function processVoiceMail(VoiceResponse $voiceResponse): void
    {
        $voiceResponse->say('Please leave a message after the beep. Press * key to finish.');
        $voiceResponse->record([
            'action'      => route(RouteNames::API_TWILIO_CALL_VOICEMAIL),
            'maxLength'   => 60,
            'finishOnKey' => '*',
        ]);
    }

    public function handleNoAnswer(Request $request)
    {
        $callStatus = $request->input('DialCallStatus');
        $response   = new VoiceResponse();
        if ($callStatus === 'no-answer' || $callStatus === 'busy') {
            $this->processVoiceMail($response);
        }

        return response($response, 200)->header('Content-Type', 'text/xml');
    }

    public function handleKeypress(Request $request)
    {
        $digits   = $request->input('Digits');
        $response = new VoiceResponse();

        if ($digits == '1') {
            $this->processCall($response);
        } elseif ($digits == '2') {
            $this->processVoiceMail($response);
        } else {
            $response->say('Invalid input. Please try again.');
            $response->redirect(route(RouteNames::API_TWILIO_CALL_INCOMING), ['method' => 'POST']);
        }

        return response($response, 200)->header('Content-Type', 'text/xml');
    }

    public function handleVoicemail(Request $request)
    {
        $recordingUrl = $request->input('RecordingUrl');
        $response     = new VoiceResponse();

        try {
            $this->client->messages->create($this->cleanAgentNumber, [
                'from' => $this->cleanTwilioNumber,
                'body' => 'You have a new voicemail: ' . $recordingUrl,
            ]);

            $response->say('Thank you for your message. Goodbye!');
        } catch (TwilioException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }

        return response($response, 200)->header('Content-Type', 'text/xml');
    }
    /*    public function handleSms(Request $request)
        {
            $recordingStatus = $request->input('RecordingStatus');

            $recordingUrl = $request->input('RecordingUrl');
            $response     = new MessagingResponse();
            if ($recordingStatus === 'completed') {
                try {
                    $this->client->messages->create($this->cleanAgentNumber, [
                        'from' => $this->cleanTwilioNumber,
                        'body' => 'You have a new voicemail: ' . $recordingUrl,
                    ]);
                } catch (TwilioException $e) {
                    return response()->json(['error' => $e->getMessage()], $e->getCode());
                }
            }

            return response($response, 200)->header('Content-Type', 'text/xml');
        }*/
}

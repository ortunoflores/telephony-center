<?php

namespace Tests\Feature\Api\Twilio\Call;

use App\Constants\RouteNames;
use App\Http\Controllers\Api\Twilio\CallController;
use App\Services\Twilio\RestClient;
use Illuminate\Support\Facades\Request;
use Twilio\Rest\Client;
use Mockery;
use Tests\TestCase;

/** @see CallController */
class voicemailTest extends TestCase
{
    private string $routeName = RouteNames::API_TWILIO_CALL_VOICEMAIL;
    protected      $twilioMock;
    protected      $callController;

    public function setUp(): void
    {
        parent::setUp();

        $this->twilioMock = Mockery::mock(RestClient::class);
        $this->app->instance(Client::class, $this->twilioMock);
        $this->callController = new CallController($this->twilioMock);
    }

    /** @test */
    public function it_sends_Twilio_sms_with_voicemail()
    {
        $mockMessageList            = Mockery::mock('\Twilio\Rest\Api\V2010\Account\MessageList');
        $recordingUrl               = 'https://api.twilio.com/Recordings/RE0bb5e0ab6f80dfe7be0f97b9d96384a1';
        $request                    = Request::create($this->routeName, 'POST', ['RecordingUrl' => $recordingUrl]);
        $cleanAgentNumber           = 3029300705;
        $cleanCallerNumber          = 4235893616;
        $accountSid                 = 'loremaccounSid';
        $this->twilioMock->messages = $mockMessageList;

        $this->twilioMock->shouldReceive('getAccountSid')->andReturn($accountSid);
        $this->twilioMock->shouldReceive('request')->andReturn($this->callController);

        $this->twilioMock->messages->shouldReceive('create')->once()->with($cleanAgentNumber, [
            'from' => $cleanCallerNumber,
            'body' => 'You have a new voicemail: ' . $recordingUrl,
        ]);

        $response = $this->callController->handleVoicemail($request);

        $this->assertEquals(200, $response->status());
        $this->assertEquals('text/xml', $response->headers->get('content-type'));
        $this->assertStringContainsString('Thank you for your message. Goodbye!', $response->getContent());
    }
}

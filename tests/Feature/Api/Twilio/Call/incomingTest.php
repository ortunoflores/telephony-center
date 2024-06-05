<?php

namespace Tests\Feature\Api\Twilio\Call;

use App\Constants\RouteNames;
use App\Http\Middleware\Api\ValidateTwilioRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Api\WithTwilioMiddlewares;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

/** @see CallController */
class incomingTest extends TestCase
{
    use WithTwilioMiddlewares;
    use RefreshDatabase;

    private string           $token     = 'valid';
    private string           $routeName = RouteNames::API_TWILIO_CALL_INCOMING;
    private RequestValidator $requestValidator;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('twilio.auth_token', $this->token);
        $this->requestValidator = new RequestValidator($this->token);
    }

    /** @test */
    public function it_generates_a_correct_dial_twiml()
    {
        $data = [
            'from'          => 'Key3432',
            'ParentCallSid' => 'CA123',
            'to'            => '343545',
        ];

        $response = $this->postToTwilio($data);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertValidXML($response->content());

        $rawTwiml    = simplexml_load_string($response->getContent());
        $objectTwiml = json_decode(json_encode($rawTwiml));
        $this->assertEquals('Response', $rawTwiml->getName());

        $this->assertValidSchema($this->successSchema(), $objectTwiml);
    }

    private function successSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'Gather' => [
                    'type'       => 'object',
                    'properties' => [
                        '@attributes' => [
                            'type'       => 'object',
                            'properties' => [
                                'numDigits' => ['type' => 'string'],
                                'action'    => ['type' => ['string']],
                                'method'    => ['type' => ['string']],
                            ],
                            'required'   => ['numDigits', 'action', 'method'],
                        ],
                        'Say'         => ['type' => ['string']],
                    ],
                    'required'   => ['@attributes'],
                ],

            ],
            'required'   => ['Gather'],
        ];
    }

    private function postToTwilio(array $data = []): TestResponse
    {
        $signature = $this->requestValidator->computeSignature(URL::route($this->routeName), $data);

        return $this->withHeaders([
            ValidateTwilioRequest::TWILIO_HEADER_NAME => $signature,
        ])->post(URL::route($this->routeName), $data);
    }
}

<?php

namespace Tests\Unit\Http\Middleware\Api;

use App\Http\Middleware\Api\ValidateTwilioRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

class ValidateTwilioRequestTest extends TestCase
{
    private array $requestData = [
        'CallSid' => 'CA1234567890ABCDE',
        'Caller'  => '+12349013030',
        'Digits'  => '1234',
        'From'    => '+12349013030',
        'To'      => '+18005551212',
    ];
    private const VALID_URL   = "https://localhost/twilio";
    private const INVALID_URL = "https://localhost/invalid";
    private string $token = 'valid';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('twilio.auth_token', $this->token);
    }

    /** @test */
    public function it_should_reject_an_incorrect_request()
    {
        $validRoute   = self::VALID_URL . '?' . Arr::query($this->requestData);
        $invalidRoute = self::INVALID_URL . '?' . Arr::query($this->requestData);

        $validator  = new RequestValidator($this->token);
        $signature  = $validator->computeSignature($invalidRoute);
        $middleware = new ValidateTwilioRequest;

        $request = Request::create($validRoute);
        $request->headers->set(ValidateTwilioRequest::TWILIO_HEADER_NAME, $signature);
        $response = $middleware->handle($request, function() {
        });

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $request = Request::create(self::VALID_URL, 'POST', $this->requestData);
        $request->headers->set(ValidateTwilioRequest::TWILIO_HEADER_NAME, $signature);
        $response = $middleware->handle($request, function() {
        });
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /** @test */
    public function it_should_accept_a_correct_request()
    {
        $validator  = new RequestValidator($this->token);
        $middleware = new ValidateTwilioRequest;
        $route      = self::VALID_URL . '?' . Arr::query($this->requestData);
        $signature  = $validator->computeSignature($route);
        $request    = Request::create($route);

        $request->headers->set(ValidateTwilioRequest::TWILIO_HEADER_NAME, $signature);
        $response = $middleware->handle($request, function() {
        });

        $this->assertNull($response);

        $route     = self::VALID_URL;
        $signature = $validator->computeSignature($route, $this->requestData);
        $request   = Request::create($route, 'POST', $this->requestData);
        $request->headers->set(ValidateTwilioRequest::TWILIO_HEADER_NAME, $signature);
        $response = $middleware->handle($request, function() {
        });
        $this->assertNull($response);
    }

    /** @test */
    public function it_should_not_throw_type_error_on_missing_header()
    {
        $request = Request::create('/');

        $middleware = new ValidateTwilioRequest();

        $middleware->handle($request, function() {
        });

        $this->assertTrue(true); // Executing this means no type error on null header was thrown
    }
}

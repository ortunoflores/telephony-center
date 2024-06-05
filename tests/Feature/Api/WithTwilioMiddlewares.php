<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\Api\ValidateTwilioRequest;
use JMac\Testing\Traits\AdditionalAssertions;

trait WithTwilioMiddlewares
{
    use AdditionalAssertions;

    /** @test */
    public function it_uses_twilio_guard(): void
    {
        $this->assertRouteUsesMiddleware($this->routeName, [ValidateTwilioRequest::class]);
    }
}


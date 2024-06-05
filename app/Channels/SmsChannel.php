<?php

namespace App\Channels;


use App\Services\Twilio\RestClient;
use Illuminate\Support\Facades\Config;
use Twilio\Exceptions\ConfigurationException;
use Twilio\Exceptions\TwilioException;

class SmsChannel
{
    private RestClient $client;

    public function __construct(RestClient $client)
    {
        $this->client = $client;
    }

    /**
     * @throws ConfigurationException
     * @throws TwilioException
     */
    public function send($phone,  $notification)
    {
        $message  = $notification->toSms();
        $response = $this->client->messages->create('+' . $phone, [
            "messagingServiceSid" => Config::get('twilio.sms_services.auth'),
            "body"                => $message,
        ]);

        return [$response];
    }
}

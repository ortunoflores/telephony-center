<?php

namespace App\Services\Twilio;

use Illuminate\Support\Facades\Config;
use Twilio\Rest\Client;

class RestClient extends Client
{
    public function __construct()
    {
        parent::__construct(Config::get('twilio.account_sid'), Config::get('twilio.auth_token'));
    }
}

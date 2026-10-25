<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sign-in code
    |--------------------------------------------------------------------------
    |
    | After the password, a six digit code is emailed to the person and has to be
    | entered before they are let in. It works once, for a few minutes, and only
    | so many wrong guesses are allowed. Switch it off only where no mail can be
    | sent, such as a developer's machine.
    |
    */

    'sign_in_code' => [
        'required' => (bool) env('SIGN_IN_CODE_REQUIRED', true),
        'minutes' => 10,
        'attempts' => 5,
        'resend_after_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | API tokens
    |--------------------------------------------------------------------------
    |
    | Each signed-in phone holds a token. One left unused this many days stops
    | working, so a phone put away in a drawer has to sign in again.
    |
    */

    'api_tokens' => [
        'idle_days' => (int) env('API_TOKEN_IDLE_DAYS', 15),
    ],

];

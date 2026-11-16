<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The firm's details on a new installation
    |--------------------------------------------------------------------------
    |
    | Read once, when the firm settings are first created. After that the firm
    | keeps them under Settings, and changing these has no effect.
    |
    */

    'name' => env('FIRM_NAME', 'Example Firm'),

    // One line per entry, separated by "|", e.g. "12 Main Street|Rustenburg|0299".
    'address' => array_filter(explode('|', (string) env('FIRM_ADDRESS', ''))),

    'phone' => env('FIRM_PHONE'),

    'email' => env('FIRM_EMAIL'),

];

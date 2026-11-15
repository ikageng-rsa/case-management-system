<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | The firm, as it is printed on its bills
    |--------------------------------------------------------------------------
    |
    | Set per installation in the environment, never in the code.
    |
    */

    'name' => env('FIRM_NAME', 'Example Firm'),

    // One line per entry, separated by "|", e.g. "12 Main Street|Rustenburg|0299".
    'address' => array_filter(explode('|', (string) env('FIRM_ADDRESS', ''))),

    'phone' => env('FIRM_PHONE'),

    'email' => env('FIRM_EMAIL'),

];

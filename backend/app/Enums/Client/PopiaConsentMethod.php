<?php

namespace App\Enums\Client;

enum PopiaConsentMethod: string
{
    case Signed = 'signed_mandate';
    case Verbal = 'verbal_witnessed';
    case Online = 'online_form';
}

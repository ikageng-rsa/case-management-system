<?php

namespace App\Enums\Client;

enum ContactKind: string
{
    case Mobile = 'mobile';
    case Email = 'email';
    case Postal = 'postal-address';
    case Physical = 'physical-address';
    case ContactPerson = 'contact-person';
}

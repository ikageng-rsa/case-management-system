<?php

declare(strict_types=1);

namespace App\Enums\Client;

enum ClientType: string
{
    case Individual = 'individual';
    case Entity = 'entity';
}

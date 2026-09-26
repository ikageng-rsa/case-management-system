<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\ContactKind;
use RuntimeException;

class ContactKindLimitException extends RuntimeException
{
    public function __construct(public readonly ContactKind $kind)
    {
        parent::__construct("Individual clients can only have one {$kind->value} contact.");
    }
}

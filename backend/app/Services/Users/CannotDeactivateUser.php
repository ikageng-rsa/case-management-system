<?php

declare(strict_types=1);

namespace App\Services\Users;

use RuntimeException;

class CannotDeactivateUser extends RuntimeException
{
    public static function yourself(): self
    {
        return new self('You cannot deactivate your own account.');
    }

    public static function noSuccessor(string $references): self
    {
        return new self('Choose who takes over on '.$references.' before deactivating.');
    }
}

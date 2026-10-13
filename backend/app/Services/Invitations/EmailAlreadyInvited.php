<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use RuntimeException;

class EmailAlreadyInvited extends RuntimeException
{
    public function __construct(string $email, public readonly bool $isUser)
    {
        parent::__construct($isUser
            ? "{$email} already belongs to a user."
            : "{$email} already has an invitation waiting. Resend it instead.");
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Invitations;

use RuntimeException;

class InvitationNoLongerValid extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This invitation can no longer be used.');
    }
}

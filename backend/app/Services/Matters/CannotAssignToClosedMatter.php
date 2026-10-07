<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Matter;
use RuntimeException;

class CannotAssignToClosedMatter extends RuntimeException
{
    public function __construct(public readonly Matter $matter)
    {
        parent::__construct("Matter {$matter->reference} is closed and cannot take new assignments.");
    }
}

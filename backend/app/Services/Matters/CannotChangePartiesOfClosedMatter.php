<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Matter\Matter;
use RuntimeException;

class CannotChangePartiesOfClosedMatter extends RuntimeException
{
    public function __construct(public readonly Matter $matter)
    {
        parent::__construct("Matter {$matter->reference} is closed, so its parties cannot be changed.");
    }
}

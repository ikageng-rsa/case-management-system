<?php

declare(strict_types=1);

namespace App\Services\Reference;

use RuntimeException;

/*
 * A reference record that has been used is part of the firm's record: changing
 * what it says would change what every file that carries it says. These are the
 * fields that therefore stop being editable once something depends on them.
 */
class ReferenceDataLocked extends RuntimeException
{
    /** @param  array<int, string>  $fields */
    public function __construct(public readonly array $fields, string $reason)
    {
        parent::__construct($reason);
    }
}

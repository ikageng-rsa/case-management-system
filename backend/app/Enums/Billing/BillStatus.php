<?php

declare(strict_types=1);

namespace App\Enums\Billing;

/*
 * A bill is a draft until it is issued, and only then is anything owed on it.
 * An issued bill is never deleted: one sent in error is voided, which frees the
 * work on it to be billed again.
 */
enum BillStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::Void => 'Void',
        };
    }
}

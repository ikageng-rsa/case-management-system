<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/*
 * The firm as it is printed on its bills and shown in the app, kept by the firm
 * rather than in the code or the server's environment.
 */
class FirmSettings extends Settings
{
    public string $name;

    /** A line shown under the name in the app, such as "Attorneys Inc.". */
    public ?string $tagline;

    /** @var list<string> */
    public array $addressLines;

    public ?string $phone;

    public ?string $email;

    public static function group(): string
    {
        return 'firm';
    }
}

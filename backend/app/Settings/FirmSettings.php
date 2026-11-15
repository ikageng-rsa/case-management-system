<?php

declare(strict_types=1);

namespace App\Settings;

use Illuminate\Support\Facades\Storage;
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

    public ?string $website;

    /** The company registration number, as printed on the letterhead. */
    public ?string $registrationNumber;

    /** Where the uploaded letterhead image is kept on the documents disk, if there is one. */
    public ?string $letterheadPath;

    public static function group(): string
    {
        return 'firm';
    }

    /** The letterhead as a data URI, so it can be printed into a PDF without a web address. */
    public function letterheadDataUri(): ?string
    {
        $disk = Storage::disk(config('media-library.disk_name'));

        if ($this->letterheadPath === null || ! $disk->exists($this->letterheadPath)) {
            return null;
        }

        return 'data:'.$disk->mimeType($this->letterheadPath).';base64,'.base64_encode($disk->get($this->letterheadPath));
    }
}

<?php

declare(strict_types=1);

namespace App\Rules\Document;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/*
 * A document is what its contents say it is. The extension has to be one the
 * firm files, and the file has to be a type that extension is allowed to be,
 * so a program with a .pdf name is refused, and so is a .pdf that is really HTML.
 */
class AllowedDocument implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $allowed = (array) config('documents.allowed');
        $extension = strtolower($value->getClientOriginalExtension());

        if (! array_key_exists($extension, $allowed)) {
            $fail('This kind of file cannot be filed. Allowed: '.implode(', ', array_keys($allowed)).'.');

            return;
        }

        // Read from the contents, not from the name or from what the browser reported.
        if (! in_array((string) $value->getMimeType(), $allowed[$extension], true)) {
            $fail("This file is not really a .{$extension} file, so it cannot be filed.");
        }
    }
}

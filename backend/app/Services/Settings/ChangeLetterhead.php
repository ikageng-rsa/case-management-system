<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Settings\FirmSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChangeLetterhead
{
    public function __construct(
        protected FirmSettings $settings,
    ) {}

    /**
     * Put a new letterhead at the top of the firm's bills, in place of the one
     * before it. It is kept with the firm's other private files, not served on
     * the web, and printed into each bill as it is drawn.
     */
    public function replace(UploadedFile $image): void
    {
        $path = $image->storeAs('settings', 'letterhead-'.Str::random(12).'.'.$image->extension(), $this->disk());

        $this->forgetCurrent();
        $this->settings->letterheadPath = $path;
        $this->settings->save();

        $this->record('replaced the letterhead');
    }

    /** Go back to printing the firm's details as text at the top of its bills. */
    public function remove(): void
    {
        if ($this->settings->letterheadPath === null) {
            return;
        }

        $this->forgetCurrent();
        $this->settings->letterheadPath = null;
        $this->settings->save();

        $this->record('removed the letterhead');
    }

    protected function forgetCurrent(): void
    {
        if ($this->settings->letterheadPath !== null) {
            Storage::disk($this->disk())->delete($this->settings->letterheadPath);
        }
    }

    protected function disk(): string
    {
        return config('media-library.disk_name');
    }

    protected function record(string $what): void
    {
        activity('settings')->event('updated')->log(sprintf('%s %s', Auth::user()?->name ?? 'System', $what));
    }
}

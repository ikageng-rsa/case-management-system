<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /* The line under the firm's name in the app, which was written into the layout until now. */
    public function up(): void
    {
        $this->migrator->add('firm.tagline', null);
    }
};

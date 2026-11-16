<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /* The firm's letterhead image, website and company registration number, for the top of its bills. */
    public function up(): void
    {
        $this->migrator->add('firm.letterheadPath', null);
        $this->migrator->add('firm.website', null);
        $this->migrator->add('firm.registrationNumber', null);
    }
};

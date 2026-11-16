<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /*
     * The firm's details start as the server's environment gives them, so an
     * installation that already set them keeps them. From then on they are
     * changed in the app.
     */
    public function up(): void
    {
        $this->migrator->add('firm.name', (string) config('firm.name'));
        $this->migrator->add('firm.addressLines', array_values((array) config('firm.address')));
        $this->migrator->add('firm.phone', config('firm.phone'));
        $this->migrator->add('firm.email', config('firm.email'));
    }
};

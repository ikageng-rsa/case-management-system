<?php

declare(strict_types=1);

use App\Enums\Billing\InvoiceTheme;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /* The app's own accent, until the firm chooses one. No account is printed until one is given. */
    public function up(): void
    {
        $this->migrator->add('invoices.theme', InvoiceTheme::Classic->value);
        $this->migrator->add('invoices.accentColour', '#C79A55');
        $this->migrator->add('invoices.paymentTerms', 'Payable within 30 days of the date of this statement.');
        $this->migrator->add('invoices.accountName', null);
        $this->migrator->add('invoices.bankName', null);
        $this->migrator->add('invoices.accountNumber', null);
        $this->migrator->add('invoices.branchCode', null);
        $this->migrator->add('invoices.accountType', null);
    }
};

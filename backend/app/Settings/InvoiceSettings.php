<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\Billing\InvoiceTheme;
use Spatie\LaravelSettings\Settings;

/*
 * How the firm's bills look when printed, and how a client is asked to pay a
 * statement: the terms and the account to pay into, quoting the bill's number.
 */
class InvoiceSettings extends Settings
{
    public InvoiceTheme $theme;

    /** A colour such as #C79A55, for the themes that use one. */
    public string $accentColour;

    public ?string $paymentTerms;

    public ?string $accountName;

    public ?string $bankName;

    public ?string $accountNumber;

    public ?string $branchCode;

    public ?string $accountType;

    public static function group(): string
    {
        return 'invoices';
    }

    /** Whether there is an account to print for paying a statement. */
    public function hasBankAccount(): bool
    {
        return filled($this->bankName) && filled($this->accountNumber);
    }
}

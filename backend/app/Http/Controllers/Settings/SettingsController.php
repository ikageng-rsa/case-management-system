<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\Auth\Permission;
use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use App\Enums\Billing\InvoiceTheme;
use App\Http\Controllers\Controller;
use App\Models\User\User;
use App\Settings\BillingSettings;
use App\Settings\FirmSettings;
use App\Settings\InvoiceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/*
 * The firm's own settings, one tab per area, each looked after under its own
 * permission. The page shows a person only the tabs they may change.
 */
class SettingsController extends Controller
{
    public function show(Request $request, BillingSettings $billing, FirmSettings $firm, InvoiceSettings $invoices): View
    {
        /** @var User $user */
        $user = $request->user();

        $tabs = array_filter([
            'billing' => $user->can(Permission::ManageBillingSettings->value) ? 'Billing' : null,
            'invoices' => $user->can(Permission::ManageBillingSettings->value) ? 'Invoices' : null,
            'firm' => $user->can(Permission::ManageFirmSettings->value) ? 'Firm' : null,
        ]);

        abort_if($tabs === [], 403);

        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : array_key_first($tabs);

        return view('settings', [
            'tabs' => $tabs,
            'tab' => $tab,
            'billing' => $billing,
            'firm' => $firm,
            'invoices' => $invoices,
            'themes' => InvoiceTheme::cases(),
            'bases' => DrawingFeeBase::cases(),
            'timings' => BillingTiming::cases(),
        ]);
    }
}

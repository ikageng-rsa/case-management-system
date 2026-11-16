<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Auth\Permission;
use App\Models\User\User;
use App\Queries\Billing\Debtors;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function show(Request $request, Debtors $debtors): View
    {
        /** @var User $user */
        $user = $request->user();

        $tabs = array_filter([
            'debtors' => $user->can(Permission::ViewBillingReports->value) ? 'Debtors' : null,
        ]);

        abort_if($tabs === [], 403);

        $tab = array_key_exists((string) $request->query('tab'), $tabs) ? $request->query('tab') : array_key_first($tabs);

        return view('reports', [
            'tabs' => $tabs,
            'tab' => $tab,
            'debtors' => $debtors->for($user),
        ]);
    }
}

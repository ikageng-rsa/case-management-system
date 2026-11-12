<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Models\Reference\ActivityType;
use App\Support\Billing\TariffCsv;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/*
 * The empty tariff, one line for each billable kind of work the firm records,
 * ready to have the gazette's rates typed in.
 */
class DownloadTariffTemplateController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize(Permission::ManageTariffs->value);

        $rows = ActivityType::query()->billable()->orderBy('code')->pluck('code')
            ->map(fn (string $code) => [$code, '', '', '', '', ''])
            ->all();

        return response(TariffCsv::write($rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tariff-template.csv"',
        ]);
    }
}

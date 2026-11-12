<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Auth\Permission;
use App\Http\Controllers\Controller;
use App\Models\Billing\TariffSchedule;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/* The file a tariff was read from, exactly as it was uploaded. */
class DownloadTariffSourceController extends Controller
{
    public function __invoke(TariffSchedule $tariffSchedule): Response
    {
        Gate::authorize(Permission::ManageTariffs->value);

        $source = $tariffSchedule->source();

        abort_if($source === null, 404);

        return $source->toResponse(request());
    }
}

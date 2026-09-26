<?php

declare(strict_types=1);

namespace App\Services\Clients;

use App\Enums\Client\PopiaConsentMethod;
use App\Models\Client;
use App\Models\PopiaConsent;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordPopiaConsent
{
    public function record(Client $client, bool $granted, PopiaConsentMethod $method, ?CarbonInterface $at = null, UploadedFile|string|null $mandate = null): PopiaConsent
    {
        $consent = DB::transaction(function () use ($client, $granted, $method, $at, $mandate) {
            $current = $client->popiaConsent()->first();

            if ($current?->isActive()) {
                if ($granted) {
                    return $current;
                }

                $current->withdraw();

                return $current;
            }

            // No active consent: record a fresh grant, or an explicit refusal.
            $consent = $client->popiaConsents()->create([
                'granted' => $granted,
                'granted_at' => $granted ? ($at ?? now()) : null,
                'method' => $method,
            ]);

            if ($mandate !== null) {
                $consent->addMandate($mandate);
            }

            return $consent;
        });

        $client->unsetRelation('popiaConsent');

        return $consent;
    }
}

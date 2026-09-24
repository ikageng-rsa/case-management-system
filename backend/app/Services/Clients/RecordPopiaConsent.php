<?php

declare(strict_types= 1);

namespace App\Services\Clients;

use App\Enums\Client\PopiaConsentMethod;
use App\Models\Client;
use App\Models\PopiaConsent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordPopiaConsent{

public function handle(Client $client, bool $granted, PopiaConsentMethod $method, ?CarbonInterface $at = null): PopiaConsent{
   $consent = DB::transaction(function () use ($client, $granted, $method, $at) {
            // ->first() on the relation query, not the cached property.
            $current = $client->popiaConsent()->first();

            if ($current?->isActive()) {
                if ($granted) {
                    return $current;            // already consented: don't stack duplicate rows
                }

                $current->withdraw();           // client is revoking

                return $current;
            }

            // No active consent: record a fresh grant, or an explicit refusal.
            return $client->popiaConsents()->create([
                'granted' => $granted,
                'granted_at' => $granted ? ($at ?? now()) : null,
                'method' => $method,
            ]);
        });

        $client->unsetRelation('popiaConsent');

        return $consent;
    }

}
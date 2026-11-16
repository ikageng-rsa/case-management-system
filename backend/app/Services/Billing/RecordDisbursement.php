<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\FirmAccount;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordDisbursement
{
    /**
     * Record money paid out on a matter, with its receipt when there is one.
     *
     * @param  array{incurred_on: string, description: string, amount_cents: int, paid_from: FirmAccount}  $data
     */
    public function record(Matter $matter, array $data, User $recordedBy, ?UploadedFile $receipt = null): Disbursement
    {
        return DB::transaction(function () use ($matter, $data, $recordedBy, $receipt): Disbursement {
            $disbursement = $matter->disbursements()->make([...$data, 'description' => trim($data['description'])]);
            $disbursement->recordedBy()->associate($recordedBy);
            $disbursement->save();

            if ($receipt !== null) {
                $disbursement->attachReceipt($receipt);
            }

            return $disbursement;
        });
    }
}
